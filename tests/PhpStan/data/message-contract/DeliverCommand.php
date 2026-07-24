<?php

namespace Test\DtoReuse\MessageContract\Module\Billing\Application\UseCase\Command\Deliver;

use Test\DtoReuse\MessageContract\Module\Billing\Application\Dto\EventPayloadDto;

final class DeliverCommand
{
    public function __construct(public EventPayloadDto $event) {}
}
