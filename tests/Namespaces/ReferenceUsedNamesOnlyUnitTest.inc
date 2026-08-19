<?php

// Fixture for ReferenceUsedNamesOnlySniff: FQCN with a leading backslash in the
// code body instead of a use import must be reported.

namespace Task\Common\Module\Example\Application\Service;

final readonly class PaymentService
{
    public function create(string $amount): string
    {
        $dto = new \Task\Common\Module\Example\Domain\Dto\PaymentResultDto($amount);

        return $dto->amount;
    }
}
