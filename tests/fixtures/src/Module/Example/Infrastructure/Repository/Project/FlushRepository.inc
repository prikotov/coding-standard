<?php

declare(strict_types=1);

// RepositoryStructureSniff — calls flush() inside a repository → FlushForbidden.
// save() has a valid conventional signature, so the only error is the flush() call.
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

final class FlushRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function save(ProjectModel $entity): void
    {
        $this->getEntityManager()->flush();
    }
}

class ProjectModel
{
}
