<?php

// Domain DTO outside Domain/Dto/ — correct contextual path.
namespace App\Module\Example\Domain\Calculator\Pricing;

final readonly class PricingResultDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
