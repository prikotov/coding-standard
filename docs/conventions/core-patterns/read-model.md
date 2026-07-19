---
name: Read Model
type: rule
description: Правила проектирования read-моделей (проекций) для агрегированных и денормализованных данных
---

# Read-модель (Read Model)

**Read-модель (Read Model)** — доменная сущность-проекция, представляющая агрегированные, вычисляемые или
денормализованные данные для чтения. В отличие от обычной [сущности (Entity)](../layers/domain/entity.md), Read-модель
**иммутабельна** и **только читается**: она не порождается бизнес-операцией и не сохраняется через `save()`,
а формируется на стороне БД.

Read-модель решает задачу: дать агрегированные данные («саммари по фильтрам», «отчёт за период», «счётчики по дням») в
рамках тех же конвенций, что и обычные сущности — через [репозиторий](../layers/infrastructure/repository.md) и
[критерии](../layers/domain/criteria.md), **без raw SQL в PHP-коде**.

> См. также: [Сущность (Entity)](../layers/domain/entity.md), [Репозиторий (Repository)](../layers/domain/repository.md),
> [CriteriaMapper](../layers/infrastructure/criteria-mapper.md).

## Общие правила

- Read-модель — это [сущность](../layers/domain/entity.md) с постфиксом `Model` (`UserAccessTokenUsageSummaryModel`,
  `PaymentDailyReportModel`), размещаемая в `Domain\Entity\*`.
- **Иммутабельность:** `final class` только с `readonly`-свойствами и конструктором; никаких мутаторов и
  бизнес-методов, меняющих состояние. Поведение — только чистые читающие методы (геттеры, `equals()`, производные
  скаляры).
- **Read-only маппинг:** `#[ORM\Entity(readOnly: true)]`. Doctrine не генерирует схему для такой сущности и не
  выполняет `INSERT/UPDATE/DELETE` для неё.
- **Источник данных — SQL-представление** (`CREATE VIEW`) или физическая таблица-проекция, обновляемая фоном.
  Агрегирующая логика (`GROUP BY`, `SUM`, `JOIN`-ы для денормализации) живёт **в DDL миграции**, а не в PHP.
- **Идентичность обязательна**, как у любой сущности: Read-модель имеет первичный ключ (`id` и/или `uuid`), по которому
  работает `getById()`. Для проекций с естественным составным ключом (например `userId` + `day`) ключ материализуется в
  представлении (surrogate `id` через `ROW_NUMBER()`/хеш либо пара колонок как составной PK).
- Доступ только на чтение — через `*ReadRepositoryInterface` с типовым read-контрактом
  (`getById`, `getOneByCriteria`, `getByCriteria`, `getCountByCriteria`); **методов `save()`/`delete()` нет**.
- Фильтры, сортировка и пагинация — через [критерии](../layers/domain/criteria.md) и
  [CriteriaMapper](../layers/infrastructure/criteria-mapper.md), как и для обычных сущностей.
- **Raw SQL запрещён в репозитории** ровно так же, как и для entity-репозиториев: `Doctrine\DBAL\Connection` не
  внедряется, вызовы `executeQuery`/`fetch*`/`prepare` недопустимы. Чтение — только через ORM `QueryBuilder` и
  CriteriaMapper поверх SQL-view.
- Write-side проекции (обновление физической таблицы-проекций по событиям) — отдельная инфраструктура
  (проектор/event-listener), не Read-модель и не репозиторий.

## Когда Read-модель, а когда Query-Service

Разделитель — «ведёт ли проекция себя как коллекция записей с фильтрами/пагинацией»:

- **Read-модель (через репозиторий + view)** — результат представим как набор строк с идентичностью и фильтруется
  стабильно: «саммари по дням», «отчёт по пользователям», «счётчики по проектам». Идеально ложится в конвенции.
