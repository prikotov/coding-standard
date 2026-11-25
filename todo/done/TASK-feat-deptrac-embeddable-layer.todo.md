---
type: feat
created: 2026-11-25 05:56:08 (1795586168)
due: 
started: 2026-11-25 05:56:08 (1795586168)
completed: 2026-11-25 10:03:40 (1795601020)
cancelled: 
value: V3
complexity: C3
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Лид Арагорн (pi)
assignee: Лид Арагорн (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/deptrac-embeddable-layer
pr: https://github.com/prikotov/coding-standard/pull/139
status: done
---

# TASK-feat-deptrac-embeddable-layer: Deptrac: выделить Embeddable VO в слой DomainEmbeddable и изолировать от Application/Presentation

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Embeddable VO (`Domain\Entity\ValueObject\*Vo`) — persistence-часть сущности (конвенция [Entity](../../docs/conventions/layers/domain/entity.md)), но в поставляемом depfile они попадали в общий слой `DomainVo` через regex `Domain\(Entity\\)?ValueObject\\…` и были доступны везде, где `DomainVo`. В TasK 4 Embeddable утекли в **25+ файлов**: Application-сервисы и их интерфейсы, мапперы, CommandHandler, Domain Strategy.
- Сниффер ловит только типы **свойств** VO; утечка идёт через параметры/возвраты/контракты — нужен архитектурный gate.
- ❗ При реализации вскрылась **вторая, более крупная дыра**: `Application: [+ApplicationCommandHandler]`. В Deptrac 4 `+Layer` — **транзитивное замыкание**: весь Application-слой наследовал все зависимости handler'ов (`Domain`, `DomainEnum`, `DomainVo`, `Presentation`…). Поэтому violation'ы на `Application → Domain` не выдавались вообще, а слой `Domain` в ruleset для `Application` был прописан пустым (`Application: [Application]`) — всё держалось на `+`. В TasK это скрывало 82 прямые зависимости мапперов/сервисов на сущности.

### Варианты или путь решения (Solution Sketch)
- Embeddable → отдельный слой `DomainEmbeddable`, изолированный как сущности: доступен `Domain` (сущность владеет своей частью) и тем, кто работает с сущностями через `Domain`; запрещён `DomainVo`, handler'ам, Application, Presentation.
- Убрать `+` у handler'ов; правила `Application` прописать явно: DTO/Enum/VO/Specification своего и Domain-слоёв + **доменные контракты** (новый слой `DomainContract` — интерфейсы `Domain\Service\…Interface`, `Repository`, `Strategy`, `Provider`, `Criteria`, `Helper`), **без** `Domain\Entity` (сущности — только через handler'ы/репозитории).
- Закрепить **семантику** ruleset регрессионным тестом, запускающим реальный deptrac на генерируемых фикстурах (regex-тесты это не ловят).

### Ожидаемый результат (Expected Result)
- На TasK: все утечки Embeddable и прямые зависимости Application на сущности видны как violations; ложных нет; базовые сценарии (handler работает с сущностью, сущность владеет Embeddable, сервис вызывает доменный интерфейс) — чисто.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как архитектор, я хочу, чтобы deptrac реально изолировал persistence-типы (сущности, Embeddable) от Application/Presentation, чтобы gate не был дырявым, а конвенция Entity/VO — проверяемой.

