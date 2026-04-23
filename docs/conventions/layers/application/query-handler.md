---
name: Query Handler
type: rule
description: Правила создания и использования обработчиков запросов
---

# Query и Query Handler

**Запрос (Query)** — разновидность [Use Case](use-case.md), описывающая намерение получить состояние приложения (модуля).
Представляет собой DTO, передаваемое в Query Handler и описывающее сам запрос.

**Обработчик запроса (Query Handler)** — реализует получение данных, оркестрируя доступ к доменной логике, сервисам и инфраструктуре.

## Общие правила

- Query — DTO, реализующее `QueryInterface<ReturnType>`.
- Query Handler не должен изменять состояние приложения.
- Запрещено вызывать другие UseCase внутри QueryHandler.
- Название запроса начинается с глагола (например: `GetCustomerQuery`).
- Класс обработчика имеет постфикс `QueryHandler`.
- Возвращает DTO, Enum или скалярное значение.

## Расположение

- [Application](../application.md)

```php
ProjectName\Common\Module\{ModuleName}\Application\UseCase\Query\{QueryGroup}\{QueryName}\{QueryName}Query
ProjectName\Common\Module\{ModuleName}\Application\UseCase\Query\{QueryGroup}\{QueryName}\{QueryName}QueryHandler
```

## Как создаем

- Создаются только для обработки внешних бизнес-запросов на чтение данных.
- Query — это [DTO](../../core-patterns/dto.md), реализующее интерфейс `QueryInterface<ReturnType>`. Оно описывает входные параметры запроса.
- Может возвращать: [DTO](../../core-patterns/dto.md), [Enum](../../core-patterns/enum.md), скалярное значение.
- Входные и возвращаемые объекты должны находиться в слое Application текущего модуля.
- Query Handler не должен изменять состояние приложения.
- Запрещено вызывать другие UseCase внутри QueryHandler, включая вызов через `__invoke()` другого `*Handler` и запуск через `CommandBus`/`QueryBus`.
- Название запроса должно начинаться с глагола, например: GetCustomerQuery.
- Класс обработчика должен иметь постфикс `QueryHandler`.


## Пример запроса

```php
<?php

declare(strict_types=1);

namespace Common\Module\Project\Application\UseCase\Query\Project\Find;

use ProjectName\Common\Application\Dto\PaginationDto;
use ProjectName\Common\Application\Dto\SortDto;
use ProjectName\Common\Application\Query\QueryInterface;
use Common\Module\Project\Application\Enum\ProjectStatusEnum;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<FindResultDto>
 */
final readonly class FindQuery implements QueryInterface
{
    public function __construct(
        public ?ProjectStatusEnum $status = null,
        public ?Uuid $userUuid = null,
        public ?Uuid $sourceUuid = null,
        public ?string $search = null,
        public ?PaginationDto $pagination = null,
        public ?SortDto $sort = null,
    ) {
    }
}
```

## Пример обработчика запроса

```php
<?php

declare(strict_types=1);

namespace Common\Module\Project\Application\UseCase\Query\Project\Find;

use ProjectName\Common\Application\Dto\SortDto;
use ProjectName\Common\Application\Enum\SortDirectionEnum;
use ProjectName\Common\Application\Mapper\SortDtoToOrderMapper;
use Common\Module\Project\Application\Mapper\ApplicationToDomainProjectStatusMapper;
use Common\Module\Project\Application\Mapper\ProjectDtoMapper;
use Common\Module\Project\Domain\Repository\Project\Criteria\ProjectFindCriteria;
use Common\Module\Project\Domain\Repository\Project\ProjectRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FindQueryHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private SortDtoToOrderMapper $sortDtoToOrderMapper,
        private ProjectDtoMapper $projectDtoMapper,
        private ApplicationToDomainProjectStatusMapper $applicationToDomainProjectStatusMapper,
    ) {
    }

    public function __invoke(FindQuery $query): FindResultDto
    {
        $projectStatusEnum = $this->applicationToDomainProjectStatusMapper->map($query->status);

        $criteria = new ProjectFindCriteria(
            status: $projectStatusEnum,
            userUuid: $query->userUuid,
            sourceUuid: $query->sourceUuid,
            search: $query->search,
        );
        $total = $this->projectRepository->getCountByCriteria($criteria);

        if ($query->pagination !== null) {
            $criteria->setLimit($query->pagination->limit);
            $criteria->setOffset($query->pagination->offset);
        }

        $criteria->setSort($this->sortDtoToOrderMapper->map(
            $query->sort ?? new SortDto(['title' => SortDirectionEnum::asc]),
        ));

        $result = $this->projectRepository->getByCriteria($criteria);
        $items = [];
        foreach ($result as $project) {
            $items[] = $this->projectDtoMapper->map($project);
        }

        return new FindResultDto(
            $items,
            $total,
        );
    }
}
```

