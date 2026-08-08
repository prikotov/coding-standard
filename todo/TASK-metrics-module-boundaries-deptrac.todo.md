---
type: chore
created: 2026-08-02
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-metrics-ai-maintainability
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-metrics-module-boundaries-deptrac: Границы модулей пакета в Deptrac (класс → модуль) для метрик

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- Для module-level метрик нужна карта «класс → модуль» и межмодульные зависимости.
- Существующий config/deptrac/depfile.yaml — шаблон для consumer-проектов (namespace `Common\Module\...`), к структуре этого пакета не применим.
- Модули пакета (компоненты src/) не определены как коллекции для Deptrac.

### Варианты или путь решения (Solution Sketch)

- Новый конфиг Deptrac для самого пакета (например, config/deptrac/metrics.yaml): слои = модули пакета (src/Sniffs/Structure, src/Sniffs/Application, src/Sniffs/Config, src/Sniffs/Namespaces, src/Language, src/PhpStan, src/Deptrac, src/Config, bin, tests), collector'ы по директориям/namespace.
- Штатный JSON Deptrac содержит только нарушения, пропущенные нарушения и непокрытые зависимости; разрешённые рёбра представлены лишь общим количеством.
- Добавить собственный форматтер Deptrac, который сохраняет полный список разрешённых, запрещённых и непокрытых рёбер с исходным и целевым классом.
- Карту «класс → модуль» строить из закреплённой конфигурации модулей и списка классов сборщика, а не извлекать из диагностического JSON.

### Ожидаемый результат (Expected Result)

- Конфиг с маппингом компонентов пакета; полный JSON-вывод зависимостей через собственный форматтер; команда прогона задокументирована.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)

> Как агрегатор метрик, я хочу машинно-читаемую карту «класс → модуль» и список межмодульных зависимостей, чтобы считать module-level метрики (внутренняя/внешняя связанность, циклы, интерфейс модуля).

### Goal (Цель по SMART)

Создать конфиг Deptrac для самого пакета: модули = компоненты src/ (по модели TASK-metrics-model-convention), собственный форматтер полного графа зависимостей и JSON-вывод в var/metrics/deptrac.json. Прогон проходит без ошибок (нарушения, если есть, зафиксированы и понятны). Существующий config/deptrac/depfile.yaml не меняется.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** config/deptrac/ (новый файл metrics.php или аналогичный), src/ или bin/ (форматтер полного графа), tests/ (тест форматтера), var/metrics/ (вывод).
- **Модули (стартовая гипотеза, уточняется по модели TASK-metrics-model-convention):** src/Sniffs/Structure, src/Sniffs/Application, src/Sniffs/Config, src/Sniffs/Namespaces, src/Language, src/PhpStan, src/Deptrac, src/Config, bin, tests.
- **Текущее поведение:** deptrac для самого пакета не настроен; config/deptrac/depfile.yaml — шаблон consumer-проектов.
- **Границы (Out of Scope):**
  - Не меняем config/deptrac/depfile.yaml (он для consumer-проектов).
  - Не гейтим CI.
  - Не пишем агрегатор (TASK-metrics-aggregator).
  - Не анализируем vendor/.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Конфиг Deptrac с модулями пакета и collector'ами по структуре src/ (bin и tests — по модели TASK-metrics-model-convention).
- [ ] Собственный форматтер на `OutputFormatterInterface` сохраняет все разрешённые, запрещённые и непокрытые зависимости, а не только диагностики штатного JSON.
- [ ] JSON содержит исходный и целевой класс каждого ребра, тип результата и контекст; данных достаточно для инверсии графа и агрегации «модуль → модуль».
- [ ] Маппинг «класс → модуль» детерминированно строится по той же конфигурации модулей для всех классов сборщика, включая классы без рёбер.
- [ ] Команда прогона задокументирована в Verification.

### 🟡 Should Have (Желательно)

- [ ] Сверка: количество классов в отчёте совпадает с фактическим (нет выпавших файлов).
- [ ] Отдельный модуль Tests (или обоснованное исключение) — по модели TASK-metrics-model-convention.

### ⚫ Won't Have (Не будем делать)

- [ ] Не меняем существующий config/deptrac/depfile.yaml.
- [ ] Не добавляем ruleset-ограничения (нужны только зависимости как данные, не enforcement).
- [ ] Не пишем агрегатор; собственный код ограничен форматтером полного графа.

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)

- [ ] Конфиг и форматтер созданы; прогон выдаёт полный JSON зависимостей.
- [ ] Маппинг «класс → модуль» покрывает все классы src/ (сверка по структуре).
- [ ] `composer validate-todo` проходит.

## 6. Verification (Самопроверка)

```bash
vendor/bin/deptrac --config-file=config/deptrac/metrics.php --formatter=metrics-json --output=var/metrics/deptrac.json
```

## 7. Risks and Dependencies (Риски и зависимости)

- Deptrac уже есть в require-dev (deptrac/deptrac ^2.0); собственный форматтер зависит от его программного контракта и требует теста совместимости при обновлении основной версии.
- Namespace пакета (PrikotovCodingStandard\...) не различает модули — collector'ы по директориям (paths) или по полным именам классов.
- tests/ использует фикстуры — включение может дать шум; решение по модели TASK-metrics-model-convention.

## 8. Sources (Источники)

- `config/deptrac/depfile.yaml` — существующий шаблон (не менять)
- [Deptrac — документация](https://deptrac.github.io/deptrac/)
- [Deptrac — штатные форматы](https://deptrac.github.io/deptrac/formatters/)
- [Deptrac — расширение собственным форматтером](https://deptrac.github.io/deptrac/extending_deptrac/#output-formatter)
- TASK-metrics-model-convention — определение модуля

## 9. Comments (Комментарии)

- Модуль для метрик — предметный компонент пакета; в этом пакете это директории src/ (структурные группы сниффов, Language, PhpStan, Deptrac, Config).
- Штатный JSON Deptrac не является полным графом: он раскрывает только диагностические зависимости. Полный граф должен выдавать собственный форматтер.

## `Change History` (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-02 | pi (Pi Coding Agent) | Создание задачи. |
| 2026-08-07 | codex (Codex) | Исправлено предположение о штатном JSON Deptrac; в scope добавлен собственный форматтер полного графа. |
| 2026-08-08 | codex (Codex) | Выполненная TASK-metrics-model-convention удалена из depends_on. |
