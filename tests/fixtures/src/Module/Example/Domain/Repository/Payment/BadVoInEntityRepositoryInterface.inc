<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — mixing *Model and *Vo in one interface is forbidden
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;
use App\Module\Example\Domain\Repository\Payment\Criteria\PaymentCriteriaInterface;
use App\Module\Example\Domain\ValueObject\PaymentBalanceVo;

interface BadVoInEntityRepositoryInterface
{
    public function getById(?int $id = null): PaymentModel;

    public function getOneByCriteria(PaymentCriteriaInterface $criteria): ?PaymentBalanceVo;
}
