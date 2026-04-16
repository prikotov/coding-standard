# prikotov/coding-standard

PHP coding standard with PHPCS sniffs and DDD conventions documentation.

## What is it

Two parts:

1. **PHPCS Sniffs** — automated code style checks (PHP CodeSniffer)
2. **Conventions** — DDD conventions documentation (layers, patterns, principles, testing)

## Sniffs

| Sniff | Description |
|---|---|
| `DtoStructureSniff` | Enforces `final readonly` DTO classes with empty promoted constructor |

Usage in your `phpcs.xml.dist`:

```xml
<config name="installed_paths" value="vendor/prikotov/coding-standard"/>
<rule ref="PrikotovCodingStandard"/>
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

## Installation

```bash
composer require --dev prikotov/coding-standard
```

## License

MIT
