<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — Doctrine-legacy / non-conventional method names
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;
use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentCriteriaInterface;

interface SuspectNamesRepositoryInterface
{
    public function findById(int $id): ?PaymentModel;

    public function findByCriteria(PaymentCriteriaInterface $criteria): array;

    public function findOneBy(array $criteria): ?PaymentModel;

    public function count(): int;
}