- **Query-Service (вне репозитория)** — проекция потоковая, с динамическим окном, вычисляемая на лету (window functions
  по входным параметрам, без материализации), либо собираемая из нескольких источников (DB + Redis + API). Тогда это
  отдельный инфраструктурный класс `*Query`/`*Projection` в `Infrastructure\...\Query\`, **не** имеющий суффикса
  `Repository` и **не** реализующий `*RepositoryInterface`. Raw SQL/Native Query в таком классе допустимы — сниффы его
  не трогают (это не репозиторий).

❗ Если для задачи кажется нужным raw SQL внутри `*Repository` — это сигнал, что задача на самом деле либо Read-модель
(перенесите агрегацию в view), либо Query-Service (вынесите класс из `Repository/`).

## Зависимости

- **Разрешено:**
  - ORM-mapping (`Doctrine\ORM\Mapping`), скаляры, `DateTimeImmutable`, `Enum`, `Uuid`, Value Object (read-only);
  - другие Read-модели и [сущности](../layers/domain/entity.md) как read-only ссылки (например ссылка на `UserModel`).
- **Запрещено:**
  - мутаторы состояния, методы записи, `save()`/`delete()` в контракте;
  - `Doctrine\DBAL\Connection` и raw DBAL-вызовы в репозитории;
  - side-эффекты (HTTP, очередь, файлы, часы реального времени вне инжектируемого `Clock`).

## Расположение

- Сущность-проекция (как [Entity](../layers/domain/entity.md)):

```
{ProjectName}\Common\Module\{ModuleName}\Domain\Entity\{GroupName?}\{Name}Model
```

- Доменный read-контракт (как [Repository](../layers/domain/repository.md)):

```
{ProjectName}\Common\Module\{ModuleName}\Domain\Repository\{Name}\{Name}ReadRepositoryInterface
```

- Реализация и CriteriaMapper — в [Infrastructure](../layers/infrastructure.md):

```
{ProjectName}\Common\Module\{ModuleName}\Infrastructure\Repository\{Name}\{Name}ReadRepository
{ProjectName}\Common\Module\{ModuleName}\Infrastructure\Repository\{Name}\Criteria\CriteriaMapper
```

- SQL-view — в Doctrine-миграции (`CREATE VIEW`/`DROP VIEW`).

- Query-Service (альтернатива, вне репозитория):

```
{ProjectName}\Common\Module\{ModuleName}\Infrastructure\...\Query\{Name}Query
```

## Как используем

- [Query Handler](../layers/application/query-handler.md) читает Read-модель через `*ReadRepositoryInterface` и
  маппит в DTO — точно так же, как и обычные сущности.
- Фильтры («период», «пользователь», «измерение») передаются через критерии; сложные фильтры — в конкретных
  `*CriteriaMapper` (см. [CriteriaMapper](../layers/infrastructure/criteria-mapper.md)).
- Read-модель не возвращается наружу за пределы Application-слоя — на границе маппится в DTO.
- Изменение структуры проекции — это миграция (DDL view) + обновление mapping; код репозитория и критериев не меняется.

## Пример

### 1. SQL-view в миграции (агрегация на стороне БД)

```php
<?php

declare(strict_types=1);

namespace ProjectName\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE VIEW user_token_usage_summary AS
            SELECT
                ROW_NUMBER() OVER (ORDER BY user_id, usage_day) AS id,
                user_id,
                usage_day,
                COUNT(*) AS request_count,
                CAST(SUM(tokens_spent) AS UNSIGNED) AS token_total
            FROM user_access_token_usage_audit
            GROUP BY user_id, usage_day
            SQL,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW user_token_usage_summary');
    }
}
```

### 2. Read-модель (иммутабельная read-only сущность)

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'user_token_usage_summary')]
final class UserAccessTokenUsageSummaryModel
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private readonly int $id,
        #[ORM\Column]
        private readonly int $userId,
        #[ORM\Column(type: 'date')]
        private readonly \DateTimeImmutable $usageDay,
        #[ORM\Column]
        private readonly int $requestCount,
        /** numeric-string: сумма токенов как строка во избежание потери точности */
        #[ORM\Column]
        private readonly string $tokenTotal,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsageDay(): \DateTimeImmutable
    {
        return $this->usageDay;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getTokenTotal(): string
    {
        return $this->tokenTotal;
    }
}
```

