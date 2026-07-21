<?php

declare(strict_types=1);

// RepositoryMethodSignatureSniff — public method returns a Doctrine type → DoctrineInfrastructureLeak
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;

final class LeakReturnRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function findActive(): QueryBuilder
    {
        return $this->createQueryBuilder('p');
    }
}
