<?php

declare(strict_types=1);

// RepositoryStructureSniff — DBAL Connection smuggled via a class property (bypassing the
// constructor type-hint, as in $registry->getManagerForClass(...)->getConnection())
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalPropertyRepository implements ProjectRepositoryInterface
{
    private readonly Connection $connection;

    public function save(ProjectModel $entity): void
    {
    }
}

class ProjectModel
{
}
