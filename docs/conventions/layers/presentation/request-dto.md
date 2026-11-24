---
package: prikotov/coding-standard
name: Request DTO
type: rule
description: Правила создания Request DTO презентационного слоя
---

# Request DTO презентационного слоя (Presentation Request DTO)

## Определение

**Presentation Request DTO** — транспортная модель входной полезной нагрузки (payload), которую контроллер получает из `MapRequestPayload`, тела JSON, multipart-полезной нагрузки или аналогичной HTTP-привязки (binding) до вызова слоя Application.

## Общие правила

- Request DTO объявляем как `final readonly class`.
- Request DTO хранит только транспортные данные и декларативные метаданные (declarative metadata), без императивной логики.
- Разрешены метаданные уровня свойства (property-level), описывающие транспортный контракт:
  атрибуты `Symfony Validator`, атрибуты `OpenAPI`, метаданные сериализатора (serializer) и пользовательское `Constraint` presentation.
- Конструктор не содержит нормализации, преобразований, `if`/`match`, исключений и побочных эффектов.
- Внутри Request DTO не используем `#[Assert\Callback]`, `validate*()` и другие императивные хуки валидации (imperative validation hooks).
- Правила для связанных полей, переиспользуемые и отдельно именуемые, выносим во внешнюю пару валидаторов (validator pair) (`*Constraint` / `*ConstraintValidator`).
- Бизнес-правила, авторизация и обращения к сервисам/репозиториям/HTTP/очередям в Request DTO запрещены.
- Для API-эндпоинта «файл + form-поля» `#[MapRequestPayload]` не подходит — см. [Multipart file-запросы (API)](#multipart-file-запросы-api).

## Зависимости

### Разрешено

- Скаляры, массивы с PHPDoc-типизацией, `BackedEnum`, `DateTimeImmutable`, `UuidInterface/Uuid`.
- Вложенные transport DTO, если это часть публичного контракта запроса.
- `Symfony Validator`, `OpenAPI` и метаданные сериализатора.
- Пользовательские ограничения presentation из того же приложения/модуля.

### Запрещено

- Сервисы, репозитории, QueryBus/CommandBus, HTTP-клиенты, файловая система, интеграции с очередями.
- `Entity`, `Value Object` и другие типы Domain.
- Реализации Infrastructure/Integration.

## Расположение

- Локальный Request DTO (controller-local):

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>RequestDto.php
```

- `<Name>RequestResolver` — рядом с DTO (см. [Multipart file-запросы (API)](#multipart-file-запросы-api)):

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>RequestResolver.php
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

Request DTO остаётся чисто транспортной (transport-only): поля и метаданные. Если `password` и `confirmPassword` должны совпадать,
это правило выносим во внешнее ограничение уровня класса (class-level constraint), а не реализуем внутри DTO.

## Multipart file-запросы (API)

Эндпоинт «файл + опциональные form-поля» (`multipart/form-data`) принимаем по отдельности: файл — аргумент контроллера с `#[MapUploadedFile]`, form-поля — резолвер рядом с DTO. `#[MapRequestPayload]` здесь не подходит: запрос только с файлом он отклоняет с 400.

### Паттерн

1. Файл — аргумент контроллера с [`#[MapUploadedFile]`](https://symfony.com/doc/current/controller.html#mapping-uploaded-files): ограничения (`File`, `Image`) — в `constraints`, ошибки валидации — `validationFailedStatusCode: Response::HTTP_BAD_REQUEST`.
2. Form-поля — резолвер `<Name>RequestResolver` рядом с Request DTO: `final readonly class`, зависимости `DenormalizerInterface` и `ValidatorInterface`, один публичный метод `create(Request $request): ?<Name>RequestDto`.
3. В резолвере:
   - нет form-полей → `null` (запрос только с файлом допустим, значения по умолчанию задаёт контроллер через `?->`);
   - булевы поля приводим явно — `filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)`, нераспознанное значение → 400;
   - денормализуем DTO: `NotNormalizableValueException` → 400 (`BadRequestHttpException`);
   - проверяем ограничения: при нарушениях — `HttpException::fromStatusCode(400, …, new ValidationFailedException($dto, $violations))`, как у `#[MapRequestPayload(validationFailedStatusCode: 400)]`.
4. Кастомный `ValueResolverInterface` с маркер-атрибутом не вводим, пока таких эндпоинтов меньше двух; пересматриваем на втором таком эндпоинте или при изменении `mapRequestPayload` в Symfony.

> Почему не `#[MapRequestPayload]`: в запросе без form-полей `$request->request->all()` пуст, и `RequestPayloadValueResolver::mapRequestPayload` подставляет вместо него сырое тело запроса (`Request::getContent()`), которое для формата `form` отклоняет с 400 — `nullable`-аргумент и значение по умолчанию не помогают (проверено на Symfony 8.0 и 8.1).

### Пример

**Request DTO:**

```php
<?php

declare(strict_types=1);

namespace Api\v1\Module\Source\Controller\Source\Request;

use ProjectName\Common\Module\Source\Application\Enum\SourceLanguageEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateByFileRequestDto
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $title = null,
        public ?bool $includeComments = null,
        public ?SourceLanguageEnum $language = null,
    ) {
    }
}
```

**Request-резолвер:**

```php
<?php

declare(strict_types=1);

namespace Api\v1\Module\Source\Controller\Source\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateByFileRequestResolver
{
    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(Request $request): ?CreateByFileRequestDto
    {
        $data = $request->request->all();

        if ([] === $data) {
            return null;
        }

        if (array_key_exists('includeComments', $data)) {
            $includeComments = filter_var($data['includeComments'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if (null === $includeComments) {
                throw new BadRequestHttpException('Request payload contains invalid "includeComments" data.');
            }

            $data['includeComments'] = $includeComments;
        }

        try {
            /** @var CreateByFileRequestDto $dto */
            $dto = $this->denormalizer->denormalize($data, CreateByFileRequestDto::class);
        } catch (NotNormalizableValueException $e) {
            throw new BadRequestHttpException('Request payload contains invalid data.', $e);
        }

        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            throw HttpException::fromStatusCode(
                Response::HTTP_BAD_REQUEST,
                'Request payload contains invalid data.',
                new ValidationFailedException($dto, $violations),
            );
        }

        return $dto;
    }
}
```

**Контроллер:**

```php
<?php

declare(strict_types=1);

namespace Api\v1\Module\Source\Controller\Source;

use Api\v1\Module\Source\Controller\Source\Request\CreateByFileRequestResolver;
use Api\v1\Module\Source\Route\SourceRoute;
use ProjectName\Common\Application\Component\CommandBus\CommandBusComponentInterface;
use ProjectName\Common\Module\Source\Application\Enum\SourceLanguageEnum;
use ProjectName\Common\Module\Source\Application\UseCase\Command\Source\CreateByFile\CreateByFileCommand;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[Route(path: SourceRoute::CREATE_FILE_PATH, name: SourceRoute::CREATE_FILE, methods: [Request::METHOD_POST])]
#[AsController]
final readonly class CreateFileController
{
    public function __construct(
        private CommandBusComponentInterface $commandBus,
        private CreateByFileRequestResolver $createByFileRequestResolver,
    ) {
    }

    public function __invoke(
        Uuid $projectUuid,
        Request $request,
        #[MapUploadedFile(
            constraints: [
                new Assert\File(maxSize: '100M'),
            ],
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST,
        )]
        UploadedFile $file,
    ): JsonResponse {
        $dto = $this->createByFileRequestResolver->create($request);

        $id = $this->commandBus->execute(new CreateByFileCommand(
            projectUuid: $projectUuid,
            file: $file,
            filename: $file->getClientOriginalName(),
            title: $dto?->title,
            language: $dto?->language ?? SourceLanguageEnum::auto,
            includeComments: $dto?->includeComments ?? true,
        ));

        return new JsonResponse(['uuid' => $id->uuid], Response::HTTP_CREATED);
    }
}
```

## Чек-лист код-ревью

- [ ] DTO объявлен как `final readonly class`.
- [ ] Внутри только транспортные данные и декларативные метаданные.
- [ ] Нет `Callback`, `validate*()`, логики в конструкторе и нормализации.
- [ ] Межполевое правило вынесено во внешнюю пару валидаторов.
- [ ] DTO зависит только от разрешённых transport-типов и presentation-метаданных.
- [ ] Для API-эндпоинта «файл + form-поля» `#[MapRequestPayload]` не используется: form-поля принимает `<Name>RequestResolver`.
