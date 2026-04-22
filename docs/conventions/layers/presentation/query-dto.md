# Query DTO презентационного слоя (Presentation Query DTO)

## Определение

**Presentation Query DTO** — transport-модель параметров query string, которую controller получает через
`MapQueryString` или аналогичный binder до вызова Application-слоя.

## Общие правила

- `QueryDto` объявляем как `final readonly class`.
- DTO остаётся `data-only`: допускаются только свойства конструктора и declarative metadata.
- Property-level `Assert` metadata разрешена, если она описывает transport contract query-параметров.
- Для query binding допустимо сохранять сырые входные значения (`string|mixed|null`), если это нужно для корректной
  transport-validation до последующего маппинга. Например, pagination или sort параметры могут приходить как строки.
- В `QueryDto` запрещены `Callback`, `validate*()`, constructor logic, normalization и выброс исключений.
- Cross-field query validation выносим во внешний class-level constraint.

## Зависимости

### Разрешено

- Скаляры, `BackedEnum`, `UuidInterface/Uuid`, `DateTimeImmutable`.
- OpenAPI и `Symfony Validator` metadata.
- Custom presentation constraints для query-level contract.

### Запрещено

- Сервисы, репозитории, QueryBus/CommandBus, filesystem, network и любое runtime I/O.
- Domain Entity/VO и infrastructure implementations.

## Расположение

- Controller-local query DTO:

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>QueryDto.php
```

- Cross-cutting query DTO:

```
apps/<app>/src/Component/<Context>/<Name>QueryDto.php
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

`QueryDto` может хранить сырой query input до последующего маппинга, но не содержит логики нормализации.
Если `from` и `to` образуют один transport-level contract, cross-field проверку выносим во внешний constraint.

## Чек-лист код-ревью

- [ ] DTO сохраняет transport contract query string без business logic.
- [ ] Metadata описывает только format/nullability/range и другие transport-level rules.
- [ ] Reusable или class-level query validation вынесена в custom constraint.
- [ ] Нет `Callback`, `validate*()`, constructor logic и скрытой нормализации.
