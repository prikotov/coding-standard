---
type: feat
created: 2026-07-21
value: V3
complexity: C3
priority: P2
depends_on:
epic:
author: Dev (Pi)
assignee:
branch:
pr:
status: backlog
---

# TASK-feat-shared-dto-reuse-validator: Проверка общих DTO модуля на переиспользование (PHPStan extension)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Общий пул DTO модуля `Module\{ModuleName}\Application\Dto\` предназначен для **переиспользуемых** DTO — проекций доменных сущностей (`Model → DTO`), которые возвращаются из нескольких use case'ов.
- На практике злоупотребляют исключением: кладут туда DTO, который по сути use-case-специфичный (используется одним query/command), хотя должен лежать рядом с владельцем в `UseCase\{Case}\`.
- Вручную отличить «реально общий» DTO от «случайно в общем пуле» сложно — нужен анализ фактического использования по коду.
- Разработка ведётся AI-агентами; агент может не прочитать конвенцию → текст-doc не барьер. Нужна **автоматическая валидация**.

### Варианты или путь решения (Solution Sketch)
- **PHPStan extension**: `Collector` собирает return-типы всех query/command-handler'ов (handler → FQCN возвращаемых DTO), затем `Rule` на DTO из `Module\{M}\Application\Dto` считает уникальные handler'ы из агрегированных данных.
- Если DTO используется < порога (по умолчанию 2 — т.е. нужен ≥2 use case'ов) → error: «перенеси рядом с владельцем».
- Проверка **не привязана к имени DTO** (суффиксу/префиксу) — только к фактическому переиспользованию. Не обходится переименованием.
- Семантически точна: PHPStan различает return-тип / комментарий / импорт (AST + Reflection через `Scope`); cross-file aggregation делает сам PHPStan (collectors → rules).
- Потребитель добавляет `phpstan/phpstan` в `require-dev` (сосуществует с Psalm, не конфликтует) и подключает extension.

### Ожидаемый результат (Expected Result)
- PHPStan extension подсвечивает DTO в `Module\{M}\Application\Dto\`, используемые < порога use case'ами, и предлагает перенести рядом с владельцем.
- Ручной разбор «общих» DTO на ревью перестаёт быть основным барьером.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
> Как сопровождающий модуля, я хочу, чтобы PHPStan при анализе подсвечивал общие DTO, которые по факту используются одним use case'ом, чтобы не плодить use-case-специфичные DTO в общем пуле.

### Goal (Цель по SMART)
- **S:** PHPStan `Collector` (return-типы handler'ов) + `Rule` (DTO в `Application\Dto` → count handlers из collected data).
- **M:** DTO с числом использований < порога (по умолчанию 2) → error; тесты покрывают 0/1/2+ использований и игнор корневого пула.
- **A:** Cross-file aggregation — нативный PHPStan (collectors → rules); без своей FS-инфры.
- **R:** Предотвращает злоупотребление общим пулом; валидация не зависит от имени (не обходится переименованием).
- **T:** Одна задача, C3.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `src/PhpStan/` (`DtoReuseRule.php`, `DtoReturnCollector.php`) + `phpstan-rules.neon` (регистрация + параметры) + `tests/PhpStan/` (fixtures через `RuleTestCase`).
- **Текущее поведение:** `DtoStructureSniff` (PHPCS) проверяет структуру DTO и запрещает `Domain\Dto\`. Переиспользование общих DTO не анализируется.
- **Границы (Out of Scope):**
  - Корневой `Common\Application\Dto\` (общие DTO приложения — `PaginationDto`, `IdDto`, `SortDto`) не проверяем — заведомо общий. Только `Module\{M}\Application\Dto\`.
  - Глобальный граф зависимостей не строим — collected data по return-типам.
  - Автоматическое перемещение DTO не делаем — только диагностика.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] `Collector`: на `ClassMethod` query/command-handler'а (класс в namespace `...\UseCase\{Query|Command}\...`) собирает resolved FQCN return-типа метода (по умолчанию `__invoke`; учесть nullable `?Dto`).
- [ ] `Rule`: на `Class_` DTO в namespace `...\Module\{M}\Application\Dto` → из collected data считает уникальные handler'ы; если < порога → error со ссылкой на `dto.md`.
- [ ] Порог настраивается (по умолчанию 2) — PHPStan parameter в `phpstan-rules.neon`.
- [ ] Корневой `Common\Application\Dto\` не проверяется.
- [ ] Тесты через `PHPStan\Testing\RuleTestCase`: 0, 1, 2+ использований DTO; игнор корневого пула; прямой return-тип `: XxxDto` и `: ?XxxDto`.

### 🟡 Should Have (Желательно) — отложено отдельной задачей
- [ ] Collections в return-типе (`array<XxxDto>`, `list<XxxDto>`) — разбор generic-типа.
- [ ] DTO как поле другого DTO / параметр handler'а.

### ⚫ Won't Have (Не будем делать)
- [ ] Глобальный граф зависимостей DTO.
- [ ] Автоматический рефакторинг (перемещение файлов).
- [ ] Проверка по имени DTO (суффиксу) — намеренно не зависит от названия.
- [ ] Psalm plugin — Psalm не имеет нативного cross-file aggregation для правил; потребители добавляют PHPStan в `require-dev`.

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
- `src/PhpStan/DtoReturnCollector.php` — `@implements Collector<ClassMethod, array{class: string, returns: list<string>}>`; проверка что класс handler (namespace) → resolved return type FQCN(s).
- `src/PhpStan/DtoReuseRule.php` — `@implements Rule<Class_>`; из `CollectedDataNode` фильтрует data где `returns` содержит FQCN этого DTO → count уникальных handler-классов → < порога → error.
- `phpstan-rules.neon` — регистрация rule+collector + `parameters: dtoReuseMinUses: 2`.
- Распространение: `phpstan/extension-installer` (автоподхват) или ручной `includes:` в `phpstan.neon` потребителя.
- Тесты: `tests/PhpStan/DtoReuseRuleTest.php` extends `RuleTestCase`, fixture PHP-строки с handlers/DTOs.
- Документация: `docs/conventions/ops/phpstan-dto-reuse.ru.md` — как подключить extension.

## 5. Definition of Done (Критерии приёмки)
- [ ] Rule + Collector подсвечивают DTO из `Module\{M}\Application\Dto\`, используемые < порога use case'ами.
- [ ] Корневой `Common\Application\Dto\` не проверяется.
- [ ] Не зависит от имени DTO (только переиспользование).
- [ ] Потребитель подключает extension (extension-installer или `includes:`) одной настройкой.
- [ ] `composer check` пройден, `RuleTestCase`-тесты покрывают ключевые сценарии.

## 6. Verification (Самопроверка)
```bash
composer check
# + RuleTestCase тесты запускаются через phpunit
```

## 7. Risks and Dependencies (Риски и зависимости)
- PHPStan extension требует PHPStan у потребителя (`require-dev`); Psalm остаётся — инструменты не конфликтуют.
- Collector API (PHPStan 2.x) — стабильный, документированный; порядок collectors→rules гарантирует PHPStan.
- Resolved return type через `Scope::getType()` / `FunctionReturnTypeResolver` — учесть nullable и FQCN.
- Ложные срабатывания на DTO, возвращаемых одним query, но легитимно общих — сглаживается allowlist-параметром (Should Have) или `@phpstan-ignore`.

## 8. Sources (Источники)
- [dto.md](../../docs/conventions/core-patterns/dto.md) — раздел «Расположение» (принцип «рядом с владельцем», общие DTO).
- Замечено в `prikotov/TasK`: 2 DTO в `Module\...\Application\Dto\` (`InitializeRegistrationResultDto`, `SessionLifecycleResultDto`), используемые одним query.
- PHPStan docs: Collectors, Custom rules, `extension-installer`.

## 9. Comments (Комментарии)
- **Эволюция подхода.** Задача прошла несколько итераций:
  1. Свой cross-file CLI-анализатор (C3, bash/grep).
  2. Имя-эвристика (per-file sniff на суффикс, C1) — **отвергнута**: суффикс это симптом, обходится переименованием (PR #70 закрыт).
  3. Psalm plugin (`findReferencesToClass`) — **отвергнута после исследования**: `findReferencesToClassLike` не работает в обычном plugin-run (требует `--find-references`); рабочий путь через `AfterCodebasePopulated` + ручной обход + custom `CodeIssue` — высокий уровень Psalm-internals (C3+).
  4. **PHPStan Collector + Rule** — нативный cross-file aggregation (collectors → rules), без internals-копания; `Scope` сам resolve'ит return-тип. Выбрано как наиболее простой и корректный. Потребитель добавляет PHPStan в `require-dev` (сосуществует с Psalm).
- **Контекст AI-агентов:** разработка ведётся агентами; агент может не прочитать doc → текст-инструкция не барьер, автоматика = основной. PHPStan extension — часть CI потребителя, срабатывает всегда.
- **PHPStan рядом с Psalm:** обоснованно — PHPStan силён в rules/контрактах (расширяемая rule-система), Psalm — в типах/taints. Многие проекты держат оба; добавление PHPStan в `require-dev` потребителя — одна строка.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-21 | Dev (Pi) | Создание задачи: свой cross-file CLI-анализатор reused DTO (C3). |
| 2026-07-22 | Dev (Pi) | Анализ альтернатив (имя-эвристика, `@shared`-маркер, PHPStan-collector, LSP). Переформулирование в per-file имя-снифф C1; rename в `TASK-sniff-dto-shared-pool-suffix`; PR #70. |
| 2026-07-22 | Dev (Pi) | Откат имя-сниффа (суффикс — симптом). Возврат к cross-file CLI-анализатору. |
| 2026-07-22 | Dev (Pi) | Переформулирование в Psalm plugin (`findReferencesToClass`). |
| 2026-07-22 | Dev (Pi) | Откат Psalm: `findReferencesToClassLike` не работает в обычном plugin-run (нужен `--find-references`); рабочий путь через `AfterCodebasePopulated` + custom Issue — высокий уровень internals. Потребитель (TasK) на Psalm, но готов добавить PHPStan в `require-dev`. Переход на **PHPStan Collector + Rule** — нативный cross-file aggregation, проще и корректнее. Rename обратно в `TASK-feat-shared-dto-reuse-validator`. |
