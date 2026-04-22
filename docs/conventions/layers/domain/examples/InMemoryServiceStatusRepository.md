---
name: InMemoryServiceStatusRepository
type: rule
description: Пример in-memory реализации репозитория
---

# InMemoryServiceStatusRepository — пример In-memory реализации

> Полный пример in-memory репозитория, иллюстрирующий паттерны, описанные в [repository.md](../repository.md).

## Общие правила

- In-memory реализация используется для тестирования и прототипирования.
- Реализует доменный интерфейс (контракт) репозитория.
- Хранит данные в массиве, без I/O.

## Расположение

- Namespace: `App\Module\{Module}\Infrastructure\Repository\`

```php
<?php

declare(strict_types=1);

namespace App\Module\Health\Infrastructure\Repository\ServiceStatus;

use App\Exception\NotFoundException;
use App\Module\Health\Domain\Entity\ServiceStatusModel;
use App\Module\Health\Domain\Enum\ServiceCategoryEnum;
use App\Module\Health\Domain\Enum\ServiceStatusEnum;
use App\Module\Health\Domain\Repository\ServiceStatus\Criteria\ServiceStatusFindCriteria;
use App\Module\Health\Domain\Repository\ServiceStatus\ServiceStatusCriteriaInterface;
use App\Module\Health\Domain\Repository\ServiceStatus\ServiceStatusRepositoryInterface;
use App\Module\Health\Domain\ValueObject\ServiceNameVo;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory реализация репозитория статусов сервисов.
 * Используется для хранения результатов health checks в памяти.
 */
final class InMemoryServiceStatusRepository implements ServiceStatusRepositoryInterface
{
    /** @var array<string, ServiceStatusModel> */
    private array $storage = [];

    public function __construct()
    {
        $this->initializeDefaultServices();
    }

    private function initializeDefaultServices(): void
    {
        $this->addService('database', ServiceCategoryEnum::infrastructure);
        $this->addService('rabbitmq', ServiceCategoryEnum::infrastructure);
        $this->addService('minio', ServiceCategoryEnum::infrastructure);
    }

    private function addService(string $name, ServiceCategoryEnum $category): void
    {
        $nameVo = new ServiceNameVo($name);
        $model = ServiceStatusModel::create(
            name: $nameVo,
            category: $category,
            status: ServiceStatusEnum::operational,
        );
        $this->storage[$name] = $model;
    }

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

## Чек-лист для проведения ревью кода

- [ ] In-memory репозиторий реализует доменный интерфейс.
- [ ] Все методы контракта реализованы.
- [ ] Нет I/O и внешних зависимостей.
- [ ] Данные хранятся в массиве.
- [ ] Исключения соответствуют контракту (NotFoundException и т.д.).

