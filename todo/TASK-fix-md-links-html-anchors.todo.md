---
type: fix
created: 2026-09-21 23:25:00 (1790007900)
due: 
started: 2026-09-21 23:25:30 (1790007930)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/md-links-html-anchors
pr: 
status: in_progress
---

# TASK-fix-md-links-html-anchors: validate-md-links — распознавать явные HTML-якоря

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `validate-md-links` строит индекс якорей только из заголовков ATX (`#`…`######`).
- Пакет `prikotov/git-workflow` использует в глоссарии явные HTML-якоря `<a id="...">` (валидные на GitHub): валидатор ложно помечает ссылки `#deployment`, `glossary.md#release-publishing` как битые.

### Варианты или путь решения (Solution Sketch)
- Дополнить `buildAnchorIndex()` сбором явных HTML-якорей `<a id="...">` / `<a name="...">` (принимаются как есть, без slug-преобразования — так же, как их разрешает GitHub).
- Покрыть фикстурами и тестами, обновить документацию инструмента.

### Ожидаемый результат (Expected Result)
- Ссылки на явные HTML-якоря валидируются корректно; документация `prikotov/git-workflow` проходит проверку без ложных срабатываний.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как потребитель пакета, я хочу, чтобы `validate-md-links` признавал явные HTML-якоря `<a id>`/`<a name>` целями ссылок — как это делает GitHub, — чтобы документация с глоссариями не падала в проверках из-за ложных `broken-anchor`.

### Цель по SMART (Goal)
- В `bin/validate-md-links` индекс якорей собирает и заголовки, и явные HTML-якоря; добавлены фикстуры/тесты (валидные и битые явные якоря); обновлена дока `docs/conventions/ops/validate-md-links.md`; `composer check` зелёный.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `bin/validate-md-links`, `tests/fixtures/md-links/`, `tests/MdLinksValidator/MdLinksValidatorTest.php`, `docs/conventions/ops/validate-md-links.md`.
* **Границы (Out of Scope):** не менять slug-генерацию заголовков; не поддерживать прочие HTML-конструкции (кроме `<a id>`/`<a name>`); не трогать остальные инструменты пакета.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `buildAnchorIndex()` собирает явные HTML-якоря `<a id="...">` / `<a name="...">` (регистронезависимо, кавычки обоих видов, якорь принимается как есть).
- [x] Фикстуры: валидные локальный/межфайловый/legacy `name`-якорь и битый явный якорь.
- [x] Тесты: распознавание валидных, детект битого, счётчик ошибок обновлён.
- [x] Документация инструмента описывает поддержку HTML-якорей.
### ⚫ Won't Have (Не будем делать)
- Поддержку многострочных HTML-якорей и якорей внутри `inline code` (те же ограничения, что и у заголовков).

## 4. План реализации (Implementation Plan)
1. [x] Добавить `extractHtmlAnchors()` и включить его в `buildAnchorIndex()`.
2. [x] Расширить фикстуры и тесты.
3. [x] Обновить документацию.
4. [x] Прогнать `composer check` и PHPUnit; сверить на документации `prikotov/git-workflow`.

## 5. Критерии приёмки (Definition of Done)
- [x] `vendor/bin/phpunit tests/MdLinksValidator/` — зелёный (21 тест).
- [x] `composer check` — зелёный (289 тестов, PHPStan, phpcs, validate-docs, language).
- [x] `php bin/validate-md-links /path/to/git-workflow/docs/git-workflow/` — все ссылки валидны (исходный кейс закрыт).

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpunit
composer check
php bin/validate-md-links <git-workflow>/docs/git-workflow/
```

## 7. Риски и зависимости (Risks и Dependencies)
- Поведение совместимо: индекс только расширяется (новые цели), ложных «битых» меньше, новые ошибки возможны только там, где ссылки вели на несуществующие явные якоря — это корректное срабатывание.

## 8. Источники (Sources)
- `bin/validate-md-links` — `buildAnchorIndex()`.
- Документация `prikotov/git-workflow` (`docs/git-workflow/glossary.md`) — исходный кейс с `<a id>`.

## 9. Комментарии (Comments)
- Обнаружено при обновлении `prikotov/git-workflow` до v0.4.0 в проекте `prikotov/task-orchestrator` (PR #404).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-21 23:25:00 (1790007900) | Бэкендер Тони (pi) | Создание задачи |
| 2026-09-21 23:26:00 (1790007960) | Бэкендер Тони (pi) | Старт задачи, заполнение постановки |
| 2026-09-21 23:30:00 (1790008200) | Бэкендер Тони (pi) | Реализация, фикстуры, тесты, документация; проверки зелёные |
