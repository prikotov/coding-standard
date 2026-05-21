<?php

// Valid service: implements interface — should pass
namespace App\Module\Example\Service\WithValidService;

final class SomeService implements SomeServiceInterface
{
    public function doSomething(): void
    {
    }
}
