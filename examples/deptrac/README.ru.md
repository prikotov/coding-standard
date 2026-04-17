# Deptrac — пример конфига для DDD-проекта на Symfony

📄 [English](README.md) · [中文](README.zh.md)

## Что здесь

`depfile.yaml.example` — пример конфига [Deptrac](https://github.com/qossmic/deptrac), который реализует **детерминированную верификацию** архитектурных правил, описанных в конвенциях (`docs/conventions/`):

```
Presentation → Application → Domain
Infrastructure → Domain (реализует интерфейсы)
Integration → Application + Domain
```

### Зачем верификация, если есть конвенции

Конвенции описывают правила текстом. Но кодовые AI-агенты не всегда строго им следуют — даже при наличии AGENTS.md и ролевой модели агент может случайно:

- Заинжектить зависимость от Infrastructure в Domain
- Обратиться к Infrastructure из Application напрямую, минуя абстракцию
- Положить DTO не в тот слой
- Создать связь между модулями в обход Integration

Deptrac ловит такие нарушения **детерминированно** — в CI, на каждый коммит. Это не «совет», а hard check: CI красный — код не попадает в main.

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

Пример использует `Common\Module\*` как базовый namespace. Для своего проекта:

### `paths`

Директории с исходниками для сканирования:

```yaml
paths:
  - ./src
  - ./apps
```

### `exclude_files`

Regex-паттерны файлов, которые пропускаются:

```yaml
exclude_files:
  - '#.*tests.*#'
```

### `layers`

Namespace-паттерны в `collectors` используют `Common\Module` — найдите и замените на ваш базовый namespace модулей. Пример:

```
Было:  ^Common\Module\.*\Domain\.*
Стало: ^MyProject\Module\.*\Domain\.*
```

### `ruleset`

Правила зависимостей между слоями — обычно не требуют изменений, если вы не добавляете/удаляете слои.

### `Presentation` collectors

Точки входа приложения (контроллеры, консольные команды) — скорректируйте под ваш namespace:

```yaml
- type: classLike
  value: ^Api\v1\Module\.*        # REST API
- type: classLike
  value: ^Console\Module\.*\Command\.*  # CLI-команды
```

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
