---
type: fix
created: 2026-08-18 13:58:26 (1787061506)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C1
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер (pi)
assignee: 
branch: 
pr: 
status: todo
---

# TASK-fix-deptrac-versioned-namespace-regex: Deptrac: Presentation-коллектор не матчит версии namespace (Api\v1)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В `config/deptrac/depfile.yaml` Presentation-коллектор содержит regex-группу `(?:\\v\\d+)?` — из-за двойного экранирования `\d` она матчит **литеральную** строку `\v\d` (бэкслеш-v-бэкслеш-d), а не «`\v` + цифры».
- Группа опциональна, поэтому не падает с ошибкой, а молча не матчится: namespace `Task\Api\v1\Module\*` не попадает в слой Presentation. Всё API-приложение вне архитектурного контроля — deptrac показывает `Violations: 0`.
- Баг внедрён коммитом `7d429da` (2026-05-18, «support versioned namespaces in Presentation layer») и живёт в consumers, включая v0.29.1 в TasK.
- Эксперимент на живом TasK (18.08): синтетический контроллер `apps/api/src/v1/...` с зависимостью от `Source\Domain\Repository\SourceRepositoryInterface` — текущий depfile: `Violations: 0`; исправленный regex: `DependsOnDisallowedLayer` ×2. `debug:layer Presentation` — 0 классов из `Task\Api\v1\…` (671 из Web/Console/Blog/Docs).
- Это первопричина инцидента 02.08 (замечание «ListController.php — тут нарушены конвенции»): агент тогда зафиксировал «Deptrac rules не блокируют», но regex-баг не нашёл.

### Варианты или путь решения (Solution Sketch)
- Заменить `\\v\\d+` на `\\v\d+` в Presentation-коллекторе (строка 104 depfile.yaml).
- Добавить регрессионный тест на depfile: парсить YAML, матчить тестовые namespace (`Task\Api\v1\Module\X`, `Stocks2\Api\v2\Module\Y`, `Task\Web\Module\Z` и негативные).
- Проверить на живом TasK: слой Presentation собирает классы `Api\v1`, пробный контроллер ловится как violation (файл-зонд удалить, отслеживаемые файлы не менять).

### Ожидаемый результат (Expected Result)
- Классы `*\Api\v1\Module\*` входят в слой Presentation, Presentation→Domain ловится deptrac в проектах с версионированными namespace.
- Регрессионный тест на depfile падает на старом regex и зелёный на новом.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик проекта-потребителя с версионированным namespace (`Task\Api\v1\`), я хочу, чтобы deptrac реально проверял Presentation-слой, а не пропускал всё API-приложение из-за regex-бага.

### Цель по SMART
- До конца задачи: `(?:\\v\d+)?` в Presentation-коллекторе; unit-тест матчит версионированные namespace; проверка на TasK подтверждает сбор ~40 контроллеров `apps/api` в слой и срабатывание DependsOnDisallowedLayer на зонде.

## 2. Контекст и Границы (Context and Scope)
- Затрагиваемые файлы: `config/deptrac/depfile.yaml` (1 строка), новый тест (по образцу `DepfileRootNamespaceRegexTest` из TASK-fix-deptrac-root-namespace-digit-regex).
- Единственное вхождение `\\d` в depfile — это место (проверено grep'ом).
- Проверка потребителей: TasK — без изменения отслеживаемых файлов (временный depfile / файл-зонд с удалением).
- Out of scope: разбор реальных violations, всплывших после фикса в TasK; новые app-namespace'ы; custom-правила (`ServiceContractDependencyRule`, `CrossModuleDomainRule`) — там паттерн версии не участвует.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] `\\v\\d+` → `\\v\d+` в Presentation-коллекторе `config/deptrac/depfile.yaml`.
- [ ] Регрессионный тест на depfile: версионированные namespace (`v1`, `v2`, с корневым префиксом и без) матчатся Presentation-слоем, неверсионированные — тоже, чужие — нет.
### 🟡 Желательно (Should Have)
- [ ] Проверка на живом TasK: `debug:layer Presentation` включает `Task\Api\v1\…`; зонд-контроллер с Domain-репозиторием ловится как violation.
### ⚫ Won't Have (Не будем делать)
- Правка violation'ов проекта-потребителя.

## 4. План реализации (Implementation Plan)
1. [ ] Фикс regex в `config/deptrac/depfile.yaml`.
2. [ ] Регрессионный тест (YAML-парсинг + `preg_match` по значению коллектора).
3. [ ] `composer check` в пакете.
4. [ ] Проверка на TasK через временный depfile + файл-зонд (удалить после), отчитаться цифрами `debug:layer`/violations.

## 5. Критерии приёмки (Definition of Done)
- [ ] `composer check` зелёный.
- [ ] Новый тест красный на старом regex (проверить git stash), зелёный на новом.
- [ ] На TasK: слой Presentation собирает классы `Api\v1`; зонд ловит `DependsOnDisallowedLayer`; рабочее дерево потребителя чистое.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
composer check
```

## 7. Риски и зависимости
- После фикса в TasK ожидаем всплеск реальных violations по API-контроллерам (возможно, включая incident-код 02.08) — правильное поведение, разбирается потребителем отдельной задачей.
- Deptrac кеширует анализ — при проверке на потребителе использовать `--no-cache`.
- Релиз с фиксом должен попасть в потребители (TasK на v0.29.1): совместить с `TASK-build-update-prikotov-packages`.

## 8. Источники (Sources)
- Анализ сессий codex/pi за 18.07–18.08.2026 (TasK, stocks2, task-orchestrator): инцидент 02.08 «ListController нарушает конвенции», ответ агента «Deptrac rules не блокируют».
- Воспроизведение бага 18.08: зонд-контроллер в TasK, `debug:layer Presentation`, фикс через sed-подмену depfile.
- Прецедент: TASK-fix-deptrac-root-namespace-digit-regex (та же природа — тихий regex-баг в depfile).

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-18 13:58:26 (1787061506) | Бэкендер (pi) | Создание задачи |
