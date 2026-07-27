---
name: Repository
type: rule
description: Правила реализации репозиториев
---

# Репозиторий (Repository)

**Репозиторий** — инфраструктурная реализация доменного репозитория, которая скрывает работу с БД.

> **Фильтрация:** Для изоляции условий выборки используется [`CriteriaMapper`](criteria-mapper.md).

> См. также: [доменный контракт репозитория](../domain/repository.md), [`CriteriaMapper`](criteria-mapper.md)

## Общие правила

1. Каждый репозиторий наследует `ServiceEntityRepository` и реализует доменный интерфейс `{EntityName}RepositoryInterface`.
2. Репозиторий не содержит условных запросов напрямую; все фильтры строятся через [`CriteriaMapper`](criteria-mapper.md).
3. Репозиторий оперирует только доменными сущностями и критериями; никаких зависимостей из Application/Presentation.
4. Исключения Doctrine маппятся в [`NotFoundException`](../../core-patterns/exception.md) или [`InfrastructureException`](../../core-patterns/exception.md).

## Зависимости

- Разрешено: `ManagerRegistry`, [`CriteriaMapper`](criteria-mapper.md), доменные сущности и критерии, сервисы Doctrine.
- Запрещено: сервисы Application/Presentation, внешние API.

## Расположение

```
{ProjectName}\Common\Module\{ModuleName}\Infrastructure\Repository\{Entity}\{Entity}Repository.php
```

## Как используем

- В Application слое используем только доменный интерфейс; инфраструктурная реализация не просачивается наружу.

## Пример

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Project\Infrastructure\Repository\Project;

