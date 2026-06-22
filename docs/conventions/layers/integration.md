---
name: Integration Layer
type: rule
description: Слой интеграций: межмодульное взаимодействие, события и транспортные адаптеры
---

# Слой интеграций (Integration)

**Слой интеграций (Integration Layer)** — отвечает за межмодульное взаимодействие, обработку событий и адаптацию внешних transports.

## Общие правила

- Координирует работу между модулями.
- Реагирует на доменные события.
- Адаптирует внешний framework/transport context перед входом в Application.
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

Integration слой отвечает за межмодульное взаимодействие, обработку событий и адаптацию внешнего transport/framework context.

## Компоненты

- [Listener](integration/listener.md) — обработчики событий
- [Middleware](integration/middleware.md) — framework-specific адаптеры pipeline/transport lifecycle
- **Service** — реализация Domain Service-интерфейсов для межмодульного взаимодействия
- Команды межмодульного взаимодействия
- Transport adapters для входа в Application

## Правила реализации

- Координирует работу между модулями.
- Реагирует на доменные события.
- Адаптирует внешний framework/transport context перед входом в Application.
- Не содержит HTTP/SDK-клиенты внешних API — такие адаптеры размещаются в [Infrastructure](infrastructure.md).
- Не содержит бизнес-логики.
- Использует Application слой для выполнения операций.

### Service

- Реализует Domain Service-интерфейсы, когда реализация связывает модули или адаптирует внешний transport.
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
