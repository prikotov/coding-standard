<?php

// Test class in ValueObject namespace — should pass (not a VO)
namespace App\Module\Example\Domain\ValueObject;

use PHPUnit\Framework\TestCase;

final class RuntimeContextVoTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
