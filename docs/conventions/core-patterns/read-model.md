---
name: Read Model
type: rule
description: Правила проектирования read-моделей (проекций) для агрегированных и денормализованных данных
---

# Read-модель (Read Model)

**Read-модель (Read Model)** — иммутабельная доменная проекция данных для чтения: агрегаты, отчёты, счётчики,
денормализованные срезы. В отличие от обычной [сущности (Entity)](../layers/domain/entity.md), Read-модель
**только читается**: она не порождается бизнес-операцией и не сохраняется через `save()`, а формируется из
хранилища или агрегирующего запроса.

Read-модель решает задачу: дать агрегированные данные («саммари по фильтрам», «отчёт за период», «счётчики по
дням») в рамках тех же конвенций, что и обычные сущности — через [репозиторий](../layers/infrastructure/repository.md)
и [критерии](../layers/domain/criteria.md), **без raw SQL в PHP-коде**.

> См. также: [Сущность (Entity)](../layers/domain/entity.md), [Репозиторий (Repository)](../layers/domain/repository.md),
> [CriteriaMapper](../layers/infrastructure/criteria-mapper.md).

## Общие правила (для любой реализации)

- Read-модель — это `final` класс с постфиксом `Model` (`UserAccessTokenUsageSummaryModel`,
  `PaymentDailyReportModel`), размещаемый в `Domain\Entity\*` рядом с другими доменными моделями.
- **Иммутабельность:** только `readonly`-свойства и конструктор; никаких мутаторов и методов, меняющих состояние.
  Поведение — только чистые читающие методы (геттеры, `equals()`, производные скаляры).
- **Только чтение:** в доменном контракте **нет** `save()`/`delete()`/`persist` — Write-side к Read-модели не
  применяется. Обновление материализованных проекций (если есть) — отдельная инфраструктура
  (проектор/event-listener), не репозиторий.
- Доступ — через `*ReadRepositoryInterface` с read-контрактом (`getByCriteria`, `getCountByCriteria`,
  опционально `getOneByCriteria`/`getById` — если у проекции есть идентичность).
- Фильтры, сортировка, пагинация — через [критерии](../layers/domain/criteria.md) и
  [CriteriaMapper](../layers/infrastructure/criteria-mapper.md), как и для обычных сущностей.
- **Raw SQL запрещён в репозитории** ровно так же, как и для entity-репозиториев: `Doctrine\DBAL\Connection` не
  внедряется, вызовы `executeQuery`/`fetch*`/`prepare` недопустимы. Чтение — только через ORM `QueryBuilder`,
  CriteriaMapper и (для storage-backed варианта) ORM-маппинг.

## Способы реализации

Read-модель НЕ обязана быть таблицей или `CREATE VIEW` — выбор зависит от характера данных. Допустимые варианты:

### A. Storage-backed: SQL-view или таблица-проекция

Read-модель маппится Doctrine как обычная (read-only) сущность поверх готового набора данных.

- Источник данных — `CREATE VIEW` в миграции либо физическая таблица-проекция, обновляемая фоном.
  Агрегирующая логика (`GROUP BY`, `SUM`, JOIN-ы для денормализации) живёт **в DDL** миграции (для view) либо в
  проекторе (для таблицы-проекции).
- `#[ORM\Entity(readOnly: true)]` + `#[ORM\Table(...)]`. Doctrine не генерирует схему и не пишет в неё.
- Есть идентичность (`id`/`uuid`) → работает `getById()`. Для естественного составного ключа материализуйте
  surrogate-`id` в представлении (`ROW_NUMBER()`/хеш) либо используйте составной PK.
- Репозиторий `extends ServiceEntityRepository` + `CriteriaMapper`, идентичен обычному entity-репозиторию.

**Когда:** агрегат стабилен, фильтруется как «обычные строки», нужен `getById` или частый рерид. Самый зрелый
вариант — single source of truth живёт в схеме.

### B. Query-formed: агрегирующий запрос через QueryBuilder

Read-модель — это `final readonly` класс **без** `#[ORM\Entity]`: она не соответствует таблице, а собирается из
агрегирующего DQL-запроса.

- Агрегация (`GROUP BY`, `SUM`, `COUNT`, JOIN-ы) — в **DQL**, построенном через `QueryBuilder`/CriteriaMapper.
  Результат (`->getResult()` массив массивов/скаляров) маппится в Read-модель **на стороне PHP**.
- `getById()` обычно не нужен (нет естественной идентичности) — контракт ограничивается
  `getByCriteria`/`getCountByCriteria`.
- Репозиторий не наследует `ServiceEntityRepository` (нет entity-класса для маппинга); работает через
  `EntityManagerInterface` + `QueryBuilder` + CriteriaMapper.
- Raw SQL по-прежнему запрещён — только DQL через `QueryBuilder`.

**Когда:** агрегат вычисляется по входным параметрам запроса, его невыгодно материализовать (динамические
группировки, редкие отчёты), но он всё ещё «коллекция записей с фильтрами».

### C. PHP-агрегация (узкий случай)

