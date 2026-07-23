<?php

// Violation: use-case-specific DTO (ResultDto suffix) in module shared pool.
namespace App\Common\Module\Example\Application\Dto;

final readonly class InitializeRegistrationResultDto
{
    public function __construct(public string $id)
    {
    }
}
