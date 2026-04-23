---
name: Presentation Layer
type: rule
description: Слой представления: приём/отдача данных через публичные интерфейсы
---

# Слой Представления (Presentation)

Слой Presentation отвечает за приём/отдачу данных через публичные интерфейсы (Web, API, Console) и строго взаимодействует только со слоем Application.

## Общие правила

- Контроллеры, команды консоли и HTTP-эндпоинты обращаются только к UseCase/Handler из Application.
- Публичные контракты Presentation формируют входные данные в Request DTO/Command/Query и принимают Response DTO.
- В Presentation запрещено использовать типы из Domain напрямую (Entity, VO, Repository, Specification), а также классы из Infrastructure/Integration.
- Исключения маппятся в ответы/сообщения через обработчики уровня Presentation (listeners/subscribers/exception mappers).
- Для transport DTO и custom validators используем профильные правила:
  [Request DTO](presentation/request-dto.md),
  [Query DTO](presentation/query-dto.md),
  [Response DTO](presentation/response-dto.md),
  [Validator](presentation/validator.md).
- Любая presentation-level validation остаётся declarative в DTO/FormModel либо выносится во внешний validator pair; императивные `Callback`/`validate*()` в transport model запрещены.

## Расположение

```
apps/{web|api|console}/src/...
```

## Как используем

- Разбираем вход (query/body/route), валидируем в DTO/форме, создаём Command/Query.
- Вызываем соответствующий Handler (через `__invoke`) и возвращаем представление/JSON.
- Не обращаемся к репозиториям, ORM-моделям, доменным сущностям или VO напрямую.

## Чек-лист

- [ ] Контроллер зависит только от Application-слоя.
- [ ] Нет импортов из `Domain/*`, `Infrastructure/*`, `Integration/*`.
- [ ] Вход/выход — только Request/Response DTO.

## Сущности проекта

- Controller: `apps/<app>/src/Module/<ModuleName>/Controller`. [Контроллер](presentation/controller.md)
- ListController: специализированные контроллеры списков. [Контроллер списка](presentation/list-controller.md)
- ConsoleCommand: консольные команды Presentation. [Консольная команда](presentation/console-command.md)
- Route: генераторы URL и имён маршрутов. [Маршруты](presentation/route.md)
- TwigExtension: презентационные функции/фильтры для Twig-шаблонов. [Twig Extension](presentation/twig-extension.md)
- TwigComponent: переиспользуемые UI-компоненты Symfony UX. [Twig Component](presentation/twig-component.md)
- Forms: `FormType` и `FormModel` для валидации входа. [Формы](presentation/forms.md)
- Request DTO / Query DTO / Response DTO: transport-контракты HTTP binding и ответа. [Request DTO](presentation/request-dto.md), [Query DTO](presentation/query-dto.md), [Response DTO](presentation/response-dto.md)
- Validator: custom `Constraint` / `ConstraintValidator` pair для cross-field и reusable validation. [Validator](presentation/validator.md)
- Authorization: `PermissionEnum`, `ActionEnum`, `Rule`, `Voter`, `Grant`. [Авторизация](presentation/authorization.md), [Permission Enum](presentation/permission-enum.md), [Правило](presentation/rule.md), [Voter](presentation/voter.md), [Grant](presentation/grant.md)

## Уведомления (Notification) в Presentation

Сущности и точки входа, которые используются для Web-уведомлений и live-обновлений:

- Controllers: `AppName\Web\Module\Notification\Controller\Notification\ListController`, `AppName\Web\Module\Notification\Controller\Notification\AcknowledgeController`
- Route: `AppName\Web\Module\Notification\Route\NotificationRoute`
- UI glue: `AppName\Web\Component\Notification\NotificationStreamConfigProvider`, `AppName\Web\Component\Twig\Extension\NotificationExtension`, `AppName\Web\Component\Twig\Extension\TurboStreamExtension`
- Frontend: `apps/web/assets/controllers/notification-toast_controller.js`
- Turbo Stream template (source status): `apps/web/src/Module/Notification/Resource/templates/source/status_turbo_stream.html.twig`
- Mercure auth cookie: `AppName\Web\EventSubscriber\MercureAuthorizationSubscriber`
- Turbo Stream topics: `AppName\Web\Component\Turbo\TurboStreamTopicRegistry`

## Дополнительно

- [Контроллеры](presentation/controller.md)
- [Проверка прав](presentation/authorization.md)
- [Перечисление прав](presentation/permission-enum.md)
- [Грант-сервис](presentation/grant.md)
- [Правило доступа](presentation/rule.md)
- [Голосующий объект](presentation/voter.md)
- [Формы](presentation/forms.md)
- [Request DTO](presentation/request-dto.md)
- [Query DTO](presentation/query-dto.md)
- [Response DTO](presentation/response-dto.md)
- [Validator](presentation/validator.md)
- [Контроллер списка](presentation/list-controller.md)
- [Маршруты](presentation/route.md)
- [Twig Component](presentation/twig-component.md)
- [Twig Extension](presentation/twig-extension.md)
