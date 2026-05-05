<?php

namespace App\Module\User\Domain\Service;

class BadVo
{
    use SomeTrait;

    public const TYPE = 'bad';

    public string $name;

    private UserEntity $entity;

    public function __construct(public string $value)
    {
        $this->name = $value;
    }

    public function __set(string $name, mixed $value): void
    {
    }

    public function doSomething(): void
    {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
