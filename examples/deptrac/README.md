# Deptrac — DDD project config example for Symfony

📄 [Русский](README.ru.md) · [中文](README.zh.md)

## What's here

`depfile.yaml.example` — an example [Deptrac](https://github.com/qossmic/deptrac) config that implements **deterministic verification** of architectural rules described in the conventions (`docs/conventions/`):

```
Presentation → Application → Domain
Infrastructure → Domain (implements interfaces)
Integration → Application + Domain
```

### Why verification if conventions exist

Conventions describe rules as text. But AI coding agents don't always follow them strictly — even with AGENTS.md and role-based prompts, an agent may accidentally:

- Inject an Infrastructure dependency into Domain
- Access Infrastructure from Application directly, bypassing abstractions
- Place a DTO in the wrong layer
- Create a cross-module coupling bypassing Integration

Deptrac catches such violations **deterministically** — in CI, on every commit. This is not "advice" but a hard check: CI red — code doesn't reach main.

## Layers

| Layer | Description |
|---|---|
| `Domain` | Entities, VOs, repository interfaces |
| `DomainVo` | Value Objects (→ Domain, Enum) |
| `DomainEnum` | Domain enum (closed) |
| `DomainDto` | Domain DTOs (→ Domain, VO, Enum) |
| `Application` | Use cases, services |
| `ApplicationDto` | Application DTOs (→ DTO, Enum) |
| `ApplicationCommand` | Command (CQRS) |
| `ApplicationQuery` | Query (CQRS) |
| `ApplicationCommandHandler` | Command handler |
| `ApplicationQueryHandler` | Query handler |
| `Infrastructure` | Repositories, external services |
| `InfrastructureComponent` | Infrastructure components |
| `Integration` | External APIs, events |
| `IntegrationListener` | Event listeners |
| `Presentation` | Controllers, console commands |

## Dependency rules

- **Domain** knows nothing but itself (DTO, VO, Enum)
- **Application** → Domain, but not Infrastructure
- **Infrastructure** implements Domain interfaces, doesn't depend on Application
- **Command/Query** — pure DTOs without logic
- **Handler** — single entry point to a use case
- **Presentation** → Application (Command/Query + Handler)
- **IntegrationListener** listens to events, dispatches Command/Query

## Adapting for your project

The example uses `Common\Module\*` as base namespace. For your project:

### `paths`

Source directories to scan:

```yaml
paths:
  - ./src
  - ./apps
```

### `exclude_files`

Regex patterns for files to skip:

```yaml
exclude_files:
  - '#.*tests.*#'
```

### `layers`

Namespace patterns in `collectors` follow the conventions from `docs/conventions/`. The example uses an optional universal prefix `(?:[A-Za-z_]+\\)?` that matches any root namespace — so the same config works for both `Common\Module\...` and `TaskOrchestrator\Common\Module\...` without modification.

### `ruleset`

Dependency rules between layers — usually don't need changes unless you add/remove layers.

### `Presentation` collectors

The Presentation layer uses a single regex that matches any application-level namespace (Api, Console, Web, Blog, Docs):

```yaml
- type: classLike
  value: ^(?:[A-Za-z_]+\\)?(?:Api|Console|Web|Blog|Docs)\\Module\\.*
```

The optional prefix `(?:[A-Za-z_]+\\)?` handles both `Console\Module\...` and `TaskOrchestrator\Console\Module\...`. Add your app namespaces to the alternation if needed.

## Custom rules

### ServiceContractDependencyRule

A custom Deptrac event subscriber that enforces **service contract boundaries** — goes beyond what static layer rules can express:

1. **Cross-module service calls are forbidden** — a service in `Module\Billing` must not depend on a service in `Module\User`. Use Integration layer for cross-module communication.
2. **Service interfaces must be referenced/implemented only by allowed layers** — for example, Domain may only reference its own Domain service interfaces, while Integration may reference Domain + Application + Integration interfaces.
3. **`use` imports are ignored** — only actual dependencies (parameter types, return types, method calls, inheritance) are checked, so IDE-importing a class from another module doesn't trigger a false positive.

Allowed service interface references per layer:

| Layer         | Can reference (use)          | Can implement (inherit)       |
|---------------|------------------------------|-------------------------------|
| Domain        | Domain                       | Domain                        |
| Application   | Domain, Application          | Domain, Application           |
| Infrastructure| Domain, Infrastructure       | Domain, Infrastructure        |
| Integration   | Domain, Application,         | Domain, Integration           |
|               |   Integration                |                               |

**Installation**: the rule is provided by `prikotov/coding-standard` as an autoloaded class — no need to copy files. Just register it in your `depfile.yaml`:

```yaml
services:
  - class: PrikotovCodingStandard\Deptrac\ServiceContractDependencyRule
    tags:
      - { name: kernel.event_subscriber }
```

Full test suite (28 cases) is included in the package (`tests/Deptrac/ServiceContractDependencyRuleTest.php`).

## Running

Directly:

```bash
vendor/bin/deptrac analyse
```

Via Makefile:

```makefile
.PHONY: deptrac
deptrac:
	vendor/bin/deptrac analyse --no-progress
```

In CI:

```yaml
- run: vendor/bin/deptrac analyse --no-progress
```

As part of `make check`:

```makefile
.PHONY: check
check: install tests deptrac phpcs phpmd psalm
```