use ProjectName\Common\Exception\InfrastructureException;
use ProjectName\Common\Exception\NotFoundException;
use ProjectName\Common\Module\Project\Domain\Entity\ProjectModel;
use ProjectName\Common\Module\Project\Domain\Repository\Project\ProjectCriteriaInterface;
use ProjectName\Common\Module\Project\Domain\Repository\Project\ProjectRepositoryInterface;
use ProjectName\Common\Module\Project\Infrastructure\Repository\Project\Criteria\CriteriaMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final class ProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CriteriaMapper $criteriaMapper,
    ) {
        parent::__construct($registry, ProjectModel::class);
    }

    /**
     * @inheritDoc
     */
    public function save(ProjectModel $model): void
    {
        $this->getEntityManager()->persist($model);
    }

    /**
     * @inheritDoc
     */
    public function getById(?int $id = null, ?Uuid $uuid = null): ProjectModel
    {
        if ($id === null && $uuid === null) {
            throw new InvalidArgumentException(
                sprintf('Either an ID or a UUID must be provided for entity %s.', $this->getEntityName()),
            );
        }

        if ($id !== null) {
            return $this->find($id) ?? throw new NotFoundException(sprintf(
                'Cannot find %s with id %s',
                $this->getEntityName(),
                $id,
            ));
        }

        if ($uuid !== null) {
            return $this->createQueryBuilder('p')
                ->andWhere('p.uuid = :uuid')
                ->setParameter('uuid', $uuid, UuidType::NAME)
                ->getQuery()
                ->getOneOrNullResult() ?? throw new NotFoundException(sprintf(
                    'Cannot find %s with uuid %s',
                    $this->getEntityName(),
                    $uuid,
                ));
        }

        throw new NotFoundException(sprintf('%s not found', $this->getEntityName()));
    }

    public function getOneByCriteria(ProjectCriteriaInterface $criteria): ?ProjectModel
    {
        return $this
            ->getQueryBuilderByCriteria($criteria)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * @inheritDoc
     */
    public function getByCriteria(ProjectCriteriaInterface $criteria): array
    {
        return $this
            ->getQueryBuilderByCriteria($criteria)
            ->getQuery()
            ->getResult();
    }

    private function getQueryBuilderByCriteria(ProjectCriteriaInterface $criteria): QueryBuilder
    {
        try {
            return $this->criteriaMapper->map($this, $criteria);
        } catch (QueryException $exception) {
            throw new InfrastructureException(
                message: sprintf('Failed to build query for %s: %s', $this->getEntityName(), $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
```

## Чек-лист для проведения ревью кода

- [ ] Репозиторий реализует доменный интерфейс (контракт).
- [ ] Маппинг критериев изолирован (CriteriaMapper или аналогичный).
- [ ] Нет утечек Doctrine `QueryBuilder` за пределы репозитория.
- [ ] Транзакции управляются на уровне Application-слоя, а не в репозитории.

## Внутрипроцессная реализация (in-memory) для тестов

Для модульных тестов и сценариев, где не требуется персистентность, используется внутрипроцессная реализация репозитория.
Данные хранятся в PHP-массиве внутри объекта, что обеспечивает высокую скорость и изоляцию от БД.
Внутрипроцессный репозиторий подчиняется тем же правилам, что и Doctrine-реализация: реализует доменный интерфейс, оперирует только доменными сущностями и критериями, не вызывает `flush()`.

### Пример внутрипроцессного репозитория

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Health\Infrastructure\Repository\ServiceStatus;

use ProjectName\Common\Exception\NotFoundException;
use ProjectName\Common\Module\Health\Domain\Entity\ServiceStatusModel;
use ProjectName\Common\Module\Health\Domain\Repository\ServiceStatus\Criteria\ServiceStatusFindCriteria;
use ProjectName\Common\Module\Health\Domain\Repository\ServiceStatus\ServiceStatusCriteriaInterface;
use ProjectName\Common\Module\Health\Domain\Repository\ServiceStatus\ServiceStatusRepositoryInterface;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory реализация репозитория статусов сервисов.
 * Используется для тестов и сценариев без требования персистентности.
 */
final class InMemoryServiceStatusRepository implements ServiceStatusRepositoryInterface
{
    /** @var array<string, ServiceStatusModel> */
    private array $storage = [];

    #[Override]
    public function getById(?int $id = null, ?Uuid $uuid = null): ServiceStatusModel
    {
        if ($id !== null) {
            foreach ($this->storage as $model) {
                if ($model->getId() === $id) {
                    return $model;
                }
            }
            throw new NotFoundException(sprintf('Service status with ID "%d" not found.', $id));
        }

        if ($uuid !== null) {
            foreach ($this->storage as $model) {
                if ($model->getUuid()->equals($uuid)) {
                    return $model;
                }
            }
            throw new NotFoundException(sprintf('Service status with UUID "%s" not found.', $uuid->toString()));
        }

        throw new NotFoundException('Service status not found: no ID or UUID provided.');
    }

    #[Override]
    public function getOneByCriteria(ServiceStatusCriteriaInterface $criteria): ?ServiceStatusModel
    {
        $results = $this->getByCriteria($criteria);
        return $results[0] ?? null;
    }

    #[Override]
    public function getByCriteria(ServiceStatusCriteriaInterface $criteria): array
    {
        $results = [];
        foreach ($this->storage as $model) {
            if ($this->matchesCriteria($model, $criteria)) {
                $results[] = $model;
            }
        }
        return $results;
    }

    #[Override]
    public function getCountByCriteria(ServiceStatusCriteriaInterface $criteria): int
    {
        return count($this->getByCriteria($criteria));
    }

    #[Override]
    public function exists(ServiceStatusCriteriaInterface $criteria): bool
    {
        return $this->getCountByCriteria($criteria) > 0;
    }

    #[Override]
    public function save(ServiceStatusModel $serviceStatus): void
    {
        $name = $serviceStatus->getName();
        $this->storage[$name] = $serviceStatus;
    }

    #[Override]
    public function delete(ServiceStatusModel $serviceStatus): void
    {
        $name = $serviceStatus->getName();
        unset($this->storage[$name]);
    }

    private function matchesCriteria(ServiceStatusModel $model, ServiceStatusCriteriaInterface $criteria): bool
    {
        if (!($criteria instanceof ServiceStatusFindCriteria)) {
            return true;
        }

        $name = $criteria->getName();
        if ($name !== null && $model->getName() !== $name) {
            return false;
        }

        $category = $criteria->getCategory();
        if ($category !== null && $model->getCategory() !== $category) {
            return false;
        }

        $status = $criteria->getStatus();
        if ($status !== null && $model->getStatus() !== $status) {
            return false;
        }

        return true;
    }
}
```

### Особенности внутрипроцессной реализации

1. **Временное хранение** — данные существуют только во время жизни процесса PHP.
2. **Быстродействие** — нет сетевых запросов к БД, всё в памяти.
3. **Идеально для тестов** — изоляция от БД, детерминированные результаты.
4. **Ключ хранилища** — выбирается на основе бизнес-логики (например, уникальное имя сервиса).

Полный пример: [`InMemoryServiceStatusRepository.php`](examples/InMemoryServiceStatusRepository.php)
