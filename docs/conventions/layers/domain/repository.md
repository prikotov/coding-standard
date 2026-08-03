---
name: Domain Repository
type: rule
description: Правила создания доменных контрактов репозиториев
---

# Репозиторий (Repository)

**Репозиторий (Repository)** — контракт для сохранения и извлечения доменных сущностей из хранилища по доменным критериям.
Репозиторий изолирует доменную модель от деталей инфраструктуры и скрывает механику выборок (ORM, SQL и т.п.).

## Общие правила

- Репозиторий объявляется в `Domain\Repository\*` и работает на доменных сущностях (`*Model`).
- Для вычисляемых или сводных доменных значений допускается возвращать доменные [Value Object](../../core-patterns/value-object.md) (например, баланс на дату, сумма операций, статистика). Для этого создают отдельный репозиторий; возвращать VO из репозитория сущности запрещено. VO-репозиторий следует тем же правилам, что и репозиторий сущности (см. «Рекомендуемые методы»); разница лишь в типе элемента (`*Vo` вместо `*Model`) и отсутствии `save`/`delete`. Смешивать `*Model` и `*Vo` в одном репозитории нельзя.
- Интерфейс репозитория именуется `{EntityName}RepositoryInterface`.
- **Рекомендуемые методы** — для типовых операций используются следующие имена и сигнатуры (набор выбирается по потребности: не каждой сущности нужны все операции — например, read-only сущности не требуют `save`/`delete`):
  - `save(Entity $entity): void` — добавление/обновление сущности.
  - `getById(?int $id = null, ?Uuid $uuid = null): Entity` — загрузка по идентификатору или `UUID`, при отсутствии выбрасывает [`NotFoundExceptionInterface`](../../core-patterns/exception.md) (**не nullable**).
  - `getOneByCriteria(Criteria): ?Entity` — возвращает сущность или `null`.
  - `getByCriteria(Criteria): list<*Model|*Vo>` — коллекция доменных объектов (возможно пустая, не null).
  - `getCountByCriteria(Criteria): int` — подсчитать количество сущностей по критерию.
  - `exists(Criteria): bool` — проверить наличие сущности по критерию (без загрузки).
  - `delete(Entity $entity): void` — только для жёсткого удаления (hard-delete).
- Если в домене предусмотрен только **мягкое удаление** (soft-delete), метод `delete()` в репозитории не объявляется.
- При мягком удалении (soft-delete) используем бизнес-методы сущности (`markAsDeleted()`, `deactivate()` и др.).
- Для поддержки `CQRS` интерфейсы на чтение и запись рекомендуется разделять на `{EntityName}ReadRepositoryInterface` и `{EntityName}WriteRepositoryInterface`.
- Репозиторий не управляет `Unit of Work` (`flush`, `commit`). Контроль транзакции всегда на уровне `CommandHandler`/UseCase, чтобы обеспечить атомарность бизнес-операции.
- Транзакционная граница (`flush()`) устанавливается в [`CommandHandler`](../application/command-handler.md) через `PersistenceManagerInterface::flush()`; в методах репозитория вызывается только `persist()` (регистрация сущности в `Unit of Work`).
- Репозиторий маппит исключения ORM/SDK в доменные: `NotFoundExceptionInterface` для отсутствия сущности, [`InfrastructureExceptionInterface`](../../core-patterns/exception.md) для ошибок работы хранилища.
- Реализации интерфейса размещаются в слое [Infrastructure](../infrastructure.md). Интерфейс репозитория — часть домена, реализация — часть инфраструктуры.
- Правила построения инфраструктурных репозиториев и `CriteriaMapper` описаны в [разделе Infrastructure](../infrastructure/repository.md); при добавлении реализации следуем этому шаблону.
- Интерфейсы репозиториев зависят только от доменных типов (Entity/VO/Criteria); инфраструктурные классы (Doctrine, PDO и т.п.) в домен не протекают.

Критерии (Criteria) инкапсулируют фильтры/сортировки/пагинацию для выборок. Репозитории принимают интерфейсы критериев вместо именованных методов. См. [`Criteria`](../domain/criteria.md).

## Расположение

- Интерфейс в слое [Domain](../domain.md):

```php
namespace {ProjectName}\Common\Module\{ModuleName}\Domain\Repository\{EntityName}\{EntityName}RepositoryInterface
```

- Реализация в слое [Infrastructure](../infrastructure.md):

```php
namespace {ProjectName}\Common\Module\{ModuleName}\Infrastructure\Repository\{EntityName}\{EntityName}Repository
```

## Пример

### Репозиторий
```php
<?php

declare(strict_types=1);

namespace ProjectName\Common\Module\Billing\Domain\Repository\Payment;

use ProjectName\Common\Exception\NotFoundExceptionInterface;
use ProjectName\Common\Module\Billing\Domain\Entity\PaymentModel;
use ProjectName\Common\Module\Billing\Domain\Repository\Payment\PaymentCriteriaInterface;
use Symfony\Component\Uid\Uuid;

interface PaymentRepositoryInterface
{
    /**
     * @throws NotFoundExceptionInterface
     */
    public function getById(?int $id = null, ?Uuid $uuid = null): PaymentModel;

    public function getOneByCriteria(PaymentCriteriaInterface $criteria): ?PaymentModel;

    /**
     * @return PaymentModel[]
     */
    public function getByCriteria(PaymentCriteriaInterface $criteria): array;

    public function getCountByCriteria(PaymentCriteriaInterface $criteria): int;

    public function save(PaymentModel $model): void;
}
```

### Использование в `CommandHandler`
```php
<?php

declare(strict_types=1);

use ProjectName\Common\Component\Persistence\PersistenceManagerInterface;

final readonly class InitCommandHandler
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private PersistenceManagerInterface $persistenceManager,
    ) {}

    public function __invoke(InitCommand $c): void
    {
        $payment = new PaymentModel(
            user: $c->user,
            type: PaymentTypeEnum::user_top_up,
            amount: $c->amount,
        );

        $this->payments->save($payment);

        $this->persistenceManager->flush(); // транзакционная граница
    }
}
```

## Чек-лист для ревью

- [ ] Интерфейс лежит в Domain и зависит только от доменных типов.
- [ ] Реализация лежит в Infrastructure.
- [ ] Для типовых операций используются **только** рекомендованные имена методов (`getById`/`getByCriteria`/`getCountByCriteria`/`exists`/`getOneByCriteria`/`save`/`delete`).
- [ ] `getByCriteria()` возвращает коллекцию доменных объектов (`list<*Model|*Vo>`, возможна пустая, не null); `getOneByCriteria()` — `?Entity`.
- [ ] Исключения ORM маппятся в доменные интерфейсы исключений.
- [ ] Пагинация/сортировка — через `Criteria` (Limit/Offset/Sortable).
- [ ] Принимаемые и возвращаемые типы максимально конкретны; для массивов оформлен PHPDoc.
- [ ] `flush()` не вызывается в репозитории; `save()` делает только `persist()`.
