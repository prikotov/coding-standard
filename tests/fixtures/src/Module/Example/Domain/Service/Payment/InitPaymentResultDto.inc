<?php

// Domain DTO in Domain/Service/{GroupName}/ — example of a correct contextual path
namespace App\Module\Example\Domain\Service\Payment;

final readonly class InitPaymentResultDto
{
    public function __construct(
        public int $amount,
    ) {
    }
}
