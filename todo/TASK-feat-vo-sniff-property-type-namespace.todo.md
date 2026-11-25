---
type: feat
created: 2026-11-25 02:27:09 (1795573629)
due: 
started: 2026-11-25 03:25:40 (1795577140)
completed: 
cancelled: 
value: V2
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Лид Арагорн (pi)
assignee: Лид Арагорн (pi)
branch: task/vo-sniff-type-namespace-task
pr: 
status: in_progress
---

# TASK-feat-vo-sniff-property-type-namespace: ValueObjectStructureSniff: проверка типов свойств VO по namespace зависимостей

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Сниффер `ValueObjectStructureSniff` проверяет типы свойств VO по **чёрному списку суффиксов** (`Entity`/`Repository`/`Service`): ошибка только если имя типа заканчивается на запрещённый суффикс. Это эвристика «по плохому имени», а не по сути зависимости.
- Конвенция [value-object.md](../docs/conventions/core-patterns/value-object.md) разрешает зависимостям VO только примитивы, `DateTimeImmutable`, `Uuid`, `Enum` и другие VO **того же слоя**; запрещены Infrastructure, ORM/DB, внешние SDK. Но суффикс-проверка пропускает:
  - `private Closure $loader;` — замыкание, суффикса нет;
  - `private TickerModel $model;` — ORM-модель: по конвенции живёт в `{Module}\Domain\Entity\`, суффикс `Model` в чёрный список не входит.
- Оба случая реально прошли все проверки в stocks2 (см. [ретроспективу эпика](https://github.com/prikotov/stocks2/blob/master/docs/agents/team-retro/2026-11-24_18-59-refactor-vo-conventions-epic.md)); находка зафиксирована в [TASK-feat-vo-sniff-property-visibility](done/TASK-feat-vo-sniff-property-visibility.todo.md) как Should Have и вынесена сюда.

### Варианты или путь решения (Solution Sketch)
- Отклонено — белый список **имён типов** (`string`, `DateTimeImmutable`, … по списку): не выражает «другие VO и DTO того же слоя» без резолва имён, превращается в догонялки за списком.
- Выбрано — **проверка по namespace** (решение с владельцем, 2026-11-25): у конвенции уже есть формальный источник истины — пути размещения. Резолвим тип свойства в FQCN через `use`-импорты и классифицируем:
  - **глобальные типы** — маленький явный allowlist (`string`, `int`, `float`, `bool`, `array`, `DateTimeImmutable`, `Uuid`-типы); `Closure` и прочие глобальные не из списка → ошибка;
  - **проектный класс того же модуля и того же слоя** (для Domain-VO: `Domain\ValueObject`, `Domain\Enum`, …) → разрешено; сюда естественно попадают другие VO и DTO того же слоя — перечислять их не нужно, их разрешает сама структура;
  - **проектный класс другого слоя** (`Domain\Entity\` = ORM-модели, `Infrastructure\`, `Presentation\`) или **другого модуля** → ошибка `ForbiddenPropertyType`.
- `TickerModel` по конвенции обязан лежать в `{Module}\Domain\Entity\` — проверка ловит его по структуре пути, независимо от суффикса имени. Механика резолва в пакете уже есть: `extractFqcnFromUse` + `resolveFqcn` + use-map в `RepositoryStructureSniff`.

### Ожидаемый результат (Expected Result)
- Свойство VO с типом `Closure`, ORM-моделью (`Domain\Entity\…`), классом `Infrastructure\`/`Presentation\` или другого модуля не проходит `phpcs`; свойство с примитивом, `DateTimeImmutable`, `Uuid`, классом того же слоя — проходит чисто; пример в конвенции и валидные фикстуры пакета новых ошибок не дают; `composer check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя, я хочу, чтобы сниффер проверял зависимости VO по реальному расположению классов (namespace), а не по суффиксу имени, чтобы замыкания, ORM-модели и чужеслоевые зависимости не проходили проверку из-за «неудобного» имени типа.

