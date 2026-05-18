---
type: feat
created: 2026-05-04
value: V3
complexity: C2
priority: P1
depends_on:
epic:
author: pi
assignee:
branch:
pr:
status: done
---

# TASK-feat-specification-application-layer-convention: Specification — устранить противоречие + накрыть Deptrac

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> Как архитектор, я хочу чтобы конвенции не противоречили друг другу, чтобы разработчики следовали единому подходу.

### Goal (Цель по SMART)
Устранить противоречие между `specification.md` (только Domain) и `application.md` (разрешает Specification напрямую). Накрыть Deptrac-правилом.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/conventions/layers/domain/specification.md`, `docs/conventions/layers/application.md`, `config/deptrac/depfile.yaml`
*   **Текущее поведение:** `specification.md` запрещает Application вызывать Specification, `application.md` разрешает.
*   **Границы (Out of Scope):** Не меняем подход к Specification — только фиксируем «Application идёт через Domain Service».

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Уточнить `specification.md`: Application не вызывает Specification напрямую
- [x] Исправить `application.md`: заменить строку в таблице «Application → Domain»
- [x] Добавить Deptrac-правило: Application → `Domain\Specification\*` запрещено
### 🟡 Should Have (Желательно)
- [x] Добавить пример: Domain Service инкапсулирует Specification

## 4. Implementation Plan (План реализации)
1. [x] Обновить `specification.md`
2. [x] Обновить `application.md`
3. [x] Добавить Deptrac-правило в `depfile.yaml`

## 5. Definition of Done (Критерии приёмки)
- [x] Противоречие устранено
- [x] Deptrac-правило добавлено
- [x] `composer check` проходит

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Deptrac-конфиг: `config/deptrac/depfile.yaml`

## 8. Sources (Источники)
- `docs/conventions/layers/domain/specification.md`
- `docs/conventions/layers/application.md`

## 9. Comments (Комментарии)
Specification — деталь реализации Domain. Application не должен вызывать `isSatisfiedBy()` напрямую. Если нужно бизнес-правило — Application идёт через Domain Service.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | pi | Создание задачи |
| 2026-05-18 | pi | Конвертация в формат todo-md, статус done |
