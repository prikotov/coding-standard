<?php

// Companion class — Service exists in same dir but has no interface
namespace App\Module\Example\Service\WithServiceNoInterface;

final readonly class SomeHelper
{
    public function __construct(
        public string $name,
    ) {
    }
}
