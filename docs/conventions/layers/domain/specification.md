---
name: Specification
type: rule
description: Правила использования паттерна Specification для формализации бизнес-правил
---

# Спецификация (Specification pattern)

**Спецификация ([Specification](https://martinfowler.com/apsupp/spec.pdf))** — артефакт доменного слоя, формализующий
бизнес-правило или условие применительно к объекту домена.

## Общие правила

- Не взаимодействует с базой данных и внешними сервисами.
- Не хранит изменяемое состояние (stateless). Конфигурация допускается через конструктор, бизнес-данные передаются в
  `isSatisfiedBy()`.
- Возвращает только `bool`.
- Каждая спецификация реализует одну публичную функцию: `isSatisfiedBy(mixed $value): bool`.
- Название спецификации должно ясно отражать проверяемое правило (например, `InviteResendAllowedSpecification`).
- Внедряется в потребителей через механизм DI.
- Если спецификация комбинируется с другими, можем использовать композицию (AndSpecification, OrSpecification,
  NotSpecification).

## Зависимости

- Разрешено внедрение сервисов, мапперов, фабрик и других спецификаций из своего домена (в пределах одного bounded context).
- Метод `isSatisfiedBy()` должен принимать только значения из текущего домена — примитивы, DTO, VO или Entity.
- ❗ **Запрещено** передавать данные из других модулей напрямую.
- ❗ **Запрещено** внедрять репозитории и сервисы инфраструктурного слоя.

## Расположение

- В слое [Domain](../domain.md):

```php
{ProjectName}\Common\Module\{ModuleName}\Domain\Specification\{Context}\{SpecificationName}Specification
```

`{Context}` используется при необходимости логически сгруппировать спецификации внутри модуля.

## Как используем

- Спецификации определяются в **Domain-слое**, могут использоваться в **Application-слое** (Command/Query Handler) и в доменных сервисах.
- Передаются через конструктор и используются вызовом метода `isSatisfiedBy()`.
- ❗ **Запрещено** использовать спецификации из других модулей напрямую.
- ❗ **Запрещено** оборачивать спецификацию в сервис без веской причины. Если спецификация — это чистое бизнес-правило без внешних зависимостей, внедряйте её в хендлер напрямую.

## Пример спецификации

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Domain\Specification\TeamMembership;

use DateTimeImmutable;

final readonly class InviteResendAllowedSpecification
{
    public function __construct(
        private int $intervalMinutes = 15,
    ) {
    }

    public function isSatisfiedBy(?DateTimeImmutable $invitedAt, DateTimeImmutable $now): bool
    {
        if ($invitedAt === null) {
            return true;
        }

        return $invitedAt->modify(sprintf('+%d minutes', $this->intervalMinutes)) <= $now;
    }
}
```

## Пример использования в Command Handler

Спецификация внедряется в хендлер напрямую — без прослойки в виде сервиса:

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Application\UseCase\Command\TeamMembership\ResendInvite;

use ProjectName\Common\Component\Event\EventBusInterface;
use ProjectName\Common\Component\Persistence\PersistenceManagerInterface;
use ProjectName\Common\Exception\ValidationException;
use ProjectName\Common\Module\User\Domain\Repository\TeamMembership\TeamMembershipRepositoryInterface;
use ProjectName\Common\Module\User\Domain\Service\Invitation\SendTeamInvitationEmailServiceInterface;
use ProjectName\Common\Module\User\Domain\Specification\TeamMembership\InviteResendAllowedSpecification;
use ProjectName\Common\Module\User\Domain\ValueObject\Invitation\TeamInvitationEmailContext;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResendInviteCommandHandler
{
    public function __construct(
        private TeamMembershipRepositoryInterface $membershipRepository,
        private PersistenceManagerInterface $persistenceManager,
        private SendTeamInvitationEmailServiceInterface $emailService,
        private InviteResendAllowedSpecification $resendAllowedSpecification,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ResendInviteCommand $command): void
    {
        $membership = $this->membershipRepository->getById($command->membershipId);
        $now = $this->clock->now();

        if (!$this->resendAllowedSpecification->isSatisfiedBy($membership->getInvitedAt(), $now)) {
            throw new ValidationException('Invitation can only be resent 15 minutes after the previous one.');
        }

        $membership->resendInvite($now);
        $this->persistenceManager->flush();

        $this->emailService->send(
            $membership->getEmail(),
            new TeamInvitationEmailContext($membership),
        );
    }
}
```

## Чек-лист для проведения ревью кода

- [ ] Specification находится в Domain-слое.
- [ ] Specification реализует единый метод проверки `isSatisfiedBy`.
- [ ] Specification не содержит I/O и зависимостей от инфраструктуры.
- [ ] Specification легко комбинируется с другими Specification.
- [ ] Логика Specification покрывается unit-тестами.
