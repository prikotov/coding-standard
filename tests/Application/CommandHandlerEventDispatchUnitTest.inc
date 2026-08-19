<?php

namespace App\Module\User\Application\UseCase\Command\User\Create;

class CreateCommandHandler
{
    public function __invoke(CreateCommand $command): void
    {
        $user = new UserModel($command->uuid);
        $this->userRepository->save($user);
    }
}

class FailIncompleteCommandHandler
{
    public function __invoke(FailIncompleteCommand $command): void
    {
        foreach ($this->projectSourceRepository->getByCriteria($command->criteria) as $projectSource) {
            $this->persistenceManager->persist($projectSource);
        }

        $this->persistenceManager->flush();
    }
}

class RemoveCommandHandler
{
    public function __invoke(RemoveCommand $command): void
    {
        $document = $this->documentRepository->getById($command->documentUuid);
        $this->persistenceManager->remove($document);
        $this->persistenceManager->flush();
    }
}

class PublishCommandHandler
{
    public function __invoke(PublishCommand $command): void
    {
        $project = $this->projectRepository->getById($command->projectUuid);
        $project->publish();
        $this->persistenceManager->flush();

        $this->eventBus->dispatch(new ProjectPublishedEvent($project->getUuid()));
    }
}

class TrackUsageCommandHandler
{
    public function __invoke(TrackUsageCommand $command): void
    {
        $event = new UsageTrackedEvent($command->userUuid, $command->amount);
        $this->eventBus->dispatch($event);
    }
}

class NotifyCommandHandler
{
    public function __invoke(NotifyCommand $command): void
    {
        $this->notifier->flushQueue($command->userUuid);
    }
}

class FindQueryHandler
{
    public function __invoke(FindQuery $query): UserDto
    {
        return $this->userRepository->getByCriteria($query->criteria);
    }
}
