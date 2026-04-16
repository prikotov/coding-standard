<?php

final readonly class GoodDto
{
    public function __construct(
        public string $id,
        public int $count = 0,
    ) {
    }
}
