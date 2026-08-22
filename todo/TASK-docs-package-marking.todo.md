---
type: docs
created: 2026-08-21 14:01:04 (1787320864)
due:
started: 2026-08-22 15:48:56 (1787413736)
completed:
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид (pi)
assignee: Разработчик (pi)
branch: task/docs-package-marking
pr: https://github.com/prikotov/coding-standard/pull/116
status: review
---

# TASK-docs-package-marking: Маркировка package распределяемых документов конвенций

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- `bin/coding-standard-init` копирует `docs/conventions/` (75 `.md`) в проекты-потребители — и там пакетные документы визуально неотличимы от написанных в самом проекте.
- Документы конвенций не помечены происхождением: ни AI-агент, ни человек в consumer-репо не может определить, что документ пришёл из `prikotov/coding-standard` и его нельзя править локально (правки затрутся при следующем init).
- Практика маркировки уже введена в sibling-пакетах: `prikotov/git-workflow` (PR #8) и `prikotov/todo-md` (PR #29) — ключ `package: <имя пакета>` в front matter как атрибуция источника.
- `prikotov/coding-standard` остаётся без маркировки — непоследовательно на фоне остальных распределяемых пакетов.

### Варианты или путь решения (Solution Sketch)

- Добавить ключ `package: prikotov/coding-standard` **первым ключом** в существующий front matter 74 файлов (у них уже есть блок с `name`/`type`/`description`).
- Для `docs/conventions/AGENTS.md` — единственного без front matter — создать новый блок front matter первым блоком файла.
- Итого размечаются все 75 `.md` из `docs/conventions/`.
- Штатные проверки пакета не затронуты: `validate-language` читает из front matter только ключ `lang` (`src/Language/DocumentLanguageDetector.php`), `validate-md-links` от front matter не зависит. Исполнителю — прогнать проверки по CI репозитория.

### Ожидаемый результат (Expected Result)

- Каждый из 75 `.md` в `docs/conventions/` начинается с front matter, где первым ключом стоит `package: prikotov/coding-standard`.
- После `coding-standard-init` в consumer-проекте любой документ конвенций опознаваем как пакетный.
- Проверки CI (`composer check` и пайплайн репо) — зелёные.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)

> Как AI-агент или разработчик проекта-потребителя, я хочу видеть в каждом скопированном документе конвенций маркер источника (`package: prikotov/coding-standard`), чтобы отличать пакетные документы от проектных и не править регенерируемый скаффолд локально.

### Цель по SMART (Goal)

