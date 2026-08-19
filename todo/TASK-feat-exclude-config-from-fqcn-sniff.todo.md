---
type: feat
created: 2026-08-19 04:16:39 (1787112999)
due: 
started: 2026-08-19 04:17:25 (1787113045)
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер (pi)
assignee: Бэкендер (pi)
branch: task/exclude-config-from-fqcn-sniff
pr: https://github.com/prikotov/coding-standard/pull/113
status: review
---

# TASK-feat-exclude-config-from-fqcn-sniff: PHPCS: exclude config and migrations from ReferenceUsedNamesOnly

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После #112 (ReferenceUsedNamesOnly в `ruleset.xml` пакета) «сырой» прогон на stocks2 даёт 15 нарушений, из них 12 — в `apps/*/config/*`: Symfony-конфиги (`bundles.php`, `modules.php`) конвенционально пишутся FQCN-ключами без `use`.
- В эталонной конфигурации TasK (коммит `10f33abcc`) эти каталоги исключены прямо на правиле (`exclude-pattern`), но в пакет excludes не перенесли — вопрос был осознанно вынесен из scope #112 на совесть потребителя.

### Варианты или путь решения (Solution Sketch)
- Добавить `exclude-pattern` `*/config/*` и `*/migrations/*` на `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly` в `ruleset.xml` пакета — конфигурация становится полной копией эталона TasK, потребители не повторяют одну и ту же правку.

### Ожидаемый результат (Expected Result)
- FQCN-проверка «из коробки» не шумит на Symfony-конфигах; замер на stocks2 падает с 15 до 3 (остаются реальные нарушения в `tests/Integration`).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как потребитель пакета, я хочу, чтобы FQCN-проверка не требовала докрутки exclude-паттернов для конфигов, которые по природе пишутся FQCN.

### Цель по SMART (Goal)
- До конца задачи: оба exclude-паттерна в `ruleset.xml`, фикстура в `config/`-пути проходит без ошибок, замер stocks2 — 3 нарушения вместо 15.

## 2. Контекст и Границы (Context and Scope)
- Затрагиваемые файлы: `ruleset.xml`, `tests/namespaces-import-fixtures.php` + фикстура в `tests/Namespaces/config/`, `docs/conventions/principles/code-style.md`.
- Продолжение #112 / TASK-feat-ship-namespace-import-sniffs (done).
- Out of scope: чистка существующих нарушений у потребителей; изменения `DisallowGroupUse` (group-use в конфигах не встречается); другие exclude-паттерны.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `exclude-pattern` `*/config/*` и `*/migrations/*` на `ReferenceUsedNamesOnly` в `ruleset.xml`.
- [x] Фикстура снифф-теста в пути `*/config/*` (FQCN в Symfony-конфиге) — без ошибок.
- [x] Пункт в `docs/conventions/principles/code-style.md` про исключение конфигов и миграций.
### ⚫ Won't Have (Не будем делать)
- Правка файлов потребителей.

## 4. План реализации (Implementation Plan)
1. [x] Добавить exclude-паттерны в `ruleset.xml` (порядок — как в эталоне TasK: exclude-pattern до properties).
2. [x] Фикстура `tests/Namespaces/config/` + запись в `tests/namespaces-import-fixtures.php`; `composer check`.
3. [x] Повторный замер на stocks2 (read-only): ожидаемо 3 вместо 15.
4. [x] Обновить пункт в `code-style.md`.

## 5. Критерии приёмки (Definition of Done)
- [x] `composer check` зелёный.
- [x] Замер stocks2 — 3 нарушения (только `tests/Integration`), дерево потребителя чистое.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Потребитель, желающий проверять FQCN внутри `config/`, не сможет снять exclude пакета локально (ограничение PHPCS для включённых стандартов) — сценарий признан маловероятным; при необходимости правило отключается и включается напрямую в конфиге потребителя.

## 8. Источники (Sources)
- Эталон: TasK `phpcs.xml.dist`, коммит `10f33abcc` (2026-08-03).
- Замеры из TASK-feat-ship-namespace-import-sniffs (todo/done/): stocks2 — 15 (12 в config), task-orchestrator — 13, TasK — 0 при собственных excludes.

## 9. Комментарии (Comments)

- Замер stocks2 после excludes (read-only, два правила из #112): было 15 → стало 3 — все в `tests/Integration/Module/TInvest/*`; конфиги `apps/*/config/*` больше не шумят. Дерево потребителя чистое.
- Фикстура `tests/Namespaces/config/ReferenceUsedNamesOnlyConfigExcludedUnitTest.inc` (FQCN-ключ без `use`) проходит с 0 ошибок — исключение подтверждено и в харнессе снифф-тестов.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-19 04:16:39 (1787112999) | Бэкендер (pi) | Создание задачи |
| 2026-08-19 | Бэкендер (pi) | Реализация: excludes в `ruleset.xml`, фикстура, дока; замер stocks2 15 → 3 |
