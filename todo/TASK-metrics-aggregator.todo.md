---
type: feat
created: 2026-08-02
value: V3
complexity: C3
priority: P2
depends_on:
epic: EPIC-metrics-ai-maintainability
author: pi
assignee: Разработчик (codex)
branch: task/metrics-aggregator
pr:
status: in_progress
---

# TASK-metrics-aggregator: PHP-агрегатор метрик → единый var/metrics/report.json

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Инструменты дают разрозненные JSON/XML: анализатор классов, Deptrac, git log — единого отчёта по модели метрик нет.
- ИИ-агентам нужен один machine-readable файл с class-level и module-level метриками.
- Агрегации (медианы, p90/p95, доли, циклы) пришлось бы считать вручную.

### Варианты или путь решения (Solution Sketch)

- bin/metrics-aggregate.php (PHP 8.4, strict_types, по образцу bin/validate-docs.php / bin/run-sniff-tests.php).
- Входы: JSON собственного сборщика или внешнего эталона (по TASK-metrics-tools-evaluation), полный deptrac.json собственного форматтера (по TASK-metrics-module-boundaries-deptrac), git log (churn).
- Выход: var/metrics/report.json по модели TASK-metrics-model-convention + метаданные (дата, commit, версии инструментов).
- PHPUnit-тесты на агрегации с фикстурами.

### Ожидаемый результат (Expected Result)

- Скрипт считает все метрики модели и пишет report.json; тесты покрывают агрегации; команда задокументирована.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как ИИ-агент, я хочу получать единый JSON-отчёт со всеми метриками модели (class-level и module-level) одной командой, чтобы оценивать поддерживаемость проекта без ручного сведения данных из разных инструментов.

### Goal (Цель по SMART)

