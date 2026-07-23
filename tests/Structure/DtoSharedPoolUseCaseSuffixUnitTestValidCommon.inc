<?php

// Valid: root shared pool (Common\Application\Dto) is not checked, even with suffix.
namespace App\Common\Application\Dto;

final readonly class PaginationResultDto
{
    public function __construct(public int $total)
    {
    }
}
