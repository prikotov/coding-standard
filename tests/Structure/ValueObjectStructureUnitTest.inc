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

    public function withName(string $name): self
    {
        return new self(value: $this->value, name: $name);
    }

    public function toModifiedValue(): self
    {
        return new self(value: $this->value . '_modified');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
