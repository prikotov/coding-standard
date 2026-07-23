<?php
namespace Test\DtoReuse\Reusable\Module\Billing\Application\UseCase\Query\Bar;
use Test\DtoReuse\Reusable\Module\Billing\Application\Dto\ReusableSummaryDto;
final class BarHandler
{
    public function __invoke(): ReusableSummaryDto
    {
        return new ReusableSummaryDto('bar');
    }
}
