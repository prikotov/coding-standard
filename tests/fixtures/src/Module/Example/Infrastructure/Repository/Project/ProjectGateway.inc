<?php

declare(strict_types=1);

// RepositoryStructureSniff — implements Domain interface but has no "Repository" suffix
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;

final class ProjectGateway implements ProjectRepositoryInterface
{
}
