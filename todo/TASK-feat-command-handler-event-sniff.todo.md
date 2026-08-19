---
type: feat
created: 2026-08-18 15:05:52 (1787065552)
due: 
started: 2026-08-19 04:34:40 (1787114080)
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
branch: task/command-handler-event-sniff
pr: https://github.com/prikotov/coding-standard/pull/114
status: review
---

# TASK-feat-command-handler-event-sniff: Событие в write-хендлере: кодификация + warning-проверка

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Повторяющееся замечание (TasK, 04.08, минимум 3 хендлера за день): `FailIncompleteCommandHandler`, `PrepareProjectDocumentsCommandHandler`, `MarkDocumentChunksReadyCommandHandler` — «нет события и его логирования, у нас так принято, посмотри по коду».
- Правило «write-хендлер публикует событие» в конвенциях **не кодифицировано**: `command-handler.md` требует только «события диспетчеризуются ПОСЛЕ `flush()`» (условие на порядок), но не требует наличия события. `event.md` описывает устройство события, не обязательность.
- Агенты пишут хендлер, меняющий состояние через `save()`, без единого `*Event` — все проверки зелёные, нарушение всплывает на моём ревью.

### Варианты или путь решения (Solution Sketch)
- Шаг 1 — кодификация: в `docs/conventions/layers/application/command-handler.md` зафиксировать «хендлер, изменивший состояние (`save()`/`delete()`), публикует хотя бы одно `*Event`; читающие Query-хендлеры событий не публикуют». Определить исключения (например, idempotent-фиксы, внутренние служебные команды) — по прецедентам потребителей.
- Шаг 2 — проверка (warning, не error): снифф/phpstan-правило — класс `*CommandHandler` в `Application\UseCase\Command\`, вызывающий `save`/`delete` репозитория, но не содержащий `dispatch(new ...Event(...))` → предупреждение с отсылкой к конвенции.
- Warning, а не error: эвристика (не всякий мутирующий хендлер обязан событие — исключения из шага 1), но сигнал на ревью появляется автоматически.

### Ожидаемый результат (Expected Result)
- Категория замечаний «нет события и логирования» уходит из ручного ревью: либо хендлер публикует событие, либо phpcs предупреждает.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как ревьюер, я хочу, чтобы отсутствие события в мутирующем хендлере подсвечивалось автоматикой, а не всплывало при моём прогоне по коду после мержа.

### Цель по SMART
- До конца задачи: правило в `command-handler.md` + warning-проверка в пакете; на TasK прогон показывает только осознанные исключения.

## 2. Контекст и Границы (Context and Scope)
- Затрагиваемые файлы: `docs/conventions/layers/application/command-handler.md`, новый снифф в `src/Sniffs/Application/` (или phpstan-правило — решить по выразительности AST), тесты.
- Эвристика детекции мутации: вызов метода `save`/`delete`/`persist` на репозитории; детекция события — наличие `dispatch(` с `new *Event`.
- Out of scope: требование логирования событий (логирование — отдельный слушатель в модуле логирования, его наличие не видно из хендлера); Query-хендлеры.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Правило в `command-handler.md` (с исключениями, согласованными по прецедентам TasK/stocks2).
- [x] Warning-проверка: мутирующий CommandHandler без `*Event`-диспетча.
- [x] Тесты сниффа: кейс с событием / кейс без события / QueryHandler / исключение.
### 🟡 Желательно (Should Have)
- [x] Пункт в чек-лист ревью `command-handler.md`.
### ⚫ Won't Have (Не будем делать)
- Проверка логирования событий.

## 4. План реализации (Implementation Plan)
1. [x] Собрать прецеденты по TasK (хендлеры с событиями vs без) — границы исключений.
2. [x] Кодифицировать правило в доке.
3. [x] Реализовать проверку + тесты; `composer check`.
4. [x] Прогон на TasK (без правки файлов): список warning'ов приложить в задачу.

## 5. Критерии приёмки (Definition of Done)
- [x] `composer check` зелёный.
- [x] Тесты проверки зелёные; на историческом кейсе (`MarkDocumentChunksReady` до фикса 06.08) проверка бы сработала.
      Проверено на исторических версиях из git-истории TasK: `FailIncompleteCommandHandler` (до фикса, коммит 9eb94115d) и `MarkDocumentNeedChunksCommandHandler` (до переименования, коммит 424e4a6d4) — обе дают `MissingEventDispatch`.
- [x] Прогон на TasK: 59 warning'ов, список приложен (см. «Результаты»);
      из них 39 подпадают под задокументированные исключения (служебные/агрегатные команды),
      остальные 20 — реальные кандидаты на событие: воспроизводят тот самый класс замечаний ревью 04.08.
      Чистка TasK — отдельная задача потребителя, не этого пакета.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
composer check
```

## 7. Риски и зависимости
- False positives на служебных/миграционных хендлерах — лечится перечнем исключений в доке и warning-уровнем.
- «Мутирующий» детект по имени метода (`save`/`delete`) может пропустить кастомные методы репозитория — принять как ограничение эвристики, задокументировать.

## 8. Источники (Sources)
- Замечания 04.08 (сессия rollout-2026-08-03T08-05-49, TasK): три хендлера без событий; формулировка «у нас так принято, посмотри по коду».
- docs/conventions/layers/application/command-handler.md, event.md — текущие формулировки (порядок flush/dispatch без требования наличия события).

## 9. Комментарии (Comments)

### Результаты прогона на TasK (без правки файлов, снифф `CommandHandlerEventDispatch`)

Прогон: 205 CommandHandler'ов в `Application/UseCase/Command/`, **59 warning'ов** (`MissingEventDispatch`).
Эвристика: мутация = `save`/`delete`/`persist`/`remove`/`flush`; событие = вызов `dispatch(` (включая `dispatch($event)` и фабрики).

**Подпадают под задокументированные исключения (служебные/агрегатные, без внешних наблюдателей) — 39:**
- Attribution: `AttributionSession/Create`, `LinkSessionToUser`, `RevenueAttribution/Create|TrackPayment|TrackUsage`, `UserAcquisition/Create`, `PaymentAttribution/RecordContext|Upsert`
- Billing: `Usage/DeleteByProject`, `Usage/RecalculateFx`, `TBusinessPayment/Cancel|CheckState|Notification|ProcessAutopay`
- Health: `Alert/Send`, `RecordStatusHistory`, `UpdateServiceStatus`
- Project/Source: `DiskUsage/Recalculate` (×2), `AdjustDiskUsage`, `AdjustUsageCost`, `MarkUsed`, `FileStorage/RemoveOrphanFiles`, `Document/RemoveBySourceProject`
- User: `AdjustStatsCounters`, `InitializePreferredCurrency`, `RecordPaymentConsent`, `MarkAccessTokenUsed`, `RecordUserAccessTokenUsageAudit`
- Notification: `Acknowledge`, `Trigger`; Rag: `MarkDocumentNeedRechunk`, `MarkProjectDocumentsNeedRechunk`
- PublicAgent (сессии, аудит): `AcceptLandingChatHandoff`, `ForgetMe`, `LinkUser`, `LogoutUser`, `SendLandingChatMessage`, `SwitchUser`
- Webhook: `WebhookSubscription/DisableForDeliveryFailure`; Chat: `DoNotSaveQuickAsk`

**Реальные кандидаты на событие (пользовательские факты — тот самый класс замечаний 04.08) — 20:**
- Invitation (User): `ApproveInvite`, `DeleteInvite`, `RejectInvite`, `Request`, `ResendInvite`, `SendInvite`, `UndoRejectInvite`, `VisitInvite`
- Tag/ProjectTag: `Tag/Create|Update|Delete|DeleteIfUnused`, `ProjectTag/Add`
- UserBilling: `Create`, `DisableAutopay`, `SelectPlan`
- Chat/ChatMessage: `SetPublicAccess`, `UpdateChatSettings`, `ChatMessage/UpdateRating`
- AppOption: `Delete`, `DeleteByName` (конфиг — на усмотрение команды)

Полный машиночитаемый список: строки `WARNING` из прогона
`vendor/bin/phpcs --standard=ruleset.xml --sniffs=PrikotovCodingStandard.Application.CommandHandlerEventDispatch` по 205 хендлерам TasK.

### Реализация
- Снифф: `src/Sniffs/Application/CommandHandlerEventDispatchSniff.php` — warning `MissingEventDispatch`, указатель на класс хендлера.
- Фикстуры: `tests/Application/CommandHandlerEventDispatchUnitTest{,Valid}.inc` + 2 fixtures/src (реальный путь Command + Query-путь).
- Подавление исключения: `// phpcs:ignore PrikotovCodingStandard.Application.CommandHandlerEventDispatch.MissingEventDispatch` (строкой выше класса) — семантика PHPCS 4.x.
- Дока: правило + исключения + чек-лист в `docs/conventions/layers/application/command-handler.md`; строка в README.
- Правка по ревью: термин «мутирующий Command Handler» убран — правило сформулировано для любого Command Handler,
  упоминание Query-хендлеров убрано (дока про Command Handler), исключения — простым списком;
  модель валидации исключений: снифф их не различает, осознанное исключение подавляется `phpcs:ignore` с причиной и видно на ревью.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-18 15:05:52 (1787065552) | Бэкендер (pi) | Создание задачи |
| 2026-08-18 | Бэкендер (pi) | Выполнение: правило в конвенции, снифф `CommandHandlerEventDispatch`, тесты, прогон на TasK |
| 2026-08-18 | Бэкендер (pi) | Ревью: упрощена формулировка правила в конвенции (без термина «мутирующий»), исключения списком |
