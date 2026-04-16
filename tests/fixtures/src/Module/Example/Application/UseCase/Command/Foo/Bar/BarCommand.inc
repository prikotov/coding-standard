<?php

declare(strict_types=1);

namespace Common\Module\Example\Application\UseCase\Command\Foo\Bar;

final readonly class BarCommand
{
    public function __construct(
        public string $id,
    ) {
    }
}
