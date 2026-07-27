---
name: Request DTO
type: rule
description: Правила создания Request DTO презентационного слоя
---

# DTO запроса презентационного слоя (Presentation Request DTO)

## Определение

**DTO запроса** (Presentation Request DTO) — транспортная модель входной полезной нагрузки (payload), которую контроллер получает из `MapRequestPayload`, тела JSON, multipart-полезной нагрузки или аналогичной HTTP-привязки (binding) до вызова слоя Application.

## Общие правила

- `RequestDto` объявляем как `final readonly class`.
- `RequestDto` хранит только транспортные данные и декларативные метаданные (declarative metadata), без императивной логики.
- Разрешены метаданные уровня свойства (property-level), описывающие транспортный контракт:
  атрибуты `Symfony Validator`, атрибуты `OpenAPI`, метаданные сериализатора (serializer) и пользовательское `Constraint` presentation.
- Конструктор не содержит нормализации, преобразований, `if`/`match`, исключений и побочных эффектов.
- Внутри `RequestDto` не используем `#[Assert\Callback]`, `validate*()` и другие императивные хуки валидации (imperative validation hooks).
- Межполевые (cross-field), переиспользуемые и отдельно именуемые правила выносим во внешнюю пару валидаторов (validator pair) (`*Constraint` / `*ConstraintValidator`).
- Бизнес-правила, авторизация и обращения к сервисам/репозиториям/HTTP/очередям в `RequestDto` запрещены.

## Зависимости

### Разрешено

- Скаляры, массивы с PHPDoc-типизацией, `BackedEnum`, `DateTimeImmutable`, `UuidInterface/Uuid`.
- Вложенные transport DTO, если это часть публичного контракта запроса.
- `Symfony Validator`, `OpenAPI` и метаданные сериализатора.
- Пользовательские ограничения presentation из того же приложения/модуля.

### Запрещено

- Сервисы, репозитории, `QueryBus`/`CommandBus`, HTTP-клиенты, файловая система, интеграции с очередями.
- Entity, Value Object и другие типы Domain.
- Реализации Infrastructure/Integration.

## Расположение

- Локальный DTO запроса (controller-local):

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>RequestDto.php
```

- Сквозной DTO запроса (cross-cutting):

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

`RequestDto` остаётся чисто транспортной (transport-only): поля и метаданные. Если `password` и `confirmPassword` должны совпадать,
это правило выносим во внешнее ограничение уровня класса (class-level constraint), а не реализуем внутри DTO.

## Чек-лист код-ревью

- [ ] DTO объявлен как `final readonly class`.
- [ ] Внутри только транспортные данные и декларативные метаданные.
- [ ] Нет `Callback`, `validate*()`, логики в конструкторе и нормализации.
- [ ] Межполевое правило вынесено во внешнюю пару валидаторов.
- [ ] DTO зависит только от разрешённых transport-типов и presentation-метаданных.
