---
name: Events and DB Transactions
type: rule
description: Правила работы с доменными событиями и транзакциями базы данных
---

# События и транзакции БД

## Общие правила

- События dispatch'ся **после** `flush()`, когда данные уже записаны в БД.
- Транзакциями управляет код хендлера явно через `flush()`.


## Обзор

Документ описывает архитектурное соотношение между доменными событиями (Domain Events) и транзакциями базы данных.

## Ключевое правило

**События dispatch'ся ПОСЛЕ `flush()`**, когда данные уже записаны в БД.

Данное правило зафиксировано в конвенции: [События (Event) — Порядок dispatch](../../layers/application/event.md#порядок-dispatch-событий).

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
| Если dispatch до flush — listener'ы не найдут данные в БД | Данные гарантированно записаны до отправки события |
| Rollback транзакции — слушатели уже получили событие о несуществующих данных | События отправляются только после успешного commit |
| Нарушение консистентности между БД и очередями | Гарантия "сначала данные, потом уведомления" |

## Outbox Pattern (для критичных уведомлений)

Для критически важных уведомлений, где требуется гарантия доставки, используется **Outbox Pattern**.

### Схема Outbox Pattern

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

### Когда использовать Outbox

- Критически важные уведомления (например, live-updates статуса)
- Финансовые операции с внешними системами
- Интеграции с внешними API, где потеря события недопустима

### Когда Outbox не нужен

- Обычные доменные события между модулями
- Уведомления с допустимой задержкой
- События, обрабатываемые через retry_strategy Messenger

## Текущая реализация

### Конфигурация Messenger

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

### Routing событий

События маршрутизируются по транспортам в соответствии с конфигурацией `messenger.yaml`.

## Резюме

| Аспект | Реализация |
|--------|------------|
| **Порядок** | `flush()` → `dispatch()` |
| **Транзакционность событий** | Нет (eventual consistency) |
| **Outbox pattern** | Опционально для критичных уведомлений |
| **Гарантия доставки** | Через retry_strategy Messenger |
| **Консистентность** | Данные в БД → потом события |

Это классический паттерн **"Post-Commit Event Dispatching"** — простой, надёжный, без overhead Outbox для всех событий.

## Связанные документы

- [События (Event)](../../layers/application/event.md)
- [Обработчик Команд (Command Handler)](../../layers/application/command-handler.md)
- [Внешние сервисы](../../core-patterns/external-service.md)

## Расположение

Транзакции управляются в Application-слое, внутри [Command Handler](../../layers/application/command-handler.md):


## Чек-лист для проведения ревью кода

- [ ] События dispatch'ся после `flush()`, а не внутри транзакции.
- [ ] Транзакции управляются явно, без `doctrine_transaction` middleware.
- [ ] Retry-strategy настроен для критичных событий.
