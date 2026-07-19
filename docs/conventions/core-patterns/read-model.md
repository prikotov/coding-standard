---
name: Read Model
type: rule
description: Правила проектирования read-моделей (проекций) для агрегированных и денормализованных данных
---

# Read-модель (Read Model)

**Read-модель (Read Model)** — [сущность](../layers/domain/entity.md) для чтения: агрегаты, отчёты, счётчики,
денормализованные срезы. Это проекция данных, а не бизнес-объект: она **только читается**, не порождается
бизнес-операцией, не сохраняется через `save()` и формируется из хранилища или агрегирующего запроса.

Read-модель подчиняется конвенциям [сущности](../layers/domain/entity.md) и [репозитория](../layers/domain/repository.md)
без изменений; этот документ фиксирует только **отличия и способы реализации**.

> См. также: [Сущность (Entity)](../layers/domain/entity.md), [Репозиторий (Repository)](../layers/domain/repository.md),
> [CriteriaMapper](../layers/infrastructure/criteria-mapper.md).

## Общие правила

- Это [сущность](../layers/domain/entity.md) с теми же структурными правилами (`final`/`readonly`, постфикс `Model`, `Domain\Entity\*`) и тем же read-контрактом (`*ReadRepositoryInterface` + [CriteriaMapper](../layers/infrastructure/criteria-mapper.md), без raw SQL и без `Doctrine\DBAL\Connection` — см. [репозиторий](../layers/domain/repository.md)). Ниже — только отличия.
- **Только чтение:** в доменном контракте **нет** `save()`/`delete()`/`persist`. Write-side к Read-модели не применяется. Обновление материализованной проекции (если есть) — отдельная инфраструктура (проектор/event-listener), не репозиторий.
- **Источник — данные, а не бизнес-операция:** модель формируется агрегатом на стороне БД, агрегирующим запросом или (редко) PHP-агрегацией, а не конструируется в домене и не persist'ится.
- Применяется, когда данные нужны «как есть» для чтения, но не соответствуют бизнес-сущности 1:1: агрегаты по измерениям, саммари за период, счётчики, денормализованные срезы для списков/отчётов.

## Расположение

- Модель — рядом с другими доменными моделями: `{ProjectName}\Common\Module\{ModuleName}\Domain\Entity\{GroupName?}\{Name}Model` (см. [Entity](../layers/domain/entity.md)).
- Read-контракт, реализация и CriteriaMapper — по правилам [репозитория](../layers/domain/repository.md), с суффиксом `*ReadRepository`.
- SQL-view (только для варианта A) — в Doctrine-миграции.
- Query-Service (альтернатива, вне репозитория): `{ProjectName}\Common\Module\{ModuleName}\Infrastructure\...\Query\{Name}Query`.

## Способы реализации

Read-модель НЕ обязана быть таблицей или `CREATE VIEW` — выбор зависит от характера данных:

### A. Storage-backed: SQL-view или таблица-проекция

Read-модель маппится Doctrine как обычная read-only сущность поверх готового набора данных.

- Источник — `CREATE VIEW` в миграции (агрегация `GROUP BY`/`SUM`/JOIN — в DDL) либо физическая таблица-проекция,
  обновляемая фоном.
- `#[ORM\Entity(readOnly: true)]` + `#[ORM\Table(...)]`; есть идентичность → работает `getById()`.
- Репозиторий идентичен обычному entity-репозиторию (`extends ServiceEntityRepository` + CriteriaMapper).
- **Когда:** стабильный агрегат, нужен `getById` или частый рерид; source of truth удобно держать в схеме.

### B. Query-formed: агрегирующий запрос через QueryBuilder

Read-модель — `final readonly` класс **без** ORM-маппинга: не соответствует таблице, а собирается из DQL.

- Агрегация (`GROUP BY`/`SUM`/`COUNT`/JOIN) — в **DQL** через `QueryBuilder`/CriteriaMapper; результат
  (`->getResult()`) маппится в Read-модель **на стороне PHP**.
- `getById()` обычно не нужен; контракт ограничен `getByCriteria`/`getCountByCriteria`.
- Репозиторий работает через `EntityManagerInterface` + `QueryBuilder` (без `extends ServiceEntityRepository` —
  нет entity-класса для маппинга). `EntityManagerInterface` внедряется легитимно (это ORM, не `DBAL\Connection`).
- **Когда:** агрегат вычисляется по входным параметрам запроса, невыгодно материализовать (динамические
  группировки, редкие отчёты), но всё ещё «коллекция записей с фильтрами».

### C. PHP-агрегация (узкий случай)

Малые объёмы: репозиторий читает обычные entity-строки через CriteriaMapper и группирует/суммирует **в PHP**.
Read-модель — `final readonly` класс, конструируется в репозитории. Допустимо только при гарантированно малом
объёме данных (справочники, единичные счётчики); иначе — вариант A или B.

❗ Во всех вариантах в репозитории нет `Doctrine\DBAL\Connection` и raw-DBAL-вызовов.

## Граница с Query-Service

Если проекция **потоковая** (window functions по входным параметрам, без группировки в «записи») или
**мульти-источник** (DB + Redis + API) — это не Read-модель, а Query-Service: отдельный класс `*Query`/`*Projection`
в `Infrastructure\...\Query\`, **без** суффикса `Repository` и **без** `*RepositoryInterface`. Raw SQL/Native Query
в нём допустимы — сниффы его не трогают.

❗ Raw SQL внутри `*Repository` — сигнал, что задача на самом деле либо Read-модель (постройте агрегат через
DQL/QueryBuilder или вынесите в view), либо Query-Service (вынесите класс из `Repository/`).

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
- [ ] Выбран корректный способ реализации: A (storage-backed), B (query-formed) или C (PHP-агрегация для малых объёмов).
- [ ] В репозитории нет `Doctrine\DBAL\Connection` и raw DBAL-вызовов — чтение через ORM `QueryBuilder`/CriteriaMapper.
- [ ] Если нужен raw SQL — это Query-Service вне `Repository/` (не `*Repository`, не `*RepositoryInterface`).
