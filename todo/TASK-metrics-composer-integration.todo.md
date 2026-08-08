---
type: build
created: 2026-08-02
value: V2
complexity: C1
priority: P2
depends_on: TASK-metrics-html-dashboard, TASK-metrics-codebase-size
epic: EPIC-metrics-ai-maintainability
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-metrics-composer-integration: composer metrics + документация для агентов

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Цепочка метрик запускается вручную несколькими командами (анализатор, deptrac, агрегатор, размер/тесты, дашборд).
- Агенты и разработчики не знают, как запустить сбор и где лежит отчёт.
- var/metrics/ не исключён из git — сгенерированные отчёты рискуют попасть в историю.

### Варианты или путь решения (Solution Sketch)

- composer script `metrics`: последовательный запуск (анализатор → deptrac → агрегатор → размер/тесты/покрытие → дашборд) через composer-скрипты.
- var/metrics/ в .gitignore.
- README: раздел «Метрики качества» (команда, артефакты, как читать).
- AGENTS.md: инструкция для агентов — как получить report.json и интерпретировать по конвенции TASK-metrics-model-convention.

### Ожидаемый результат (Expected Result)

- `composer metrics` одной командой собирает весь отчёт; документация описывает запуск и чтение отчёта.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как ИИ-агент (или разработчик), я хочу запускать сбор метрик одной командой и знать, где и как читать отчёт, чтобы быстро оценить поддерживаемость проекта.

### Goal (Цель по SMART)

Добавить composer script `metrics` (полный пайплайн: анализ классов → deptrac → агрегация → дашборд), исключить var/metrics/ из git, описать запуск и интерпретацию в README и AGENTS.md (со ссылкой на конвенцию TASK-metrics-model-convention). `composer check` остаётся зелёным.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** composer.json (scripts, require-dev), .gitignore, README.md, AGENTS.md.
- **Текущее поведение:** скрипта metrics нет; var/metrics/ не исключён из git.
- **Границы (Out of Scope):**
  - Не гейтим CI метриками.
  - Не публикуем отчёты в репозиторий.
  - Не меняем существующие composer-скрипты (check, test и т.д.).
  - Не трогаем todo/*.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] composer script `metrics`: последовательный запуск всех шагов пайплайна (инструмент TASK-metrics-tools-evaluation, deptrac TASK-metrics-module-boundaries-deptrac, агрегатор TASK-metrics-aggregator, размер/тесты TASK-metrics-codebase-size, дашборд TASK-metrics-html-dashboard); ненулевой код завершения при ошибке шага.
- [ ] var/metrics/ добавлен в .gitignore.
- [ ] README: раздел «Метрики качества» — команда, артефакты (report.json, index.html), порядок запуска.
- [ ] AGENTS.md: инструкция для агентов — как получить отчёт и интерпретировать (ссылка на конвенцию TASK-metrics-model-convention).

### 🟡 Should Have (Желательно)

- [ ] Summary-вывод в консоль (количество классов/модулей, топ проблемных модулей по доле внешних зависимостей и циклам).
- [ ] Проверка: `composer metrics` работает после `composer install` на чистом клоне.

### ⚫ Won't Have (Не будем делать)

- [ ] Не добавляем CI-гейты по метрикам.
- [ ] Не коммитим сгенерированные отчёты.
- [ ] Не меняем существующие composer-скрипты.

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)

- [ ] `composer metrics` генерирует report.json и index.html end-to-end.
- [ ] README и AGENTS.md содержат раздел о метриках.
- [ ] .gitignore содержит var/metrics/.
- [ ] `composer check` зелёный (включая validate-todo).

## 6. Verification (Самопроверка)

```bash
composer metrics
git status --short   # var/metrics/ не появляется в untracked
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)

- Dev-зависимости анализатора увеличивают вес установки — допустимо (только публичные Packagist-пакеты; в CI нет доступа к VCS-репозиториям).
- CI: `composer check` не должен зависеть от инструментов метрик и git-истории (metrics не входит в check).
- Пайплайн зависит от результатов TASK-metrics-tools-evaluation, TASK-metrics-module-boundaries-deptrac, TASK-metrics-aggregator, TASK-metrics-codebase-size, TASK-metrics-html-dashboard.

## 8. Sources (Источники)

- `composer.json` — scripts
- `README.md`
- `AGENTS.md`
- TASK-metrics-aggregator, TASK-metrics-html-dashboard, TASK-metrics-codebase-size

## 9. Comments (Комментарии)

- Для агентов главный артефакт — report.json; AGENTS.md должен давать агенту команду запуска и адрес отчёта.

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи. |
| 2026-08-08 | Codex | Выполненная TASK-metrics-aggregator удалена из depends_on. |