Малые объёмы: репозиторий читает обычные entity-строки через CriteriaMapper и группирует/суммирует **в PHP**.
Read-модель — `final readonly` класс, конструируется в репозитории. Допустимо, только если объём данных
гарантированно мал (справочники, конфигурация, единичные счётчики); иначе — вариант A или B.

❗ Во всех трёх вариантах репозиторий остаётся без `Doctrine\DBAL\Connection` и без raw-DBAL-вызовов.

## Когда Read-модель, а когда Query-Service

Разделитель — «коллекция записей с фильтрами/пагинацией» vs «поток/мульти-источник»:

- **Read-модель** (любой из вариантов A/B/C) — результат представим как набор записей, фильтруется критериями,
  читается через `*ReadRepositoryInterface`.
- **Query-Service** (вне репозитория) — проекция потоковая, с динамическим окном (window functions по входным
  параметрам, без группировки в «записи»), либо собираемая из нескольких источников (DB + Redis + API). Тогда это
  отдельный инфраструктурный класс `*Query`/`*Projection` в `Infrastructure\...\Query\`, **без** суффикса
  `Repository` и **без** `*RepositoryInterface`. Raw SQL/Native Query в нём допустимы — сниффы его не трогают
  (это не репозиторий).

❗ Если для задачи кажется нужным raw SQL внутри `*Repository` — это сигнал, что задача на самом деле либо
Read-модель (постройте агрегат через DQL/QueryBuilder или вынесите его в view), либо Query-Service (вынесите
класс из `Repository/`).

## Зависимости

- **Разрешено:**
  - для storage-backed — ORM-mapping (`Doctrine\ORM\Mapping`);
  - скаляры, `DateTimeImmutable`, `Enum`, `Uuid`, Value Object (read-only);
  - другие Read-модели и [сущности](../layers/domain/entity.md) как read-only ссылки (например ссылка на `UserModel`).
- **Запрещено:**
  - мутаторы состояния, методы записи, `save()`/`delete()` в контракте;
  - `Doctrine\DBAL\Connection` и raw DBAL-вызовы в репозитории;
  - side-эффекты (HTTP, очередь, файлы, часы реального времени вне инжектируемого `Clock`).

## Расположение

- Модель-проекция (как [Entity](../layers/domain/entity.md)):

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

- SQL-view (только для варианта A) — в Doctrine-миграции (`CREATE VIEW`/`DROP VIEW`).
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
- Для варианта A изменение структуры проекции — миграция (DDL view) + обновление mapping; код репозитория и
  критериев не меняется.

## Пример — вариант A: storage-backed (SQL-view)

### A.1. SQL-view в миграции (агрегация на стороне БД)

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

### A.2. Read-модель (иммутабельная read-only сущность)

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

Реализация репозитория идентична обычному entity-репозиторию (`extends ServiceEntityRepository` + CriteriaMapper).

## Пример — вариант B: query-formed (агрегирующий DQL через QueryBuilder)

### B.1. Read-модель — `final readonly` класс без ORM-маппинга

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

### B.2. Реализация репозитория (QueryBuilder + PHP-маппинг результата)

```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\User\Infrastructure\Repository\UserAccessTokenUsageAudit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use ProjectName\Common\Exception\InfrastructureException;
use ProjectName\Common\Module\User\Domain\Entity\UserAccessTokenUsageAuditModel;
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
        $rows = $this->aggregateQuery($criteria)
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

    private function aggregateQuery(UserAccessTokenUsageSummaryCriteriaInterface $criteria): QueryBuilder
    {
        // Агрегация — в DQL через QueryBuilder; фильтры — через CriteriaMapper.
        return $this->criteriaMapper
            ->mapBase($this->entityManager->createQueryBuilder(), $criteria)
            ->select(
                'a.userId AS userId',
                'a.usageDay AS usageDay',
                'COUNT(a.id) AS requestCount',
                'SUM(a.tokensSpent) AS tokenTotal',
            )
            ->groupBy('a.userId, a.usageDay');
    }
}
```

> `CriteriaMapper::mapBase()` строит `FROM`/`WHERE` по критериям (без агрегатов); агрегирующий `select/groupBy`
> добавляется репозиторием. `EntityManagerInterface` внедряется легитимно (это ORM, не `DBAL\Connection`).

## Чек-лист для ревью

- [ ] Проекция оформлена как `final`/`final readonly` класс `*Model` в `Domain\Entity\*`.
- [ ] Иммутабельна: только `readonly`-свойства и читающие методы; мутаторов и `save()`/`delete()` в контракте нет.
- [ ] Выбран подходящий способ реализации: A (storage-backed, `#[ORM\Entity(readOnly: true)]` + view/проекция),
      B (query-formed, агрегация в DQL через QueryBuilder + PHP-маппинг) или C (PHP-агрегация для малых объёмов).
- [ ] В репозитории нет `Doctrine\DBAL\Connection` и raw DBAL-вызовов (`executeQuery`/`fetch*`/`prepare`); чтение —
      только через ORM `QueryBuilder`/CriteriaMapper (или ORM-маппинг для варианта A).
- [ ] Доступ — через `*ReadRepositoryInterface`; фильтры/сортировка/пагинация — через критерии.
- [ ] Если нужен raw SQL — это Query-Service вне `Repository/` (не `*Repository`, не `*RepositoryInterface`).
