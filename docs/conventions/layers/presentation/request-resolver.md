---
package: prikotov/coding-standard
name: Request Resolver
type: rule
description: Правила привязки данных запроса к Request DTO вне декларативных атрибутов
---

# Резолвер запроса (Request Resolver)

**Резолвер запроса (Request Resolver)** — локальный класс Presentation рядом с Request DTO. Он превращает `Request` в DTO там, где декларативная привязка (`#[MapRequestPayload]`, `#[MapQueryString]`) не работает. Это обычный сервис, который контроллер вызывает сам, — не реализация `ValueResolverInterface` ядра.

Источник: [Symfony, привязка полезной нагрузки запроса](https://symfony.com/doc/current/controller.html#controller-mapping-request-payload)

## Общие правила

- `final readonly class` с постфиксом `RequestResolver` и одним публичным методом `create(Request $request): ?<Name>RequestDto`.
- Зависимости — только `DenormalizerInterface` и `ValidatorInterface`.
- Внутри только привязка: привести скаляры, денормализовать DTO, проверить ограничения. Бизнес-логика, I/O и обращения к Application запрещены.
- Данных для привязки нет → `null`; значения по умолчанию задаёт контроллер через `?->`.
- Скаляры без типа в транспорте приводим явно: булевы form-поля — `filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)`.
- Контракт ошибок — как у `#[MapRequestPayload(validationFailedStatusCode: 400)]`: ошибка типа → 400 (`BadRequestHttpException`), нарушение ограничений → 400 с `ValidationFailedException`.
- Не реализуем `ValueResolverInterface` и не вводим маркер-атрибут, пока таких эндпоинтов меньше двух.

## Зависимости

- Разрешено: `Request`, `DenormalizerInterface`, `ValidatorInterface`, исключения `Symfony HttpKernel`.
- Запрещено: `CommandBus`, `QueryBus`, репозитории, типы Domain и Infrastructure.

## Расположение

Рядом с Request DTO, в контексте контроллера:

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Request/<Name>RequestResolver.php
```

## Как используем

- Применяем, только когда декларативная привязка не работает. Сегодня это API-эндпоинты «файл + опциональные form-поля»: `#[MapRequestPayload]` отклоняет запрос только с файлом (см. [Request DTO, «Multipart file-запросы (API)»](request-dto.md#multipart-file-запросы-api)).
- Файл при этом принимает сам контроллер — аргументом с `#[MapUploadedFile]`.
- Контроллер внедряет резолвер и вызывает `create($request)` до обращения к Application.

## Пример

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

**Резолвер:**

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

## Чек-лист для проведения ревью кода

- [ ] `final readonly class` рядом с DTO, один публичный метод `create(Request): ?Dto`.
- [ ] Зависимости — только `DenormalizerInterface` и `ValidatorInterface`; бизнес-логики нет.
- [ ] Ошибки — 400 с контрактом `#[MapRequestPayload(validationFailedStatusCode: 400)]`.
- [ ] Резолвер не пишется там, где работает `#[MapRequestPayload]` или `#[MapQueryString]`.
- [ ] Нет реализации `ValueResolverInterface`, пока таких эндпоинтов меньше двух.
