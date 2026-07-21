<?php

declare(strict_types=1);

// RepositoryStructureSniff — write-repository without ServiceEntityRepository is allowed
// (uses EntityManagerInterface directly). MustImplement / FlushForbidden still apply.
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectWriteRepositoryInterface;

final class ProjectWriteRepository implements ProjectWriteRepositoryInterface
{
    public function save(ProjectModel $entity): void
    {
        // persist without flush — transaction boundary is in the CommandHandler
    }
}

class ProjectModel
{
}
