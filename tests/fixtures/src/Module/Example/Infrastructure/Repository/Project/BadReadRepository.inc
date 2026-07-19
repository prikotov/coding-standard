<?php

declare(strict_types=1);

// RepositoryMethodSignatureSniff — getById() must return a non-nullable entity; getOneByCriteria() — nullable
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

final class BadReadRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function getById(): ?ProjectModel
    {
        return null;
    }

    public function getOneByCriteria(object $criteria): ProjectModel
    {
        throw new \RuntimeException();
    }
}

class ProjectModel
{
}
