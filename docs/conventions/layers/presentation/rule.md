---
name: Access Rule
type: rule
description: Правила создания правил доступа
---

# Правило доступа (Access Rule)

## Определение

**Правило доступа (Access Rule)** — сервис слоя Presentation, который содержит итоговую логику проверки доступа.
`Rule` связывает `ActionEnum`, `PermissionEnum` и проверки владения/участия. См.
[документацию Symfony по авторизации](https://symfony.com/doc/current/security.html#authorization).

## Общие правила

- Класс объявляем `final readonly`.
- Внедряем только сервисы, необходимые для проверки доступа.
- Методы именуем `can<Action>` (`canCreate`, `canViewOwn`) и принимают `TokenInterface` + предмет проверки.
- Внутри `Rule` используем перечисление прав и проверки владения/участия.
- `Rule` вызывается из `Voter`, не из контроллеров, шаблонов или Grant.
- `Rule` возвращает `bool` и не бросает исключения при отказе.

## Зависимости

- Разрешено: `TokenInterface`, `RoleHierarchyInterface`, публичные Application-компоненты (`QueryBus`), DTO Presentation.
- Запрещено: контроллеры, Twig, Grant, репозитории Domain, `EntityManager`, внешние сервисы без адаптеров.

## Расположение

```
apps/<app>/src/Module/<ModuleName>/Security/<SubjectName>/Rule.php
```

## Как используем

1. Внедряем `Rule` в [`Voter`](voter.md).
2. `Voter` передаёт в `Rule` `TokenInterface`, `ActionEnum` и `subject`.
3. `Rule` проверяет перечисление прав и дополнительные условия доступа.
4. `Rule` может обращаться к Application через `QueryBus` для фактов доступа.
5. Grant и шаблоны обращаются к `AuthorizationCheckerInterface`, а не к `Rule`.

## Пример

```php
<?php

declare(strict_types=1);

namespace ProjectName\Web\Module\Project\Security\Project;

use ProjectName\Common\Application\Component\QueryBus\QueryBusComponentInterface;
use ProjectName\Common\Module\Project\Application\UseCase\Query\ProjectUser\CheckMember\CheckMemberQuery;
use ProjectName\Common\Module\Project\Application\UseCase\Query\ProjectUser\CheckOwner\CheckOwnerQuery;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProjectRule
{
    public function __construct(
        private RoleHierarchyInterface $roleHierarchy,
        private QueryBusComponentInterface $queryBus,
    ) {
    }

    public function canCreate(TokenInterface $token, ?Uuid $userUuid): bool
    {
        return $this->canCreateAll($token) || $this->canCreateOwn($token, $userUuid);
    }

    public function canCreateAll(TokenInterface $token): bool
    {
        return $this->hasPermission(ProjectPermissionEnum::createAll, $token);
    }

    public function canCreateOwn(TokenInterface $token, ?Uuid $userUuid): bool
    {
        $hasPermission = $this->hasPermission(ProjectPermissionEnum::createOwn, $token);
        if (!$hasPermission) {
            return false;
        }
        if ($userUuid === null) {
            return true;
        }
        return $this->hasAccessToUserProjects($token, $userUuid);
    }

    public function canView(TokenInterface $token, ?Uuid $userUuid = null, ?Uuid $projectUuid = null): bool
    {
        return $this->canViewAll($token) || $this->canViewOwn($token, $userUuid, $projectUuid);
    }

    public function canViewAll(TokenInterface $token): bool
    {
        return $this->hasPermission(ProjectPermissionEnum::viewAll, $token);
    }

    public function canViewOwn(TokenInterface $token, ?Uuid $userUuid = null, ?Uuid $projectUuid = null): bool
    {
        $hasPermission = $this->hasPermission(ProjectPermissionEnum::viewOwn, $token);
        if (!$hasPermission) {
            return false;
        }

        if ($userUuid !== null) {
            $hasPermission = $this->hasAccessToUserProjects($token, $userUuid);
            if (!$hasPermission) {
                return false;
            }
        }

        if ($projectUuid !== null) {
            $hasPermission = $this->hasViewAccessToProject($token, $projectUuid);
            if (!$hasPermission) {
                return false;
            }
        }

        return true;
    }

    public function canEdit(TokenInterface $token, ?Uuid $projectUuid): bool
    {
        return $this->canEditAll($token) || $this->canEditOwn($token, $projectUuid);
    }

    public function canEditAll(TokenInterface $token): bool
    {
        return $this->hasPermission(ProjectPermissionEnum::editAll, $token);
    }

    public function canEditOwn(TokenInterface $token, ?Uuid $projectUuid): bool
    {
        $hasPermission = $this->hasPermission(ProjectPermissionEnum::editOwn, $token);
        if (!$hasPermission) {
            return false;
        }
        if ($projectUuid === null) {
            return true;
        }
        return $this->hasAccessToProject($token, $projectUuid);
    }

    public function canDelete(TokenInterface $token, ?Uuid $projectUuid): bool
    {
        return $this->canDeleteAll($token) || $this->canDeleteOwn($token, $projectUuid);
    }

    public function canDeleteAll(TokenInterface $token): bool
    {
        return $this->hasPermission(ProjectPermissionEnum::editAll, $token);
    }

    public function canDeleteOwn(TokenInterface $token, ?Uuid $projectUuid): bool
    {
        $hasPermission = $this->hasPermission(ProjectPermissionEnum::editOwn, $token);
        if (!$hasPermission) {
            return false;
        }
        if ($projectUuid === null) {
            return true;
        }
        return $this->hasAccessToProject($token, $projectUuid);
    }

    private function hasPermission(ProjectPermissionEnum $permissionEnum, TokenInterface $token): bool
    {
        return in_array(
            $permissionEnum->value,
            $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()),
            true,
        );
    }

    private function hasAccessToUserProjects(TokenInterface $token, Uuid $userUuid): bool
    {
        $user = $token->getUser();

        if ($user === null) {
            return false;
        }

        return $user->getUuid()->toString() === $userUuid->toString();
    }

    private function hasAccessToProject(TokenInterface $token, Uuid $projectUuid): bool
    {
        $user = $token->getUser();

        if ($user === null) {
            return false;
        }

        return $this->queryBus->query(new CheckOwnerQuery(
            projectUuid: $projectUuid,
            ownerUuid: $user->getUuid(),
        ));
    }

    private function hasViewAccessToProject(TokenInterface $token, Uuid $projectUuid): bool
    {
        $user = $token->getUser();

        if ($user === null) {
            return false;
        }

        return $this->queryBus->query(new CheckMemberQuery(
            projectUuid: $projectUuid,
            userUuid: $user->getUuid(),
        ));
    }
}
```

## Чек-лист для проведения ревью кода

- [ ] `Rule` объявлен `final readonly` и находится в каталоге `Security`.
- [ ] Все публичные методы начинаются с `can*` и возвращают `bool`.
- [ ] Используется перечисление прав, а не строки.
- [ ] Дополнительные проверки выполняются через Application (`QueryBus`) или Presentation сервисы.
- [ ] `Rule` вызывается из `Voter`, не из контроллеров, шаблонов или Grant.
- [ ] `Rule` не использует классы Domain/Infrastructure и не бросает исключения при отказе.
