---
type: chore
created: 2026-08-02
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-metrics-ai-maintainability
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-metrics-tools-evaluation: Оценка анализаторов метрик (PhpMetrics / PDepend / PhpCodeArcheology) и выбор основы

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Обсуждение с ChatGPT дало две стратегии: быстрый эксперимент (PhpCodeArcheology + Deptrac) и контролируемая система (PhpMetrics или PDepend + Deptrac + агрегатор + дашборд). Выбор не сделан.
- Значения LCOM, Ca, Ce у разных инструментов могут расходиться; доверять можно только после сверки на знакомых классах.
- Неизвестна совместимость кандидатов с кодом пакета (PHP 8.4: атрибуты, readonly-свойства, строгая типизация).

### Варианты или путь решения (Solution Sketch)

- Установить кандидатов как временные dev-зависимости и прогнать на src/ пакета.
- Сверить LCOM, Ca, Ce, LOC на 3–5 известных классах (например, DtoStructureSniff, ServiceStructureSniff, EnumStructureSniff, CrossModuleDomainRule).
- Оценить JSON-выход каждого: полнота полей для class-level модели (LOC/LLOC, LCOM, Ca, Ce, CC, методы/свойства).
- Зафиксировать решение: инструмент-основа (и запасной для cross-check при необходимости), обоснование, команды прогона.

### Ожидаемый результат (Expected Result)

- Таблица сравнения кандидатов (метрики, JSON, PHP 8.4, лицензия, активность) в комментариях задачи.
- Выбран инструмент-основа; решение зафиксировано в задаче и эпике.
- Временные dev-зависимости невыбранных кандидатов удалены из composer.json.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как автор эпика метрик, я хочу выбрать инструмент-основу по результатам прогона на коде этого пакета, а не по описаниям, чтобы отчёт строился на проверенных значениях LCOM/coupling и поддерживал PHP 8.4.

### Goal (Цель по SMART)

Прогнать PhpMetrics, PDepend и PhpCodeArcheology на src/ пакета, сверить LCOM, Ca, Ce, LOC на 3–5 знакомых классах, оценить JSON-выход и совместимость с PHP 8.4. Результат: выбранный инструмент-основа с обоснованием, таблица сравнения, задокументированные команды прогона. Срок: в рамках эпика EPIC-metrics-ai-maintainability.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** composer.json (временные dev-зависимости), src/ пакета (объект анализа), var/metrics/ (вывод).
- **Кандидаты:** phpmetrics/phpmetrics, pdepend/pdepend, php-code-archeology/php-code-archeology.
- **Знакомые классы для сверки:** например, DtoStructureSniff, ServiceStructureSniff, EnumStructureSniff, CrossModuleDomainRule, ServiceContractDependencyRule.
- **Текущее поведение:** метрики не собираются; инструменты не установлены.
- **Границы (Out of Scope):**
  - Не пишем агрегатор (отдельная задача TASK-metrics-aggregator).
  - Не меняем код пакета.
  - Не настраиваем Deptrac (отдельная задача TASK-metrics-module-boundaries-deptrac).
  - Не оставляем в composer.json dev-зависимости невыбранных кандидатов.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Прогон каждого кандидата на src/ пакета с JSON-выводом.
- [ ] Сверка LCOM, Ca, Ce, LOC на 3–5 знакомых классах (совпадение/расхождение значений между инструментами).
- [ ] Оценка JSON-выхода: наличие полей class-level модели (LOC/LLOC, LCOM, Ca, Ce, CC, количество методов и свойств).
- [ ] Выбор инструмента-основы с обоснованием; решение зафиксировано в задаче и эпике.
- [ ] Удаление из composer.json dev-зависимостей невыбранных кандидатов (остаётся только основа).

### 🟡 Should Have (Желательно)

- [ ] Проверка поддержки PHP 8.4 (атрибуты, readonly) — отсутствие пропусков файлов и падений.
- [ ] Оценка активности и лицензии кандидатов (риск долгосрочной поддержки).

### ⚫ Won't Have (Не будем делать)

- [ ] Не выбираем инструмент без прогона на этом коде (только по README).
- [ ] Не пишем агрегатор и дашборд в этой задаче.
- [ ] Не меняем существующий код и конфиги пакета.

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)

- [ ] Таблица сравнения кандидатов (метрики, JSON-поля, PHP 8.4, лицензия) в комментариях задачи.
- [ ] Выбран инструмент-основа; решение отражено в разделе Comments и в эпике.
- [ ] composer.json: оставлена только dev-зависимость основы (и запасной, если решено).
- [ ] Команды прогона задокументированы (Comments / Verification).
- [ ] `composer validate-todo` проходит.

## 6. Verification (Самопроверка)

```bash
composer require --dev phpmetrics/phpmetrics
vendor/bin/phpmetrics --report-json=var/metrics/phpmetrics.json src

composer require --dev pdepend/pdepend
vendor/bin/pdepend --summary-xml=var/metrics/pdepend.xml src

composer require --dev php-code-archeology/php-code-archeology
vendor/bin/phpcodearcheology --report-type=json --report-dir=var/metrics/code-archeology src
```

## 7. Risks and Dependencies (Риски и зависимости)

- PhpMetrics/PDepend могут не парсить отдельные конструкции PHP 8.4 — файлы могут выпадать из анализа (сверить количество классов в отчётах с фактическим).
- Расхождение LCOM между инструментами — ожидаемо (разные определения); критерий выбора — воспроизводимость и соответствие определениям (например, LCOM* Henderson-Sellers).
- PhpCodeArcheology — молодой инструмент; его LCOM сверять с PhpMetrics/PDepend перед доверием.
- В composer.json `minimum-stability: stable` — если кандидат доступен только в dev-ветках, это аргумент против него.
- Зависимости: нет (входная задача эпика).

## 8. Sources (Источники)

- [PhpMetrics — описание метрик](https://github.com/phpmetrics/PhpMetrics/blob/master/doc/metrics.md)
- [PDepend — метрики](https://pdepend.org/documentation/software-metrics/index.html)
- [Deptrac — документация](https://deptrac.github.io/deptrac/)
- [PhpCodeArcheology — GitHub](https://github.com/PhpCodeArcheology/PhpCodeArcheology)
- Эпик EPIC-metrics-ai-maintainability

## 9. Comments (Комментарии)

- Рекомендация из обсуждения: PhpCodeArcheology + Deptrac — быстрый эксперимент; PhpMetrics/PDepend + Deptrac + агрегатор — контролируемая система. Задача выбирает между ними по фактам прогона.
- Гипотеза автора: метрики должны покрывать cohesion, coupling, размер и количество компонентов (пара класс-метод / модуль-класс).

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи. |
