---
type: feat
created: 2026-07-21
value: V3
complexity: C1
priority: P2
depends_on:
epic:
author: Dev (Pi)
assignee: Dev (Pi)
branch: task/dto-shared-pool-suffix
pr:
status: in_progress
---

# TASK-sniff-dto-shared-pool-suffix: Запрет use-case-специфичных DTO (Request/Result/Response) в общем пуле модуля

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Общий пул DTO модуля `Module\{M}\Application\Dto\` предназначен для **переиспользуемых** проекций доменных сущностей (`Model → DTO`), используемых несколькими use case'ами.
- Конвенция `dto.md` уже заявляет: «Use-case-специфичные DTO (`*RequestDto`/`*ResultDto`/`*ResponseDto`) сюда не кладём — их место рядом с use case'ом». Но правило существует только в тексте, без автоматической проверки.
- Разработка ведётся AI-агентами; агент **может не прочитать** конвенцию перед тем, как положить DTO в общий пул. Текст-док как барьер ненадёжен — нужна валидация.
- На практике (`prikotov/TasK`): `InitializeRegistrationResultDto`, `SessionLifecycleResultDto` лежат в общем пуле, хотя по суффиксу `ResultDto` — use-case-специфичные.

### Варианты или путь решения (Solution Sketch)
- Per-file sniff: класс в namespace `...\Module\{M}\Application\Dto\` с именем `*RequestDto` / `*ResultDto` / `*ResponseDto` → error «use-case-специфичный, перенеси в `UseCase\{Case}\`» со ссылкой на `dto.md`.
- Это **автоматическая валидация существующей конвенции** (enforcement `dto.md`, раздел «Расположение»), а не новая метрика. Срабатывает всегда, независимо от того, прочитал агент doc или нет.
- Дёшево: per-file (без cross-file инфры), сложность C1.

### Точный cross-file анализ (сколько use case'ов используют DTO) — сознательно отложен
- Имя-снифф ловит **основную зафиксированную боль** (DTO с use-case-суффиксом в общем пуле).
- Cross-file анализатор (подсчёт использований, C3) добавляется отдельной задачей, **только если** имя-снифф пропустит много реальных случаев (DTO без характерного суффикса, используемые одним use case'ом). Загадывать заранее — YAGNI.

### Ожидаемый результат (Expected Result)
- Sniff в `ruleset.xml` ловит use-case-специфичные DTO в общем пуле модуля и предлагает перенос рядом с владельцем.
- Ручной разбор «общих» DTO на ревью перестаёт быть основным барьером.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
> Как сопровождающий модуля, я хочу, чтобы автоматика запрещала класть use-case-специфичные DTO (`*ResultDto`/`*RequestDto`/`*ResponseDto`) в общий пул `Application\Dto\`, чтобы они лежали рядом с use case'ом — независимо от того, прочитал автор конвенцию или нет.

### Goal (Цель по SMART)
- **S:** Per-file sniff, помечающий классы в `...\Module\{M}\Application\Dto\` с именем на `(Request|Result|Response)Dto$`.
- **M:** Fixture-тесты покрывают violation (суффикс в Module pool), valid (суффикс в `UseCase\{Case}\`), valid (проекция в Module pool), valid (корневой `Common\Application\Dto\`).
- **A:** Per-file, без cross-file, без новой инфры; входит в базовый `ruleset.xml`.
- **R:** Enforcement существующей конвенции `dto.md`; основная боль ловится автоматикой.
- **T:** Одна задача, C1.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** новый sniff в `src/Sniffs/DTO/` + `ruleset.xml` + fixture-тесты (`tests/.../*.inc`).
- **Текущее поведение:** `DtoStructureSniff` проверяет структуру DTO и запрещает `Domain\Dto\`. Use-case-суффикс в общем пуле не проверяется.
- **Границы (Out of Scope):**
  - Корневой `Common\Application\Dto\` (общие DTO приложения — `PaginationDto`, `IdDto`, `SortDto`) **не проверяем** — он заведомо общий. Проверяем только `Module\{M}\Application\Dto\`.
  - Cross-file подсчёт использований — отдельная будущая задача (см. раздел 0).
  - Автоматическое перемещение DTO не делаем — только диагностика.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Per-file sniff: класс в namespace, оканчивающемся на `\Module\{M}\Application\Dto\`, с именем на `(Request|Result|Response)Dto$` → error со ссылкой на `dto.md` и предложением перенести в `UseCase\{Case}\`.
- [ ] Корневой `Common\Application\Dto\` не проверяется.
- [ ] Fixture-тесты: violation (ResultDto в Module pool); valid (ResultDto в `UseCase\Query\{Case}\`); valid (проекция `PaymentSummaryDto` в Module pool); valid (DTO в `Common\Application\Dto\`).

### 🟡 Should Have (Желательно)
- [ ] Усилить `dto.md` чек-листом для ревью/агента (best-effort: «кладёшь DTO в `Application\Dto\` — это должна быть переиспользуемая проекция, не use-case-специфичный Result/Request/Response»). Помечено как дополнение к автоматике, не замена.

### ⚫ Won't Have (Не будем делать)
- [ ] Cross-file подсчёт использований (отложено, см. раздел 0).
- [ ] Автоматический рефакторинг (перемещение файлов).
- [ ] Проверка DTO вне `Module\{M}\Application\Dto\`.

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
- Новый sniff (напр. `DtoSharedPoolUseCaseSuffixSniff`) в `src/Sniffs/DTO/`.
- Триггер: `T_CLASS`; проверка FQBN/namespace оканчивается на `\Application\Dto\` и при этом содержит `\Module\`; имя класса на `(Request|Result|Response)Dto$`.
- Регистрация в `ruleset.xml`.
- Fixture-тесты по Must Have.

## 5. Definition of Done (Критерии приёмки)
- [x] Sniff ловит use-case-суффикс в `Module\{M}\Application\Dto\`.
- [x] Корневой `Common\Application\Dto\` не проверяется.
- [x] `composer check` пройден, fixture-тесты покрывают ключевые сценарии.

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Эвристика по имени хрупка: DTO без характерного суффикса, используемый одним use case'ом, не ловится. Сознательно: основная боль (явный суффикс) ловится; тонкие случаи — будущий cross-file анализатор, если проявятся.
- Ложные срабатывания на DTO с суффиксом, который реально общий (редко) — allowlist исключений в конфиге, если проявится.
- Полагаться на doc-инструкцию для AI-агента как на барьер нельзя (агент может не прочитать) — поэтому sniff = основной барьер.

## 8. Sources (Источники)
- [dto.md](../docs/conventions/core-patterns/dto.md) — раздел «Расположение»: «Use-case-специфичные DTO (`*RequestDto`/`*ResultDto`/`*ResponseDto`) [в общий пул] не кладём».
- `prikotov/TasK`: `InitializeRegistrationResultDto`, `SessionLifecycleResultDto` в `Module\...\Application\Dto\` — оба со суффиксом `ResultDto`.

## 9. Comments (Комментарии)
- Задача переформулирована из первоначальной `TASK-feat-shared-dto-reuse-validator` (cross-file C3) после анализа альтернатив: имя-эвристика (выбрана), конвенция-маркер `@shared`, PHPStan-collector, LSP-сервер. Имя-снифф решает зафиксированную боль за C1.
- LSP/agent-контекст учтён, но не как замена валидации: агент может не прочитать конвенцию, поэтому автоматический sniff — основной барьер. LSP-инфру в пакет не тащим.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-21 | Dev (Pi) | Создание как `TASK-feat-shared-dto-reuse-validator` (cross-file анализатор reused DTO, C3). |
| 2026-07-22 | Dev (Pi) | Анализ альтернатив (имя-эвристика, `@shared`-маркер, PHPStan-collector, LSP). Переформулирование в per-file имя-снифф C1 — enforcement существующей конвенции `dto.md`; cross-file отложен. Rename в `TASK-sniff-dto-shared-pool-suffix`. Обоснование: автоматика — основной барьер (агент может не прочитать doc), LSP-контекст учтён, но не как замена валидации. |
