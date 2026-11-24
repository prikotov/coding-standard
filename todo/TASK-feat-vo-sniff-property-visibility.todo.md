---
type: feat
created: 2026-11-24 12:44:28 (1795524268)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C2
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Лид Арагорн (pi)
assignee: 
branch: 
pr: 
status: todo
---

# TASK-feat-vo-sniff-property-visibility: ValueObjectStructureSniff: проверка видимости свойств VO — только private

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Конвенция [value-object.md](../docs/conventions/core-patterns/value-object.md) требует: «свойства приватные и строго типизированные». Сниффер (автопроверка стиля) `ValueObjectStructureSniff` это правило не проверяет: объект-значение (Value Object, VO) с публичным или защищённым свойством проходит `phpcs` без единой ошибки.
- Проверено прогонами на v0.34.2 (коммит `7f8fb61`, PHP_CodeSniffer 4.0.4) командой `vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Structure.ValueObjectStructure`:
  - публичные свойства в конструкторе (promoted) — `public function __construct(public string $ticker) {}` — 0 ошибок;
  - защищённое promoted-свойство (`protected string $ticker`) — 0 ошибок;
  - классическое свойство `public readonly string $ticker;` — 0 ошибок;
  - контроль: `private readonly string $ticker;` и приватные promoted-свойства — 0 ошибок (здесь всё верно).
