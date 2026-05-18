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

# TASK-feat-value-object-structure-sniff: ValueObject Structure Sniff

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> Как разработчик, я хочу чтобы PHPCS автоматически проверял структуру ValueObject, чтобы конвенция `value-object.md` соблюдалась механически.

### Goal (Цель по SMART)
Создать `ValueObjectStructureSniff` — аналог `DtoStructureSniff` для ValueObject. Проверять классы с суффиксом `Vo` в namespace `Domain\ValueObject\*`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Sniffs/Structure/ValueObjectStructureSniff.php`, `tests/Sniffs/Structure/`
*   **Текущее поведение:** Есть сниффы для DTO и Enum. ValueObject описан в конвенциях, но автоматических проверок нет.
*   **Границы (Out of Scope):** Не проверяем Enum, DTO, Entity.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Проверка `final readonly class`
- [x] Проверка promoted `readonly`-свойств, пустое тело конструктора
- [x] Запрет `const`, `use` (traits)
- [x] Проверка разрешённых методов: геттеры, предикаты, фабричные, `__toString()`
- [x] Проверка namespace: `Domain\ValueObject\`
- [x] Тесты
- [x] Регистрация в `ruleset.xml`
### 🟡 Should Have (Желательно)
- [x] Обновить `docs/conventions/core-patterns/value-object.md` — ссылка на снифф

## 4. Implementation Plan (План реализации)
1. [x] Создать `ValueObjectStructureSniff`
2. [x] Создать тесты
3. [x] Зарегистрировать в `ruleset.xml`
4. [x] Обновить конвенцию

## 5. Definition of Done (Критерии приёмки)
- [x] Снифф проверяет структуру VO
- [x] Тесты проходят
- [x] `composer check` проходит
- [x] Конвенция обновлена

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от конвенции: `docs/conventions/core-patterns/value-object.md`
- Аналог: `src/Sniffs/Structure/DtoStructureSniff.php`

## 8. Sources (Источники)
- Конвенция: `docs/conventions/core-patterns/value-object.md`
- Аналог: `src/Sniffs/Structure/DtoStructureSniff.php`

## 9. Comments (Комментарии)
Разрешённые типы свойств: primitives, `\DateTimeImmutable`, `\UnitEnum`, другие VO / Enum из того же модуля.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | pi | Создание задачи |
| 2026-05-18 | pi | Конвертация в формат todo-md, статус done |
