<?php

declare(strict_types=1);

// RepositoryStructureSniff — In-memory implementation: no ServiceEntityRepository needed.
// save() follows the conventional contract; no flush() is used.
namespace App\Module\Example\Infrastructure\Repository\ServiceStatus;

use App\Module\Example\Domain\Repository\ServiceStatus\ServiceStatusRepositoryInterface;

final class InMemoryServiceStatusRepository implements ServiceStatusRepositoryInterface
{
    public function save(ServiceStatusModel $entity): void
    {
        // stored in an in-memory array — no flush() used
    }
}
