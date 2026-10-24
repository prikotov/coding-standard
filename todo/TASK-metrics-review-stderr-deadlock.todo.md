---
type: fix
created: 2026-10-24 06:32:28 (1792823548)
due: 
started: 2026-10-24 06:40:58 (1792824058)
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Лид Арагорн (pi)
assignee: Разработчик Фродо (pi)
branch: task/metrics-review-stderr-deadlock
pr: https://github.com/prikotov/coding-standard/pull/128
status: review
---

# TASK-metrics-review-stderr-deadlock: Устранить дедлок чтения пайпов в запуске процессов (MetricsReviewPipeline::process и git-хелперы)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Утилита `coding-standard-metrics-review` навсегда зависает на стадии `composer install` в baseline-worktree, если дочерний процесс пишет в stderr больше размера буфера пайпа (~64 КБ). В проекте-потребителе stocks2 воспроизводится стабильно (2 прогона подряд, зависание 11–30+ минут): композер блокируется на записи в stderr, stdout не закрывается, родитель вечнозастревает в `stream_get_contents($pipes[1])`; дочерние unzip-процессы остаются зомби.

Причина в `MetricsReviewPipeline::process()`: stdout читается блокирующе до EOF, при этом stderr никто не читает. Тот же паттерн последовательного чтения повторён в `GitChurnCollector::command()` и `MetricsSnapshotMetadata::gitValue()` (у git объемы stderr малы — там риск ниже, но механизм тот же).

### Варианты или путь решения (Solution Sketch)
Единый helper запуска процессов с **параллельным** чтением stdout и stderr через `stream_select` (как Symfony Process): цикл до EOF на обоих пайпах, накопление обеих строк. Применить в трёх местах: `MetricsReviewPipeline::process()`, `GitChurnCollector::command()`, `MetricsSnapshotMetadata::gitValue()`. Регрессионный тест — дочерний PHP-процесс, пишущий >64 КБ в stderr при пустом stdout: до фикса висит (проверять с таймаутом), после — проходит.

### Ожидаемый результат (Expected Result)
`coding-standard-metrics-review --base=<ref>` в проекте-потребителе проходит до конца без зависаний даже когда `composer install` в baseline-worktree активно пишет в stderr; `composer check` пакета зелёный; патч-релиз опубликован.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя, я хочу, чтобы `coding-standard-metrics-review` надёжно завершался на любых объемах вывода дочерних процессов, чтобы сбор дельты метрик перед PR не приходилось обходить самописными скриптами.

### Цель по SMART (Goal)
- До следующего патч-релиза пакета: дедлок устранён в трёх местах запуска процессов, добавлен регрессионный тест, `composer check` зелёный, релиз выпущен.

## 2. Контекст и Границы (Context and Scope)

