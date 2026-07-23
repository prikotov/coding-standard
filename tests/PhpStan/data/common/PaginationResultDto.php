<?php
namespace Test\DtoReuse\Common\Application\Dto;
final readonly class PaginationResultDto
{
    public function __construct(public int $total) {}
}