### Цель по SMART (Goal)
- К ближайшему релизу: слой `DomainEmbeddable` выделен; `+` в ruleset не используется; правила Application явные; семантика закреплена тестом на реальном deptrac; на TasK остаются только истинные нарушения.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `config/deptrac/depfile.yaml` (слои `DomainEmbeddable`, `DomainContract`; `Domain` must_not; ruleset `Application`, handler'ы, все, кто видел `Domain`); `tests/Deptrac/DepfileRulesetSemanticsTest.php` (новый).
- **Аудит на TasK (2026-11-25, deptrac 4.7.2, кэш очищен):** базeline 0 violations (из-за `+`); после: 151, из них 82 — Application Mapper/Service/Factory → `Entity\*Model` (истинные), 36 — утечки Embeddable, 11 — сервис диспатчит `ApplicationCommand` напрямую, 2 — `DomainVo → DomainContract`. Ложных нет.
- **Границы (Out of Scope):** рефакторинг утечек в TasK (TasK#3221 + отдельные задачи); поведение `imports` (пути импортированного depfile резолвятся относительно импортирующего файла, а `paths` суммируются — задокументировано в тесте).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Слой `DomainEmbeddable` (`Domain\Entity\ValueObject\…Vo`); исключён из `Domain` и `DomainVo`.
- [x] `DomainEmbeddable` доступна `Domain` (сущность владеет), запрещена `DomainVo`, handler'ам, `Application`, `Presentation`.
- [x] `+Layer` не используется в ruleset; правила `Application` прописаны явно.
- [x] Слой `DomainContract` (доменные интерфейсы без `*Model`/`*Entity`) разрешён `Application`, handler'ам, `Domain`, `Integration`; запрещён VO/DTO/Specification.
- [x] Регрессионный тест на реальном deptrac: Embeddable/Entity/Contract сценарии.
- [x] `composer check` зелёный.

### ⚫ Won't Have (Не будем делать)
- Изменения в TasK; смягчение правил через `skip_violations` в поставляемом depfile.

## 4. План реализации (Implementation Plan)
1. [x] Воспроизвести: reproducer внутри пакета (фикстуры вне `tests/` — depfile исключает `#.*tests.*#`).
2. [x] Выделить `DomainEmbeddable`, убрать `\(Entity\\)?` из `DomainVo`.
3. [x] Найти причину пропуска `Application → Domain`: транзитивное замыкание `+ApplicationCommandHandler` (`vendor/deptrac/…/LayerProvider.php:44`).
4. [x] Убрать `+`, прописать `Application` явно; ввести `DomainContract`.
5. [x] Тест `DepfileRulesetSemanticsTest` (реальный deptrac, json-formatter, фикстуры в tempdir, `cache_file` в tempdir).
6. [x] Прогон на TasK с чистым кэшем — разбор 151 нарушения, все истинные.

**Заметки по реализации:**
- Reproducer в TasK не сработал (TasK#3221): причина была не в окружении, а в `+`. Первые прогоны на фикстурах в `tests/fixtures/` давали 0/0 из-за `exclude_files: '#.*tests.*#'` — фикстуры теста живут в `sys_get_temp_dir()`.
- Deptrac кладёт токен во **все** матчящиеся слои; violation считается по каждому. Поэтому `DomainContract` исключён из `Domain` (`must_not`), а классы с суффиксами `*Model`/`*Entity` — из `DomainContract`.
- Имена фикстур должны соответствовать конвенции (`*CommandHandler$`), иначе класс не попадает в слой handler'а.
- Зависит от PR #138 (обновление зависимостей): на PHPUnit 13 старые `@dataProvider` в Deptrac-тестах падают — там уже починено.

## 5. Критерии приёмки (Definition of Done)
- [x] Reproducer-тесты зелёные на новом depfile и **красные** на depfile из master (проверено: 4 failures).
- [x] На TasK все утечки Embeddable видны; ложных нет.
- [x] `composer check` зелёный (после rebase на #138).

## 6. Самопроверка (Verification)
```bash
composer check
vendor/bin/phpunit tests/Deptrac/DepfileRulesetSemanticsTest.php
```

## 7. Риски и зависимости (Risks and Dependencies)
- **BREAKING для потребителей:** после обновления `make deptrac` покажет ранее скрытые нарушения (TasK: ~151). Это ожидаемый эффект — gate стал честным. Освободить команды заранее.
- `ApplicationCommand`/`ApplicationQuery` прямо из Application-сервисов (TasK: 11) — теперь нарушение; если команда считает это допустимым, расширить правила осознанно.
- Зависит от #138; связана с TasK#3221 (задача в потребителе создана до нахождения причины — закрыть как устранённую).

## 8. Источники (Sources)
- [Entity](../../docs/conventions/layers/domain/entity.md) — Embeddable как часть сущности; [Value Object](../../docs/conventions/core-patterns/value-object.md).
- Deptrac 4: `LayerProvider::getTransitiveDependencies` — семантика `+Layer`.
- Аудит: `~/MyProjects/TasK/Development`, `vendor/bin/deptrac analyse --config-file=depfile.yaml` с подменённым vendor-depfile.

## 9. Комментарии (Comments)
- Задача выросла из запланированного «добавить слой»: главный результат — найденная и устранённая дыра `+` в ruleset, из-за которой gate молчал для Application.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-25 05:56:08 (1795586168) | Лид Арагорн (pi) | Создание задачи, взятие в работу (pi) |
| 2026-11-25 13:33:48 (1795588428) | Лид Арагорн (pi) | Реализация: слой DomainEmbeddable; найдена и устранена дыра `+` (транзитивное замыкание) — правила Application прописаны явно, введён DomainContract; тест на реальном deptrac; TasK 0 → 151 (все истинные) |
