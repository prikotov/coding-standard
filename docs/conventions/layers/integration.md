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
- Не содержит HTTP/SDK-клиенты внешних API — такие клиенты размещаются в [Infrastructure](infrastructure.md).
- Не зависит от Infrastructure.
- Не содержит бизнес-логики.
- Использует Application слой для выполнения операций.

### Service

- Реализует Domain Service-интерфейсы, когда реализация связывает модули внутри процесса.
- Не обращается к Infrastructure напрямую.
- Если операция требует HTTP/SDK-клиента внешнего API, реализация доменного интерфейса размещается в Infrastructure,
  а Integration Service остаётся только для межмодульной оркестрации.
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
- [ ] Service не зависит от Infrastructure.
- [ ] Listener обрабатывает события через Application-слой.
- [ ] Middleware адаптирует транспортный контекст, не реализуя бизнес-правила.
- [ ] Нет прямых вызовов к Domain/Infrastructure из Listener.
- [ ] Межмодульное взаимодействие идёт через Application-контракты.
- [ ] Не содержит прямую работу с внешними API/SDK-клиентами.
