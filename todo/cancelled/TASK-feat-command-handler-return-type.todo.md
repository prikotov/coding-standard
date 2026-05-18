---
type: feat
created: 2026-05-06
value: V2
complexity: C1
priority: P3
depends_on:
epic:
author: pi
assignee:
branch:
pr:
status: cancelled
---

# TASK-feat-command-handler-return-type: CommandHandler return type — compute-проекты без БД

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> Как разработчик compute-проекта без БД, я хочу понять, почему CommandHandler возвращает DTO, чтобы не нарушать конвенцию без причины.

### Goal (Цель по SMART)
Оценить, нужно ли менять конвенцию `command-handler.md`, чтобы разрешить возврат DTO из CommandHandler.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/conventions/layers/application/command-handler.md`
*   **Текущее поведение:** Конвенция предписывает `void`, ID или `IdDto` как возвращаемое значение CommandHandler.
*   **Границы (Out of Scope):** Не меняем конвенцию. Не меняем код task-orchestrator.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Провести анализ: является ли возврат DTO из CommandHandler нарушением или допустимой особенностью compute-проектов.
### ⚫ Won't Have (Не будем делать)
- [ ] Изменение конвенции `command-handler.md`

## 4. Implementation Plan (План реализации)
1. [x] Проанализировать CQRS-контекст
2. [x] Сравнить с альтернативой Store + Command/Query
3. [x] Принять решение

## 5. Definition of Done (Критерии приёмки)
- [x] Решение принято и документировано
- [x] Конвенция остаётся без изменений
- [x] В коде task-orchestrator используется `phpcs:ignore` с комментарием

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- При появлении web API с асинхронным запуском цепочек — пересмотреть подход.

## 8. Sources (Источники)
- Конвенция: `docs/conventions/layers/application/command-handler.md`

## 9. Comments (Комментарии)
**Итог:** Конвенция `command-handler.md` **остаётся без изменений**. Возврат `void`, ID или `IdDto` — правильное правило для CRUD+БД проектов.

В task-orchestrator все 3 CommandHandler возвращают DTO — это не исключение, а системная особенность compute-проекта без БД.

Решение: `phpcs:ignore` в коде с комментарием.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | pi | Создание задачи |
| 2026-05-18 | pi | Конвертация в формат todo-md, статус cancelled |
