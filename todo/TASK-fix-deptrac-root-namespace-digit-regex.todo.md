---
type: fix
created: 2026-08-15 16:31:47 (1786811507)
due: 
started: 2026-08-15 16:32:12 (1786811532)
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
branch: task/fix-deptrac-root-namespace-digit-regex
pr: https://github.com/prikotov/coding-standard/pull/105
status: review
---

# TASK-fix-deptrac-root-namespace-digit-regex: Deptrac: префикс корневого namespace не матчит имена с цифрами (Stocks2)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Общий depfile пакета (`config/deptrac/depfile.yaml`) использует опциональный префикс корневого namespace `^(?:[A-Za-z_]+\\)?` — он не матчит имя с цифрой (`Stocks2\`).
- В проектах с корневым namespace вида `Stocks2\` (`prikotov/stocks2`) deptrac собирал 0 классов во все слои: `Allowed: 0, Violations: 0` — проверка архитектуры в `make check` молча проверяла пустоту.
- Тот же паттерн продублирован в кастомных правилах `ServiceContractDependencyRule` и `CrossModuleDomainRule` и в README пакета.
- Граф метрик валиден — рёбра собирает AST-сборщик, regex тут не участвует.

### Варианты или путь решения (Solution Sketch)
- Заменить `[A-Za-z_]+` на `[A-Za-z_][A-Za-z0-9_]*` (первый символ — буква/подчёркивание, далее буквы/цифры/подчёркивания) во всех местах: depfile, оба PHP-правила, тесты-хелперы, README (en/ru/zh).
- Добавить unit-тесты с корневым namespace с цифрой.

### Ожидаемый результат (Expected Result)
- deptrac в проекте с namespace `Stocks2\` собирает классы в слои и показывает реальные violations.
- Кастомные правила корректно парсят классы с корневым namespace `Stocks2`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя с namespace `Stocks2\`, я хочу, чтобы `deptrac analyse` реально проверял слои, а не молча пропускал всё из-за regex-бага.

### Цель по SMART
- До конца задачи: префикс корневого namespace во всех артефактах пакета матчит PHP-идентификаторы с цифрами; покрыто тестами; проверено на stocks2 (классы собираются) и TasK.

## 2. Контекст и Границы (Context and Scope)

- Затрагиваемые файлы: `config/deptrac/depfile.yaml`, `src/Deptrac/ServiceContractDependencyRule.php`, `src/Deptrac/CrossModuleDomainRule.php`, тесты обоих правил, `config/deptrac/README{,.ru,.zh}.md`.
- Проверка потребителей: stocks2 (реальный кейс), TasK — без изменения отслеживаемых файлов проектов.
- Out of scope: разбор реальных violations, всплывших после фикса в stocks2; поддержка многосегментного корневого namespace (`Acme\Billing\Common\...`); Presentation-alternation новых app-namespace'ов.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `[A-Za-z_]+` → `[A-Za-z_][A-Za-z0-9_]*` во всех consumers паттерна в пакете.
- [x] Unit-тесты: корневой namespace с цифрой (`Stocks2\Common\Module\...`) для обоих правил и depfile-паттернов.
### ⚫ Won't Have (Не будем делать)
- Правка violation'ов проекта-потребителя.

## 4. План реализации (Implementation Plan)
1. [x] Заменить паттерн в `config/deptrac/depfile.yaml` (37 строк, 40 вхождений).
2. [x] Заменить паттерн в `parseModuleClass()` обоих Deptrac-правил.
3. [x] Обновить тестовые хелперы `extractModuleLayer()` и добавить кейсы с `Stocks2\`.
4. [x] Обновить README (en/ru/zh): описание префикса.
5. [x] `composer check` в пакете.
6. [x] Проверить на stocks2 и TasK через временный depfile, импортирующий исправленный конфиг.

## 5. Критерии приёмки (Definition of Done)
- [x] `composer check` зелёный.
- [x] В stocks2 deptrac с фиксом собирает классы: было `Allowed: 0, Violations: 0` → стало `Allowed: 4847, Violations: 17, Uncovered: 3182`.
- [x] В TasK регрессии нет: `Allowed: 12536, Violations: 0` идентичны до/после фикса.
- [x] README'и трёх языков синхронны.
- [x] Дополнительно: регрессионный тест на сам depfile (`DepfileRootNamespaceRegexTest`) — на старом коде 10 падений, на новом 0.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости
- После фикса в проектах с цифровым namespace ожидаем всплеск реальных violations — это правильное поведение, разбирается потребителем отдельно.
- Deptrac кеширует анализ — при проверке на потребителях учитывать `--clear-cache` (по умолчанию deptrac сам сбрасывает при смене конфига).

## 8. Источники (Sources)

- Находка из PR по stocks2 (описание PR + задача-источник).

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-15 16:31:47 (1786811507) | Бэкендер (pi) | Создание задачи |
| 2026-08-15 | Бэкендер (pi) | Реализация: фикс regex, тесты, README, проверка на stocks2/TasK |
