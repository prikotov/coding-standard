# Deptrac — архитектурный анализ

## Что это

[Deptrac](https://github.com/qossmic/deptrac) — статический анализатор, который проверяет, что зависимости между слоями DDD соответствуют правилам:

```
Presentation → Application → Domain
Infrastructure → Domain (реализует интерфейсы)
Integration → Application + Domain
```

## Зачем

Конвенции DDD описывают границы слоёв текстом. Deptrac делает их **исполняемыми** — нарушение ловится в CI.

## Структура конфига

`depfile.yaml` определяет:

### Слои (layers)

| Слой | Описание |
|---|---|
| `Domain` | Сущности, VO, интерфейсы репозиториев |
| `DomainVo` | Value Objects (могут ссылаться на Domain, Enum) |
| `DomainEnum` | Enum домена (замкнут) |
| `DomainDto` | DTO домена (могут ссылаться на Domain, VO, Enum) |
| `Application` | Use cases, сервисы приложений |
| `ApplicationDto` | DTO приложения (могут ссылаться на DTO, Enum) |
| `ApplicationCommand` | Command (CQRS) |
| `ApplicationQuery` | Query (CQRS) |
| `ApplicationCommandHandler` | Обработчик команд |
| `ApplicationQueryHandler` | Обработчик запросов |
| `Infrastructure` | Репозитории, внешние сервисы |
| `InfrastructureComponent` | Компоненты инфраструктуры |
| `Integration` | Внешние API, события, межмодульное взаимодействие |
| `IntegrationListener` | Слушатели событий |
| `Presentation` | Контроллеры, консольные команды |

### Правила (ruleset)

Ключевые принципы:

- **Domain не знает ни о ком**, кроме себя самого (DTO, VO, Enum)
- **Application** может зависеть от Domain, но не от Infrastructure
- **Infrastructure** реализует интерфейсы Domain, но не зависит от Application
- **Command/Query** — чистые DTO, не содержат логики
- **Handler** — единственная точка входа в use case
- **Presentation** зависит только от Application (Command/Query + Handler)
- **IntegrationListener** слушает события и диспатчит Command/Query

## Адаптация под проект

Шаблон использует namespace `Common\Module\*`. Для своего проекта:

1. Замените `Common\Module` на ваш базовый namespace модулей
2. Скорректируйте `paths` — где искать исходники
3. Скорректируйте `exclude_files` — что исключить из анализа
4. При необходимости добавьте/удалите слои

## Запуск

```bash
vendor/bin/deptrac analyse
```

## Подключение к CI

```yaml
- run: vendor/bin/deptrac analyse --no-progress
```

## Ссылки

- [Слои и модули](../layers/layers.md) — описание каждого слоя
- [Архитектура DDD](../modules/index.md) — общие принципы модульной архитектуры
