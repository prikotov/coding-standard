# Request DTO презентационного слоя (Presentation Request DTO)

## Определение

**Presentation Request DTO** — transport-модель входного payload, которую controller получает из `MapRequestPayload`,
JSON body, multipart payload или аналогичного HTTP binding до вызова Application-слоя.

## Общие правила

- `RequestDto` объявляем как `final readonly class`.
- DTO хранит только transport-данные и declarative metadata, без императивной логики.
- Разрешена property-level metadata, которая описывает transport contract:
  `Symfony Validator` attributes, OpenAPI attributes, serializer metadata и custom presentation `Constraint`.
- Конструктор не содержит нормализации, преобразований, `if`/`match`, исключений и побочных эффектов.
- Внутри `RequestDto` не используем `#[Assert\Callback]`, `validate*()` и другие imperative validation hooks.
- Cross-field, reusable и отдельно именуемые правила выносим во внешний validator pair
  (`*Constraint` / `*ConstraintValidator`).
- Business rules, авторизация и обращения к сервисам/репозиториям/HTTP/очередям в `RequestDto` запрещены.

## Зависимости

### Разрешено

- Скаляры, массивы с PHPDoc-типизацией, `BackedEnum`, `DateTimeImmutable`, `UuidInterface/Uuid`.
- Вложенные transport DTO, если это часть публичного request contract.
- `Symfony Validator`, OpenAPI и serializer metadata.
- Custom presentation constraints из того же app/module.

### Запрещено

- Сервисы, репозитории, QueryBus/CommandBus, HTTP clients, filesystem, queue integrations.
- Entity, Value Object и другие типы Domain.
- Infrastructure/Integration implementations.

## Расположение

- Controller-local request DTO:

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>RequestDto.php
```

- Cross-cutting request DTO:

```
apps/<app>/src/Component/<Context>/<Name>RequestDto.php
```

## Пример

```php
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        public string $password,
        #[Assert\NotBlank]
        public string $confirmPassword,
    ) {
    }
}
```

`RequestDto` остаётся transport-only: поля и metadata. Если `password` и `confirmPassword` должны совпадать,
это правило выносим во внешний class-level constraint, а не реализуем внутри DTO.

## Чек-лист код-ревью

- [ ] DTO объявлен как `final readonly class`.
- [ ] Внутри только transport data и declarative metadata.
- [ ] Нет `Callback`, `validate*()`, constructor logic и нормализации.
- [ ] Cross-field rule вынесен во внешний validator pair.
- [ ] DTO зависит только от разрешённых transport-типов и presentation metadata.
