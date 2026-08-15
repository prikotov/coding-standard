---
type: fix
created: 2026-08-16 00:05:00 (1786813500)
due: 
started: 2026-08-16 00:05:00 (1786813500)
completed: 
cancelled: 
value: V2
complexity: C2
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Левша (pi)
assignee: Бэкендер Левша (pi)
branch: task/fix-deptrac-custom-rules-skip-violations
pr: https://github.com/prikotov/coding-standard/pull/107
status: review
---

# TASK-fix-deptrac-custom-rules-skip-violations: Кастомные правила Deptrac игнорируют skip_violations потребителя

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `CrossModuleDomainRule` и `ServiceContractDependencyRule` добавляют нарушения через `new Violation(...)` напрямую, минуя `EventHelper`.
- Поэтому дептрак-конфиг `skip_violations` проекта-потребителя на них не действует: нарушения остаются, а заведённые под них skip-записи дают ошибку `Skipped violation ... was not matched`.
- Случай из `prikotov/stocks2`: после фикса regex корневого namespace (v0.29.2) проверка стала реальной, 28 нарушений нельзя погасить штатным механизмом — 11 из них создаются кастомными правилами.

### Варианты или путь решения (Solution Sketch)
- Внедрить `EventHelper` в оба правила и заменить прямое `addRule(new Violation(...))` на `addSkippableViolation(...)`: совпавшая пара превращается в `SkippedViolation`, остальные остаются нарушениями.
- В общем `config/deptrac/depfile.yaml` включить `autowire: true` для обоих правил: секция `services:` депфайла грузится отдельным loader'ом без контейнерных defaults, иначе аргумент конструктора не разрешится.
- `MetricsJsonOutputFormatter` уже размечает `SkippedViolation` как `skipped` — метрики не меняются.

### Ожидаемый результат (Expected Result)
- `skip_violations` в депфайле потребителя гасит и нарушения кастомных правил: `Violations: 0`, `Skipped violations: N`, `Errors: 0`.
- Правила без skip-записей работают как раньше.

## 1. Концепция и Цель (Concept and Goal)

### Story (User Story)
> Как сопровождающий проект-потребитель, я хочу гасить известные нарушения кастомных правил через штатный `skip_violations`, чтобы фиксировать техдолг явно и убирать записи по мере исправления.

### Goal (Цель по SMART)
Кастомные правила учитывают `skip_violations` через `EventHelper::addSkippableViolation`; поведение покрыто unit-тестами обоих правил; проверено на потребителе (`prikotov/stocks2`): 28 skip-записей → 0 violations, 0 errors.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/Deptrac/CrossModuleDomainRule.php`, `src/Deptrac/ServiceContractDependencyRule.php`, `config/deptrac/depfile.yaml`, `tests/Deptrac/*Test.php`.
*   **Границы (Out of Scope):** семантика правил, формат метрик, README-примеры (механика skip уже описана в документации deptrac).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Must Have (Обязательно)
- [x] Оба правила уважают `skip_violations` (через `EventHelper`).
- [x] Общий depfile пакета корректно резолвит `EventHelper` в правилах (`autowire: true`).
- [x] Unit-тесты: skip-пара → `SkippedViolation`, без skip → `Violation`.
- [x] `composer check` зелёный.

### 🟡 Should Have (Желательно)
- [x] Проверка на реальном потребителе (stocks2): Violations 0 / Skipped 28 / Errors 0.

### 🟢 Could Have (Опционально)
- [ ] Нет требований.

### ⚫ Won't Have (не будем делать)
- [ ] Не меняем логику правил и состав допустимых путей.

## 4. План реализации (Implementation Plan)
1. [x] Ветка `task/fix-deptrac-custom-rules-skip-violations`.
2. [x] `EventHelper` в конструкторы, `addSkippableViolation` вместо прямого `Violation`.
3. [x] `autowire: true` для обоих правил в `config/deptrac/depfile.yaml`.
4. [x] Тесты: `testCrossModuleDependencyHonorsSkipViolations`, `testServiceContractDependencyHonorsSkipViolations`; фабрика `createRule()` в тестах.
5. [x] `composer check` пакета.
6. [x] Ручная проверка на `prikotov/stocks2` (подмена vendor, прогон deptrac).

## 5. Критерии приёмки (Definition of Done)
- [x] `composer check` зелёный.
- [x] На потребителе skip-записи кастомных правил гасятся без ошибок `was not matched`.

## 6. Самопроверка (Verification)
```bash
composer check
```

## 7. Риски и зависимости
- `EventHelper` помечен `@internal` в deptrac — при мажорном обновлении deptrac может измениться; риск принимаем, класс стабилен в ветке 2.x и уже используется ядровыми обработчиками.
- `autowire: true` в services-секции депфайла работает в deptrac 2.x (loader Symfony DI); при смене схемы депфайла нужно перепроверить.

## 8. Источники
- [ ] `Qossmic\Deptrac\Contract\Analyser\EventHelper::addSkippableViolation()` (vendor, deptrac 2.x).

## 9. Комментарии
- Нарушения, которые потребитель гасит skip'ом, остаются видимыми в `Skipped violations` — регресс не скрывается.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-16 | Бэкендер Левша (pi) | Создание задачи |
| 2026-08-16 | Бэкендер Левша (pi) | Переведена в review, создан PR #107 |
