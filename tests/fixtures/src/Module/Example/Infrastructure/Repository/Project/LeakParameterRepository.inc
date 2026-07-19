<?php

declare(strict_types=1);

// RepositoryMethodSignatureSniff — public method takes a Doctrine type → DoctrineInfrastructureLeak
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;

final class LeakParameterRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function refresh(Connection $connection): void
    {
    }
}
