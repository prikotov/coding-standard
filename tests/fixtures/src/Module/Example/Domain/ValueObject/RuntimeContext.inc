<?php

// Class in ValueObject namespace without Vo suffix — should error on line 5
namespace App\Module\Example\Domain\ValueObject;

final readonly class RuntimeContext
{
    public function __construct(
        public string $appId,
    ) {
    }
}
