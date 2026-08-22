---
package: prikotov/coding-standard
name: Events and DB Transactions
type: rule
description: Правила работы с доменными событиями и транзакциями базы данных
---

# События и транзакции БД

## Общие правила

- События отправляются (dispatch) **после** `flush()`, когда данные уже записаны в БД.
- Транзакциями управляет код хендлера явно через `flush()`.


## Обзор

Документ описывает архитектурное соотношение между доменными событиями (Domain Events) и транзакциями базы данных.

## Ключевое правило

**События отправляются ПОСЛЕ `flush()`**, когда данные уже записаны в БД.

Данное правило зафиксировано в конвенции: [События (Event) — Требования к событиям](../../layers/application/event.md#требования-к-событиям).

### Пример правильной реализации

```php
// ✅ Правильно — события после flush:
$events = [];
foreach ($items as $item) {
    $this->persistenceManager->persist($item);
    if ($condition) {
        $events[] = new ChangedEvent(...);
    }
}
$this->persistenceManager->flush();

// Dispatch events AFTER flush to ensure data is persisted
foreach ($events as $event) {
    $this->eventBus->dispatch($event);
}
```

### Пример неправильной реализации

```php
// ❌ Неправильно — событие внутри транзакции:
foreach ($items as $item) {
    $this->persistenceManager->persist($item);
    if ($condition) {
        $this->eventBus->dispatch(new ChangedEvent(...)); // Данных в БД ещё нет!
    }
}
$this->persistenceManager->flush();
```

## Архитектурная схема

```mermaid
sequenceDiagram
    participant Handler as CommandHandler
    participant PM as PersistenceManager
    participant DB as Database
    participant EventBus as EventBus (Messenger)
    participant Queue as Transport (RabbitMQ/Redis)
    
    Handler->>PM: persist(entity)
    Note over Handler: Накопление событий<br/>в массиве $events[]
    
    Handler->>PM: flush()
    PM->>DB: COMMIT transaction
    
    loop Для каждого события
        Handler->>EventBus: dispatch(event)
        EventBus->>Queue: send to transport
    end
    
    Note over Handler: События отправляются<br/>только после успешного COMMIT
```

## Обоснование

| Проблема | Решение |
|----------|---------|
| Если отправлять до `flush()` — слушатели не найдут данные в БД | Данные гарантированно записаны до отправки события |
| Откат транзакции — слушатели уже получили событие о несуществующих данных | События отправляются только после успешного коммита (commit) |
| Нарушение консистентности между БД и очередями | Гарантия «сначала данные, потом уведомления» |

## Шаблон `Outbox` (для критичных уведомлений)

Для критически важных уведомлений, где требуется гарантия доставки, используется шаблон **`Outbox`**.

### Схема шаблона `Outbox`

```mermaid
flowchart TD
    A[Попытка dispatch в broker] -->|Успех| B[Outbox запись не создаётся]
    A -->|Ошибка broker/network| C[Запись в Outbox<br/>status: pending]
    C --> D[Relay worker вычитывает<br/>pending/retryable]
    D --> E[Повторная отправка]
    
    subgraph "Транзакционный контракт"
        F[Enqueue успешен] --> G[Outbox не нужен]
        H[Enqueue failed] --> I[Outbox persist ДО<br/>завершения handler]
        I --> J[Ack upstream только<br/>после persist]
    end
```

### Когда использовать шаблон `Outbox`

- Критически важные уведомления (например, live-updates статуса)
- Финансовые операции с внешними системами
- Интеграции с внешними API, где потеря события недопустима

### Когда шаблон `Outbox` не нужен

- Обычные доменные события между модулями
- Уведомления с допустимой задержкой
- События, обрабатываемые через `retry_strategy` компонента `Messenger`

## Текущая реализация

### Конфигурация `Messenger`

```yaml
framework:
  messenger:
    default_bus: event.bus
    buses:
      event.bus:
        default_middleware: allow_no_handlers
        middleware:
          - validation
          - doctrine_ping_connection
```

**Важно:** Middleware `doctrine_transaction` отсутствует — транзакциями управляет код хендлера явно через `flush()`.

### Маршрутизация событий (Routing)

События маршрутизируются по транспортам в соответствии с конфигурацией `messenger.yaml`.

## Резюме

| Аспект | Реализация |
|--------|------------|
| **Порядок** | `flush()` → `dispatch()` |
| **Транзакционность событий** | Нет (eventual consistency) |
| **Шаблон `Outbox`** | Опционально для критичных уведомлений |
| **Гарантия доставки** | Через `retry_strategy` компонента `Messenger` |
| **Консистентность** | Данные в БД → потом события |

Это классический паттерн **отправки событий после коммита (Post-Commit Event Dispatching)** — простой и надёжный, без накладных расходов шаблона `Outbox` для всех событий.

## Связанные документы

- [События (Event)](../../layers/application/event.md)
- [Обработчик Команд (Command Handler)](../../layers/application/command-handler.md)
- [Внешние сервисы](../../core-patterns/external-service.md)

## Расположение

Транзакции управляются в Application-слое, внутри [Command Handler](../../layers/application/command-handler.md):


## Чек-лист для проведения ревью кода

- [ ] События отправляются после `flush()`, а не внутри транзакции.
- [ ] Транзакции управляются явно, без `doctrine_transaction` middleware.
- [ ] Стратегия повторов (retry strategy) настроена для критичных событий.
