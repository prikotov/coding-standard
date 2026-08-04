---
name: Presentation Layer
type: rule
description: Слой представления: приём/отдача данных через публичные интерфейсы
---

# Слой Представления (Presentation)

Слой Presentation отвечает за приём/отдачу данных через публичные интерфейсы (Web, API, Console) и строго взаимодействует только со слоем Application.

## Общие правила

- Контроллеры, команды консоли и HTTP-эндпоинты обращаются только к UseCase/`Handler` из Application.
- Публичные контракты Presentation формируют входные данные в DTO запроса, `Command`/`Query` и принимают DTO ответа.
- В Presentation запрещено использовать типы из Domain напрямую (`Entity`, `VO`, `Repository`, `Specification`), а также классы из Infrastructure/Integration.
- Исключения маппятся в ответы/сообщения через обработчики уровня Presentation (listeners/subscribers/exception mappers).
- Для transport DTO и пользовательских валидаторов используем профильные правила:
  [DTO запроса](presentation/request-dto.md),
  [Query DTO](presentation/query-dto.md),
  [DTO ответа](presentation/response-dto.md),
  [Validator](presentation/validator.md).
- Любая валидация уровня presentation остаётся декларативной в DTO/`FormModel` либо выносится во внешнюю пару валидаторов (validator pair); императивные `Callback`/`validate*()` в транспортной модели запрещены.

## Расположение

```
apps/{web|api|console}/src/...
```

## Как используем

- Разбираем вход (query/body/route), валидируем в DTO/форме, создаём `Command`/`Query`.
- Вызываем соответствующий `Handler` (через `__invoke`) и возвращаем представление/JSON.
- Не обращаемся к репозиториям, ORM-моделям, доменным сущностям или `VO` напрямую.

## Чек-лист

- [ ] Контроллер зависит только от Application-слоя.
- [ ] Нет импортов из `Domain/*`, `Infrastructure/*`, `Integration/*`.
- [ ] Вход/выход — только DTO запроса/ответа.

## Сущности проекта

- `Controller`: `apps/<app>/src/Module/<ModuleName>/Controller`. [Контроллер](presentation/controller.md)
- `ListController`: специализированные контроллеры списков. [Контроллер списка](presentation/list-controller.md)
- `ConsoleCommand`: консольные команды Presentation. [Консольная команда](presentation/console-command.md)
- `Route`: генераторы URL и имён маршрутов. [Маршруты](presentation/route.md)
- `TwigExtension`: презентационные функции/фильтры для Twig-шаблонов. [Twig Extension](presentation/twig-extension.md)
- `TwigComponent`: переиспользуемые UI-компоненты Symfony UX. [Twig Component](presentation/twig-component.md)
- `Forms`: `FormType` и `FormModel` для валидации входа. [Формы](presentation/forms.md)
- DTO запроса / Query DTO / DTO ответа: transport-контракты HTTP-привязки и ответа. [DTO запроса](presentation/request-dto.md), [Query DTO](presentation/query-dto.md), [DTO ответа](presentation/response-dto.md)
- `Validator`: пользовательская пара `Constraint` / `ConstraintValidator` для проверки связанных полей и переиспользуемой валидации. [Validator](presentation/validator.md)
- `Authorization`: `PermissionEnum`, `ActionEnum`, `Rule`, `Voter`, Grant. [Авторизация](presentation/authorization.md), [Permission Enum](presentation/permission-enum.md), [Правило](presentation/rule.md), [Voter](presentation/voter.md), [Grant](presentation/grant.md)

## Уведомления (Notification) в Presentation

Сущности и точки входа, которые используются для Web-уведомлений и live-обновлений:

- Контроллеры: `ProjectName\Web\Module\Notification\Controller\Notification\ListController`, `ProjectName\Web\Module\Notification\Controller\Notification\AcknowledgeController`
- `Route`: `ProjectName\Web\Module\Notification\Route\NotificationRoute`
- UI-связка: `ProjectName\Web\Component\Notification\NotificationStreamConfigProvider`, `ProjectName\Web\Component\Twig\Extension\NotificationExtension`, `ProjectName\Web\Component\Twig\Extension\TurboStreamExtension`
- Фронтенд: `apps/web/assets/controllers/notification-toast_controller.js`
- Шаблон `Turbo Stream` (source status): `apps/web/src/Module/Notification/Resource/templates/source/status_turbo_stream.html.twig`
- Куки аутентификации `Mercure`: `ProjectName\Web\EventSubscriber\MercureAuthorizationSubscriber`
- Темы `Turbo Stream`: `ProjectName\Web\Component\Turbo\TurboStreamTopicRegistry`

## Дополнительно

- [Контроллеры](presentation/controller.md)
- [Проверка прав](presentation/authorization.md)
- [Перечисление прав](presentation/permission-enum.md)
- [Grant](presentation/grant.md)
- [Правило доступа](presentation/rule.md)
- [Голосующий объект](presentation/voter.md)
- [Формы](presentation/forms.md)
- [DTO запроса](presentation/request-dto.md)
- [Query DTO](presentation/query-dto.md)
- [DTO ответа](presentation/response-dto.md)
- [Validator](presentation/validator.md)
- [Контроллер списка](presentation/list-controller.md)
- [Маршруты](presentation/route.md)
- [Twig Component](presentation/twig-component.md)
- [Twig Extension](presentation/twig-extension.md)
