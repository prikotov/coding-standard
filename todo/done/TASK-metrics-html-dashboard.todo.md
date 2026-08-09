---
type: feat
created: 2026-08-02
value: V2
complexity: C3
priority: P3
depends_on:
epic: EPIC-metrics-ai-maintainability
author: pi
assignee: Разработчик (codex)
branch: task/metrics-html-dashboard
pr: https://github.com/prikotov/coding-standard/pull/93
status: done
---

# TASK-metrics-html-dashboard: Статический HTML-дашборд метрик

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- report.json (TASK-metrics-aggregator) неудобен для человека: узкие места не видны.
- Нужна визуализация: крупные несвязные классы, сильно связанные модули, циклы зависимостей.

### Варианты или путь решения (Solution Sketch)

- Генератор bin/metrics-dashboard.php: читает var/metrics/report.json и пишет var/metrics/index.html с данными, встроенными в HTML (file:// без fetch).
- Чарты на vanilla JS/SVG, без сервера и внешних CDN (офлайн).
- Чарты по данным отчёта: bubble chart модулей (X=размер в LOC/классах, Y=доля внешних зависимостей, размер пузыря=churn, цвет=число циклов/cohesion), scatter классов (X=LOC, Y=LCOM, размер=CBO/Ce, цвет=CC), treemap (модуль → класс; площадь=LOC, цвет=LCOM/churn), матрица зависимостей модулей (строки=источники, столбцы=получатели).

### Ожидаемый результат (Expected Result)

- var/metrics/index.html открывается из file:// и показывает 4 чарта на реальных данных пакета.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как разработчик, я хочу видеть узкие места проекта на одном экране (крупные несвязные классы, модули с высокой внешней связанностью, циклы), чтобы приоритизировать рефакторинг.

### Goal (Цель по SMART)

Реализовать генератор bin/metrics-dashboard.php: читает var/metrics/report.json (схема из TASK-metrics-model-convention), пишет var/metrics/index.html с встроенными данными и четырьмя визуализациями (bubble chart модулей, scatter классов, treemap, матрица зависимостей). Открывается из file:// без сервера и интернета. Проверка: генерация и просмотр на реальном отчёте пакета.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** bin/metrics-dashboard.php (новый), var/metrics/index.html (выход), tests/ (опционально, если логика генерации тестируема).
- **Вход:** var/metrics/report.json (TASK-metrics-aggregator).
- **Текущее поведение:** дашборда нет.
- **Границы (Out of Scope):**
  - Без серверного бэкенда и БД.
  - Без внешних CDN (Chart.js и т.п.) — только vanilla JS/SVG.
  - Без новых composer-зависимостей.
  - Не считаем метрики (только визуализация report.json).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] Генератор index.html из report.json (данные встроены — file:// без fetch).
- [x] Bubble chart модулей: X=размер (LOC или классы), Y=доля внешних зависимостей, размер пузыря=churn, цвет=число циклов (или cohesion — по модели TASK-metrics-model-convention).
- [x] Scatter классов: X=LOC, Y=LCOM, размер=CBO/Ce, цвет=CC.
- [x] Treemap: модуль → класс, площадь=LOC, цвет=LCOM или churn.
- [x] Матрица зависимостей модулей (строки=источники, столбцы=получатели; подсветка циклов).
- [x] Открывается из file:// без консольных ошибок.

### 🟡 Should Have (Желательно)

- [x] Подписи, легенды, тултипы; ранжирование модулей по метрикам.
- [x] Подсветка «правого верхнего угла» (крупные и сильно связанные модули).

### ⚫ Won't Have (Не будем делать)

- [ ] Не добавляем npm/webpack и внешние библиотеки.
- [ ] Не строим серверный дашборд и историю в БД.
- [ ] Не добавляем чарты вне модели TASK-metrics-model-convention.

## 4. Implementation Plan (План реализации)

- [x] Реализовать генератор, который валидирует корневой отчёт и рекурсивно собирает данные модулей, классов и связей из зеркала `var/metrics/`.
- [x] Сформировать автономный HTML с встроенными данными и четырьмя визуализациями на vanilla JS/SVG, легендами и тултипами.
- [x] Покрыть загрузку отчётов и генерацию HTML PHPUnit-тестами; проверить CLI на реальных метриках пакета и выполнить `composer check`.

## 5. Definition of Done (Критерии приёмки)

- [x] bin/metrics-dashboard.php генерирует index.html из реального report.json пакета.
- [x] Все 4 чарта отображаются при открытии file:// (проверено в браузере).
- [x] `composer validate-todo` проходит; `composer test` не сломан.

## 6. Verification (Самопроверка)

```bash
php bin/metrics-dashboard.php --input=var/metrics/report.json --output=var/metrics/index.html
# открыть var/metrics/index.html в браузере и проверить 4 чарта
```

## 7. Risks and Dependencies (Риски и зависимости)

- Объём данных: сотни классов — SVG может быть тяжёлым; ограничивать отрисовку (топ N) или агрегировать.
- LCOM из разных инструментов (TASK-metrics-tools-evaluation) — цветовая шкала должна быть устойчивой к масштабу значений.
- Проверка в headless-браузере не обязательна; ручной просмотр достаточен для этой задачи.

## 8. Sources (Источники)

- TASK-metrics-aggregator — report.json
- TASK-metrics-model-convention — схема отчёта
- Обсуждение: чарты (bubble chart модулей, scatter классов, treemap, матрица зависимостей)

## 9. Comments (Комментарии)

- Дашборд — для людей; ИИ-агенты читают report.json (TASK-metrics-aggregator). Поэтому задача помечена P3: не блокирует ценность эпика для агентов.
- Генератор следует ссылкам `children` в зеркале отчётов: модульные агрегаты берёт из каталогов, классы и связи — из файловых отчётов.
- На реальном отчёте пакета отрисованы 6 модулей, 39 классов и 4 межмодульные зависимости; автономный файл проверен в Google Chrome без ошибок консоли.

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи. |
| 2026-08-08 | Codex | Выполненная TASK-metrics-aggregator удалена из depends_on. |
| 2026-08-09 | Codex | Задача взята в работу: создана ветка `task/metrics-html-dashboard`, добавлен план реализации. |
| 2026-08-09 | Codex | Реализованы и проверены генератор, четыре SVG-визуализации и PHPUnit-тесты. |
| 2026-08-09 | Codex | Создан черновик PR #93; задача переведена в review. |
| 2026-08-09 | Codex | По обратной связи добавлена справка по смыслу и интерпретации метрик. |
| 2026-08-10 | codex (Codex) | PR #93 и PR #94 приняты и объединены с master; задача завершена. |
