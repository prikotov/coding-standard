<?php
namespace Test\DtoReuse\Underused\Module\Billing\Application\UseCase\Query\Foo;
use Test\DtoReuse\Underused\Module\Billing\Application\Dto\UnderusedResultDto;
final class FooHandler
{
    public function __invoke(string $id): UnderusedResultDto
    {
        return new UnderusedResultDto($id);
    }
}
