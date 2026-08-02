---
type: feat
created: 2026-08-02
value: V3
complexity: C3
priority: P2
depends_on: TASK-metrics-model-convention, TASK-metrics-module-boundaries-deptrac
epic: EPIC-metrics-ai-maintainability
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-metrics-aggregator: PHP-агрегатор метрик → единый var/metrics/report.json

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Инструменты дают разрозненные JSON/XML: анализатор классов, Deptrac, git log — единого отчёта по модели метрик нет.
- ИИ-агентам нужен один machine-readable файл с class-level и module-level метриками.
- Агрегации (медианы, p90/p95, доли, циклы) пришлось бы считать вручную.

### Варианты или путь решения (Solution Sketch)

- bin/metrics-aggregate.php (PHP 8.4, strict_types, по образцу bin/validate-docs.php / bin/run-sniff-tests.php).
- Входы: JSON анализатора (по TASK-metrics-tools-evaluation), deptrac.json (по TASK-metrics-module-boundaries-deptrac), git log (churn).
- Выход: var/metrics/report.json по модели TASK-metrics-model-convention + метаданные (дата, commit, версии инструментов).
- PHPUnit-тесты на агрегации с фикстурами.

### Ожидаемый результат (Expected Result)

- Скрипт считает все метрики модели и пишет report.json; тесты покрывают агрегации; команда задокументирована.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как ИИ-агент, я хочу получать единый JSON-отчёт со всеми метриками модели (class-level и module-level) одной командой, чтобы оценивать поддерживаемость проекта без ручного сведения данных из разных инструментов.

### Goal (Цель по SMART)

Реализовать bin/metrics-aggregate.php: чтение JSON выбранного анализатора, deptrac.json и git-истории; вычисление class-level и module-level метрик по модели TASK-metrics-model-convention (включая медианы, p90/p95, доли внешних зависимостей, циклы по графу модулей, размер интерфейса, churn); запись var/metrics/report.json. Покрытие агрегаций PHPUnit-тестами. `composer test` и `composer validate-todo` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** bin/metrics-aggregate.php (новый), при необходимости классы в src/ (по образцу существующих bin-скриптов пакета), tests/ — тесты агрегатора, var/metrics/ — выход.
- **Входные данные:** JSON выбранного в TASK-metrics-tools-evaluation анализатора; var/metrics/deptrac.json (TASK-metrics-module-boundaries-deptrac); git log (churn).
- **Текущее поведение:** единого отчёта нет.
- **Границы (Out of Scope):**
  - Не строим HTML-дашборд (TASK-metrics-html-dashboard).
  - Не настраиваем инструменты анализа (задачи TASK-metrics-tools-evaluation / TASK-metrics-module-boundaries-deptrac).
  - Не гейтим CI.
  - Не меняем существующие сниффы.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] CLI-скрипт с аргументами (пути к JSON, выходной файл; дефолты var/metrics/*).
- [ ] class-level блок: для каждого класса — LOC/LLOC, методы/свойства, LCOM, Ca, Ce (или CBO), CC, churn, module.
- [ ] module-level блок: количество классов и файлов, суммарный LOC, медиана/макс/p90/p95 размера класса, медиана/макс LCOM, внутренние зависимости, входящие/исходящие межмодульные, доля внешних зависимостей, число циклов (по графу модулей), размер публичного интерфейса, churn модуля.
- [ ] Детерминированный JSON: стабильный порядок ключей, фиксированная схема; метаданные (дата, commit, версии инструментов).
- [ ] PHPUnit-тесты на ключевые агрегации (медиана/перцентили, доли, циклы) на фикстурах.

### 🟡 Should Have (Желательно)

- [ ] Churn из git (число коммитов и изменённых строк по файлу); обработка пустой/короткой истории без падений.
- [ ] Читаемые сообщения об ошибках (нет файла, пустой вход, несовместимая схема).

### ⚫ Won't Have (Не будем делать)

- [ ] Не пишем HTML/JS (TASK-metrics-html-dashboard).
- [ ] Не добавляем новых анализаторов сверх выбранного в TASK-metrics-tools-evaluation.
- [ ] Не меняем существующий код пакета (только новый скрипт/классы и тесты).

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом.*

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
