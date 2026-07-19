<?php

declare(strict_types=1);

// RepositoryInterfaceContractSniff — write interface must declare save()
namespace App\Module\Example\Domain\Repository\Payment;

interface NoSaveWriteRepositoryInterface
{
    public function persist(): void;
}
