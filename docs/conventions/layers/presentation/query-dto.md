---
name: Query DTO
type: rule
description: Правила создания Query DTO презентационного слоя
---

# Query DTO презентационного слоя (Presentation Query DTO)

## Определение

**Presentation Query DTO** — транспортная модель параметров строки запроса, которую контроллер получает через `MapQueryString` или аналогичный связыватель (binder) до вызова слоя Application.

## Общие правила

- Query DTO объявляем как `final readonly class`.
- DTO остаётся `data-only`: допускаются только свойства конструктора и декларативные метаданные (declarative metadata).
- Метаданные уровня свойства (property-level) через `Assert` разрешены, если они описывают транспортный контракт query-параметров.
- Для привязки строки запроса (query binding) допустимо сохранять сырые входные значения (`string|mixed|null`), если это нужно для корректной транспортной валидации до последующего маппинга. Например, параметры пагинации или сортировки могут приходить как строки.
- В Query DTO запрещены `Callback`, `validate*()`, логика в конструкторе, нормализация и выброс исключений.
- Валидацию связанных полей query-параметров выносим во внешнее ограничение уровня класса (class-level constraint).

## Зависимости

### Разрешено

- Скаляры, `BackedEnum`, `UuidInterface/Uuid`, `DateTimeImmutable`.
- `OpenAPI` и метаданные `Symfony Validator`.
- Пользовательские ограничения presentation для контракта уровня query.

### Запрещено

- Сервисы, репозитории, `QueryBus`/`CommandBus`, файловая система, сеть и любой I/O во время исполнения.
- Domain `Entity`/`VO` и реализации слоя Infrastructure.

## Расположение

- Локальный query DTO (controller-local):

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>QueryDto.php
```

## Пример

```php
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListProjectQueryDto
{
    public function __construct(
        #[Assert\Regex('/^\d+$/')]
        public ?string $page = null,
        #[Assert\Regex('/^\d+$/')]
        public ?string $limit = null,
        #[Assert\Choice(['createdAt', 'name'])]
        public ?string $sort = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
```

Query DTO может хранить сырой query-ввод до последующего маппинга, но не содержит логики нормализации.
Если `from` и `to` образуют один контракт транспортного уровня (transport-level contract), проверку связанных полей выносим во внешнее ограничение.

## Чек-лист код-ревью

- [ ] DTO сохраняет транспортный контракт строки запроса без бизнес-логики.
- [ ] Метаданные описывают только формат, допустимость `null` и диапазон, а также другие правила транспортного уровня.
- [ ] Переиспользуемая или уровня класса валидация query вынесена в пользовательское ограничение.
- [ ] Нет `Callback`, `validate*()`, логики в конструкторе и скрытой нормализации.
