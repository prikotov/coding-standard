---
type: docs
created: 2026-10-12 09:53:12 (1791773592)
due:
started: 2026-11-24 01:33:31 (1795484011)
completed:
cancelled:
value: V2
complexity: C2
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Лид Арагорн (pi)
assignee: Разработчик (pi)
consultants: []
reviewer:
approver:
informed: []
branch: task/api-multipart-binding-convention
pr: https://github.com/prikotov/coding-standard/pull/133
status: review
---

# TASK-api-multipart-request-binding: Добавить конвенцию биндинга multipart-запросов в API (MapUploadedFile + Request-резолвер)

> **Статус: предложение принято владельцем 2026-11-23 (PR #127); текст конвенции внесён 2026-11-24 — на ревью в PR #133.**

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Конвенции покрывают загрузку файлов только для web-форм (FormModel/FormType/`var/uploads/`). Для **API multipart-эндпоинтов** (`#[MapUploadedFile]` + опциональные form-поля) биндинга нет, а штатный `#[MapRequestPayload]` Symfony 8 для них **физически не работает**: при запросе «только файл, без form-полей» пустой `$request->request->all()` фолбэчится на `$request->getContent()` (сырое multipart-тело), `is_array` false, формат `form` → `BadRequestHttpException 400` (`RequestPayloadValueResolver::mapRequestPayload`); nullable/default аргумента не спасают — ранний `return null` требует пустого `$data`.
- Проект-потребитель (TasK, Symfony 8.0.15) столкнулся с этим на реальном API file-эндпоинте и вынужден был решить проблему локально: Presentation-резолвер `CreateByFileRequestResolver` рядом с DTO (нормализация булевых form-полей через `FILTER_VALIDATE_BOOL`, денормализация DTO, ручная валидация констрейнтов → 400 + `ValidationFailedException`; контракт ошибок идентичен `#[MapRequestPayload]`). Без общей конвенции следующий проект решит ту же задачу иначе — фрагментация практик.

### Варианты или путь решения (Solution Sketch)
Предложение (прошло архитектурную проверку в проекте-потребителе; вердикт — минимально достаточное решение, ValueResolver осознанно отложен):
1. **`request-dto.md`** — новый подраздел «Multipart file-запросы (API)»:
   - `#[MapRequestPayload]` для multipart-эндпоинтов с `#[MapUploadedFile]` не применяем (обоснование выше, с указанием точки отказа в ядре Symfony; nullable/default аргумента не спасают).
   - Паттерн: файл — `#[MapUploadedFile]` (констрейнты `File`); опциональные form-поля — controller-local Presentation-резолвер `*RequestResolver` рядом с DTO (`final readonly`, зависимости `DenormalizerInterface` + `ValidatorInterface`, метод `create(Request): ?Dto`); нормализация скаляров form-полей (bool — `FILTER_VALIDATE_BOOL` + `FILTER_NULL_ON_FAILURE`, мусор → 400), денормализация DTO (`NotNormalizableValueException` → 400), ручная валидация констрейнтов → `HttpException::fromStatusCode(400, …, ValidationFailedException)` (контракт ошибок идентичен `#[MapRequestPayload]`).
   - Сигнатура: `null` = нет form-полей (file-only, обратная совместимость с существующими клиентами).
2. **`controller.md`** — в раздел «Обработка загрузки файлов» добавить блок «Загрузка файлов в API (multipart)» (3–5 строк) со ссылкой на подраздел `request-dto.md`.
3. **Осознанные границы:** кастомный `ValueResolverInterface` + маркер-атрибут (декларативный биндинг) **не вводить**, пока multipart file-эндпоинтов с этим биндингом меньше двух; триггеры пересмотра — (а) второй такой эндпоинт, (б) апгрейд Symfony, меняющий `mapRequestPayload`. Форм-путь (`filter_bool` контекст Symfony) при наличии form-полей даёт ту же семантику булевых, что и предлагаемый паттерн (`filter_var(FILTER_VALIDATE_BOOL)`), — расхождения нет.

### Ожидаемый результат (Expected Result)
- В конвенциях описан воспроизводимый паттерн для API multipart-эндпоинтов; проект-потребитель переносит локальный резолвер на общую конвенцию без изменения кода; следующие проекты не изобретают свой путь.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как разработчик API file-эндпоинта, я хочу общую конвенцию биндинга multipart-запросов, чтобы использовать подтверждённый паттерн вместо изобретания обхода несовместимости Symfony в каждом проекте.

### Цель по SMART (Goal)
- **S:** два дополнения в `docs/conventions/layers/presentation/` (`request-dto.md`, `controller.md`) по формулировкам из Solution Sketch.
- **M:** подраздел и блок приняты владельцем (или отклонены с обоснованием); docs-валидация пакета зелёная.
- **A:** формулировки готовы и проверены на живой реализации (TasK).
- **R:** запрос владельца проекта TasK; Symfony-несовместимость подтверждена чтением vendor-кода 8.0.15 и интеграционными тестами.
- **T:** ближайшая итерация пакета.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `docs/conventions/layers/presentation/request-dto.md`, `docs/conventions/layers/presentation/controller.md`.
*   **Референс реализации:** проект TasK — `apps/api/src/v1/Module/Source/Controller/Source/Request/CreateByFileRequestResolver.php` + `CreateFileController.php` ([PR #3053](https://github.com/prikotov/TasK/pull/3053), эпик api-rag-context-control, [PR #3051](https://github.com/prikotov/TasK/pull/3051));Symfony 8.0.15.
*   **Out of Scope:** реализация ValueResolver-варианта; изменения web-формового раздела; правки ядра Symfony (апстрим-предложение — отдельное решение).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Решение владельца по предложению: принять (влить дополнения в конвенции) или отклонить (зафиксировать обоснование в комментариях задачи). — **Принято** (2026-11-23, PR #127).
### ⚫ Won't Have (Не будем делать)
- ValueResolver-вариант (кастомный `ValueResolverInterface` + маркер-атрибут), апстрим-пуллреквест в Symfony.

## 4. План реализации (Implementation Plan)
1. [x] Владелец рассматривает предложение: принять / отклонить / доработать. — принято.
2. [x] При принятии — внести текст подраздела и блока по формулировкам из Solution Sketch; прогнать `composer check` (включая `validate-docs`).

## 5. Критерии приёмки (Definition of Done)
- [x] Решение зафиксировано; при принятии — текст в конвенциях, `composer check` зелёный, копия конвенций в проектах-потребителях обновляется штатным `coding-standard-init`.

## 6. Самопроверка (Verification)
```bash
composer check
php vendor/bin/todo-md validate todo/TASK-api-multipart-request-binding.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Конвенции копируются в проекты-потребители: изменение увидят все подключённые проекты — формулировки должны быть без привязки к конкретному проекту (TasK указан только как референс).
- Поведение Symfony может измениться в будущих версиях (`mapRequestPayload`) — в тексте зафиксированы триггеры пересмотра.

## 8. Источники (Sources)
- [ ] TasK, PR [#3053](https://github.com/prikotov/TasK/pull/3053) (CreateByFileRequestResolver — референс-реализация) и [#3051](https://github.com/prikotov/TasK/pull/3051) (эпик).
- [ ] `vendor/symfony/http-kernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php` (Symfony 8.0.15) — точка отказа для file-only multipart; контекст `filter_bool` для form.
- [Там же в Symfony 8.1.11](https://github.com/symfony/http-kernel/blob/v8.1.11/Controller/ArgumentResolver/RequestPayloadValueResolver.php) — перепроверено при реализации: поведение не изменилось.
- [Symfony Controller: Mapping Uploaded Files](https://symfony.com/doc/current/controller.html#mapping-uploaded-files).

## 9. Комментарии (Comments)
- Архитектурная проверка (Локи, проект TasK): минимально достаточное решение — explicit-резолвер; ValueResolver — строгое надмножество сложности без прироста поведения при N=1; триггеры пересмотра зафиксированы.
- Терминологическое: в конвенции «Обработка загрузки файлов» описывает web-формы; предлагаемый блок — про API; пересечение только тематическое.
- 2026-11-23 — Решение владельца (PR #127): предложение **принято**. Задача вливается в `master` готовой к реализации (п.2 плана — отдельной веткой). Поле `branch` очищено: ветка предложения удаляется после merge.
- 2026-11-24 — Reverse Briefing (Разработчик (pi)): план автора ясен, реализован по Solution Sketch без расширения scope. Внесено: раздел «Multipart file-запросы (API)» в `request-dto.md` (обоснование, паттерн, пример из DTO + резолвера + контроллера, пункт чек-листа, указатель в «Общих правилах», путь резолвера в «Расположении»); блок «Загрузка файлов в API (multipart)» и пункт чек-листа в `controller.md`.
- 2026-11-24 — Отличие примера от референса: аргумент файла объявлен `UploadedFile $file` (не `UploadedFile|array`) без проверки `instanceof` — отсутствующий файл ядро отклоняет с 400 по `validationFailedStatusCode` ещё до тела контроллера.
- 2026-11-24 — Проверка на реальном коде (скрипт в `/tmp`, vendor TasK Symfony 8.1.11, отслеживаемые файлы TasK не менялись): PHP-блоки загружены из документа и прогнаны в 10 сценариях — `#[MapRequestPayload]` + запрос только с файлом даёт 400 (в т.ч. с `nullable` и `mapWhenEmpty: true`); с form-полями биндится; паттерн отрабатывает file-only → `null`, булевы/enum/строка, `garbage` → 400, неизвестный enum → 400, нарушение ограничения → 400 + `ValidationFailedException`; `#[MapUploadedFile]`: файл есть → `UploadedFile`, файла нет → 400. Все сценарии пройдены.
- 2026-11-24 — Нюанс для владельца (не менял — вне scope): контракт «400 + violations» совпадает у резолвера и `#[MapRequestPayload]` для нарушений ограничений; для ошибок типа (например, неизвестный enum) ядро возвращает 400 со списком violations (`collect_denormalization_errors`), а резолвер — 400 без списка. Статус одинаков, тело ответа для ошибок типа различается. При необходимости выровнять — денормализовать с `collect_denormalization_errors => true` и собирать violations, как ядро.
- 2026-11-24 — `composer check` зелёный (293 теста, 797 утверждений); `coding-standard-init` в чистый каталог копирует оба файла байт-в-байт.
- 2026-11-24 09:39:35 (1795487975) — Нюанс 2, найден при разборе нюанса 1 на реальном коде 8.1.11 (ответы отрендерены штатным `ProblemNormalizer`): для `?bool` `#[MapRequestPayload]` с `filter_bool` нераспознанное значение (`garbage`) молча превращает в `false`, а пустая строка и литералы `true/false/1/0/yes/no/on/off` распознаются одинаково в обоих путях. Формулировка первой редакции «та же семантика булевых» была неточной — исправлена: в разделе теперь прямо сказано, что резолвер строже ядра, и что ошибки типа возвращаются без списка нарушений (ядро — со списком и путём поля). Оба отличия — только в теле ответа для ошибок типа и в строгости булевых; успешные пути и нарушения ограничений идентичны. Оставлено как в референсе — ждёт решения владельца: оставить как есть или выровнять резолвер с ядром.
- 2026-11-24 10:29:56 (1795490996) — Мапперы исключений потребителя (TasK: `ExceptionSubscriber` → `ExceptionToHttpMapper` → `HttpExceptionNormalizer`) проверены на реальных классах: они обрабатывают только исключения проекта (`ValidationExceptionInterface` → 422 с текстом сообщения), а ошибки биндинга запроса идут мимо них — 400 через problem-формат. Значит «прогнать резолвер через маппер» даст третий формат, а не совпадение с JSON-эндпоинтами. По принципу умеренности конвенция не усложняется: код примера — как в референсе (вариант A), сравнения с ядром убраны из текста правил и остаются в этом комментарии и в PR #133.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-10-12 09:53:12 (1791773592) | Лид Арагорн (pi) | Создание задачи-предложения по указанию владельца проекта TasK; реализация не начинается до решения по конвенции |
| 2026-11-23 21:57:14 (1795445834) | Разработчик (pi) | Решение владельца: предложение принято (PR #127); задача готова к реализации, поле `branch` очищено |
| 2026-11-24 08:45:06 (1795484706) | Разработчик (pi) | Взята в работу (`in_progress`), ветка `task/api-multipart-binding-convention`; текст конвенции внесён, проверка на Symfony 8.1.11 пройдена, `composer check` зелёный |
| 2026-11-24 08:45:06 (1795484706) | Разработчик (pi) | Создан PR #133, задача переведена в `review`; ожидается одобрение формулировок владельцем (в т.ч. решение по нюансу тела ответа для ошибок типа) |
| 2026-11-24 09:39:35 (1795487975) | Разработчик (pi) | Уточнён раздел про булевы поля и контракт ошибок: убрано неверное «та же семантика булевых», зафиксированы два отличия резолвера от `#[MapRequestPayload]` |
| 2026-11-24 10:29:56 (1795490996) | Разработчик (pi) | Проверены мапперы исключений потребителя; текст правил сокращён до коротких пунктов без сравнений с ядром, код примера — как в референсе |
