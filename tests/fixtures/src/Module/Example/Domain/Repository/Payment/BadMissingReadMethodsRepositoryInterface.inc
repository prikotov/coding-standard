<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — entity interface missing getByCriteria + getCountByCriteria
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;
use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentCriteriaInterface;

interface BadMissingReadMethodsRepositoryInterface
{
    public function getById(?int $id = null): PaymentModel;

    public function getOneByCriteria(PaymentCriteriaInterface $criteria): ?PaymentModel;

    public function save(PaymentModel $model): void;
}
