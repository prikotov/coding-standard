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
- API multipart-эндпоинт «файл + form-поля» не биндим через `#[MapRequestPayload]`: файл — `#[MapUploadedFile]`, form-поля — `<Name>RequestResolver` рядом с DTO (см. [Multipart file-запросы (API)](#multipart-file-запросы-api)).

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

- Резолвер form-полей multipart-запроса — рядом с DTO (см. [Multipart file-запросы (API)](#multipart-file-запросы-api)):

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

API-эндпоинт «файл + опциональные form-поля» (`multipart/form-data`) не биндим через `#[MapRequestPayload]` — даже с `nullable`-аргументом или значением по умолчанию. В запросе без form-полей `$request->request->all()` пуст, резолвер ядра подставляет вместо него сырое тело запроса (`Request::getContent()`) и для формата `form` отклоняет его с 400 (`RequestPayloadValueResolver::mapRequestPayload`, проверено на Symfony 8.0 и 8.1). Запрос только с файлом от существующих клиентов завершится ошибкой.

### Паттерн

- Файл — аргумент контроллера с [`#[MapUploadedFile]`](https://symfony.com/doc/current/controller.html#mapping-uploaded-files): ограничения (`File`, `Image`) передаём в `constraints`, статус ошибки валидации — `validationFailedStatusCode: Response::HTTP_BAD_REQUEST`.
- Form-поля — локальный Presentation-резолвер (controller-local) `<Name>RequestResolver` рядом с Request DTO: `final readonly class`, зависимости `DenormalizerInterface` и `ValidatorInterface`, единственный публичный метод `create(Request $request): ?<Name>RequestDto`.
- Нет form-полей → `null`: запрос только с файлом остаётся допустимым, значения по умолчанию задаём в контроллере через `?->` при передаче в Command.
- Скалярные form-поля нормализуем в резолвере явно: в `multipart/form-data` нет типов, булево поле приходит строкой. Используем `filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)` — та же семантика булевых, что у штатного биндинга form-полей в `#[MapRequestPayload]`; нераспознанное значение → 400. DTO остаётся транспортной.
- Денормализация DTO: `NotNormalizableValueException` → 400 (`BadRequestHttpException`).
- Ограничения DTO проверяем вручную: при нарушениях бросаем `HttpException::fromStatusCode(400, …, new ValidationFailedException($dto, $violations))` — тот же контракт, что у `#[MapRequestPayload(validationFailedStatusCode: 400)]`.
- Кастомный `ValueResolverInterface` с маркер-атрибутом не вводим, пока таких эндпоинтов меньше двух. Триггеры пересмотра: второй такой эндпоинт либо изменение `mapRequestPayload` в Symfony.

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
- [ ] API-эндпоинт «файл + form-поля»: `#[MapRequestPayload]` не используется; файл — `#[MapUploadedFile]`, form-поля — `<Name>RequestResolver`, запрос только с файлом даёт `null`.
