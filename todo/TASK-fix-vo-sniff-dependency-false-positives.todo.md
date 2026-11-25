---
type: fix
created: 2026-11-25 10:50:00 (1795578600)
due: 
started: 2026-11-25 10:50:00 (1795578600)
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
assignee: Лид Арагорн (pi)
branch: task/vo-sniff-dependency-false-positives
pr: 
status: in_progress
---

# TASK-fix-vo-sniff-dependency-false-positives: ValueObjectStructureSniff: ложные срабатывания ForbiddenPropertyType на Uid, Embeddable VO и общих Enum

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После внедрения проверки типов свойств VO по namespace ([TASK-feat-vo-sniff-property-type-namespace](done/TASK-feat-vo-sniff-property-type-namespace.todo.md)) прогон на проекте-потребителе TasK дал 41 ошибку `ForbiddenPropertyType`, из которых **27 — ложные срабатывания**: сниффер строже конвенции в трёх местах.
- Кейс 1 — `Symfony\Component\Uid\Uuid` (22 срабатывания): конвенция разрешает «`Uuid`», а allowlist вендорных префиксов знал только `Ramsey\Uuid`. При этом сами примеры конвенции (`layers/infrastructure/repository.md`) используют `Symfony\Component\Uid\Uuid`.
- Кейс 2 — Embeddable VO `Domain\Entity\ValueObject\{Name}Vo` (2): конвенция прямо разрешает такое размещение, но группа бралась как «сегмент сразу после слоя» → `Entity` → запрет. Domain-VO `AttributionResolutionVo` корректно зависит от своих embeddable `DeviceVo`/`UtmVo`.
- Кейс 3 — модуль-независимые Enum (3): конвенция `enum.md` разрешает `ProjectName\Common\Enum`; пути без `Module\{Name}\` давали `module === null` → запрет. Пострадавшие: `Task\Common\Component\ProcessingActivity\ProcessingActivityTargetEnum`, `ScopeEnum`, `ValueTypeEnum`.

### Варианты или путь решения (Solution Sketch)
- Не ослаблять проверку целиком (честные нарушения — ORM-модели, `Domain\Service`, другие модули — должны остаться), а устранить три конкретных расхождения сниффера с конвенцией:
  - в allowlist вендорских префиксов добавить `symfony\component\uid\`;
  - группу зависимости определять по **последнему** сегменту-категории перед именем класса (так `Domain\Entity\ValueObject\X` → `valueobject`, а `Domain\Entity\PaymentModel` → `entity`);
  - разрешить «общие» типы `{Project}\Common\{Enum|Component|ValueObject}\…` без `Module`-сегмента — они по определению одинаковы для всех модулей и слоёв.
- Конвенцию `value-object.md` дополнить: уточнить `Uuid` (любая реализация), явно перечислить разрешённые общие и Embeddable VO, явно запретить другие модули и слои (кейс Infrastructure-VO → Domain выявил, что это следовало из текста лишь косвенно).

### Ожидаемый результат (Expected Result)
- На TasK `ForbiddenPropertyType` падает с 41 до 14 — остаются только честные нарушения; все 138 `NonPrivateProperty` без изменений. Валидные фикстуры и пример конвенции зелёные; `composer check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя, я хочу, чтобы сниффер запрещал ровно то, что запрещено конвенцией — не строже, чтобы не тратить время ревью на разбор ложных ошибок и не привыкать игнорировать проверки.

