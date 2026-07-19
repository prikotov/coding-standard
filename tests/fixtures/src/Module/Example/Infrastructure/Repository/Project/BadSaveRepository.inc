<?php

declare(strict_types=1);

// RepositoryMethodSignatureSniff — save() must take a single *Model and return void
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

final class BadSaveRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function save(string $notAnEntity): int
    {
        return 0;
    }

    public function delete(object $entity): void
    {
    }

    public function getCountByCriteria(object $criteria): string
    {
        return '';
    }

    public function getByCriteria(object $criteria): string
    {
        return '';
    }
}
