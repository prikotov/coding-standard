<?php

namespace Test\DtoReuse\MessageMisplaced\Module\Billing\Application\Service\SourceEvent;

final readonly class SourceEventDto
{
    public function __construct(public string $eventId) {}
}
