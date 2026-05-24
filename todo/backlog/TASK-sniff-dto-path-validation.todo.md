---
type: feat
created: 2026-05-23
value: V2
complexity: C2
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Dev (Pi)
assignee:
branch:
pr:
status: backlog
---

# TASK-sniff-dto-path-validation: Валидация путей DTO для всех слоёв

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- DtoStructureSniff проверяет структуру DTO (final readonly, нет методов), но не проверяет, лежат ли DTO в правильных директориях.
- Агенты могут разместить DTO в произвольной папке, и sniff это пропустит.
- Для Domain-слоя проверка уже добавлена (запрет `Domain/Dto/`), но Application, Infrastructure, Integration и Presentation не покрыты.

### Варианты или путь решения (Solution Sketch)
- Добавить в DtoStructureSniff проверку путей DTO по конвенции для каждого слоя.
- Использовать существующий подход: анализировать нормализованный путь файла и сопоставлять с разрешёнными паттернами.

### Ожидаемый результат (Expected Result)
- DTO вне разрешённых путей вызывают ошибку sniff с указанием правильного расположения.
- Агенты не могут разместить DTO в произвольной директории без замечания.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
> Как разработчик, я хочу чтобы sniff автоматически проверял расположение DTO в правильных namespace, чтобы агенты не размещали DTO в произвольных директориях.

### Goal (Цель по SMART)
- **S:** Реализовать валидацию путей DTO в DtoStructureSniff для слоёв Application, Infrastructure, Integration.
- **M:** Неверные пути вызывают ошибку sniff, правильные проходят. Тест-фикстуры покрывают каждый слой.
- **A:** Механизм уже работает для Domain — расширить подход на остальные слои.
- **R:** Предотвращает некорректное размещение DTO агентами.
- **T:** Одна задача, ограниченный scope.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `src/Sniffs/Structure/DtoStructureSniff.php`
- **Текущее поведение:** Sniff проверяет структуру DTO и путь для Domain (запрет `Domain/Dto/`). Остальные слои не проверяются.
- **Границы (Out of Scope):** Domain DTO — уже реализовано. Presentation DTO — отдельно (apps/, другая структура путей).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Application DTO: `{Module}\Application\Dto\{Name}Dto` или `{Module}\Application\UseCase\{Command|Query}\{Case}\{Name}Dto`
- [ ] Integration DTO: `{Module}\Integration\Component\{Component}\Dto\{Name}Dto`
- [ ] Infrastructure DTO: `{Module}\Infrastructure\Component\{Component}\Dto\{Name}Dto`
- [ ] Общие DTO: `Common\Application\Dto\{Name}Dto`

### 🟡 Should Have (Желательно)
- [ ] Presentation DTO: `apps/.../Dto/` (Request DTO, Query DTO, Response DTO)

### ⚫ Won't Have (Не будем делать)
- [ ] Domain DTO — уже реализовано
- [ ] Миграция существующих DTO — только sniff

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)
- [ ] Неверные пути DTO ловятся ошибкой для каждого слоя
- [ ] Правильные пути проходят без ошибок
- [ ] Тест-фикстуры для каждого слоя
- [ ] `composer check` пройден

## 6. Verification (Самопроверка)
```bash
composer check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Может потребоваться обновление `phpstan-baseline.neon`
- Presentation DTO живут в `apps/` — sniff работает с `src/Module/`, может потребоваться расширение

## 8. Sources (Источники)
- [dto.md](../../docs/conventions/core-patterns/dto.md)

## 9. Comments (Комментарии)
Доменные DTO уже проверяются: `assertDomainDtoPath` в DtoStructureSniff запрещает `Domain/Dto/`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-23 | Dev (Pi) | Создание задачи |
