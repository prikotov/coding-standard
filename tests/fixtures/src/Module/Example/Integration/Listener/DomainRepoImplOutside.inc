<?php

declare(strict_types=1);

// RepositoryStructureSniff — class implements a Domain repository interface outside Infrastructure/Repository/
namespace App\Module\Example\Integration\Listener;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;

final class DomainRepoImplOutside implements ProjectRepositoryInterface
{
}
