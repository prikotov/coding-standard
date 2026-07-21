<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — valid read interface (full conventional read contract)
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;
use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentCriteriaInterface;

interface ValidPaymentReadRepositoryInterface
{
    public function getById(?int $id = null): PaymentModel;

    public function getOneByCriteria(PaymentCriteriaInterface $criteria): ?PaymentModel;

    /**
     * @return list<PaymentModel>
     */
    public function getByCriteria(PaymentCriteriaInterface $criteria): array;

    public function getCountByCriteria(PaymentCriteriaInterface $criteria): int;
}
