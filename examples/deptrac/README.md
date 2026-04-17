# Deptrac — пример конфига для DDD-проекта на Symfony

## Что здесь

`depfile.yaml.example` — пример конфига [Deptrac](https://github.com/qossmic/deptrac), который проверяет архитектурные границы между слоями DDD:

```
Presentation → Application → Domain
Infrastructure → Domain (реализует интерфейсы)
Integration → Application + Domain
```

Deptrac делает конвенции DDD **исполняемыми** — нарушение ловится в CI.

## Слои

| Слой | Описание |
|---|---|
| `Domain` | Сущности, VO, интерфейсы репозиториев |
| `DomainVo` | Value Objects (→ Domain, Enum) |
| `DomainEnum` | Enum домена (замкнут) |
| `DomainDto` | DTO домена (→ Domain, VO, Enum) |
| `Application` | Use cases, сервисы |
| `ApplicationDto` | DTO приложения (→ DTO, Enum) |
| `ApplicationCommand` | Command (CQRS) |
| `ApplicationQuery` | Query (CQRS) |
| `ApplicationCommandHandler` | Обработчик команд |
| `ApplicationQueryHandler` | Обработчик запросов |
| `Infrastructure` | Репозитории, внешние сервисы |
| `InfrastructureComponent` | Компоненты инфраструктуры |
| `Integration` | Внешние API, события |
| `IntegrationListener` | Слушатели событий |
| `Presentation` | Контроллеры, консольные команды |

## Правила зависимостей

- **Domain** не знает ни о ком, кроме себя (DTO, VO, Enum)
- **Application** → Domain, но не Infrastructure
- **Infrastructure** реализует интерфейсы Domain, не зависит от Application
- **Command/Query** — чистые DTO без логики
- **Handler** — единственная точка входа в use case
- **Presentation** → Application (Command/Query + Handler)
- **IntegrationListener** слушает события, диспатчит Command/Query

## Адаптация под проект

Пример использует `Common\Module\*`. Для своего проекта:

1. Замените `Common\Module` на ваш базовый namespace модулей
2. Скорректируйте `paths` — где искать исходники
3. Скорректируйте `exclude_files` — что исключить
4. При необходимости добавьте/удалите слои

## Запуск

Напрямую:

```bash
vendor/bin/deptrac analyse
```

Через Makefile:

```makefile
.PHONY: deptrac
deptrac:
	vendor/bin/deptrac analyse --no-progress
```

В CI:

```yaml
- run: vendor/bin/deptrac analyse --no-progress
```

В составе `make check`:

```makefile
.PHONY: check
check: install tests deptrac phpcs phpmd psalm
```
