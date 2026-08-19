<?php

// Fixture for ReferenceUsedNamesOnlySniff: namespaced classes are imported via
// use; FQCN global classes (\DateTimeImmutable) and exceptions (\RuntimeException) are allowed.

namespace Task\Common\Module\Example\Application\Service;

use Task\Common\Module\Example\Domain\Dto\PaymentResultDto;

final readonly class PaymentService
{
    public function create(string $amount): PaymentResultDto
    {
        $dto = new PaymentResultDto($amount, new \DateTimeImmutable());

        if ($dto->amount === '') {
            throw new \RuntimeException('Empty amount');
        }

        return $dto;
    }
}
