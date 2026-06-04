<?php

// Domain DTO in Domain/Dto/ — wrong path: Domain/Dto/ is forbidden
namespace App\Module\Example\Domain\Dto;

final readonly class WrongPathDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
