<?php

declare(strict_types=1);

// RepositoryStructureSniff + MethodSignatureSniff — DBAL Connection dependency and raw DBAL calls
namespace App\Module\Example\Infrastructure\Repository\Project;

use App\Module\Example\Domain\Repository\Project\ProjectRepositoryInterface;
use Doctrine\DBAL\Connection;

final class RawSqlRepository implements ProjectRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findSomething(int $id): ?string
    {
        return $this->connection->fetchOne('SELECT name FROM project WHERE id = ?', [$id]);
    }
}
