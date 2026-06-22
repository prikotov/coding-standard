---
name: Integration Layer
type: rule
description: Слой интеграций: межмодульное взаимодействие, события и middleware
---

# Слой интеграций (Integration)

**Слой интеграций (Integration Layer)** — отвечает за межмодульное взаимодействие, обработку событий и адаптацию внешнего контекста перед входом в Application.

## Общие правила

- Координирует работу между модулями.
- Реагирует на доменные события.
- Адаптирует внешний контекст фреймворка или очереди перед входом в Application.
- Не содержит бизнес-логики.
- Использует Application слой для выполнения операций.

## Расположение

```
src/Module/{ModuleName}/Integration/
├── Listener/
│   └── {EventName}Listener.php
├── Middleware/
│   └── {MiddlewareName}.php
└── Service/
    └── {ServiceName}.php
```

## Описание

Integration слой отвечает за межмодульное взаимодействие, обработку событий и адаптацию внешнего контекста перед входом в Application.

## Компоненты

- [Listener](integration/listener.md) — обработчики событий
- [Middleware](integration/middleware.md) — адаптеры pipeline/lifecycle внешнего фреймворка или очереди
- **Service** — реализация Domain Service-интерфейсов для межмодульного взаимодействия
- Команды межмодульного взаимодействия

## Правила реализации

- Координирует работу между модулями.
- Реагирует на доменные события.
- Адаптирует внешний контекст фреймворка или очереди перед входом в Application.
- Не содержит HTTP/SDK-клиенты внешних API — такие адаптеры размещаются в [Infrastructure](infrastructure.md).
- Не содержит `Component`: `Integration\Component` не используем.
- Не содержит бизнес-логики.
- Использует Application слой для выполнения операций.

### Локальный модуль или вынесенный сервис

- Если реализация связывает модули внутри одного процесса и не использует внешний HTTP/gRPC/API/SDK или клиент очереди,
  размещаем её в `Integration\Service`.
- Если та же возможность вынесена в отдельный сервис и связь идёт через HTTP/gRPC/API/SDK или клиент очереди,
  реализация размещается в `Infrastructure\Service`/`Infrastructure\Component`, даже если сервис вырос из бывшего
  модуля проекта.
- Интерфейс остаётся в `Domain`; DI выбирает локальную `Integration`-реализацию или удалённую `Infrastructure`-реализацию.

### Service

- Реализует Domain Service-интерфейсы, когда реализация связывает модули внутри процесса.
- Application оркестрирует Domain через интерфейсы, не зная, где находится реализация — в Domain, Infrastructure или Integration.

### Listener

- Обрабатывает события через Application-слой.
- Не вызывает Domain/Infrastructure напрямую.

### Middleware

- Адаптирует транспортный контекст, не реализуя бизнес-правила.

## См. также

- [Domain Layer](domain.md)
- [Application Layer](application.md)

## Чек-лист для проведения ревью кода

- [ ] Integration не содержит бизнес-логику.
- [ ] Service реализует Domain Service-интерфейс и использует только разрешённые зависимости.
- [ ] Listener обрабатывает события через Application-слой.
- [ ] Middleware адаптирует транспортный контекст, не реализуя бизнес-правила.
- [ ] Нет прямых вызовов к Domain/Infrastructure из Listener.
- [ ] Межмодульное взаимодействие идёт через Application-контракты.
- [ ] Исходящие внешние API/SDK-клиенты размещены в Infrastructure, а не в Integration.
- [ ] В модуле нет каталога `Integration\Component`.
