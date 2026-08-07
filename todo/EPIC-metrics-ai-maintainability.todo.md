---
type: epic
created: 2026-08-02
value: V3
complexity: C3
priority: P2
author: pi
assignee:
status: todo
pr:
---

# EPIC-metrics-ai-maintainability: Метрики качества проекта для ИИ-агентов

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- ИИ-агенты и разработчики не имеют объективных числовых метрик качества кода пакета: связности (cohesion), связанности (coupling), размера и количества компонентов на уровнях «метод → класс» и «класс → модуль».
- Поддерживаемость оценивается субъективно (чтение кода, code review); нет данных для приоритизации рефакторинга и точки входа для агента в незнакомый код.
- Единого инструмента «модуль → классы → cohesion/coupling/размер → узкие места» нет; есть набор частичных анализаторов (PhpMetrics, PDepend, Deptrac, PhpCodeArcheology), которые нужно свести в единую модель и отчёт.

### Варианты или путь решения (Solution Sketch)

- Оценить кандидатов (PhpMetrics / PDepend / PhpCodeArcheology) на коде самого пакета и выбрать основу — TASK-metrics-tools-evaluation.
- Зафиксировать модель метрик (class-level и module-level, определение модуля, интерпретация) в docs/conventions/ — TASK-metrics-model-convention.
- Определить границы модулей пакета в конфиге Deptrac с JSON-выводом зависимостей — TASK-metrics-module-boundaries-deptrac.
- Собрать агрегатор: JSON анализатора + Deptrac + git churn → единый var/metrics/report.json — TASK-metrics-aggregator.
- Построить статический HTML-дашборд (bubble chart, scatter, treemap, матрица зависимостей) — TASK-metrics-html-dashboard.
- Собрать метрику размера кодовой базы (scc), статистику тестов и покрытие — TASK-metrics-codebase-size.
- Связать цепочку в composer metrics и задокументировать запуск и чтение отчёта — TASK-metrics-composer-integration.

### Ожидаемый результат (Expected Result)

- Команда `composer metrics` генерирует var/metrics/report.json со всеми метриками модели и статический HTML-дашборд.
- ИИ-агент по report.json может оценить поддерживаемость пакета: крупные несвязные классы, сильно связанные модули, циклы зависимостей, узкие места.
- Документация (docs/conventions/ + README + AGENTS.md) описывает модель метрик, команду запуска и интерпретацию чисел.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как ИИ-агент и разработчик, я хочу получать объективные метрики качества проекта (cohesion, coupling, размер, количество компонентов) на уровнях «метод → класс» и «класс → модуль», чтобы оценивать поддерживаемость и находить узкие места без ручного чтения всего кода.

### Goal (Цель по SMART)

Собрать в пакете `prikotov/coding-standard` инструментальную цепочку метрик качества: одна команда `composer metrics` генерирует `var/metrics/report.json` (class-level: LOC/LLOC, количество методов и свойств, LCOM, Ca/Ce, CC, churn, принадлежность модулю; module-level: размер, внутренняя/внешняя связанность, циклы, размер публичного интерфейса, churn; project-level: размер кодовой базы (scc), статистика тестов и покрытие) и статический HTML-дашборд (bubble chart модулей, scatter классов, treemap, матрица зависимостей). Модуль определяется как предметный компонент пакета по модели метрик, а не как технический слой. Критерий: отчёт покрывает модель из docs/conventions/ops/quality-metrics.md, генерируется одной командой, `composer check` остаётся зелёным.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** этот пакет — src/ (Sniffs, Language, PhpStan, Deptrac, Config), bin/, config/, docs/conventions/, composer.json, README.md, AGENTS.md.
- **Модули пакета для метрик (стартовая гипотеза, уточняется в задачах):** src/Sniffs/Structure, src/Sniffs/Application, src/Sniffs/Config, src/Sniffs/Namespaces, src/Language, src/PhpStan, src/Deptrac, src/Config, bin, tests.
- **Границы (Out of Scope):**
  - Не внедряем цепочку метрик в consumer-проекты автоматически (init-скрипт не трогаем).
  - Не меняем существующие сниффы и конвенции.
  - Не строим CI-гейты по метрикам в этой итерации.
  - Не строим серверный дашборд и не храним историю метрик в БД.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Выбор инструмента-основы по результатам прогона на этом пакете (TASK-metrics-tools-evaluation).