- Последствие в проекте-потребителе `stocks2`: около тридцати VO с публичными свойствами (PR prikotov/stocks2#466–#469) прошли все проверки и были найдены только вручную — см. [ретроспективу эпика](https://github.com/prikotov/stocks2/blob/master/docs/agents/team-retro/2026-11-24_18-59-refactor-vo-conventions-epic.md).

### Варианты или путь решения (Solution Sketch)
- Дополнить `assertProperties()` сниффера проверкой видимости: любое свойство VO, кроме `private`, — ошибка с кодом вида `NonPrivateProperty` и ссылкой на конвенцию.
- Попутно закрыть причину пропуска: promoted-свойства сегодня не проверяются вовсе (см. раздел 2) — распространить на них уже существующие проверки (`readonly`, типы) и новую проверку видимости.
- Покрыть тестами: негативные и позитивные фикстуры для классических и promoted-свойств.

### Ожидаемый результат (Expected Result)
- VO с `public`/`protected` свойством любого вида не проходит `phpcs`; корректные VO (только `private`) новых ошибок не получают; `composer check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя, я хочу, чтобы сниффер ловил публичные и защищённые свойства VO, чтобы правило конвенции соблюдалось автоматически, а не только вниманием ревьюера.

### Цель по SMART (Goal)
- К ближайшему релизу пакета `ValueObjectStructureSniff` сообщает ошибку за каждое не приватное свойство VO — классическое и promoted; проверка покрыта тестами для обоих видов; `composer check` зелёный; в CHANGELOG отмечено, что новая проверка может упасть у потребителей.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `src/Sniffs/Structure/ValueObjectStructureSniff.php` (методы `assertProperties()`, `belongsToClass()`), тесты `tests/Structure/ValueObjectStructureUnitTest.inc`, `tests/Structure/ValueObjectStructureUnitTestValid.inc`, ожидания в `tests/fixtures.php`.
- **Почему свойства пропускаются (по чтению кода, подтвердить при реализации):**
  1. Promoted-свойства не проверяются целиком. `belongsToClass()` сравнивает с указателем класса последний элемент `conditions` токена; у токенов внутри метода последний элемент — сама функция, поэтому для параметров конструктора проверка возвращает `false`, и `assertProperties()` делает `continue`. Косвенное подтверждение — в негативной фикстуре строка 17 (`public function __construct(public string $value)` в не-readonly классе) не имеет ожидаемой ошибки.
  2. Для классических свойств проверяются только `is_readonly` и суффиксы типа; поле `scope` из `getMemberProperties()` не используется.
- **Побочное наблюдение (решить команде пакета):** классическое свойство без слова `readonly` внутри `readonly class` (по PHP оно неявно readonly) помечается ошибкой «ValueObject properties must be readonly» — `private string $ticker;` в `final readonly class`. Это либо ложное срабатывание (тогда исправить), либо требование явного ключевого слова (тогда оставить и закрепить тестом).
- **Типы свойств (выходят за рамки обязательного):** проверка идёт по суффиксам `Entity`/`Repository`/`Service`. Проходят без ошибок `private Closure $loader` и свойство типа ORM-модели (`private TickerModel $model`). В stocks2 так «VO» с замыканием и зависимостью от ORM-модели прошло все проверки.
- **Границы (Out of Scope):** другие сниффы; автоисправления (fixer); изменение текста конвенции.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Новая ошибка (код вида `NonPrivateProperty`) за каждое свойство VO с видимостью `public` или `protected` — для классических и для promoted-свойств; в тексте ошибки — ссылка на `docs/conventions/core-patterns/value-object.md`.
- [ ] Существующие проверки `readonly` и типов свойств распространены на promoted-свойства (сейчас они пропускаются целиком) — без ложных срабатываний на корректных приватных promoted-свойствах.
- [ ] Тесты: в негативной фикстуре — `public` и `protected` в обоих видах (классическое и promoted); в позитивной — `private` и `private readonly` в обоих видах; ожидания в `tests/fixtures.php` обновлены.
- [ ] Валидные фикстуры пакета (в том числе `tests/fixtures/src/Module/Example/Domain/ValueObject/EmailVo.inc`) и примеры кода в `docs/conventions/` новых ошибок не дают.
- [ ] `composer check` зелёный.

### 🟡 Should Have (Желательно)
- [ ] Типы свойств — по списку разрешённых (примитивы, `\DateTimeImmutable`, `UnitEnum`, другие VO и Enum), а не по суффиксам запрещённых: так ловятся `Closure` и ORM-модели. Если команда предпочитает отдельную задачу — вынести без потери находки.

### ⚫ Won't Have (Не будем делать)
- Изменение других сниффов и конвенции; автоисправления.

## 4. План реализации (Implementation Plan)
1. [ ] Воспроизвести пропуски пробами из раздела 9 на текущем master.
2. [ ] Исправить обход свойств: учитывать promoted-параметры конструктора (принадлежность классу проверять по вхождению класса в `conditions`, а не по последнему элементу).
3. [ ] Добавить проверку видимости (`scope === 'private'`) с новым кодом ошибки.
4. [ ] Обновить фикстуры и ожидания; при решении по неявному `readonly` — закрепить тестом.
5. [ ] `composer check`; обновить CHANGELOG по регламенту релизов (новая проверка может упасть у потребителей — оценить как minor по SemVer на усмотрение команды).

## 5. Критерии приёмки (Definition of Done)
- [ ] Все пробы из раздела 9 дают ожидаемый результат: `public`/`protected` — ошибка, `private` — без ошибок.
- [ ] Тесты на оба вида свойств проходят; `composer check` зелёный.
- [ ] После обновления пакета в stocks2 (`composer update prikotov/coding-standard`) `make check` либо зелёный, либо показывает только реальные нарушения вида `NonPrivateProperty` (проверяет команда stocks2, не эта задача).

## 6. Самопроверка (Verification)
```bash
composer check
vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Structure.ValueObjectStructure tests/Structure/ValueObjectStructureUnitTest.inc
```

## 7. Риски и зависимости (Risks and Dependencies)
- Новая проверка у потребителей может «сломать» их `phpcs` на существующем коде — это ожидаемый эффект; упомянуть в CHANGELOG.
- Ложные срабатывания на VO с публичными константами недопустимы: константы (public — для доменных ограничений) разрешены конвенцией и проверяться не должны.
- Зависит от конвенции `docs/conventions/core-patterns/value-object.md`; исходная задача сниффера — [TASK-feat-value-object-structure-sniff](done/TASK-feat-value-object-structure-sniff.todo.md).

## 8. Источники (Sources)
- Конвенция: `docs/conventions/core-patterns/value-object.md` (раздел «Общие правила»).
- Исходная задача: [TASK-feat-value-object-structure-sniff](done/TASK-feat-value-object-structure-sniff.todo.md).
- stocks2: [ретроспектива эпика EPIC-refactor-vo-conventions](https://github.com/prikotov/stocks2/blob/master/docs/agents/team-retro/2026-11-24_18-59-refactor-vo-conventions-epic.md), PR [prikotov/stocks2#466](https://github.com/prikotov/stocks2/pull/466).

## 9. Комментарии (Comments)
Образцы для воспроизведения (в каталоге `ValueObject`, к которому применён сниффер):

```php
<?php
declare(strict_types=1);
namespace Probe\Common\Module\X\Domain\ValueObject;

// Ожидается: ошибка NonPrivateProperty (сейчас — 0 ошибок).
final readonly class PublicPromotedVo
{
    public function __construct(
        public string $ticker,
        public readonly int $quantity,
    ) {}

    public function getTicker(): string
    {
        return $this->ticker;
    }
}
```

```php
<?php
declare(strict_types=1);
namespace Probe\Common\Module\X\Domain\ValueObject;

// Ожидается: без ошибок.
final readonly class PrivateVo
{
    private readonly string $code;

    public function __construct(
        private string $ticker,
        private readonly int $quantity,
        string $code,
    ) {
        $this->code = $code;
    }

    public function getTicker(): string
    {
        return $this->ticker;
    }
}
```

- Задача создана по результатам эпика stocks2 и его ретроспективы (2026-11-24) по просьбе владельца: «создай там задачу, команда проекта её сделает».

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-24 12:44:28 (1795524268) | Лид Арагорн (pi) | Создание задачи |
