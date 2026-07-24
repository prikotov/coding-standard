<?php

namespace Test\DtoReuse\MessageMisplaced\Module\Billing\Application\UseCase\Command\Deliver;

use Test\DtoReuse\MessageMisplaced\Module\Billing\Application\Service\SourceEvent\SourceEventDto;

final class DeliverCommand
{
    public function __construct(
        public string $subscriptionUuid,
        public SourceEventDto $event,
    ) {
    }
}
