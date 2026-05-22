<?php

// Factory class inside Service/ directory — should pass per convention
namespace App\Module\Example\Application\Service\SomeService;

final readonly class PaymentDtoFactory
{
    public function create(int $amount): object
    {
        return new \stdClass();
    }
}
