<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — valid VO repository: full read contract, element type is *Vo
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentSummaryCriteriaInterface;
use App\Module\Example\Domain\ValueObject\PaymentSummaryVo;

interface ValidPaymentSummaryRepositoryInterface
{
    public function getById(?int $id = null): PaymentSummaryVo;

    public function getOneByCriteria(PaymentSummaryCriteriaInterface $criteria): ?PaymentSummaryVo;

    /**
     * @return list<PaymentSummaryVo>
     */
    public function getByCriteria(PaymentSummaryCriteriaInterface $criteria): array;

    public function getCountByCriteria(PaymentSummaryCriteriaInterface $criteria): int;

    public function exists(PaymentSummaryCriteriaInterface $criteria): bool;
}