- **S (конкретная):** добавить ключ `package: prikotov/coding-standard` первым ключом front matter во все 75 `.md` из `docs/conventions/` (74 — в существующий блок, `AGENTS.md` — в новый блок первым блоком файла).
- **M (измеримая):** 75 из 75 файлов содержат ключ; проверки CI зелёные.
- **A (достижимая):** механическая правка front matter без изменения содержимого документов и кода инструментов.
- **R (релевантная):** единообразие с `prikotov/git-workflow` (PR #8) и `prikotov/todo-md` (PR #29); атрибуция источника для потребителей.
- **T (ограниченная по времени):** одна задача C1 — одиночный PR.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `docs/conventions/**/*.md` — 75 файлов, включая `examples/`, `index.md`, `README.md`, `AGENTS.md`.
* **Текущее состояние:** 74 файла имеют front matter (`name`, `type`, `description`; опционально `lang`) — ключа `package` нет ни у одного; `docs/conventions/AGENTS.md` — без front matter вовсе.
* **Канон правок:** только в этом пакете. Копии `docs/conventions/` в consumer-проектах — перегенерируемый скаффолд, локальные правки затираются при следующем `coding-standard-init`.
* **Триггер:** введение маркировки `package:` в `prikotov/git-workflow` (PR #8) и `prikotov/todo-md` (PR #29) — третий распределяемый пакет остаётся без атрибуции.
* **Влияние на проверки:**
    * `validate-language` — не затронут: детектор читает из front matter только `lang` (`src/Language/DocumentLanguageDetector.php`, regex по ключу `lang`).
    * `validate-md-links` — не затронут: проверяет ссылки в теле документа, от front matter не зависит.
    * `validate-docs` / сниффы / PHPUnit — не затронуты: изменения только в front matter документов.
* **Границы (Out of Scope):** см. «Won't Have».

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)

- [x] В 74 `.md` из `docs/conventions/` (существующий front matter) ключ `package: prikotov/coding-standard` добавлен **первым ключом** блока — до `name`/`type`/`description`/`lang`.
- [x] В `docs/conventions/AGENTS.md` создан новый блок front matter первым блоком файла, начинающийся с ключа `package: prikotov/coding-standard`.
- [x] Размечены все 75 `.md` из `docs/conventions/` — включая `examples/`, `index.md`, `README.md`.
- [x] Содержимое документов (заголовки, текст, ссылки, примеры) не изменяется.

### 🟡 Желательно (Should Have)

- [x] Правка выполнена скриптом/массовой заменой, а не руками по файлам — для повторяемости и отсутствия пропусков.

### ⚫ Won't Have (Не будем делать)

- [ ] Правки README пакета с заметкой о маркере `package` — владелец признал такую заметку лишней (из git-workflow она убирается).
- [ ] Изменения кода инструментов (`src/`, `bin/`) — включая валидацию ключа `package`.
- [ ] Исключения для `examples/` — это тоже распределяемые документы, помечаются все `.md` из `docs/conventions`.

## 4. План реализации (Implementation Plan)

1. [x] Одноразовым скриптом пройтись по 74 `.md` с front matter: вставить `package: prikotov/coding-standard` первой строкой YAML-блока (сразу после открывающего `---`).
2. [x] Для `docs/conventions/AGENTS.md`: добавить блок front matter первым блоком файла (открывающий `---`, ключ `package`, закрывающий `---`).
3. [x] Проверить: `grep -rL "^package: prikotov/coding-standard" docs/conventions --include="*.md"` — пусто (75 из 75 размечены).
4. [ ] Прогнать проверки CI репозитория (`composer check` и пайплайн CI).
5. [x] Проверить `bin/coding-standard-init` на тестовом consumer-проекте — копия документов содержит маркер.

## 5. Критерии приёмки (Definition of Done)

- [x] Все 75 `.md` из `docs/conventions/` содержат `package: prikotov/coding-standard` первым ключом front matter.
- [ ] Проверки CI зелёные.
- [x] Шаблонов-исключений нет — не осталось ни одного неразмеченного `.md` в `docs/conventions/`.

## 6. Самопроверка (Verification)

```bash
# 75 из 75 размечены, список пуст:
grep -rL "^package: prikotov/coding-standard" docs/conventions --include="*.md"
# Полный набор проверок пакета (PHPUnit + sniff-test + validate-docs + validate-md-links + validate-language + validate-todo + phpstan + phpcs):
composer check
```

## 7. Риски и зависимости (Risks and Dependencies)

- **Распространение (important):** consumer-проекты получают маркировку только после ре-инита `coding-standard-init`; до этого их локальные копии остаются без маркера. Задачу по синхронизации consumer'ов не создаём — инициатива их владельцев.
- **Конфликты при последующих upstream-обновлениях:** если в consumer-проекте локально правили копию `docs/conventions/`, ре-инит с маркировкой затрёт правки — но это существующее поведение скаффолда, не новый риск.
- **Парсеры front matter у потребителей:** ключ идёт первым и не конфликтует со схемой `name`/`type`/`description`/`lang`; YAML остаётся валидным.
- Зависимостей от других задач нет (`depends_on` пуст).

## 8. Источники (Sources)

- `prikotov/git-workflow` PR #8 — введение маркировки `package:` в front matter распределяемых документов.
- `prikotov/todo-md` PR #29 — та же практика маркировки `package:`.
- `docs/conventions/` — 75 `.md`, объект правки; 74 с front matter, `AGENTS.md` без.
- `src/Language/DocumentLanguageDetector.php` — подтверждение, что `validate-language` читает из front matter только ключ `lang`.
- `AGENTS.md` (корень) — правила front matter для `docs/conventions/`.

## 9. Комментарии (Comments)

- Маркер ставится **первым** ключом — так же, как в git-workflow и todo-md: визуально срабатывает мгновенно при открытии файла, до чтения остальных ключей.
- Файлы в `examples/` без расширения `.md` (например, `Kernel.php`, `Makefile`) не размечаются — маркировка только для Markdown с front matter.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-21 14:01:04 (1787320864) | Тимлид (pi) | Создание задачи |
| 2026-08-21 | Тимлид (pi) | Заполнение секций: проблема атрибуции распределяемых документов, решение `package: prikotov/coding-standard` для 75 `.md`, границы Won't Have, DoD. |
| 2026-08-22 | Разработчик (pi) | Маркер добавлен в 75 документов; пройдены локальные проверки и проверено копирование через `coding-standard-init`. |
