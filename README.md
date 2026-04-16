# prikotov/coding-standard

PHP CodeSniffer standard with custom sniffs and DDD conventions documentation.

## What is it

Two parts:

1. **PHPCS Sniffs** — automated structural checks via PHP CodeSniffer 4.x
2. **Conventions** — DDD conventions documentation (layers, patterns, principles, testing)

## Sniffs

| Sniff | Description |
|---|---|
| `DtoStructureSniff` | Enforces `final readonly` DTO classes with empty promoted constructor |
| `EnumStructureSniff` | Enforces pure enums (no methods, constants, traits) with camelCase cases |
| `CommandQueryStructureSniff` | Enforces Command/Query DTO structure (constructor-only, no properties/methods) |
| `CommandHandlerStructureSniff` | Enforces CommandHandler structure (`__invoke` only, no public properties) |
| `UseCaseNamingSniff` | Enforces UseCase naming conventions (suffix, filename, namespace match path) |
| `GlobalFunctionCallStyleSniff` | Forbids `use function` imports and `\func()` calls for global functions |

## Installation

```bash
composer require --dev prikotov/coding-standard
```

## Usage

In your `phpcs.xml.dist`:

```xml
<config name="installed_paths" value="vendor/prikotov/coding-standard"/>
<rule ref="PrikotovCodingStandard"/>
```

## Sniff tests

```bash
composer sniff-test
# or
php bin/run-sniff-tests.php
```

## Conventions

Located in `docs/`:

- **Principles** — values, code style
- **Core Patterns** — DTO, Entity, Value Object, Factory, Service, etc.
- **Layers** — Domain, Application, Infrastructure, Integration, Presentation
- **Modules** — modular architecture
- **Testing** — test conventions
- **Configuration** — Symfony configuration
- **Symfony** — folder structure, applications

## License

MIT
