<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — valid write interface (save convention)
namespace App\Module\Example\Domain\Repository\Payment;

use App\Module\Example\Domain\Entity\PaymentModel;

interface ValidPaymentWriteRepositoryInterface
{
    public function save(PaymentModel $model): void;
}
