---
name: PHPStan DTO Reuse
type: rule
description: PHPStan-extension для проверки переиспользования общих DTO модуля
---

# Проверка переиспользования общих DTO (PHPStan extension)

**PHPStan extension** находит общие DTO в `Module\{M}\Application\Dto\`, которые по факту используются меньшим числом use case'ов, чем порог (по умолчанию 2), и предлагает перенести их рядом с владельцем в `UseCase\{Case}\`.

## Общие правила

- Общий пул DTO модуля предназначен для **переиспользуемых** проекций доменных сущностей.
- DTO, который используется одним query/command — use-case-специфичный, ему место рядом с владельцем, а не в общем пуле.
- Проверка не зависит от имени DTO (суффикса/префикса) — только от фактического переиспользования. Не обходится переименованием.
- Корневой общий пул `Common\Application\Dto\` не проверяется — он заведомо общий.

## Как это работает

Extension состоит из двух collector'ов и одного rule:

- `HandlerReturnCollector` — для каждого query/command-handler'а (`...\UseCase\{Query|Command}\...`) собирает FQCN return-типа метода `__invoke()` (прямой `: XxxDto` и nullable `: ?XxxDto`).
- `DtoLocationCollector` — собирает DTO-классы в `Module\{M}\Application\Dto\` (FQCN + позиция для ошибки).
- `DtoReuseRule` — на агрегированных данных считает для каждого DTO количество уникальных handler'ов; если < порога — error.

Cross-file aggregation делает сам PHPStan (collectors → rule на `CollectedDataNode`).

## Установка

1. Потребитель должен иметь PHPStan в `require-dev` (сосуществует с Psalm, не конфликтует):
   ```bash
   composer require --dev phpstan/phpstan
   ```

2. Подключить extension. При установленном `phpstan/extension-installer` — автоматически. Иначе добавить в `phpstan.neon`:
   ```neon
   includes:
       - vendor/prikotov/coding-standard/phpstan-rules.neon
   ```

## Настройка

По умолчанию порог — 2 (нужно ≥2 use case'ов). Изменить через аргумент сервиса в `phpstan.neon` потребителя:
```neon
services:
    -
        class: PrikotovCodingStandard\PhpStan\DtoReuseRule
        arguments:
            minUses: 1
```

## Пример ошибки

```text
DTO App\Module\Billing\Application\Dto\InitializeRegistrationResultDto
в общем пуле Application\Dto используется 1 use case'ом(ами), порог 2.
Перенесите рядом с владельцем в UseCase\{Case}\.
See: docs/conventions/core-patterns/dto.md
```

## Ограничения

- Must Have учитывает только прямой return-тип handler'а (`: XxxDto`, `: ?XxxDto`).
- Collections в return-типе (`array<XxxDto>`, `list<XxxDto>`) и DTO как поле другого DTO — не учитываются (запланированы отдельной задачей).
- DTO как параметр handler'а — не учитывается.

## Расположение

- Extension: `vendor/prikotov/coding-standard/phpstan-rules.neon`
- Конфиг потребителя: `phpstan.neon` (в корне проекта)

Подробнее о конвенции размещения DTO: [dto.md](../core-patterns/dto.md).

## Чек-лист для проведения ревью кода

- [ ] DTO в общем пуле `Application\Dto\` — переиспользуемая проекция, а не use-case-специфичный Result/Request/Response.
- [ ] Use-case-специфичный DTO лежит рядом с use case'ом в `UseCase\{Case}\`, не в общем пуле.
- [ ] Порог `minUses` осмыслен для проекта (по умолчанию 2).
