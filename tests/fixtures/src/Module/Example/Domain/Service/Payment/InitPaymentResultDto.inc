<?php

// Domain DTO in Domain/Service/{GroupName}/ — correct path
namespace App\Module\Example\Domain\Service\Payment;

final readonly class InitPaymentResultDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
