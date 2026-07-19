<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — read-model interface (returns VO, not Model) is skipped
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentSummaryCriteriaInterface;
use App\Module\Example\Domain\ValueObject\PaymentSummaryVo;

interface PaymentSummaryRepositoryInterface
{
    /**
     * @return list<PaymentSummaryVo>
     */
    public function getByCriteria(PaymentSummaryCriteriaInterface $criteria): array;

    public function getCountByCriteria(PaymentSummaryCriteriaInterface $criteria): int;
}
