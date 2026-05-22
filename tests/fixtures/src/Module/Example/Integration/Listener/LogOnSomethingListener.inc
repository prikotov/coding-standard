<?php

// Class implements non-Service interface outside Service/ — should pass
namespace App\Module\Example\Integration\Listener;

use App\Module\Example\Domain\Event\SomethingHappenedEvent;

final readonly class LogOnSomethingListener
{
    public function __invoke(SomethingHappenedEvent $event): void
    {
    }
}
