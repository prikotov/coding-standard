<?php

// Class in ValueObject namespace with Vo suffix — should pass
namespace App\Module\Example\Domain\ValueObject;

final readonly class EmailVo
{
    public function __construct(
        private string $value,
    ) {
    }
}
