---
type: feat
created: 2026-07-26
value: V3
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс) [task-orchestrator, потребитель пакета]
assignee: Dev (Pi)
branch: task/feat-validate-language-dictionary
pr: "#78"
status: review
---

# TASK-feat-validate-language-dictionary: Словарь стандартных переводов (dictionary) для validate-language

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `validate-language` (с v0.22) считает англицизмы — латинские слова вне `allowlist` — в русскоязычной документации и задачах. Но валидатор **только сообщает** о превышении `ratio` и_samples_ слов, **не подсказывая перевод**.
- Из-за этого перевод англицизмов **нестандартизирован**: одно и то же английское слово разные проекты, AI-агенты и ревьюеры переводят по-разному. Реальные кейсы из ревью `task-orchestrator` (потребителя пакета):
  - `hook` → «перехватчик» / «хук»;
  - `God object` → «объект-бог» (нестандарт, статьи в Википедии нет) / «божественный объект» (устоявшийся термин);
  - `resume` (концепт, не метод) → «резюме» / «возобновление»;
  - `execution` → оставляют / «выполнение»;
  - `path` → оставляют / «путь».
- Сейчас в конфиге есть только `allowlist` («что НЕ трогать» — термины/жаргон/имена). **Нет** списка «переводить ТАК» → нет стандарта перевода → возникают дискуссии на ревью и случайные переводы от AI-агентов.
- Следствие: ради снижения `ratio` потребители порой **раздувают `allowlist` общими словами** (`execution`, `path`, `design`, …), делая «0 over» формальным, а не качественным (кейс `task-orchestrator`: 373 → 18 за счёт широкого `allowlist`).

### Варианты или путь решения (Solution Sketch)
- Новый ключ конфигурации `language.dictionary` в `.coding-standard.php` — map «английское слово/фраза → стандартный русский перевод».
- `validate-language` **читает** `dictionary`: если англицизм (латинское слово вне `allowlist`) найден в `dictionary` — в выводе рядом с ним подсказывается перевод, например `⚠ hook → «хук»`. В `--json` — поле `suggestion`/`translation` в записи слова.
- `bin/coding-standard-init` пишет **стартовый словарь** (базовый набор устоявшихся переводов) — потребитель дописывает под проект.
- Дополняет `allowlist`: `allowlist` = «не трогать» (термины), `dictionary` = «переводить так» (общие слова со стандартом).

### Ожидаемый результат (Expected Result)
- `validate-language` рядом с англицизмом из `dictionary` подсказывает стандартный перевод → AI-агенты и ревьюеры используют единый перевод, меньше субъективщины и дискуссий.
- Стартовый `dictionary` покрывает типовые случаи (hook, execution, path, resume, God object, parallel, design, action, …).
- Документация: новый раздел в `docs/conventions/ops/validate-language.ru.md`.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
> Как автор/ревьюер русскоязычной документации, я хочу, чтобы `validate-language` рядом с найденным англицизмом подсказывал стандартный перевод из проекта, чтобы переводы были согласованы и не зависели от того, кто (человек или AI-агент) чистит текст.

### Goal (Цель по SMART)
- **S:** новый ключ `language.dictionary` (map англ → рус) + чтение его в `AnglicismAnalyzer`/`AnalysisResult` + подсказка перевода в выводе (text и `--json`).
- **M:** стартовый словарь через `coding-standard-init`; тесты покрывают «слово в dictionary → подсказка», «слово вне dictionary → без подсказки», «слово в allowlist → игнор».
- **A:** обратно совместимо — отсутствие `dictionary` = текущее поведение (без подсказок).
- **R:** стандартизация переводов у потребителей; снижение раздувания `allowlist` общими словами.
- **T:** одна задача, C2.

## 2. Context and Scope (Контекст и Границы)
- **Где делаем:** `src/Language/` (`AnglicismAnalyzer.php`, `AnalysisResult.php`) + `bin/validate-language` (вывод подсказки) + чтение конфига (`MarkdownTextExtractor`/конфиг-лоадер) + `bin/coding-standard-init` (стартовый словарь) + `tests/Language/` + `docs/conventions/ops/validate-language.ru.md`.
- **Текущее поведение:** `validate-language` считает `ratio` = (латинские слова вне `allowlist`) / (все слова); выводит файлы с превышением и sample слов. Переводов не подсказывает. Конфиг: `paths`, `exclude`, `max_ratio`, `allowlist`.
- **Границы (Out of Scope):**
  - Авто-перевод (LLM/словари) — вне задачи; только статический `dictionary` из конфига.
  - Принудительная замена слов в файлах — только диагностика/подсказка, не авто-fix.
  - Изменение `max_ratio` или алгоритма `ratio` — не трогаем.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Ключ `language.dictionary` в конфиге `.coding-standard.php`: map «английское слово/фраза → русский перевод» (case-insensitive совпадение ключа, как `allowlist`).
- [ ] `validate-language` читает `dictionary`; для англицизма, найденного в `dictionary`, добавляет подсказку перевода в вывод.
- [ ] Текстовый вывод: рядом со словом в sample (или в отдельной строке-подсказке) — `→ «перевод»`. Пример: `Words: ..., hook → «хук», ...`.
- [ ] `--json`: поле перевода в записи слова (например `suggestion` или `translation`).
- [ ] Слово в `allowlist` игнорируется как раньше (без подсказки — оно не англицизм).
- [ ] `bin/coding-standard-init` пишет стартовый `dictionary` в генерируемый `.coding-standard.php` (базовый набор устоявшихся переводов).
- [ ] Тесты (`tests/Language/`): слово в dictionary → подсказка; слово вне dictionary → без подсказки; слово в allowlist → игнор; `--json` содержит перевод; отсутствие dictionary = текущее поведение.

