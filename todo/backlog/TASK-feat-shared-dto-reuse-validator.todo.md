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

# TASK-feat-shared-dto-reuse-validator: Проверка общих DTO модуля на переиспользование (Psalm plugin)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Общий пул DTO модуля `Module\{ModuleName}\Application\Dto\` предназначен для **переиспользуемых** DTO — проекций доменных сущностей (`Model → DTO`), которые возвращаются из нескольких use case'ов.
- На практике злоупотребляют исключением: кладут туда DTO, который по сути use-case-специфичный (используется одним query/command), хотя должен лежать рядом с владельцем в `UseCase\{Case}\`.
- Вручную отличить «реально общий» DTO от «случайно в общем пуле» сложно — нужен анализ фактического использования по коду.
- Разработка ведётся AI-агентами; агент может не прочитать конвенцию → текст-doc не барьер. Нужна **автоматическая валидация**.

### Варианты или путь решения (Solution Sketch)
- **Psalm plugin**: для каждого DTO из `Module\{M}\Application\Dto\` через `$codebase->findReferencesToClass()` находим все ссылки, фильтруем до query/command-handler'ов, считаем уникальные use case'ы.
- Если DTO используется ≤ порога (по умолчанию 1) use case'ами → issue: «перенеси рядом с владельцем».
- Проверка **не привязана к имени DTO** (суффиксу/префиксу) — только к фактическому переиспользованию. Не обходится переименованием.
- Семантически точна: Psalm различает return-тип / параметр / комментарий / импорт (в отличие от grep/regex).
- Потребители уже на Psalm (TasK: `vimeo/psalm ^6`), плагин подключается стандартно через `psalm.xml`.

### Ожидаемый результат (Expected Result)
- Psalm plugin подсвечивает DTO в `Module\{M}\Application\Dto\`, используемые ≤ порога use case'ами, и предлагает перенести рядом с владельцем.
- Ручной разбор «общих» DTO на ревью перестаёт быть основным барьером.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
> Как сопровождающий модуля, я хочу, чтобы Psalm при анализе подсвечивал общие DTO, которые по факту используются одним use case'ом, чтобы не плодить use-case-специфичные DTO в общем пуле.

### Goal (Цель по SMART)
- **S:** Psalm plugin, который для DTO из `Module\{M}\Application\Dto\` через `findReferencesToClass` находит query/command-handler'ы (return-тип/параметр) и считает уникальные use case'ы.
- **M:** DTO с числом использований ≤ порога (по умолчанию 1) → issue; тесты покрывают 0/1/N использований и игнор корневого пула.
- **A:** Использует существующий Psalm `Codebase` (без своей FS-инфры и grep); конфиг порога через `psalm.xml`/атрибуты.
- **R:** Предотвращает злоупотребление общим пулом; валидация не зависит от имени (не обходится переименованием).
- **T:** Одна задача, C3.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** новый Psalm plugin (`src/Psalm/`): класс, реализующий `PluginEntryPointInterface` / `AfterClassLikeVisitInterface`; регистрация через `psalm.xml` потребителя.
- **Текущее поведение:** `DtoStructureSniff` (PHPCS) проверяет структуру DTO и запрещает `Domain\Dto\`. Переиспользование общих DTO не анализируется.
- **Границы (Out of Scope):**
  - Корневой `Common\Application\Dto\` (общие DTO приложения — `PaginationDto`, `IdDto`, `SortDto`) не проверяем — заведомо общий. Только `Module\{M}\Application\Dto\`.
  - Глобальный граф зависимостей не строим — reverse-lookup одного класса.
  - Автоматическое перемещение DTO не делаем — только диагностика.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Psalm plugin: для DTO в namespace `...\Module\{M}\Application\Dto` вызывает `$codebase->findReferencesToClass()`, фильтрует до query/command-handler'ов (`...\UseCase\{Query|Command}\...`), считает уникальные use case'ы.
- [ ] DTO с числом использований ≤ порога → issue со ссылкой на `dto.md` и предложением перенести рядом с владельцем.
- [ ] Порог настраивается (по умолчанию 1).
- [ ] Корневой `Common\Application\Dto\` не проверяется.
- [ ] Тесты плагина (Psalm test fixtures `tests/Psalm/`): 0, 1, 2+ использований DTO; игнор корневого пула; DTO как return-тип и как параметр.

### 🟡 Should Have (Желательно)
- [ ] Учёт DTO как поля другого DTO (не только handler return/param).
- [ ] Конфиг: исключения (allowlist общих DTO), свои пороги.

### ⚫ Won't Have (Не будем делать)
- [ ] Глобальный граф зависимостей DTO.
- [ ] Автоматический рефакторинг (перемещение файлов).
- [ ] Проверка по имени DTO (суффиксу) — намеренно не зависит от названия.
- [ ] Свой bash/grep CLI-анализатор — используем инфру Psalm.
- [ ] PHPStan-extension — потребители на Psalm, PHPStan не запускают.

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
- `src/Psalm/DtoReusePlugin.php` — реализует `PluginEntryPointInterface` (современный Psalm plugin API); регистрирует `AfterClassLikeVisitInterface` handler.
- Handler: на DTO-класс в `Module\{M}\Application\Dto` → `$codebase->findReferencesToClass($fqcn)` → locations → group по содержащему use-case → count. ≤ порога → issue.
- Распространение: потребителю добавить `<pluginClass class="PrikotovCodingStandard\Psalm\DtoReusePlugin"/>` в `psalm.xml` (или через bundled plugin descriptor).
- Psalm в `require-dev` coding-standard для тестов (`tests/Psalm/*` fixtures через `Psalm\Test\TestCase...` или `vimeo/psalm` internal test runner).
- Документация: `docs/conventions/ops/` — как подключить Psalm plugin.

## 5. Definition of Done (Критерии приёмки)
- [ ] Psalm plugin подсвечивает DTO из `Module\{M}\Application\Dto\`, используемые ≤ порога use case'ами.
- [ ] Корневой `Common\Application\Dto\` не проверяется.
- [ ] Не зависит от имени DTO (только переиспользование).
- [ ] Потребитель (TasK) подключает плагин одной строкой в `psalm.xml`.
- [ ] `composer check` пройден, тесты плагина покрывают ключевые сценарии.

## 6. Verification (Самопроверка)
```bash
composer check
# + запуск Psalm с плагином на fixture-проекте
```

## 7. Risks and Dependencies (Риски и зависимости)
- Psalm plugin API (events, Codebase methods) — требует изучения; API может различаться между версиями Psalm (TasK на `^6`).
- `findReferencesToClass` — потенциально тяжёлый (full scan), но Psalm кеширует; производительность на крупных проектах.
- Фильтр «use-case-handler» по namespace/структуре — эвристика (`...\UseCase\{Query|Command}\...`); неточные cases — настраиваемый allowlist.
- coding-standard проверяет свой код PHPStan, а распространяет Psalm-plugin — для тестов плагина добавить Psalm в `require-dev` (см. Comments).

## 8. Sources (Источники)
- [dto.md](../../docs/conventions/core-patterns/dto.md) — раздел «Расположение» (принцип «рядом с владельцем», общие DTO).
- Замечено в `prikotov/TasK`: 2 DTO в `Module\...\Application\Dto\` (`InitializeRegistrationResultDto`, `SessionLifecycleResultDto`), используемые одним query.
- Psalm plugin docs: `Psalm\Plugin\PluginEntryPointInterface`, `Codebase::findReferencesToClass()`.
- Примеры плагинов: `psalm/plugin-symfony` (есть в TasK), `vimeo/psalm-phpunit`.

## 9. Comments (Комментарии)
- **Эволюция подхода.** Задача прошла несколько итераций проектирования:
  1. Исходно — свой cross-file CLI-анализатор reused DTO (C3, bash/grep).
  2. Имя-эвристика (per-file sniff на суффикс `Request/Result/Response`, C1) — **отвергнута**: суффикс это симптом, обходится переименованием.
  3. `@shared`-маркер, PHPStan-extension — отвергнуты: меняют конвенцию / потребители на Psalm (PHPStan не запускают).
  4. **Psalm plugin** — потребители уже на Psalm; `findReferencesToClass` даёт семантически точный cross-file reverse-lookup без своей инфры. Выбрано.
- **Контекст AI-агентов:** разработка ведётся агентами; агент может не прочитать doc → текст-инструкция не барьер, автоматика = основной. Psalm plugin — часть CI потребителя, срабатывает всегда.
- **Два стат-анализатора в coding-standard:** пакет сам проверяется PHPStan (`@check`), но распространяет Psalm-plugin. Для тестов плагина — Psalm в `require-dev`. Вопрос унификации self-check на Psalm (вариант B) — отдельное решение, не в scope этой задачи.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-21 | Dev (Pi) | Создание задачи: свой cross-file CLI-анализатор reused DTO (C3). |
| 2026-07-22 | Dev (Pi) | Анализ альтернатив (имя-эвристика, `@shared`-маркер, PHPStan-collector, LSP). Переформулирование в per-file имя-снифф C1; rename в `TASK-sniff-dto-shared-pool-suffix`; PR #70. |
| 2026-07-22 | Dev (Pi) | Откат имя-сниффа (суффикс — симптом). Возврат к cross-file CLI-анализатору; rename обратно. |
| 2026-07-22 | Dev (Pi) | Архитектурный поворот: потребители на Psalm (TasK: `vimeo/psalm ^6`), PHPStan-extension бесполезен. Переформулирование в **Psalm plugin** (`findReferencesToClass`), форма не CLI, а plugin Psalm. Унификация: markdown-CLI + PHPCS-style + Psalm-plugin — по природе сущностей. |
