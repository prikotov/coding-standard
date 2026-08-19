<?php

declare(strict_types=1);

namespace Common\Module\Example\Application\UseCase\Command\Publish;

final readonly class PublishCommandHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private PersistenceManagerInterface $persistenceManager,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(PublishCommand $command): void
    {
        $project = $this->projectRepository->getById($command->projectUuid);
        $project->publish();
        $this->persistenceManager->flush();

        $this->eventBus->dispatch(new ProjectPublishedEvent($command->projectUuid));
    }
}
