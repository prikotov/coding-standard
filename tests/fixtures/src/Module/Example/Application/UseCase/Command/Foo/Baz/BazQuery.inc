<?php

declare(strict_types=1);

namespace Common\Module\Example\Application\UseCase\Command\Foo\Baz;

final readonly class BazQuery
{
    public function __construct(
        public string $id,
    ) {
    }
}