## Пример вызова запроса

```php
<?php

declare(strict_types=1);

namespace ProjectName\Web\Module\Project\Controller\Project;

use ProjectName\Common\Application\Component\QueryBus\QueryBusComponentInterface;
use Common\Module\Project\Application\Enum\ProjectStatusEnum as ApplicationProjectStatusEnum;
use Common\Module\Project\Application\UseCase\Query\Project\CountByStatus\CountByStatusQuery;
use Common\Module\Project\Application\UseCase\Query\Project\Find\FindQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use ProjectName\Web\Component\Pagination\PaginationRequestDto as ComponentPaginationRequestDto;
use ProjectName\Web\Component\Pagination\PaginationRequestToApplicationDtoMapper;
use ProjectName\Web\Module\Project\Security\Project\ActionEnum as ProjectActionEnum;
use ProjectName\Web\Module\Project\Controller\Project\Request\PaginationRequestDto;
use ProjectName\Web\Module\Project\Form\Project\FilterFormModel;
use ProjectName\Web\Module\Project\Form\Project\FilterFormType;
use ProjectName\Web\Module\Project\List\FastFilterProjectStatusList;
use ProjectName\Web\Module\Project\Mapper\ProjectStatusToTextMapper;
use ProjectName\Web\Module\Project\Route\ProjectRoute;
use ProjectName\Web\Security\UserInterface;

#[Route(
    path: ProjectRoute::LIST_PATH,
    name: ProjectRoute::LIST,
    methods: [Request::METHOD_GET],
)]
#[AsController]
final class ListController extends AbstractController
{
    public function __construct(
        private readonly QueryBusComponentInterface $queryBus,
        private readonly PaginationRequestToApplicationDtoMapper $paginationRequestToApplicationDtoMapper,
        private readonly ProjectRoute $projectRoute,
        private readonly FastFilterProjectStatusList $fastFilterProjectStatusList,
        private readonly ProjectStatusToTextMapper $projectStatusToTextMapper,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        UserInterface $currentUser,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PaginationRequestDto $paginationRequestDto,
        Request $request,
    ): Response {
        if (!$this->isGranted(ProjectActionEnum::view->value, ['userUuid' => $currentUser->getUuid()])) {
            throw new AccessDeniedException('Access Denied.');
        }

        $pagination = $this->paginationRequestToApplicationDtoMapper->map(
            paginationRequest: new ComponentPaginationRequestDto(
                $paginationRequestDto->page,
                $paginationRequestDto->perPage,
            ),
        );

        $filterFormModel = new FilterFormModel();
        $filterForm = $this->createForm(FilterFormType::class, $filterFormModel);
        $filterForm->handleRequest($request);
        $status = $filterFormModel->getStatus() !== null
            ? ApplicationProjectStatusEnum::from($filterFormModel->getStatus()->value)
            : null;
        $dto = $this->queryBus->query(new FindQuery(
            status: $status,
            userUuid: $currentUser->isAdmin() ? null : $currentUser->getUuid(),
            search: $filterFormModel->getSearch(),
            pagination: $pagination,
        ));

        $statusCounts = $this->queryBus->query(new CountByStatusQuery(
            userUuid: $currentUser->isAdmin() ? null : $currentUser->getUuid(),
            search: $filterFormModel->getSearch(),
        ));

        $filter = $filterFormModel->toQueryParams($filterForm->getName());

        return $this->render('@web.project/project/list.html.twig', [
            'projects' => $dto->items,
            'total' => $dto->total,
            'pagination' => $paginationRequestDto,
            'filterForm' => $filterForm,
            'filter' => $filter,
            'projectRoute' => $this->projectRoute,
            'statuses' => $this->projectStatusToTextMapper->map(
                $this->fastFilterProjectStatusList->getList()
            ),
            'statusCounts' => $statusCounts,
        ]);
    }
}
```

> 💡 В продакшн-коде рекомендуется использовать QueryBus для доставки запросов, особенно при использовании Symfony Messenger и очередей. Прямой вызов QueryHandler допустим для unit-тестов или простых MVP-прототипов.

## Чек-лист для проведения ревью кода

- [ ] Query — `final readonly class`, реализующий `QueryInterface`.
- [ ] Query Handler не изменяет состояние приложения.
- [ ] Нет вызовов других UseCase/Handler внутри.
- [ ] Название запроса начинается с глагола.
- [ ] Возвращается DTO, Enum или скаляр.
- [ ] Входные и возвращаемые объекты находятся в Application-слое модуля.

