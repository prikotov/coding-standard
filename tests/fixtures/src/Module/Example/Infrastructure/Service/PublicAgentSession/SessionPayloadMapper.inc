<?php

// Mapper class inside Service/ directory — should pass per convention
namespace App\Module\Example\Infrastructure\Service\PublicAgentSession;

use App\Module\Example\Application\Dto\SessionDto;

final readonly class SessionPayloadMapper
{
    public function map(SessionDto $dto): array
    {
        return ['id' => $dto->id];
    }
}
