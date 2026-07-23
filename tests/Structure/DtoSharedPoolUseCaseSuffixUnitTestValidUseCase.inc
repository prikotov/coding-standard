<?php

// Valid: use-case-specific DTO placed next to the use case (not in shared pool).
namespace App\Common\Module\Example\Application\UseCase\Query\Registration\Initialize;

final readonly class InitializeRegistrationResultDto
{
    public function __construct(public string $id)
    {
    }
}
