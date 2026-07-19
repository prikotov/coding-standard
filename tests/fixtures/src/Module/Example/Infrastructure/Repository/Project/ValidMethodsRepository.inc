<?php

declare(strict_types=1);

// RepositoryMethodSignatureSniff — fully valid repository: domain-only signatures, no Doctrine leak
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectCriteriaInterface;
use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;

final class ValidMethodsRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private readonly CriteriaMapper $criteriaMapper,
    ) {
        // constructor may depend on infrastructure collaborators — exempt from the leak check
    }

    public function save(ProjectModel $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }

    public function delete(ProjectModel $entity): void
    {
        $this->getEntityManager()->remove($entity);
    }

    public function getById(?int $id = null): ProjectModel
    {
        return $this->find($id);
    }

    public function getOneByCriteria(ProjectCriteriaInterface $criteria): ?ProjectModel
    {
        return $this->getQueryBuilderByCriteria($criteria)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<ProjectModel>
     */
    public function getByCriteria(ProjectCriteriaInterface $criteria): array
    {
        return $this->getQueryBuilderByCriteria($criteria)->getQuery()->getResult();
    }

    public function getCountByCriteria(ProjectCriteriaInterface $criteria): int
    {
        return 0;
    }

    private function getQueryBuilderByCriteria(ProjectCriteriaInterface $criteria): QueryBuilder
    {
        return $this->criteriaMapper->map($this, $criteria);
    }
}

class ProjectModel
{
}

class CriteriaMapper
{
    public function map(self $repository, ProjectCriteriaInterface $criteria): QueryBuilder
    {
        return $repository->createQueryBuilder('p');
    }
}
