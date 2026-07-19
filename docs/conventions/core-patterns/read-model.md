---
name: Read Model
type: rule
description: Правила проектирования read-моделей (проекций) для агрегированных и денормализованных данных
---

# Read-модель (Read Model)

**Read-модель (Read Model)** — доменная модель для чтения: агрегаты, отчёты, балансы, счётчики,
денормализованные срезы. Это read-only проекция (нет `save()`/`delete()`) — не порождается
бизнес-операцией напрямую, а вычисляется из хранилища или агрегирующего запроса и не имеет
самостоятельного жизненного цикла, как сохраняемая сущность.

## Общие правила

- Размещается как [сущность](../layers/domain/entity.md) (постфикс `Model`, `Domain\Entity\*`) и читается через `*ReadRepositoryInterface` + [CriteriaMapper](../layers/infrastructure/criteria-mapper.md) по правилам [репозитория](../layers/domain/repository.md).
- **Только чтение:** `final readonly`, без мутаторов (в отличие от изменяемой entity); репозиторий не имеет write-side — `save()`/`delete()` в контракте отсутствуют.
- Применяется, когда данные удобнее представить вычисляемым срезом, а не сохраняемой сущностью.

## Расположение

- Модель — рядом с другими доменными моделями: `{ProjectName}\Common\Module\{ModuleName}\Domain\Entity\{GroupName?}\{Name}Model` (см. [Entity](../layers/domain/entity.md)).

## Способы реализации

Read-модель может формироваться из таблицы (`TABLE`), представления (`VIEW`) или агрегирующего запроса — выбор зависит от характера данных:

### A. На основе хранилища: представление или таблица-проекция

Read-модель оформляется как обычная сущность только для чтения, источником которой служит готовый набор данных.

- Источник — представление в миграции (агрегация `GROUP BY`/`SUM`/JOIN — на уровне схемы) либо физическая таблица-проекция, обновляемая в фоне.
- `#[ORM\Entity(readOnly: true)]` + `#[ORM\Table(...)]`; есть идентичность → работает `getById()`.
- Репозиторий не отличается от обычного репозитория сущности (`extends ServiceEntityRepository` + CriteriaMapper).
- **Когда применять:** агрегат стабилен, нужен `getById` или частые запросы на чтение; единый источник данных удобно держать в схеме БД.

### B. На основе агрегирующего запроса

Read-модель — `final readonly` класс без ORM-отображения: она не привязана к таблице, а собирается из DQL-запроса.

- Агрегация (`GROUP BY`/`SUM`/`COUNT`/JOIN) — в DQL через `QueryBuilder`/CriteriaMapper; результат (`->getResult()`) преобразуется в Read-модель на стороне PHP.
- `getById()` обычно не нужен; контракт ограничен `getByCriteria`/`getCountByCriteria`.
- Репозиторий работает через `EntityManagerInterface` + `QueryBuilder` (без `extends ServiceEntityRepository` — нет класса сущности для отображения через ORM). Внедрение `EntityManagerInterface` допустимо.
- **Когда применять:** агрегат вычисляется по входным параметрам запроса, материализовать невыгодно (динамические группировки, редкие отчёты), но это по-прежнему «коллекция записей с фильтрами».

## Пример — вариант B (query-formed)

### Модель — `final readonly` класс без ORM-маппинга

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Domain\Entity;

final readonly class UserAccessTokenUsageSummaryModel
{
    public function __construct(
        public int $userId,
        public \DateTimeImmutable $usageDay,
        public int $requestCount,
        /** numeric-string */
        public string $tokenTotal,
    ) {
    }
}
```

### Репозиторий — агрегирующий DQL через QueryBuilder + PHP-маппинг

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Infrastructure\Repository\UserAccessTokenUsageAudit;

use Doctrine\ORM\EntityManagerInterface;
use ProjectName\Common\Module\User\Domain\Entity\UserAccessTokenUsageSummaryModel;
use ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit\Criteria\UserAccessTokenUsageSummaryCriteriaInterface;
use ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit\UserAccessTokenUsageSummaryReadRepositoryInterface;
use ProjectName\Common\Module\User\Infrastructure\Repository\UserAccessTokenUsageAudit\Criteria\CriteriaMapper;

final class UserAccessTokenUsageSummaryReadRepository implements UserAccessTokenUsageSummaryReadRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CriteriaMapper $criteriaMapper,
    ) {
    }

    /**
     * @return list<UserAccessTokenUsageSummaryModel>
     */
    public function getByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): array
    {
        $rows = $this->criteriaMapper
            ->mapBase($this->entityManager->createQueryBuilder(), $criteria)
            ->select(
                'a.userId AS userId',
                'a.usageDay AS usageDay',
                'COUNT(a.id) AS requestCount',
                'SUM(a.tokensSpent) AS tokenTotal',
            )
            ->groupBy('a.userId, a.usageDay')
            ->getQuery()
            ->getResult();

        $models = [];
        foreach ($rows as $row) {
            $models[] = new UserAccessTokenUsageSummaryModel(
                userId: (int) $row['userId'],
                usageDay: new \DateTimeImmutable($row['usageDay']),
                requestCount: (int) $row['requestCount'],
                tokenTotal: (string) $row['tokenTotal'],
            );
        }

        return $models;
    }

    public function getCountByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): int
    {
        return (int) $this->criteriaMapper
            ->mapBase($this->entityManager->createQueryBuilder(), $criteria)
            ->select('COUNT(DISTINCT a.userId, a.usageDay)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
```

> `CriteriaMapper::mapBase()` строит `FROM`/`WHERE` по критериям (без агрегатов); агрегирующий `select`/`groupBy`
> добавляет репозиторий. Для варианта A репозиторий выглядит как обычный entity-репозиторий — см. пример в
> [репозитории](../layers/infrastructure/repository.md).

## Чек-лист для ревью

- [ ] Модель — `final`/`final readonly` класс `*Model` в `Domain\Entity\*`, без мутаторов; в контракте нет
      `save()`/`delete()`.
- [ ] Выбран корректный способ реализации: A (на основе хранилища) или B (на основе агрегирующего запроса).
- [ ] В репозитории нет `Doctrine\DBAL\Connection` и raw DBAL-вызовов — чтение через ORM `QueryBuilder`/CriteriaMapper.
- [ ] Если нужен raw SQL — это не Read-модель: либо вынесите агрегат в представление/агрегирующий запрос (варианты A/B), либо класс вне `Repository/`.
