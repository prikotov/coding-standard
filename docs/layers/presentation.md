# Слой Представления (Presentation)

Слой Presentation отвечает за приём/отдачу данных через публичные интерфейсы (Web, API, Console) и строго взаимодействует только со слоем Application.

## Общие правила

- Контроллеры, команды консоли и HTTP-эндпоинты обращаются только к UseCase/Handler из Application.
- Публичные контракты Presentation формируют входные данные в Request DTO/Command/Query и принимают Response DTO.
- В Presentation запрещено использовать типы из Domain напрямую (Entity, VO, Repository, Specification), а также классы из Infrastructure/Integration.
- Исключения маппятся в ответы/сообщения через обработчики уровня Presentation (listeners/subscribers/exception mappers).
- Любая валидация ввода — либо на уровне формы/DTO, либо делегируется в Application.

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
- ConsoleCommand: консольные команды Presentation. [Консольная команда](presentation/console_command.md)
- Route: генераторы URL и имён маршрутов. [Маршруты](presentation/route.md)
- TwigExtension: презентационные функции/фильтры для Twig-шаблонов. [Twig Extension](presentation/twig_extension.md)
- TwigComponent: переиспользуемые UI-компоненты Symfony UX. [Twig Component](presentation/twig_component.md)
- Forms: `FormType` и `FormModel` для валидации входа. [Формы](presentation/forms.md)
- Authorization: `PermissionEnum`, `ActionEnum`, `Rule`, `Voter`, `Grant`. [Авторизация](presentation/authorization.md), [Permission Enum](presentation/permission_enum.md), [Правило](presentation/rule.md), [Voter](presentation/voter.md), [Grant](presentation/grant.md)

## Уведомления (Notification) в Presentation

Сущности и точки входа, которые используются для Web-уведомлений и live-обновлений:

- Controllers: `Web\Module\Notification\Controller\Notification\ListController`, `Web\Module\Notification\Controller\Notification\AcknowledgeController`
- Route: `Web\Module\Notification\Route\NotificationRoute`
- UI glue: `Web\Component\Notification\NotificationStreamConfigProvider`, `Web\Component\Twig\Extension\NotificationExtension`, `Web\Component\Twig\Extension\TurboStreamExtension`
- Frontend: `apps/web/assets/controllers/notification-toast_controller.js`
- Turbo Stream template (source status): `apps/web/src/Module/Notification/Resource/templates/source/status_turbo_stream.html.twig`
- Mercure auth cookie: `Web\EventSubscriber\MercureAuthorizationSubscriber`
- Turbo Stream topics: `Web\Component\Turbo\TurboStreamTopicRegistry`

## Дополнительно

- [Контроллеры](presentation/controller.md)
- [Проверка прав](presentation/authorization.md)
- [Перечисление прав](presentation/permission_enum.md)
- [Грант-сервис](presentation/grant.md)
- [Правило доступа](presentation/rule.md)
- [Голосующий объект](presentation/voter.md)
- [Формы](presentation/forms.md)
- [Контроллер списка](presentation/list-controller.md)
- [Маршруты](presentation/route.md)
- [Twig Component](presentation/twig_component.md)
- [Twig Extension](presentation/twig_extension.md)
