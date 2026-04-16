<?php

declare(strict_types=1);

namespace Common\Module\Example\Application\UseCase\Command\Foo\Right;

final readonly class LeftCommand
{
    public function __construct(
        public string $id,
    ) {
    }
}
