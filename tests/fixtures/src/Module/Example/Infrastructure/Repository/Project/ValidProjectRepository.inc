<?php

declare(strict_types=1);

// RepositoryStructureSniff — valid repository: extends ServiceEntityRepository + implements Domain interface.
// No public methods outside the conventional set.
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

final class ValidProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
}
