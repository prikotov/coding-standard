<?php

declare(strict_types=1);

namespace Test\DirectHandlerInvocation;

use Test\DirectHandlerInvocation\Module\Document\Application\UseCase\Command\MarkReady\MarkReadyCommandHandler;
use Test\DirectHandlerInvocation\Module\Document\Application\UseCase\Query\FindDocument\FindDocumentQueryHandler;

final readonly class ExplicitInvokeCalls
{
    public function __construct(
        private MarkReadyCommandHandler $commandHandler,
        private FindDocumentQueryHandler $queryHandler,
    ) {
    }

    public function run(object $command, object $query): void
    {
        $this->commandHandler->__invoke($command);
        $this->queryHandler->__invoke($query);
    }
}
