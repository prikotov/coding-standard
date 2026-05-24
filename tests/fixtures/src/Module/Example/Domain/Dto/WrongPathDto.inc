<?php

// Domain DTO in Domain/Dto/ — wrong path, should be Domain/Service/{Context}/
namespace App\Module\Example\Domain\Dto;

final readonly class WrongPathDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
