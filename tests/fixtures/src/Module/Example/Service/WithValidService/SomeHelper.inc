<?php

// Companion class — valid Service exists in same directory — should pass
namespace App\Module\Example\Service\WithValidService;

final readonly class SomeHelper
{
    public function __construct(
        public string $name,
    ) {
    }
}
