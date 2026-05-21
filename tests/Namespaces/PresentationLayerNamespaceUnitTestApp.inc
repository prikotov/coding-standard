<?php

namespace Task\Web\Module\Landing\Application\Dto;

final readonly class UseCasePage
{
    public function __construct(
        public string $slug,
        public string $templateSlug,
    ) {
    }
}
