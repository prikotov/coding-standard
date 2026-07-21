<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — exists() must return bool
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;
use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentCriteriaInterface;

interface BadExistsRepositoryInterface
{
    public function getById(?int $id = null): PaymentModel;

    public function getOneByCriteria(PaymentCriteriaInterface $criteria): ?PaymentModel;

    /**
     * @return list<PaymentModel>
     */
    public function getByCriteria(PaymentCriteriaInterface $criteria): array;

    public function getCountByCriteria(PaymentCriteriaInterface $criteria): int;

    public function exists(PaymentCriteriaInterface $criteria): int;
}
