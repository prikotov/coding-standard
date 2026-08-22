---
package: prikotov/coding-standard
name: Grant
type: rule
description: Правила создания грант-сервисов для проверки доступа
---

# Grant

## Определение

**Grant** — фасад презентационного слоя для удобного вызова
 [Symfony AuthorizationChecker](https://symfony.com/doc/current/security.html#checking-user-roles) из контроллеров,
шаблонов и компонентов пользовательского интерфейса (UI).

## Общие правила

- Класс объявляется `final readonly` и хранит только зависимости через конструктор.
- Методы именуются с префиксом `can*` и возвращают `bool` без побочных эффектов.
- Каждый метод вызывает `AuthorizationCheckerInterface::isGranted()` с Action Enum и `subject`.
- Grant не решает доступ сам: итоговое решение остаётся в [Voter](voter.md) и [Rule](rule.md).
- UI-флаги допустимы только для отображения кнопок и ссылок, не для защиты точки входа (endpoint).
- Внутри не используем `TokenInterface` напрямую.
- Не выполняем запросы к базе, не обращаемся к Domain/Application, не модифицируем состояние.

## Зависимости

- Разрешено: `AuthorizationCheckerInterface`, `*ActionEnum`, простые типы (`Uuid`), DTO Presentation, UI-флаги.
- Запрещено: репозитории, QueryBus/CommandBus, сервисы Domain/Application/Infrastructure, обращения к глобальному состоянию.

## Расположение

```
apps/<app>/src/Module/<ModuleName>/Security/<SubjectName>/Grant.php
```

- Имя файла совпадает с контекстом (`Security/User/Grant.php`, `Security/Project/Grant.php`).
- Хранится в каталоге `Security/<SubjectName>` рядом с Permission Enum, Action Enum, Rule и Voter.

## Как используем

1. Создаём Grant для сущности и регистрируем его как сервис в модуле.
2. Внедряем Grant в контроллеры, Twig-шаблоны и компоненты UI через DI.
3. Вызываем методы `can*`, чтобы скрыть/показать действия (кнопки, ссылки, формы).
4. Точку входа (endpoint) защищаем через `isGranted()`/Voter, а не через Grant.

## Пример

```php
<?php

declare(strict_types=1);

namespace ProjectName\Web\Module\User\Security\User;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

// ActionEnum определён в том же каталоге (`Security/User/ActionEnum.php`).

final readonly class Grant
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function canEdit(Uuid $userUuid): bool
    {
        return $this->authorizationChecker->isGranted(ActionEnum::edit->value, $userUuid);
    }

    public function canSoftDelete(Uuid $userUuid, bool $isDeleted): bool
    {
        return !$isDeleted && $this->canDelete($userUuid);
    }

    public function canDelete(Uuid $userUuid): bool
    {
        return $this->authorizationChecker->isGranted(ActionEnum::delete->value, $userUuid);
    }

    public function canVerify(Uuid $userUuid): bool
    {
        return $this->authorizationChecker->isGranted(ActionEnum::verify->value, $userUuid);
    }
}
```

## Чек-лист для проведения ревью кода

- [ ] Grant лежит в каталоге `Security` соответствующего модуля и объявлен `final readonly`.
- [ ] Все публичные методы начинаются с `can*` и возвращают `bool`.
- [ ] Внутри используются значения `*ActionEnum`, а не строки.
- [ ] Grant только готовит `subject` и вызывает `AuthorizationCheckerInterface::isGranted()`.
- [ ] Нет зависимостей на Domain/Application/Infrastructure-сервисы.
- [ ] Нет логики доступа, дублирующей Rule.
- [ ] Шаблоны и контроллеры обращаются к Grant вместо прямых вызовов `is_granted()`.
