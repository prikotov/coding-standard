<?php

namespace App\Module\User\Application\UseCase\Command\User\Create;

class CreateCommandHandler
{
    public function __invoke(CreateCommand $command): void
    {
        $user = new UserModel($command->uuid);
        $this->userRepository->save($user);

        $this->eventBus->dispatch(new UserCreatedEvent($user->getUuid()));
    }
}

class DeleteCommandHandler
{
    public function __invoke(DeleteCommand $command): void
    {
        $user = $this->userRepository->getById($command->uuid);
        $this->persistenceManager->remove($user);
        $this->persistenceManager->flush();

        $this->eventBus->dispatch($this->eventFactory->createUserDeleted($user));
    }
}

class MarkUsedCommandHandler
{
    public function __invoke(MarkUsedCommand $command): void
    {
        $accessToken = $this->accessTokenRepository->getById($command->tokenUuid);
        $accessToken->markUsed($command->usedAt);
        $this->persistenceManager->flush();

        $this->eventBus?->dispatch(new AccessTokenUsedEvent($command->tokenUuid));
    }
}

// Внутренняя служебная команда без внешних наблюдателей — осознанное исключение.
// phpcs:ignore PrikotovCodingStandard.Application.CommandHandlerEventDispatch.MissingEventDispatch
class AcknowledgeCommandHandler
{
    public function __invoke(AcknowledgeCommand $command): void
    {
        $notification = $this->notificationRepository->getById($command->notificationUuid);
        $notification->acknowledge($command->userUuid);
        $this->persistenceManager->flush();
    }
}
