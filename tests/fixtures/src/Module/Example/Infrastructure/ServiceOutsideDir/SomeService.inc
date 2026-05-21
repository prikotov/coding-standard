<?php

// Service class outside Service/ directory — should error on line 5
namespace App\Module\Example\Infrastructure\ServiceOutsideDir;

final class SomeService
{
    public function doSomething(): void
    {
    }
}
