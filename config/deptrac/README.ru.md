# Deptrac — пример конфига для DDD-проекта на Symfony

📄 [English](README.md) · [中文](README.zh.md)

## Что здесь

`depfile.yaml` — конфиг [Deptrac](https://github.com/qossmic/deptrac), который реализует **детерминированную верификацию** архитектурных правил, описанных в конвенциях (`docs/conventions/`):

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

Namespace-паттерны в `collectors` следуют конвенциям из `docs/conventions/`. Пример использует опциональный универсальный префикс `(?:[A-Za-z_]+\\)?`, который ловит любой корневой namespace — поэтому один и тот же конфиг работает и для `Common\Module\...`, и для `TaskOrchestrator\Common\Module\...` без модификации.

### `ruleset`

Правила зависимостей между слоями — обычно не требуют изменений, если вы не добавляете/удаляете слои.

### `Presentation` collectors

Слой Presentation использует один regex, который ловит любой прикладной namespace (Api, Console, Web, Blog, Docs):

```yaml
- type: classLike
  value: ^(?:[A-Za-z_]+\\)?(?:Api|Console|Web|Blog|Docs)\\Module\\.*
```

Опциональный префикс `(?:[A-Za-z_]+\\)?` поддерживает и `Console\Module\...`, и `TaskOrchestrator\Console\Module\...`. При необходимости добавьте свои app-namespace в альтернативу.

## Пользовательские правила

### ServiceContractDependencyRule

Пользовательский event subscriber для Deptrac, который проверяет **границы сервис-контрактов** — выходит за рамки того, что могут выразить статические правила слоёв:

1. **Межмодульные вызовы сервисов запрещены** — сервис в `Module\Billing` не должен зависеть от сервиса в `Module\User`. Используйте Integration-слой для межмодульного взаимодействия.
2. **Сервис-интерфейсы должны использоваться/реализовываться только разрешёнными слоями** — например, Domain может ссылаться только на свои Domain-сервис-интерфейсы, а Integration — на Domain + Application + Integration.
3. **`use`-импорты игнорируются** — проверяются только реальные зависимости (типы параметров, возвращаемые типы, вызовы методов, наследование), поэтому импорт класса из другого модуля через IDE не даёт ложное срабатывание.

Разрешённые ссылки на сервис-интерфейсы по слоям:

| Слой           | Может ссылаться (use)        | Может реализовывать (inherit) |
|----------------|-------------------------------|-------------------------------|
| Domain         | Domain                        | Domain                        |
| Application    | Domain, Application           | Domain, Application           |
| Infrastructure | Domain, Infrastructure        | Domain, Infrastructure        |
| Integration    | Domain, Application,          | Domain, Integration           |
|                |   Integration                 |                               |

**Установка**: правило поставляется в составе `prikotov/coding-standard` как автозагружаемый класс — копировать файлы не нужно. Просто зарегистрируйте в `depfile.yaml`:

```yaml
services:
  - class: PrikotovCodingStandard\Deptrac\ServiceContractDependencyRule
    tags:
      - { name: kernel.event_subscriber }
```

Полный набор тестов (28 кейсов) включён в пакет (`tests/Deptrac/ServiceContractDependencyRuleTest.php`).

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
