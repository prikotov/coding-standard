<?php
namespace Test\DtoReuse\Reusable\Module\Billing\Application\UseCase\Query\Baz;
use Test\DtoReuse\Reusable\Module\Billing\Application\Dto\ReusableSummaryDto;
final class BazHandler
{
    public function __invoke(): ReusableSummaryDto
    {
        return new ReusableSummaryDto('baz');
    }
}
