<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Verify;

/**
 * Outcome of the wiring verification: one entry per check result.
 */
final class VerificationResult
{
    /** @var list<array{level: 'ok'|'warn'|'fail', message: string, hint: string|null}> */
    private array $entries = [];

    public function ok(string $message, ?string $hint = null): void
    {
        $this->entries[] = ['level' => 'ok', 'message' => $message, 'hint' => $hint];
    }

    public function warn(string $message, ?string $hint = null): void
    {
        $this->entries[] = ['level' => 'warn', 'message' => $message, 'hint' => $hint];
    }

    public function fail(string $message, ?string $hint = null): void
    {
        $this->entries[] = ['level' => 'fail', 'message' => $message, 'hint' => $hint];
    }

    public function hasFailed(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['level'] === 'fail') {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{level: 'ok'|'warn'|'fail', message: string, hint: string|null}> */
    public function entries(): array
    {
        return $this->entries;
    }
}
