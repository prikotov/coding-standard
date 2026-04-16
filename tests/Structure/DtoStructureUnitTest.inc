<?php

final class BadDto
{
    use SomeTrait;

    public const TYPE = 'bad';

    public string $name;

    public function __construct(public string $id)
    {
        $this->name = $id;
    }

    public function toArray(): array
    {
        return [];
    }
}