- [ ] Модель метрик (class-level + module-level, определение модуля, интерпретация для агентов) в docs/conventions/ (TASK-metrics-model-convention).
- [ ] Границы модулей пакета и JSON-вывод зависимостей через Deptrac (TASK-metrics-module-boundaries-deptrac).
- [ ] Агрегатор: единый var/metrics/report.json по модели (TASK-metrics-aggregator).
- [ ] `composer metrics` + документация запуска и чтения отчёта (README, AGENTS.md) (TASK-metrics-composer-integration).

### 🟡 Should Have (Желательно)

- [ ] HTML-дашборд: bubble chart, scatter, treemap, матрица зависимостей (TASK-metrics-html-dashboard).
- [ ] Git churn как метрика класса и модуля (в TASK-metrics-aggregator).
- [ ] Метрика размера кодовой базы (scc), статистика тестов и покрытие (TASK-metrics-codebase-size).

### 🟢 Could Have (Опционально)

- [ ] История метрик между версиями (сравнение отчётов).
- [ ] Пороговые проверки (например, в CI) на основе отчёта.

### ⚫ Won't Have (Не будем делать)

- [ ] Не гейтим CI метриками в этой итерации.
- [ ] Не добавляем метрики в consumer-проекты автоматически (init-скрипт не трогаем).
- [ ] Не заменяем существующие проверки (phpcs, phpstan, deptrac) метриками.
- [ ] Не строим серверный дашборд и не храним историю в БД.

## 4. Solution Design (Техническое решение)

```mermaid
flowchart LR
    A[Анализатор классов: PhpMetrics / PDepend / PhpCodeArcheology] -->|JSON| D
    B[Deptrac: границы модулей пакета] -->|JSON| D
    C[git log: churn] --> D
    S[scc: размер кодовой базы, статистика тестов, покрытие clover.xml] -->|JSON/XML| D
    D[Агрегатор bin/metrics-aggregate.php] -->|report.json| E[HTML-дашборд]
    D -->|report.json| F[ИИ-агенты: оценка поддерживаемости]
```

- Выбор анализатора — результат TASK-metrics-tools-evaluation.
- Схема report.json — из модели TASK-metrics-model-convention.
- Маппинг «класс → модуль» — из конфига Deptrac TASK-metrics-module-boundaries-deptrac.
- Дашборд читает только report.json — зависит от схемы, не от инструментов.

## 5. Implementation Plan (План реализации)

- [ ] [TASK-metrics-tools-evaluation](TASK-metrics-tools-evaluation.todo.md) — оценка анализаторов и выбор основы
- [ ] [TASK-metrics-model-convention](TASK-metrics-model-convention.todo.md) — модель метрик в docs/conventions/
- [ ] [TASK-metrics-module-boundaries-deptrac](TASK-metrics-module-boundaries-deptrac.todo.md) — границы модулей пакета в Deptrac
- [ ] [TASK-metrics-aggregator](TASK-metrics-aggregator.todo.md) — агрегатор и report.json
- [ ] [TASK-metrics-codebase-size](TASK-metrics-codebase-size.todo.md) — размер кодовой базы (scc), статистика тестов, покрытие
- [ ] [TASK-metrics-html-dashboard](TASK-metrics-html-dashboard.todo.md) — статический HTML-дашборд
- [ ] [TASK-metrics-composer-integration](TASK-metrics-composer-integration.todo.md) — composer metrics и документация

## 6. Definition of Done (Критерии приёмки эпика)

