---
type: docs
created: 2026-10-11 11:15:12 (1791716112)
due:
started:
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
assignee:
consultants: []
reviewer:
approver:
informed: []
branch: task/api-multipart-request-binding
pr:
status: todo
---

# TASK-api-multipart-request-binding: Добавить конвенцию биндинга multipart-запросов в API (MapUploadedFile + Request-резолвер)

> **Статус: предложение на рассмотрение.** Задача описывает проблему и предполагаемое решение; принятие/отклонение — за владельцем конвенций. До решения реализация не начинается.

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
- [ ] Решение владельца по предложению: принять (влить дополнения в конвенции) или отклонить (зафиксировать обоснование в комментариях задачи).
### ⚫ Won't Have (Не будем делать)
- До решения: реализация текста в конвенциях, ValueResolver-вариант, апстрим-пуллреквест в Symfony.

## 4. План реализации (Implementation Plan)
1. [ ] Владелец рассматривает предложение: принять / отклонить / доработать.
2. [ ] При принятии — внести текст подраздела и блока по формулировкам из Solution Sketch; прогнать `composer check` (включая `validate-docs`).

## 5. Критерии приёмки (Definition of Done)
- [ ] Решение зафиксировано; при принятии — текст в конвенциях, `composer check` зелёный, копия конвенций в проектах-потребителях обновляется штатным `coding-standard-init`.

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

## 9. Комментарии (Comments)
- Архитектурная проверка (Локи, проект TasK): минимально достаточное решение — explicit-резолвер; ValueResolver — строгое надмножество сложности без прироста поведения при N=1; триггеры пересмотра зафиксированы.
- Терминологическое: в конвенции «Обработка загрузки файлов» описывает web-формы; предлагаемый блок — про API; пересечение только тематическое.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-10-11 11:15:12 (1791716112) | Лид Арагорн (pi) | Создание задачи-предложения по указанию владельца проекта TasK; реализация не начинается до решения по конвенции |
