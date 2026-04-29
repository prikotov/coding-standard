<?php

declare(strict_types=1);

namespace Task\Common\Module\Example\Application\UseCase\Command\Foo\BazTask;

final readonly class BazTaskCommand
{
    public function __construct(
        public string $id,
    ) {
    }
}