- [ ] Все Must-задачи выполнены: report.json покрывает модель метрик.
- [ ] `composer metrics` работает end-to-end одной командой.
- [ ] HTML-дашборд отображает реальные данные пакета.
- [ ] docs/conventions/, README, AGENTS.md обновлены; `composer check` зелёный.

## 7. Release Notes and Deployment (Инструкция по релизу)

- [ ] В composer.json добавлены dev-зависимости выбранного анализатора (только публичные Packagist-пакеты).
- [ ] Добавлен composer script `metrics`.
- [ ] var/metrics/ добавлен в .gitignore.
- [ ] README содержит раздел «Метрики качества».

## 8. Risks and Dependencies (Риски и зависимости)

- Значения LCOM/Ca/Ce у разных инструментов расходятся — нужна сверка на знакомых классах (TASK-metrics-tools-evaluation).
- Поддержка PHP 8.4 (атрибуты, readonly-свойства) у PhpMetrics/PDepend может быть неполной — проверяется в TASK-metrics-tools-evaluation.
- Модуль ≠ namespace для этого пакета: маппинг «класс → модуль» делается по структуре src/, а не по namespace-шаблону consumer-проектов.
- Churn требует истории git репозитория; на короткой истории значения малоинформативны.
- scc — внешний Go-бинарник (не composer-зависимость), покрытие требует расширения pcov: шаги пайплайна должны быть опциональными при их отсутствии (TASK-metrics-codebase-size).
- Новые dev-зависимости — только публичные пакеты Packagist (в CI нет доступа к VCS-репозиториям).

## 9. Sources (Источники)

- [Обсуждение с ChatGPT: метрики качества проекта для ИИ-агентов](https://chatgpt.com/c/6a6c272c-a234-83eb-a421-5175608b5f2a)
- [PhpMetrics — метрики классов](https://github.com/phpmetrics/PhpMetrics)
- [PDepend — метрики PHP](https://pdepend.org)
- [Deptrac — документация](https://deptrac.github.io/deptrac/)
- [PhpCodeArcheology](https://github.com/PhpCodeArcheology/PhpCodeArcheology)
- `docs/conventions/index.md` — индекс конвенций (для модели метрик)
- `config/deptrac/depfile.yaml` — существующий шаблон Deptrac для consumer-проектов (не менять)

## 10. Comments (Комментарии)

- Гипотеза автора: метрики завязаны на Cohesion, Coupling, Размер и количество компонентов; «компонент» — пара класс-метод на нижнем уровне и модуль-класс на верхнем.
- Средний LCOM классов не является cohesion модуля; для модуля нужна отдельная графовая метрика (доля зависимостей внутри модуля, размер интерфейса наружу) — зафиксировано в TASK-metrics-model-convention.
- Для ИИ-агентов главный артефакт — machine-readable report.json; HTML-дашборд — для людей.
- По результатам TASK-metrics-tools-evaluation инструментом-основой выбран PhpCodeArcheology 2.11.x: его JSON напрямую содержит LOC/LLOC, LCOM, coupling, CC, количество методов и свойств. PhpMetrics и PDepend оставлены только как изученные альтернативы и в зависимости пакета не включаются. Подробности: [сравнение PHP-анализаторов метрик](../docs/research/metrics-tools-evaluation.md).

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание эпика по итогам обсуждения с ChatGPT о метриках качества проекта для ИИ-агентов. |
| 2026-08-02 | pi (Pi Coding Agent) | Добавлена задача TASK-metrics-codebase-size (размер кодовой базы через scc, статистика тестов, покрытие) по итогам обсуждения с пользователем; обновлены план, MoSCoW, схема пайплайна и риски. |
| 2026-08-06 | codex (Codex) | Зафиксирован выбор PhpCodeArcheology 2.11.x как инструмента-основы. |
| 2026-08-07 | codex (Codex) | Добавлена ссылка на постоянный отчёт об исследовании анализаторов метрик. |
| 2026-08-07 | codex (Codex) | По итогам расширенного поиска подтверждён основной анализатор; `scc` классифицирован как дополнительный источник размера проекта. |