### 3. Доменный read-контракт

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit;

use ProjectName\Common\Module\User\Domain\Entity\UserAccessTokenUsageSummaryModel;
use ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit\Criteria\UserAccessTokenUsageSummaryCriteriaInterface;
use Symfony\Component\Uid\Uuid;

interface UserAccessTokenUsageSummaryReadRepositoryInterface
{
    public function getById(?int $id = null, ?Uuid $uuid = null): UserAccessTokenUsageSummaryModel;

    public function getOneByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): ?UserAccessTokenUsageSummaryModel;

    /**
     * @return list<UserAccessTokenUsageSummaryModel>
     */
    public function getByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): array;

    public function getCountByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): int;
}
```

### 4. Реализация репозитория (ORM + CriteriaMapper)

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Infrastructure\Repository\UserAccessTokenUsageAudit;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use ProjectName\Common\Exception\InfrastructureException;
use ProjectName\Common\Module\User\Domain\Entity\UserAccessTokenUsageSummaryModel;
use ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit\Criteria\UserAccessTokenUsageSummaryCriteriaInterface;
use ProjectName\Common\Module\User\Domain\Repository\UserAccessTokenUsageAudit\UserAccessTokenUsageSummaryReadRepositoryInterface;
use ProjectName\Common\Module\User\Infrastructure\Repository\UserAccessTokenUsageAudit\Criteria\CriteriaMapper;

/**
 * @extends ServiceEntityRepository<UserAccessTokenUsageSummaryModel>
 */
final class UserAccessTokenUsageSummaryReadRepository
    extends ServiceEntityRepository
    implements UserAccessTokenUsageSummaryReadRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CriteriaMapper $criteriaMapper,
    ) {
        parent::__construct($registry, UserAccessTokenUsageSummaryModel::class);
    }

    public function getOneByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): ?UserAccessTokenUsageSummaryModel
    {
        return $this->getQueryBuilderByCriteria($criteria)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<UserAccessTokenUsageSummaryModel>
     */
    public function getByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): array
    {
        return $this->getQueryBuilderByCriteria($criteria)->getQuery()->getResult();
    }

    public function getCountByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): int
    {
        return (int) $this->getQueryBuilderByCriteria($criteria)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getQueryBuilderByCriteria(UserAccessTokenUsageSummaryCriteriaInterface $criteria): QueryBuilder
    {
        try {
            return $this->criteriaMapper->map($this, $criteria);
        } catch (QueryException $exception) {
            throw new InfrastructureException(
                message: sprintf('Failed to build query for %s: %s', 'UserAccessTokenUsageSummaryModel', $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
```

## Чек-лист для ревью

- [ ] Проекция оформлена как `final` read-only сущность `*Model` в `Domain\Entity\*` (`#[ORM\Entity(readOnly: true)]`).
- [ ] Сущность иммутабельна: только `readonly`-свойства и читающие методы; мутаторов и `save()`/`delete()` нет.
- [ ] Агрегация/денормализация вынесена в SQL-view или таблицу-проекцию (DDL в миграции), а не в PHP.
- [ ] Есть идентичность (`id`/`uuid`), по которой работает `getById()`.
- [ ] Доступ — через `*ReadRepositoryInterface` с типовым read-контрактом; реализация на `ServiceEntityRepository` + CriteriaMapper.
- [ ] В репозитории нет `Doctrine\DBAL\Connection` и raw DBAL-вызовов (`executeQuery`/`fetch*`/`prepare`).
- [ ] Фильтры/сортировка/пагинация — через критерии, как у обычных сущностей.
- [ ] Если задача требует raw SQL — это либо не Read-модель (перенесите агрегацию в view), либо Query-Service вне `Repository/`.
