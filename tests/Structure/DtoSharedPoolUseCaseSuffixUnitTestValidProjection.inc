<?php

// Valid: reusable projection DTO (no use-case suffix) in module shared pool.
namespace App\Common\Module\Example\Application\Dto;

final readonly class PaymentSummaryDto
{
    public function __construct(public string $id, public int $amount)
    {
    }
}
