<?php

// Class implements Domain\Service\ interface via FQCN but outside Service/ — should error on line 5
namespace App\Module\Example\Integration\Broadcaster;

final readonly class FqcnBroadcaster implements \App\Module\Example\Domain\Service\Broadcaster\NotificationBroadcasterServiceInterface
{
    public function broadcast(string $message): void
    {
    }
}
