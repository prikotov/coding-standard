<?php

namespace Project\Common\Module\User\Domain\ValueObject;

final readonly class EmailVo
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function createFromEmail(string $email): self
    {
        return new self($email);
    }

    public static function createDefault(): self
    {
        return new self('default@example.com');
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

    public function merge(self $other): self
    {
        return new self($this->value . $other->value);
    }
}

final readonly class MoneyVo
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {}

    public static function createEmpty(): self
    {
        return new self(0, 'USD');
    }
}

final readonly class TokenCostVo
{
    private function __construct(
        private float $cost,
    ) {}

    public static function create(float $cost): self
    {
        return new self($cost);
    }
}
