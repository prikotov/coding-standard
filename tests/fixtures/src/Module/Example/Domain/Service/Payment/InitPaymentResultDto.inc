<?php

// Domain DTO in Domain/Service/{Context}/ — correct path
namespace App\Module\Example\Domain\Service\Payment;

final readonly class InitPaymentResultDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
