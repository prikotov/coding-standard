<?php

namespace Test\DtoReuse\MessageContract\Module\Billing\Application\Dto;

final readonly class EventPayloadDto
{
    public function __construct(public string $eventId) {}
}