### Цель по SMART (Goal)
- К ближайшему релизу сниффер не выдаёт `ForbiddenPropertyType` на `Symfony\Component\Uid\Uuid`, Embeddable VO своего модуля и общие Enum/VO из `{Project}\Common\…`; честные нарушения (ORM, Service, другие модули/слои) остаются; конвенция описывает все разрешённые/запрещённые категории явно; покрыто фикстурами; `composer check` зелёный.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `src/Sniffs/Structure/ValueObjectStructureSniff.php` (`ALLOWED_VENDOR_NAMESPACE_PREFIXES`, `resolveDependencyScope()`, `isAllowedDependencyFqcn()`); `docs/conventions/core-patterns/value-object.md` (раздел «Зависимости», чек-лист); фикстуры `tests/Structure/ValueObjectStructureUnitTestValid.inc`.
- **Аудит по TasK (2026-11-25):** из 41 `ForbiddenPropertyType` ложные — 22×`Uuid`, 2× Embeddable, 3× общие Enum; честные — 6× `*Model` из `Domain\Entity`, 1× `Domain\Service\…Interface`, 5× Infrastructure-VO → Domain, 1× другой модуль (`Billing` → `Llm\Domain\Enum`), 2× `apps/web` → `Application\Enum`.
- **Кейс 4 (не баг, пробел конвенции):** Infrastructure-VO зависят от Domain-типов — сниффер ловит верно, но в конвенции это следовало лишь косвенно. Решено описать явно.
- **Границы (Out of Scope):** перепроверка `NonPrivateProperty` (138 на TasK — все честные); правила для других слоёв/сниффов; изменения в TasK.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `Symfony\Component\Uid\Uuid` (и `Ramsey\Uuid`) — разрешённые типы свойств VO.
- [x] Embeddable VO своего модуля (`Domain\Entity\ValueObject\…Vo`) — разрешённая зависимость; `Domain\Entity\…Model` — по-прежнему запрещена.
- [x] Общие типы `{Project}\Common\Enum\…`, `{Project}\Common\Component\…`, `{Project}\Common\ValueObject\…` — разрешены; пути с `Module\` обрабатываются по прежней логике.
- [x] Честные нарушения остаются: ORM-модели, `Domain\Service`, другие слои и модули.
- [x] Позитивные фикстуры для всех трёх кейсов; ожидания в `tests/fixtures.php` актуальны.
- [x] Конвенция `value-object.md`: раздел «Зависимости» явно описывает разрешённые (Uuid любой реализации, общие, Embeddable) и запрещённые (Entity/Service, другие модули и слои) категории; чек-лист обновлён.
- [x] `composer check` зелёный.

### ⚫ Won't Have (Не будем делать)
- Изменения в проекте TasK; ослабление проверки целиком (списки исключений, suppress).

## 4. План реализации (Implementation Plan)
1. [x] Воспроизвести три ложных срабатывания на минимальных пробах (по мотивам реальных файлов TasK).
2. [x] Добавить `symfony\component\uid\` в `ALLOWED_VENDOR_NAMESPACE_PREFIXES`.
3. [x] Группа зависимости — последний сегмент-категория перед именем класса (`resolveDependencyScope()`).
4. [x] Разрешить общие типы `{Project}\Common\{Enum|Component|ValueObject}\…` без `Module`-сегмента (`isSharedType()`).
5. [x] Закрепить фикстурами: позитивные — Symfony Uid, `Common\Enum`, `Common\Component\…Enum`, Embeddable VO; негативные — без изменений (проверить, что честные не пропали).
6. [x] Обновить конвенцию `value-object.md`.
7. [x] `composer check`; повторный прогон на TasK — дельта 41 → 14.

**Заметки по реализации:**
- `isSharedType()`: ищет сегмент `Common`, за которым идёт один из `SHARED_TYPE_SEGMENTS` (`enum`, `component`, `valueobject`), и требует отсутствия `Module` дальше по пути — иначе это обычный модульный тип и обрабатывается по module/layer-логике.
- `resolveDependencyScope()`: из сегментов между слоем и именем класса берётся **последний** совпавший с известными категориями (`dto`, `enum`, `valueobject`, `entity`, `service`, `repository`). Поэтому `Domain\Entity\ValueObject\X` → `valueobject`, а `Domain\Entity\XModel` → `entity`.
- Повторный прогон на TasK: `ForbiddenPropertyType` 41 → 14 (`UserBalanceModel`×2, `ConversionProfileParametersInterface`×2, `ExtractionProfileParametersInterface`×2, `AttributionSessionModel`, `ModelPriceModel`, `PaymentModel`, `FxRateModel`, `ProviderEnum`, `ToolFunctionInterface`, `ValueTypeEnum`, `ScopeEnum`); `NonPrivateProperty` — 138 без изменений. Все 14 — честные нарушения по обновлённой конвенции.

## 5. Критерии приёмки (Definition of Done)
- [x] Пробы-ложные (Symfony Uid, Embeddable, общий Enum) дают 0 ошибок; пробы-честные (ORM, Service, другой слой, другой модуль) дают ошибку.
- [x] `composer check` зелёный; фикстуры покрывают все три кейса.
- [x] На TasK `ForbiddenPropertyType` падает с 41 до 14, остаются только честные нарушения.

## 6. Самопроверка (Verification)
```bash
composer check
vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Structure.ValueObjectStructure tests/Structure/ValueObjectStructureUnitTestValid.inc
```

## 7. Риски и зависимости (Risks and Dependencies)
- Зависит от конвенции `value-object.md` и `enum.md` (разрешённые расположения общих Enum); при изменении путей — синхронная правка сниффера.
- `{Project}\Common\…` без `Module` — эвристика по сегменту `Common`; проекты с другим корнем общих типов потребуют расширения `SHARED_TYPE_SEGMENTS`.
- Связанные: [TASK-feat-vo-sniff-property-type-namespace](done/TASK-feat-vo-sniff-property-type-namespace.todo.md) (внедрение проверки), [TASK-feat-vo-sniff-property-visibility](done/TASK-feat-vo-sniff-property-visibility.todo.md).

## 8. Источники (Sources)
- Конвенции: `docs/conventions/core-patterns/value-object.md` («Зависимости», «Расположение»), `docs/conventions/core-patterns/enum.md` (расположение общих Enum), `docs/conventions/layers/infrastructure/repository.md` (пример с `Symfony\Component\Uid\Uuid`).
- Аудит на TasK: `~/MyProjects/TasK/Development`, прогон `ValueObjectStructureSniff` на `src` и `apps` (2026-11-25).

## 9. Комментарии (Comments)
- Задача создана по результатам аудита сниффера на проекте-потребителе после мержа #136, сразу взята в работу.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-25 10:50:00 (1795578600) | Лид Арагорн (pi) | Создание задачи, взятие в работу (pi) |
| 2026-11-25 10:50:00 (1795578600) | Лид Арагорн (pi) | Реализация: Symfony Uid в allowlist, группа по последней категории (Embeddable), общие типы `Common\…`; конвенция дополнена; фикстуры; TasK 41 → 14; `composer check` зелёный |
