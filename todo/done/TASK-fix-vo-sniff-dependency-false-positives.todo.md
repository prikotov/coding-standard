---
type: fix
created: 2026-11-25 10:50:00 (1795578600)
due: 
started: 2026-11-25 10:50:00 (1795578600)
completed: 2026-11-25 05:34:03 (1795584843)
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
pr: https://github.com/prikotov/coding-standard/pull/137
status: done
---

# TASK-fix-vo-sniff-dependency-false-positives: ValueObjectStructureSniff: ложные срабатывания ForbiddenPropertyType на Uid и общих Enum; запрет Embeddable VO

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После внедрения проверки типов свойств VO по namespace ([TASK-feat-vo-sniff-property-type-namespace](TASK-feat-vo-sniff-property-type-namespace.todo.md)) прогон на проекте-потребителе TasK дал 41 ошибку `ForbiddenPropertyType`. Аудит показал: **25 — ложные срабатывания** (сниффер строже конвенции), **16 — честные**.
- Кейс 1 (ложное) — `Symfony\Component\Uid\Uuid` (22): конвенция разрешает «`Uuid`», а allowlist вендорных префиксов знал только `Ramsey\Uuid`. При этом примеры самих конвенций пакета (`layers/infrastructure/repository.md`) используют Symfony Uid.
- Кейс 3 (ложное) — модуль-независимые Enum (3): конвенция `enum.md` разрешает `ProjectName\Common\Enum`, но пути без `Module\{Name}\` давали `module === null` → запрет. Пострадавшие: `Task\Common\Component\ProcessingActivity\ProcessingActivityTargetEnum`, `ScopeEnum`, `ValueTypeEnum`.
- **Кейс 2 — Embeddable VO `Domain\Entity\ValueObject\{Name}Vo` (2) — по ходу задачи переквалифицирован из «ложного» в честное нарушение.** Первоначально сниффер был «исправлен» в сторону разрешения (конвенция же разрешает такое *расположение*), но владелец поставил вопрос: «это часть сущности — стоит ли разрешать?». Разбор подтвердил: Embeddable несут Doctrine-маппинг (`#[Embeddable]`, `#[Column]`, `Doctrine\DBAL\Types`) и являются persistence-частью сущности, а не доменным типом. Зависимость доменного VO от них нарушает «Запрещены ORM/DB» транзитивно. В TasK это видно: `DeviceVo`/`UtmVo` используются в 5+ файлах Application (мапперы, интерфейсы сервисов), `ClientCostVo` — в Command Handler. **Решение: запретить.** Ошибка первой версии фикса — склейка двух разных правил: «где хранить» (расположение) и «что можно инжектить в другой VO» (зависимости).

### Варианты или путь решения (Solution Sketch)
- Не ослаблять проверку целиком (честные нарушения — ORM-модели, `Domain\Service`, Embeddable, другие модули — должны остаться), а устранить два конкретных расхождения сниффера с конвенцией:
  - в allowlist вендорских префиксов добавить `symfony\component\uid\`;
  - разрешить «общие» типы `{Project}\Common\{Enum|Component|ValueObject}\…` без `Module`-сегмента — они по определению одинаковы для всех модулей и слоёв.
- Embeddable VO — явно запретить как зависимость VO; в конвенции объяснить почему (persistence-часть сущности).
- Конвенцию `value-object.md` дополнить: явно перечислить разрешённые (общие) и запрещённые (Entity, Embeddable, Service, другие модули и слои) категории — кейс Infrastructure-VO → Domain выявил, что это следовало из текста лишь косвенно. `Uuid` оставить без привязки к реализации (какое выбрать — спорно, это решение проекта; сниффер знает обе реализации).

### Ожидаемый результат (Expected Result)
- На TasK `ForbiddenPropertyType` падает с 41 до 16 — остаются только честные нарушения; все 138 `NonPrivateProperty` без изменений. Валидные фикстуры и пример конвенции зелёные; `composer check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя, я хочу, чтобы сниффер запрещал ровно то, что запрещено конвенцией — не строже и не мягче, чтобы не тратить время ревью на разбор ложных ошибок и чтобы легализация сомнительных зависимостей была осознанным решением конвенции, а не побочным эффектом фикса.

