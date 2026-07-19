<?php

declare(strict_types=1);

// RepositoryStructureSniff — In-memory implementation: no ServiceEntityRepository needed
namespace App\Module\Example\Infrastructure\Repository\ServiceStatus;

use App\Module\Example\Domain\Repository\ServiceStatus\ServiceStatusRepositoryInterface;

final class InMemoryServiceStatusRepository implements ServiceStatusRepositoryInterface
{
    public function doSave(object $entity): void
    {
        // stored in an in-memory array — no flush() used
    }
}