### 🟡 Should Have (Желательно)
- [ ] Подсказка только для слов, реально встретившихся как англицизмы (не для всех ключей dictionary) — не шуметь.
- [ ] Многословные ключи (`God object`) — совпадение по фразе, не только одно слово.
- [ ] В `--json` — агрегированный список «англицизм → перевод» по файлу/корпусу (удобно для工具).

### ⚫ Won't Have (Не будем делать)
- [ ] Авто-перевод через LLM/внешние словари.
- [ ] Авто-fix (перезапись файлов с заменой).
- [ ] Изменение `max_ratio` или алгоритма `ratio`.

## 4. Implementation Plan (План реализации)
1. [x] Конфиг-схема: чтение `language.dictionary` (map) рядом с `allowlist`; defaults — пустой map (обратная совместимость).
2. [x] `AnglicismAnalyzer`/`AnalysisResult`: для каждого найденного англицизма проверять вхождение в `dictionary` (case-insensitive; для фраз — по подстроке/токенам); добавлять перевод в результат.
3. [x] `bin/validate-language`: текстовый вывод — подсказка `→ «перевод»`; `--json` — поле перевода.
4. [x] `bin/coding-standard-init`: стартовый `dictionary` (hook→хук, execution→выполнение, path→путь, resume→возобновление, God object→божественный объект, parallel→параллельный, design→проектирование, action→действие, …).
5. [x] Тесты в `tests/Language/` (включая `--json`).
6. [x] Док: раздел в `docs/conventions/ops/validate-language.ru.md` + пример конфига с `dictionary`.

## 5. Definition of Done (Критерии приёмки)
- [x] Ключ `language.dictionary` поддержан; отсутствие = текущее поведение.
- [x] `validate-language` подсказывает перевод для англицизмов из `dictionary` (text + `--json`).
- [x] `allowlist` по-прежнему = «не трогать» (без подсказок).
- [x] `coding-standard-init` пишет стартовый `dictionary`.
- [x] Тесты покрывают ключевые сценарии; код-уровневые проверки `composer check` зелёные (phpunit 42, phpstan, phpcs, validate-docs, validate-md-links, validate-language). `@validate-todo` падает только на соседнем untracked-файле `TASK-docs-phpdoc-override-methods` — не относится к задаче.
- [x] Документация обновлена.

## 6. Verification (Самопроверка)
```bash
composer check
# вручную: .coding-standard.php с language.dictionary → vendor/bin/validate-language показывает подсказки
vendor/bin/validate-language --json | jq '.files[].sample' # содержит переводы
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Boundary «термин vs общее слово»** — на стороне потребителя: что в `allowlist`, что в `dictionary`. Пакет даёт оба механизма; потребитель (или стартовый набор init) решает.
- **Многословные фразы** (`God object`, `parallel execution`) — совпадение сложнее, чем одно слово; учитывать в экстракторе/анализаторе (возможно, по токенам).
- **Case-insensitive** ключа — как у `allowlist`; не должно ломать существующие конфиги.
- **Шум**: подсказки только для реально встретившихся англицизмов, не для всего `dictionary`.

## 8. Sources (Источники)
- Реальные кейсы из ревью `prikotov/task-orchestrator` PR #328 (чистка англицизмов): hook→хук, God object→божественный объект, resume→возобновление, execution→выполнение, path→путь; плюс раздувание `allowlist` общими словами ради `ratio` (задача `TASK-chore-validate-language-true-zero` в task-orchestrator).
- [Википедия: Божественный объект](https://ru.wikipedia.org/wiki/Божественный_объект) — пример устоявшегося рус. термина (не «объект-бог»).
- Текущая реализация: `src/Language/AnglicismAnalyzer.php`, `AnalysisResult.php`, `MarkdownTextExtractor.php`, `bin/validate-language`.
- Док: `docs/conventions/ops/validate-language.ru.md`.

## 9. Comments (Комментарии)
- **Почему это важно для потребителей:** разработка документации ведётся AI-агентами; без стандарта перевода агент переводит случайно. `dictionary` делает стандарт явным и машинно-читаемым — агент может читать подсказки и переводить единообразно.
- **Связка с `allowlist`:** `allowlist` = «оставить как есть» (термины/жаргон/имена), `dictionary` = «переводить так» (общие слова). Вместе они задают полную политику отношения к английскому в русскоязычной документации.
- **Не авто-fix:** намеренно только диагностика/подсказка — авто-замена опасна (контекст, многозначность); перевод принимает человек/агент по подсказке.
- **Стартовый `dictionary` от `coding-standard-init`** — должен быть консервативным (только устоявшиеся, бесспорные переводы), чтобы не навязывать спорные варианты; потребитель расширяет под проект.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-27 | Dev (Pi) | Реализация: `AnalysisResult.suggestions` + `AnglicismAnalyzer` (однословные ключи и многословные фразы, case-insensitive, шум-фильтр по allowlist) + `bin/validate-language` (`Hints:` в text, `suggestions` в `--json`) + starter `dictionary` в `coding-standard-init` + 8 тестов + раздел в доке. |
| 2026-07-26 | Тимлид (Алекс) [task-orchestrator] | Создание задачи. Контекст: validate-language не подсказывает переводы → несогласованность переводов у потребителя; нужен dictionary в конфиге + подсказки в валидаторе. |