Реализовать bin/metrics-aggregate.php: чтение JSON сборщика, полного deptrac.json и необязательной git-истории; вычисление method-level, class-level и module-level метрик по модели TASK-metrics-model-convention (включая медианы, p90/p95, доли внешних связей, циклы и размер интерфейса); запись var/metrics/report.json. Покрытие агрегаций PHPUnit-тестами. `composer test` и `composer validate-todo` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** bin/metrics-aggregate.php (новый), при необходимости классы в src/ (по образцу существующих bin-скриптов пакета), tests/ — тесты агрегатора, var/metrics/ — выход.
- **Входные данные:** JSON собственного сборщика или выбранного в TASK-metrics-tools-evaluation анализатора; полный var/metrics/deptrac.json собственного форматтера (TASK-metrics-module-boundaries-deptrac); git log (churn).
- **Текущее поведение:** единого отчёта нет.
- **Границы (Out of Scope):**
  - Не строим HTML-дашборд (TASK-metrics-html-dashboard).
  - Не настраиваем инструменты анализа (задачи TASK-metrics-tools-evaluation / TASK-metrics-module-boundaries-deptrac).
  - Не гейтим CI.
  - Не меняем существующие сниффы.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] CLI-скрипт с аргументами (пути к JSON, выходной файл; дефолты var/metrics/*).
- [ ] method-level блок: физический LOC и цикломатическая сложность каждого метода.
- [ ] class-level блок: идентификаторы, физический LOC, методы/свойства, сумма и максимум сложности, LCOM4, списки и количества Ca/Ce, module.
- [ ] module-level блок: количество классов и файлов, суммарный LOC, медиана/макс/p90/p95 размера и сложности, внутренние зависимости, входящие/исходящие межмодульные, доля внешних связей, число циклов и размер используемого снаружи интерфейса.
- [ ] Детерминированный JSON: стабильный порядок ключей, фиксированная схема; метаданные (дата, commit, версии инструментов).
- [ ] Отдельный блок `findings`: стабильный идентификатор правила, объект, исходные значения и объяснение; `findings` не меняют код выхода в первой версии.
- [ ] PHPUnit-тесты на ключевые агрегации (медиана/перцентили, доли, циклы) на фикстурах.

### 🟡 Should Have (Желательно)

- [ ] Churn из git (число коммитов и изменённых строк по файлу); обработка пустой/короткой истории без падений.
- [ ] Читаемые сообщения об ошибках (нет файла, пустой вход, несовместимая схема).

### ⚫ Won't Have (Не будем делать)

- [ ] Не пишем HTML/JS (TASK-metrics-html-dashboard).
- [ ] Не добавляем новых анализаторов сверх выбранного в TASK-metrics-tools-evaluation.
- [ ] Не меняем существующий код пакета (только новый скрипт/классы и тесты).

## 4. Implementation Plan (План реализации)

- [x] Подтвердить контракт входов: JSON PhpCodeArcheology 2.11.x и полный JSON-граф `metrics-json` Deptrac.
- [x] Реализовать агрегатор с нормализацией классов/методов, графом зависимостей, распределениями и SCC-циклами.
- [x] Добавить CLI с проверкой входов, детерминированной записью JSON и метаданными Git.
- [x] Покрыть формулы и `findings` PHPUnit-тестами на фикстурах; выполнить полный `composer check`.

## 5. Definition of Done (Критерии приёмки)

- [ ] Скрипт генерирует report.json со всеми полями модели TASK-metrics-model-convention (проверка схемой).
- [ ] Тесты агрегаций зелёные (`composer test`).
- [ ] Прогон на реальных данных пакета (выходы задач TASK-metrics-tools-evaluation / TASK-metrics-module-boundaries-deptrac) даёт осмысленные значения.
- [ ] `composer validate-todo` проходит.

## 6. Verification (Самопроверка)

```bash
php bin/metrics-aggregate.php --analyzer=var/metrics/phpmetrics.json --deptrac=var/metrics/deptrac.json --output=var/metrics/report.json
composer test
```

## 7. Risks and Dependencies (Риски и зависимости)

- Форматы JSON анализатора (TASK-metrics-tools-evaluation) и deptrac.json (TASK-metrics-module-boundaries-deptrac) — зафиксировать парсер под конкретные схемы; добавить тесты на парсинг.
- LCOM в разных инструментах — разные определения; в отчёт писать значение инструмента с пометкой определения (или нормализовать — по модели TASK-metrics-model-convention).
- Штатный JSON Deptrac не содержит разрешённые рёбра; агрегатор принимает только полный отчёт собственного форматтера из TASK-metrics-module-boundaries-deptrac.
- Churn: короткая git-история — слабый сигнал; обрабатывать без падений.
- Зависимости: TASK-metrics-model-convention (схема отчёта), TASK-metrics-module-boundaries-deptrac (deptrac.json).

## 8. Sources (Источники)

- `bin/run-sniff-tests.php`, `bin/validate-docs.php` — образцы bin-скриптов пакета
- TASK-metrics-model-convention — модель и схема отчёта
- TASK-metrics-module-boundaries-deptrac — deptrac.json
- [PhpMetrics — JSON report](https://github.com/phpmetrics/PhpMetrics)

## 9. Comments (Комментарии)

- report.json — главный артефакт для ИИ-агентов; схему JSON зафиксировать в модели TASK-metrics-model-convention, чтобы дашборд (TASK-metrics-html-dashboard) и агенты зависели только от неё.

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи. |
| 2026-08-07 | codex (Codex) | Входы и схема уточнены для собственного сборщика, method-level метрик и полного графа Deptrac. |
| 2026-08-07 | codex (Codex) | В выходную схему добавлен отдельный неблокирующий блок `findings` для проблемных мест. |
| 2026-08-08 | codex (Codex) | Выполненная TASK-metrics-model-convention удалена из depends_on. |
| 2026-08-08 | codex (Codex) | Выполненная TASK-metrics-module-boundaries-deptrac удалена из depends_on. |
| 2026-08-08 | Codex | Задача взята в работу: создана ветка `task/metrics-aggregator`, закреплён план реализации. |
| 2026-08-08 | Codex | Реализованы CLI и агрегатор отчётов PhpCodeArcheology/Deptrac, добавлены тесты и документация запуска. |
