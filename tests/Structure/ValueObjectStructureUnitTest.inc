<?php

namespace App\Module\User\Domain\Service;

use App\Module\User\Domain\Entity\UserEntity;
use App\Module\User\Domain\Entity\ValueObject\AuditStampVo;
use Other\Module\Billing\Domain\ValueObject\MoneyVo;

class BadVo
{
    use SomeTrait;

    public const TYPE = 'bad';

    public string $name;

    protected string $code;

    private UserEntity $entity;

    private \Closure $loader;

    private AuditStampVo $auditStamp;

    private MoneyVo $price;

    public function __construct(public string $value, protected int $count)
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
