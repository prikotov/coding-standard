<?php

// Fixture for DisallowGroupUseSniff: group use declarations (use Foo\{Bar, Baz};) are disallowed.

namespace Task\Common\Module\Example\Application\Service;

use Task\Common\Module\Example\Domain\Dto\{PaymentCreatedDto, PaymentFailedDto};

final readonly class PaymentService
{
    public function describe(string $type): string
    {
        $created = new PaymentCreatedDto('ok');
        $failed = new PaymentFailedDto('error');

        return $type === 'ok' ? $created->status : $failed->status;
    }
}
