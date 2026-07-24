<?php

namespace Test\DtoReuse\MessageContract\Module\Billing\Application\UseCase\Command\Deliver;

use Test\DtoReuse\MessageContract\Module\Billing\Application\Dto\EventPayloadDto;

final class DeliverCommandHandler
{
    public function __invoke(DeliverCommand $command): void
    {
        $event = $command->event;
        printf('%s', $event->eventId);
    }
}