* **Где:** `src/Metrics/MetricsReviewPipeline.php` (`process()`), `src/Metrics/GitChurnCollector.php` (`command()`), `src/Metrics/MetricsSnapshotMetadata.php` (`gitValue()`); тесты — `tests/`.
* **Воспроизведение** (внешний репорт, stocks2 @ task/unpin-twig-329, PR #377): `vendor/bin/coding-standard-metrics-review --base=origin/master` в корне stocks2 зависает на `composer install` в `var/metrics-worktree-*` (зомби-unzip). Обходка потребителя — воспроизведение пайплайна со stream_select-чтением — подтверждает, что при параллельном чтении всё проходит (202 пакета, ~1.5–4 мин).
* **In Scope:** фикс чтения пайпов в трёх местах, регрессионный тест, патч-релиз.
* **Out of Scope:** изменение логики пайплайна/агрегации метрик, изменение контракта возвращаемых значений (`array{int, string, string}` сохраняется), рефакторинг смежных стадий.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `MetricsReviewPipeline::process()` не блокируется, когда дочерний процесс пишет в stderr больше размера буфера пайпа; контракт возвращаемого значения сохранён.
- [x] Тот же helper/подход применён в `GitChurnCollector::command()` и `MetricsSnapshotMetadata::gitValue()`.
- [x] Регрессионный тест с дочерним процессом, пишущим >64 КБ в stderr при пустом stdout, зелёный.
- [x] `composer check` зелёный.
### ⚫ Won't Have (Не будем делать)
- Не меняем логику пайплайна метрик и формат артефактов; не вводим внешние зависимости (Symfony Process и т.п.).

## 4. План реализации (Implementation Plan)
1. [x] Вынести helper чтения двух пайпов через `stream_select` (накопление stdout/stderr до EOF, затем `proc_close`).
2. [x] Применить helper в `MetricsReviewPipeline::process()`, `GitChurnCollector::command()`, `MetricsSnapshotMetadata::gitValue()`.
3. [x] Регрессионный тест (proc_open на PHP-скрипт: stdout пуст, stderr >64 КБ; ограничить по времени, чтобы падение было быстрым, а не зависанием).
4. [x] `composer check`; при возможности — проверка пайплайна на проекте-потребителе (stocks2) без изменения его отслеживаемых файлов.
5. [ ] Патч-релиз; в stocks2 после релиза: `composer update prikotov/coding-standard` и снятие временной обходки.

## 5. Критерии приёмки (Definition of Done)
- [x] Три места запуска процессов используют параллельное чтение пайпов.
- [x] Регрессионный тест добавлен и зелёный.
- [x] `composer check` зелёный; CI PR зелёный.
- [ ] Патч-релиз выпущен, потребитель (stocks2) подтвердил прохождение `coding-standard-metrics-review` без обходок.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
vendor/bin/phpunit --filter Deadlock
# в проекте-потребителе (stocks2):
# timeout 600 vendor/bin/coding-standard-metrics-review --base=origin/master && echo OK
```

## 7. Риски и зависимости (Risks and Dependencies)
- stream_select на Windows с пайпами proc_open имеет известные ограничения; пакет развивается под Linux/macOS-окружения (CI Linux) — считается приемлемым, при необходимости зафиксировать в README.
- Воспроизведение требует проекта-потребителя с большим числом зависимостей; в самом пакете покрыто синтетическим тестом.
- Потребителю (stocks2) после релиза нужен bump пакета — координация через задачу TASK-deps-unpin-twig-329 / отдельную задачу bump.

## 8. Источники (Sources)
- Внешний репорт и обходка: stocks2, ветка `task/unpin-twig-329`, PR https://github.com/prikotov/stocks2/pull/377 (раздел «Метрики качества» и файл задачи `todo/done/TASK-deps-unpin-twig-329.todo.md`).
- Место дедлока: `src/Metrics/MetricsReviewPipeline.php`, метод `process()` (последовательное `stream_get_contents`).
- Каноничное решение: Symfony Process (параллельное чтение stdout/stderr через stream_select).

## 9. Комментарии (Comments)
- Диагностика потребителя: при зависании композер жив, но заблокирован на записи в stderr (дочерние unzip — зомби); ручной запуск с `2>&1` не воспроизводит — подтверждает пайп-дедлок, а не сетевую проблему (packagist недоступность в том окружении — отдельный независимый фактор).
- Аналогичная последовательная схема чтения в `MetricsPipeline` не уязвима: там stderr/stdout наследуются, а не пайпятся.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-10-24 06:32:28 (1792823548) | Лид Арагорн (pi) | Создание задачи |
| 2026-10-24 14:04:03 (1792825443) | Разработчик Фродо (pi) | Реализация: новый `ProcessRunner` с параллельным чтением stdout/stderr через `stream_select`, применён в трёх местах; регрессионный тест с форк-дедлайном (на последовательном чтении падает за 60с, на фиксе проходит за 0.2с); `composer check` зелёный (291 тест); проверка на stocks2 (ветка task/unpin-twig-329): пайплайн прошёл до конца за ~1м47с, отслеживаемые файлы не изменены |