### Цель по SMART (Goal)
- К очередному релизу `ValueObjectStructureSniff` классифицирует тип каждого свойства VO (классического и promoted) через FQCN + namespace и выдаёт `ForbiddenPropertyType` за глобальные типы вне allowlist, проектные классы другого слоя/модуля; суффикс-логика `FORBIDDEN_PROPERTY_TYPE_SUFFIXES` удалена; оба вида фикстур и пример конвенции зелёные; `composer check` зелёный.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `src/Sniffs/Structure/ValueObjectStructureSniff.php` (`assertPropertyTypeAllowed()`, константа `FORBIDDEN_PROPERTY_TYPE_SUFFIXES`, пути данных свойства из `assertProperties()`); паттерн резолва — `src/Sniffs/Structure/RepositoryStructureSniff.php` (`extractFqcnFromUse()`, `resolveFqcn()`, use-map); тесты `tests/Structure/ValueObjectStructureUnitTest*.inc`, `tests/fixtures.php`.
- **Классификация FQCN (зафиксировать тестами):**
  - глобальный из allowlist → разрешено;
  - глобальный вне allowlist (`Closure`, `stdClass`, …) → ошибка;
  - тот же модуль, тот же слой → разрешено (VO, DTO, Enum того же слоя);
  - другой слой того же модуля (`Domain\Entity\`, `Infrastructure\`, `Presentation\`, `Application\` для Domain-VO) → ошибка;
  - другой модуль → ошибка;
  - имя не резолвится через `use` и не глобальное (опечатка, missing import) → ошибка (безопаснее в сторону запрета; закрепить тестом).

  > Уточнено исполнителем при реализации (см. заметки в разделе 4): для кратких имён без `use` действует семантика PHP — текущий namespace; внутри слоя разрешены только группы `ValueObject`/`Enum`/`Dto` (`ALLOWED_DEPENDENCY_GROUPS`), `Domain\Entity` внутри Domain — запрещён.
- **Известные ограничения (документировать в коде):** дженерики (`array<int, TickerVo>`) и union/intersection типы — при реализации классифицируются поэлементно регэксп-разбором строки типа (Should Have закрыт больше запланированного).
- **Границы (Out of Scope):** другие сниффы (DTO/Entity), проверка типов параметров и возвращаемых значений методов, автоисправления (fixer), замена Deptrac (сниффер даёт локальный фидбек на строке свойства, Deptrac — архитектурный контроль; они дополняют друг друга).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Резолв «голого» типа свойства (классическое и promoted) в FQCN через `use`-импорты файла; глобальные типы — без `use`.
- [x] Удалена суффикс-логика `FORBIDDEN_PROPERTY_TYPE_SUFFIXES`; класс ошибки `ForbiddenPropertyType` сохранён (текст со ссылкой на `docs/conventions/core-patterns/value-object.md`).
- [x] Ошибка за глобальные типы вне allowlist (минимум `Closure` — проба из stocks2).
- [x] Ошибка за проектные классы другого слоя (минимум ORM-модель из `Domain\Entity\` — проба `TickerModel`) и другого модуля.
- [x] Без ошибок: примитивы, `DateTimeImmutable`, `Uuid`, классы того же модуля и слоя (VO/DTO/Enum).
- [x] Фикстуры: негативные (Closure, Entity-тип по FQCN, чужой модуль) и позитивные (same-layer VO/DTO/Enum, примитивы, `DateTimeImmutable`, `Uuid`); ожидания в `tests/fixtures.php` обновлены; пример в конвенции и валидные фикстуры пакета зелёные.
- [x] `composer check` зелёный.

### 🟡 Should Have (Желательно)
- [x] Поэлементная классификация union/intersection типов и дженериков (`array<int, TickerVo>`). → Сделано: регэксп-разбор строки типа на идентификаторы.

### ⚫ Won't Have (Не будем делать)
- Проверки в других сниффах, автоисправления, замена Deptrac.

## 4. План реализации (Implementation Plan)
1. [x] Воспроизвести пропуски пробами (`Closure`, `TickerModel` с корректным `use` на `Domain\Entity\…`) на текущем master.
2. [x] Перенести/переиспользовать механику резолва FQCN (use-map) из `RepositoryStructureSniff` в `ValueObjectStructureSniff`.
3. [x] Реализовать классификацию namespace (глобальные allowlist / тот же слой / другой слой / другой модуль / не резолвится) и заменить `assertPropertyTypeAllowed()`.
4. [x] Обновить фикстуры и ожидания; закрепить решение по неразрешимым именам и дженерикам тестами.
5. [x] `composer check`; отметить в коммите breaking-эффект (у потребителей могут появиться новые `ForbiddenPropertyType`).

**Заметки по реализации:**
- Резолв кратких имён: точное следование семантике PHP — не найден в `use`-карте и не глобальный → относится к **текущему namespace** файла (так делает и PHP). Пункт раздела 2 «не резолвится → ошибка» скорректирован: fail-strict действует на **квалифицированные** FQCN вне конвенционных путей `Module\{Module}\{Layer}\` и на глобальные вне allowlist. Same-namespace ссылка на соседний VO без `use` (например, `EmailVo`) обязана оставаться разрешённой — закреплено позитивной фикстурой `CustomerVo`.
- Слой внутри модуля различается по группе: разрешены только группы `ValueObject`, `Enum`, `Dto` (константа `ALLOWED_DEPENDENCY_GROUPS`); `Domain\Entity` (ORM), `Domain\Service` и классы прямо в namespace слоя — запрещены. Именно это ловит `TickerModel` из `Domain\Entity` независимо от суффикса.
- Дженерики/union/intersection: строка типа разбивается регэкспом на идентификаторы (`array<int, TickerVo>` → `array`, `int`, `TickerVo`) — элементы union и дженериков классифицируются поэлементно (Should Have закрыт больше запланированного).
- `use DateTimeImmutable;` (импорт глобального) корректно остаётся глобальным именем, а не проектным FQCN.
- Фикстуры: негативная — `UserEntity` (`Domain\Entity`), `\Closure`, чужой модуль (`Other\Module\Billing\Domain\ValueObject\MoneyVo`); позитивная — `UuidInterface` (vendor), same-namespace `EmailVo`, `Domain\Enum\UserStatus`, `DateTimeImmutable`, примитивы; отдельная Application-фикстура `UserProfileVo` с same-layer `Application\Dto\ProfileDto`.

## 5. Критерии приёмки (Definition of Done)
- [x] Пробы дают ожидаемый результат: `Closure` и `TickerModel` → ошибка, same-layer VO/DTO/Enum и примитивы → чисто.
- [x] Тесты обоих видов фикстур проходят; `composer check` зелёный.
- [ ] После обновления пакета в stocks2 (`composer update prikotov/coding-standard`) `make check` либо зелёный, либо показывает только реальные нарушения `ForbiddenPropertyType` (проверяет команда stocks2, не эта задача).

## 6. Самопроверка (Verification)
```bash
composer check
vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Structure.ValueObjectStructure tests/Structure/ValueObjectStructureUnitTest.inc
```

## 7. Риски и зависимости (Risks and Dependencies)
- Breaking-эффект: у потребителей могут появиться новые `ForbiddenPropertyType` на существующих VO (в т.ч. на legacy-классы вне конвенционных путей — правило по умолчанию считает их нарушением); упомянуть в коммите и release notes, SemVer — на усмотрение команды.
- Классификация опирается на конвенционные пути из `value-object.md` (разделы «Зависимости» и «Расположение»); изменение конвенции путей потребует синхронного изменения сниффера.
- Ложные срабатывания на легаси-код с нестандартными namespace — вероятно по дизайну (fail-strict); при необходимости команда может уточнить allowlist.

## 8. Источники (Sources)
- Конвенция: `docs/conventions/core-patterns/value-object.md` (разделы «Общие правила», «Зависимости», «Расположение»).
- Предшественник: [TASK-feat-vo-sniff-property-visibility](done/TASK-feat-vo-sniff-property-visibility.todo.md) (раздел 2, «Типы свойств» — побочное наблюдение).
- Паттерн резолва: `src/Sniffs/Structure/RepositoryStructureSniff.php` (`extractFqcnFromUse`, `resolveFqcn`).
- stocks2: [ретроспектива эпика EPIC-refactor-vo-conventions](https://github.com/prikotov/stocks2/blob/master/docs/agents/team-retro/2026-11-24_18-59-refactor-vo-conventions-epic.md).

## 9. Комментарии (Comments)
Образцы для воспроизведения (в каталоге `ValueObject`, к которому применён сниффер) — проверены 2026-11-25, обе пробы дают ожидаемый результат:

```php
<?php
declare(strict_types=1);
namespace Probe\Common\Module\X\Domain\ValueObject;

use Probe\Common\Module\X\Domain\Entity\TickerModel;

// Ожидается: 2 ошибки ForbiddenPropertyType (сейчас — 0).
final readonly class BadDependencyVo
{
    public function __construct(
        private \Closure $loader,
        private TickerModel $model,
    ) {}
}
```

```php
<?php
declare(strict_types=1);
namespace Probe\Common\Module\X\Domain\ValueObject;

use DateTimeImmutable;

// Ожидается: без ошибок.
final readonly class GoodDependencyVo
{
    public function __construct(
        private string $ticker,
        private int $quantity,
        private DateTimeImmutable $quotedAt,
        private TickerVo $tickerVo,
    ) {}
}
```

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-25 02:27:09 (1795573629) | Лид Арагорн (pi) | Создание задачи |
| 2026-11-25 10:35:17 (1795577717) | Лид Арагорн (pi) | Взятие в работу (pi); реализация проверки типов свойств VO по namespace (резолв FQCN через use-map, группы ValueObject/Enum/Dto внутри слоя, allowlist глобальных и Ramsey\Uuid), фикстуры и ожидания обновлены, Should Have по union/дженерикам закрыт, `composer check` зелёный |
