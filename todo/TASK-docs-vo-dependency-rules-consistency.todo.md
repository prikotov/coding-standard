---
type: docs
created: 2026-11-28 02:53:57 (1795834437)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Лид Арагорн (pi)
assignee: 
branch: 
pr: 
status: todo
---

# TASK-docs-vo-dependency-rules-consistency: VO: согласовать правила зависимостей между value-object.md, deptrac и ValueObjectStructureSniff

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В v0.35.0 три источника правил для объектов-значений (Value Object, VO) говорят о зависимостях VO от сущностей разное:
  - **deptrac** (шаблон `config/deptrac/depfile.yaml`, ruleset `DomainVo`): VO разрешено зависеть от слоя `Domain` (сущности), `DomainException`, `DomainEnum`, `DomainVo` — без различия, идёт ли речь о сигнатурах методов или о свойствах;
  - **PHPCS-сниффер** `ValueObjectStructureSniff` (`isAllowedDependencyFqcn`): тип с сегментом `Entity` в пути запрещён — комментарий в коде: «Domain\Entity (ORM), Domain\Service and similar are not»; проверяются типы свойств;
  - **конвенция** `docs/conventions/core-patterns/value-object.md`, раздел «Зависимости»: перечислены разрешённые типы (примитивы, `DateTimeImmutable`, `Uuid`, `Enum`, другие VO того же слоя, общие типы) и запрещённые (Embeddable VO, Infrastructure, ORM/DB, SDK, DI, другие модули и слои) — **сущности не упомянуты ни как разрешённые, ни как запрещённые**.
- Проект-потребитель (stocks2, обновление на v0.35.0, архитектурная консультация 2026-11-28) не может однозначно ответить, разрешена ли зависимость VO от сущности своего модуля: инструменты дают разные ответы, документ — никакого.

### Варианты или путь решения (Solution Sketch)
- Подтвердить каноничное намерение и привести три источника в соответствие. Рабочая гипотеза (по устройству сниффера): VO **может** зависеть от сущности своего модуля в сигнатурах методов (например, фабрика `createFromEntity(Model $m): self`), но **не может** хранить её в свойствах.
  - Если гипотеза верна: дополнить раздел «Зависимости» `value-object.md` явным правилом (сущности — только в сигнатурах, не в свойствах) и пунктом чек-листа.
  - Если нет: сузить ruleset `DomainVo` в шаблоне `depfile.yaml` (тогда тип задачи меняется на `fix`).

### Ожидаемый результат (Expected Result)
- По вопросу «VO и сущности» конвенция, deptrac и сниффер дают один и тот же ответ; в документе есть явное правило.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как архитектор проекта-потребителя, я хочу, чтобы инструменты и документ пакета отвечали на вопрос о зависимостях VO одинаково, чтобы не выбирать между ними наугад.

### Цель по SMART (Goal)
- К ближайшему релизу пакета раздел «Зависимости» `value-object.md` содержит явное правило про сущности, согласованное с `ValueObjectStructureSniff` и ruleset `DomainVo`; `composer check` зелёный.

## 2. Контекст и Границы (Context and Scope)
- **Где делаем:** `docs/conventions/core-patterns/value-object.md`; при выборе второго варианта — `config/deptrac/depfile.yaml`.
- **Точные места (v0.35.0, master `1849923`):**
  - `config/deptrac/depfile.yaml`, строки 219–223: `DomainVo: [DomainException, Domain, DomainEnum, DomainVo]`;
  - `src/Sniffs/Structure/ValueObjectStructureSniff.php`, метод `isAllowedDependencyFqcn()` (около строк 466–475): любой сегмент `entity` в пути типа → запрет; комментарий «Domain\Entity (ORM), Domain\Service and similar are not»;
  - `value-object.md`, раздел «Зависимости» — пять пунктов, сущности не упомянуты.
- **Границы (Out of Scope):** изменение логики сниффера (кроме случая, когда намерение окажется иным, — тогда отдельная задача); правила для других слоёв.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Решение зафиксировано в Комментариях: VO и сущности своего модуля — что разрешено в сигнатурах, что в свойствах.
- [ ] Раздел «Зависимости» и чек-лист `value-object.md` дополнены явным правилом; `composer validate-docs` зелёный.
- [ ] Если правила инструментов не соответствуют решению — приведены в соответствие (или создана задача с точным описанием).
- [ ] `composer check` зелёный; изменение отражено в CHANGELOG по регламенту релизов.
### ⚫ Won't Have (Не будем делать)
- Пересмотр остальных правил зависимостей VO.

## 4. План реализации (Implementation Plan)
1. [ ] Решение по вопросу (владелец пакета / архитектор).
2. [ ] Правка документа; при необходимости — ruleset; CHANGELOG.
3. [ ] `composer check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Must Have выполнен; формулировка одобрена владельцем пакета.

## 6. Самопроверка (Verification)
```bash
composer check
```

## 7. Риски и зависимости (Risks and Dependencies)
- Сужение ruleset `DomainVo` — несовместимое изменение для проектов-потребителей: оценить по их опыту (stocks2 после обновления не сообщает нарушений по этому правилу).

## 8. Источники (Sources)
- stocks2: обновление до v0.35.0 (ветка `task/coding-standard-v0-35-0`), архитектурная консультация Гэндальфа 2026-11-28, пункт 3.
- Файлы пакета: `config/deptrac/depfile.yaml` (219–223), `src/Sniffs/Structure/ValueObjectStructureSniff.php` (`isAllowedDependencyFqcn`), `docs/conventions/core-patterns/value-object.md`.

## 9. Комментарии (Comments)
- Задача создана по просьбе владельца: замечания по пакету оформлять задачами в этом репозитории.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-28 02:53:57 (1795834437) | Лид Арагорн (pi) | Создание задачи: расхождение правил зависимостей VO между deptrac, сниффером и конвенцией (v0.35.0) |
