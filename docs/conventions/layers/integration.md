# Слой интеграций (Integration)

## Описание

Integration слой отвечает за межмодульное взаимодействие и внешние события.

## Компоненты

- [Listener](integration/listener.md) — обработчики событий
- [Middleware](integration/middleware.md) — framework-specific адаптеры pipeline/transport lifecycle
- Команды межмодульного взаимодействия
- Внешние API интеграции

## Правила реализации

- Координирует работу между модулями
- Реагирует на доменные события
- Адаптирует внешний framework/transport context перед входом в Application
- Не содержит бизнес-логики
- Использует Application слой для выполнения операций

## См. также

- [Domain Layer](domain.md)
- [Application Layer](application.md)
