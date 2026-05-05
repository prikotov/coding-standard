<?php

namespace Project\Common\Module\User\Domain\ValueObject;

final readonly class EmailVo
{
    private function __construct(
        private string $value,
    ) {}

    public static function createFromEmail(string $email): self
    {
        return new self($email);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
