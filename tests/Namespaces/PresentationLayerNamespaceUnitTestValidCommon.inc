<?php

namespace Task\Common\Module\Landing\Application\Dto;

final readonly class UseCasePageDto
{
    public function __construct(
        public string $slug,
        public string $templateSlug,
    ) {
    }
}
