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

Namespace patterns in `collectors` use `Common\Module` — find & replace with your base module namespace. Example:

```
Before: ^Common\Module\.*\Domain\.*
After:  ^MyProject\Module\.*\Domain\.*
```

### `ruleset`

Dependency rules between layers — usually don't need changes unless you add/remove layers.

### `Presentation` collectors

Application entry points (controllers, console commands) — adjust to match your namespace:

```yaml
- type: classLike
  value: ^Api\v1\Module\.*        # REST API
- type: classLike
  value: ^Console\Module\.*\Command\.*  # CLI commands
```

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