### Цель по SMART (Goal)
- К ближайшему релизу сниффер не выдаёт `ForbiddenPropertyType` на `Symfony\Component\Uid\Uuid` и общие Enum/VO из `{Project}\Common\…`; выдаёт на Embeddable VO, ORM-модели, `Domain\Service`, другие модули и слои; конвенция описывает все разрешённые/запрещённые категории явно с обоснованием запрета Embeddable; покрыто фикстурами; `composer check` зелёный.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `src/Sniffs/Structure/ValueObjectStructureSniff.php` (`ALLOWED_VENDOR_NAMESPACE_PREFIXES`, `resolveDependencyScope()`, `isAllowedDependencyFqcn()`, `isSharedType()`); `docs/conventions/core-patterns/value-object.md` (разделы «Зависимости», «Расположение», чек-лист); фикстуры `tests/Structure/ValueObjectStructureUnitTest*.inc`, ожидания `tests/fixtures.php`.
- **Аудит по TasK (2026-11-25):** из 41 `ForbiddenPropertyType` ложные — 22×`Uuid`, 3× общие Enum; честные — 6× `*Model` из `Domain\Entity`, 1× `Domain\Service\…Interface`, 5× Infrastructure-VO → Domain, 1× другой модуль (`Billing` → `Llm\Domain\Enum`), 2× `apps/web` → `Application\Enum`, 2× Embeddable VO (после пересмотра).
- **Кейс 4 (не баг, пробел конвенции):** Infrastructure-VO зависят от Domain-типов — сниффер ловит верно, но в конвенции это следовало лишь косвенно. Решено описать явно.
- **Границы (Out of Scope):** перепроверка `NonPrivateProperty` (138 на TasK — все честные); правила для других слоёв/сниффов; проверки параметров/возвратов методов (утечка Embeddable в Application-контракты TasK — материал для отдельной задачи); изменения в TasK.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `Symfony\Component\Uid\Uuid` (и `Ramsey\Uuid`) — разрешённые типы свойств VO.
- [x] Общие типы `{Project}\Common\Enum\…`, `{Project}\Common\Component\…`, `{Project}\Common\ValueObject\…` — разрешены; пути с `Module\` обрабатываются по прежней логике.
- [x] Embeddable VO (`Domain\Entity\ValueObject\…Vo`) — **запрещённая** зависимость VO; вложенные ValueObject-группы (`Domain\ValueObject\Group\…Vo`) и Enum своего слоя — по-прежнему разрешены.
- [x] Честные нарушения остаются: ORM-модели, `Domain\Service`, другие слои и модули.
- [x] Фикстуры: позитивные — Symfony Uid, `Common\Enum`, `Common\Component\…Enum`; негативные — Embeddable VO; ожидания в `tests/fixtures.php` актуальны.
- [x] Конвенция `value-object.md`: «Зависимости» явно описывает разрешённые (общие) и запрещённые (Entity, Embeddable с обоснованием, Service, другие модули и слои) категории; `Uuid` без привязки к реализации; «Расположение» уточняет, что Embeddable применяются только внутри сущностей; чек-лист обновлён.
- [x] `composer check` зелёный.

### ⚫ Won't Have (Не будем делать)
- Изменения в проекте TasK; ослабление проверки целиком (списки исключений, suppress); фиксация конкретной реализации `Uuid` в конвенции.

## 4. План реализации (Implementation Plan)
1. [x] Воспроизвести ложные срабатывания на минимальных пробах (по мотивам реальных файлов TasK).
2. [x] Добавить `symfony\component\uid\` в `ALLOWED_VENDOR_NAMESPACE_PREFIXES`.
3. [x] Разрешить общие типы `{Project}\Common\{Enum|Component|ValueObject}\…` без `Module`-сегмента (`isSharedType()`).
4. [x] Embeddable VO — запретить: `resolveDependencyScope()` возвращает `path` (сегменты между слоем и именем класса), наличие `entity` в пути → запрет.
5. [x] Закрепить фикстурами: позитивные — Symfony Uid, `Common\Enum`, `Common\Component\…Enum`; негативные — Embeddable VO (плюс контроль: вложенная ValueObject-группа и Enum проходят).
6. [x] Обновить конвенцию `value-object.md`.
7. [x] `composer check`; повторный прогон на TasK — дельта 41 → 16.

**Заметки по реализации:**
- `isSharedType()`: ищет сегмент `Common`, за которым идёт один из `SHARED_TYPE_SEGMENTS` (`enum`, `component`, `valueobject`), и требует отсутствия `Module` дальше по пути — иначе это обычный модульный тип и обрабатывается по module/layer-логике.
- `resolveDependencyScope()`: возвращает `group` — последний сегмент-категория перед именем класса (из `dto`, `enum`, `valueobject`, `entity`, `service`, `repository`) и `path` — все сегменты между слоем и именем в нижнем регистре. Embeddable определяется по `entity` в `path`, поэтому `Domain\Entity\ValueObject\XVo` запрещён, а `Domain\ValueObject\Group\XVo` — нет.
- История кейса 2: первая версия фикса (коммит `39e602c`) разрешала Embeddable через «группа = последняя категория». После вопроса владельца решение пересмотрено — запрет закреплён коммитом `fix(rules): forbid Embeddable VO as VO dependency` и фикстурами.
- Повторный прогон на TasK: `ForbiddenPropertyType` 41 → 16 (`UserBalanceModel`×2, `ConversionProfileParametersInterface`×2, `ExtractionProfileParametersInterface`×2, `AttributionSessionModel`, `ModelPriceModel`, `PaymentModel`, `FxRateModel`, `ProviderEnum`, `ToolFunctionInterface`, `ValueTypeEnum`, `ScopeEnum`, `DeviceVo`, `UtmVo`); `NonPrivateProperty` — 138 без изменений. Все 16 — честные нарушения по обновлённой конвенции.

## 5. Критерии приёмки (Definition of Done)
- [x] Пробы-ложные (Symfony Uid, общий Enum) дают 0 ошибок; пробы-честные (ORM, Embeddable, Service, другой слой, другой модуль) дают ошибку; вложенная ValueObject-группа и Enum проходят.
- [x] `composer check` зелёный; фикстуры покрывают все кейсы.
- [x] На TasK `ForbiddenPropertyType` падает с 41 до 16, остаются только честные нарушения.

## 6. Самопроверка (Verification)
```bash
composer check
vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Structure.ValueObjectStructure tests/Structure/ValueObjectStructureUnitTest.inc
```

## 7. Риски и зависимости (Risks and Dependencies)
- Зависит от конвенций `value-object.md` и `enum.md` (разрешённые расположения общих Enum); при изменении путей — синхронная правка сниффера.
- `{Project}\Common\…` без `Module` — эвристика по сегменту `Common`; проекты с другим корнем общих типов потребуют расширения `SHARED_TYPE_SEGMENTS`.
- Запрет Embeddable — новое правило конвенции: у потребителей существующие зависимости VO от Embeddable (TasK: 2) станут ошибками после обновления.
- Сниффер проверяет только типы **свойств** VO; утечка Embeddable в параметры/возвраты методов и в контракты Application — вне этой задачи.
- Связанные: [TASK-feat-vo-sniff-property-type-namespace](TASK-feat-vo-sniff-property-type-namespace.todo.md) (внедрение проверки), [TASK-feat-vo-sniff-property-visibility](TASK-feat-vo-sniff-property-visibility.todo.md).

## 8. Источники (Sources)
- Конвенции: `docs/conventions/core-patterns/value-object.md` («Зависимости», «Расположение»), `docs/conventions/core-patterns/enum.md` (расположение общих Enum), `docs/conventions/layers/infrastructure/repository.md` (пример с `Symfony\Component\Uid\Uuid`).
- Аудит на TasK: `~/MyProjects/TasK/Development`, прогон `ValueObjectStructureSniff` на `src` и `apps` (2026-11-25); Embeddable: `Attribution/Domain/Entity/ValueObject/{DeviceVo,UtmVo}`, `Billing/Domain/Entity/ValueObject/{ClientCostVo,SelfCostVo}`.

## 9. Комментарии (Comments)
- Задача создана по результатам аудита сниффера на проекте-потребителе после мержа #136, сразу взята в работу.
- Решение по Embeddable принято владельцем после обсуждения: Embeddable — persistence-часть сущности, зависеть от них другим VO нельзя.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-25 10:50:00 (1795578600) | Лид Арагорн (pi) | Создание задачи, взятие в работу (pi) |
| 2026-11-25 10:50:00 (1795578600) | Лид Арагорн (pi) | Реализация: Symfony Uid в allowlist, общие типы `Common\…`; конвенция дополнена; фикстуры |
| 2026-11-25 11:51:40 (1795582300) | Лид Арагорн (pi) | Пересмотр кейса Embeddable (владелец): разрешение заменено запретом — сниффер (`entity` в пути), конвенция (обоснование), фикстуры; `Uuid` в конвенции без привязки к реализации; TasK 41 → 16 |
