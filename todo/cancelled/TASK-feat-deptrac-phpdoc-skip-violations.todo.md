---
type: feat
created: 2026-05-06
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: pi
assignee:
branch:
pr:
status: cancelled
---

# TASK-feat-deptrac-phpdoc-skip-violations: Deptrac PHPDoc-скипы нарушений

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> Как разработчик, я хочу помечать допустимые Deptrac-нарушения через `@deptrac-skip` в PHPDoc, чтобы не исключать весь файл через `exclude_files`.

### Goal (Цель по SMART)
Добавить в пакет Deptrac subscriber для PHPDoc-тега `@deptrac-skip`, превращающий matching `Violation` в `SkippedViolation`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Deptrac/`, `config/deptrac/depfile.yaml`
*   **Текущее поведение:** Только нативный `skip_violations` в YAML — скип рядом с кодом невозможен.
*   **Границы (Out of Scope):** Wildcard `*` в `@deptrac-skip` — только если явно безопасно.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Deptrac subscriber для `@deptrac-skip` тега
- [ ] Matching FQCN → `SkippedViolation`
- [ ] Unit-тесты на positive/negative scenarios
### 🟡 Should Have (Желательно)
- [ ] Документация: когда допустим `@deptrac-skip`, обязателен `@techdebt`
### ⚫ Won't Have (Не будем делать)
- [ ] Wildcard `*` в MVP

## 4. Implementation Plan (План реализации)
1. [ ] Добавить `PhpDocSkipViolationSubscriber`
2. [ ] Зарегистрировать в `depfile.yaml`
3. [ ] Покрыть тестами
4. [ ] Обновить документацию

## 5. Definition of Done (Критерии приёмки)
- [ ] `@deptrac-skip Some\Class` работает из PHPDoc depender-класса
- [ ] Нарушение отображается как `Skipped violations`
- [ ] Есть тесты на positive/negative scenarios
- [ ] `composer check` проходит

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Привязка к внутреннему API Deptrac может сломаться при обновлении.

## 8. Sources (Источники)
- Прототип: из проекта-потребителя `TasK`
- Deptrac config: `config/deptrac/depfile.yaml`

## 9. Comments (Комментарии)
**Решение:** Не реализуем. Используем нативный `skip_violations` в `depfile.yaml` — он работает для всех правил, включая кастомные. Если скипов станет много (>10) — пересмотрим.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | pi | Создание задачи |
| 2026-05-18 | pi | Конвертация в формат todo-md, статус cancelled |
