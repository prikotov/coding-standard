<?php

// Service class without interface — should error on line 5
namespace App\Module\Example\Service\WithServiceNoInterface;

final class SomeService
{
    public function doSomething(): void
    {
    }
}
