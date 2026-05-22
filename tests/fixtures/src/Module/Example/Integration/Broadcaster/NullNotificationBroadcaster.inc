<?php

// Class implements Domain\Service\ interface but is outside Service/ directory — should error on line 5
namespace App\Module\Example\Integration\Broadcaster;

use App\Module\Example\Domain\Service\Broadcaster\NotificationBroadcasterServiceInterface;

final readonly class NullNotificationBroadcaster implements NotificationBroadcasterServiceInterface
{
    public function broadcast(string $message): void
    {
    }
}
