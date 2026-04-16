<?php

final class ValidCommand
{
    public function __construct(
        public readonly string $id,
        public string $type = 'default',
    ) {
    }
}
