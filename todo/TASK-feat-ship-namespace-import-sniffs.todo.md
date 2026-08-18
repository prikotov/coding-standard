---
type: feat
created: 2026-08-18 14:03:11 (1787061791)
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
author: Бэкендер (pi)
assignee: 
branch: task/session-analysis-todo-backlog
pr: 
status: todo
---

# TASK-feat-ship-namespace-import-sniffs: PHPCS: включить ReferenceUsedNamesOnly и DisallowGroupUse в ruleset

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Агенты пишут FQCN с ведущим `\` в теле кода вместо `use` (`new \Task\Web\...\SendResponseDto(...)` в TasK, 30.07; замыкание с `\Task\Common\...\ProjectSourceModel` в Stocks2/TasK, 03.08) и group-use (`use Foo\{Bar, Baz};`, stocks2, 18.07) — замечания повторялись минимум трижды за месяц.
- Ответ агента на вопрос «у нас разве нет на это проверок?» (03.08): «PHPCS не запрещает FQCN в теле кода — проверки нет». В тот же день в TasK её включили локально (коммит `10f33abcc chore(phpcs): require imported class names`: `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly` в `phpcs.xml.dist`).
- Но в пакете `prikotov/coding-standard` сниффы не подключены: `ruleset.xml` не содержит ни одного Slevomat-правила (хотя `slevomat/coding-standard` — зависимость пакета). stocks2 и task-orchestrатор проверки не получили вовсе, каждый потребитель решает сам.

### Варианты или путь решения (Solution Sketch)
- Включить в `ruleset.xml` пакета:
  - `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly` — запрет ведущего `\` FQCN в теле кода; свойства как в TasK: `allowFullyQualifiedGlobalClasses=true`, `allowFullyQualifiedExceptions=true`, `allowFullyQualifiedNameForCollidingClasses=true` (глобальные классы, исключения и коллизии имён — допустимы);
  - `SlevomatCodingStandard.Namespaces.DisallowGroupUse` — запрет `use Foo\{...};`.
- Покрыть тестом сниффов (по образцу существующих `bin/run-sniff-tests.php`) — фикс-зона: упрощённые кейсы FQCN/group-use.
- Проверить на TasK (где ReferenceUsedNamesOnly уже локально включён — регрессии быть не должно) и на stocks2/task-orchestrator: замерить количество новых нарушений, не править их в этой задаче.

### Ожидаемый результат (Expected Result)
- Все потребители пакета получают запрет FQCN-в-теле и group-use «из коробки» через `ruleset.xml`.
- Повторяющиеся замечания категории «почему не используешь use?» уходят в автоматическую проверку.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как владелец конвенций, я хочу, чтобы стиль импортов проверялся пакетом для всех потребителей, а не настраивался в каждом проекте руками после моих замечаний на ревью.

### Цель по SMART
- До конца задачи: оба правила в `ruleset.xml` со свойствами, тест сниффов зелёный, проверено на трёх потребителях (счётчики нарушений зафиксированы в задаче).

## 2. Контекст и Границы (Context and Scope)
- Затрагиваемые файлы: `ruleset.xml`, тест сниффов (новый), при необходимости `docs/conventions/principles/code-style.md` (пункт про импорты).
- Зависимость `slevomat/coding-standard` уже в `composer.json` пакета — новых зависимостей нет.
- В TasK правило уже включено локально с теми же свойствами (коммит `10f33abcc`, 03.08) — берём его конфигурацию как эталон.
- Out of scope: чистка существующих FQCN/group-use нарушений в потребителях; прочие Slevomat-правила (`FullyQualifiedExceptions`, `UseOnlyWhitelistedNamespaces` и т.п.); настройка excludes вида `*/config/*`, `*/migrations/*` (остаётся на совести потребителя, как в TasK).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] `ReferenceUsedNamesOnly` в `ruleset.xml` с тремя свойствами-разрешениями (эталон — TasK `10f33abcc`).
- [ ] `DisallowGroupUse` в `ruleset.xml`.
- [ ] Тест сниффов: кейс FQCN-в-теле ловится, `use`-импорт — нет; group-use ловится.
### 🟡 Желательно (Should Have)
- [ ] Пункт в `docs/conventions/principles/code-style.md` (импорты: `use` вместо ведущего `\`, без группировки).
### ⚫ Won't Have (Не будем делать)
- Массовый фикс существующих нарушений в потребителях.

## 4. План реализации (Implementation Plan)
1. [ ] Добавить оба правила в `ruleset.xml`.
2. [ ] Добавить кейсы в тесты сниффов (`bin/run-sniff-tests.php`), запустить `composer check`.
3. [ ] Проверить на потребителях без правок их файлов: phpcs с новым ruleset — снять счётчики нарушений (TasK — ожидаемо 0 новых, т.к. правило уже включено; stocks2, task-orchestrator — зафиксировать цифры).
4. [ ] Обновить доку по импортам (если пункт про импорты в конвенциях отсутствует).

## 5. Критерии приёмки (Definition of Done)
- [ ] `composer check` зелёный.
- [ ] Тест сниффов красный без правил / зелёный с ними.
- [ ] Счётчики по потребителям зафиксированы в комментариях задачи; рабочие деревья потребителей чистые.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
composer check
```

## 7. Риски и зависимости
- У потребителей, где правило не было включено (stocks2, task-orchestrator), `make check`/phpcs может покраснеть от существующих нарушений — это ожидаемое поведение; устранение — отдельными задачами потребителей (прецедент: TASK-code-quality-enable-strict-coding-standard-sniffs в TasK).
- Свойства `allowFullyQualified*` — компромисс, проверенный TasK; тотальный запрет (`false`) даст шум на глобальных классах (`DateTimeImmutable`, `\Throwable`).
- Релиз должен попасть в потребителей: совместить с задачей обновления `prikotov/coding-standard` в них.

## 8. Источники (Sources)
- Анализ сессий codex/pi за 18.07–18.08.2026: замечания «почему не используешь use?» (TasK, 30.07), «у нас разве нет на это проверок?» + ответ агента (03.08), «use {…} — усложняет чтение» (stocks2, 18.07).
- Эталон конфигурации: TasK `phpcs.xml.dist`, коммит `10f33abcc` (2026-08-03).
- Slevomat sniffs: `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly`, `DisallowGroupUse`.

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-18 14:03:11 (1787061791) | Бэкендер (pi) | Создание задачи |
