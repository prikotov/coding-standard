<?php

namespace Project\Common\Module\User\Domain\ValueObject;

use DateTimeImmutable;
use Project\Common\Component\Billing\BillingModeEnum;
use Project\Common\Enum\LanguageEnum;
use Project\Common\Module\User\Domain\Enum\UserStatus;
use Project\Common\Module\User\Domain\Entity\ValueObject\AuditStampVo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\Uuid;

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

final readonly class AmountVo
{
    public const int MAX_CENTS = 100000000;

    private string $currency;

    private readonly int $cents;

    public function __construct(
        private string $code,
        private readonly int $amount,
        int $cents,
        string $currency,
    ) {
        $this->cents = $cents;
        $this->currency = $currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}

final readonly class CustomerVo
{
    public function __construct(
        private UuidInterface $id,
        private EmailVo $email,
        private UserStatus $status,
        private DateTimeImmutable $registeredAt,
        private string $note,
    ) {}

    public function getStatus(): UserStatus
    {
        return $this->status;
    }
}

// Uid from Symfony, shared (module-independent) enums and Embeddable VO are valid dependencies.
final readonly class SessionVo
{
    public function __construct(
        private Uuid $uuid,
        private LanguageEnum $language,
        private BillingModeEnum $billingMode,
        private AuditStampVo $auditStamp,
    ) {}
}
