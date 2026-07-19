<?php

declare(strict_types=1);

// RepositoryStructureSniff — no implements clause → MustImplementDomainRepositoryInterface
namespace App\Module\Example\Infrastructure\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

final class NoInterfaceRepository extends ServiceEntityRepository
{
}
