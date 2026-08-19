<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Verify;

/**
 * Latest release fixed explicitly (CLI option --latest=), for CI pinning and tests.
 */
final class FixedLatestReleaseProvider implements LatestReleaseProvider
{
    public function __construct(
        private readonly string $version,
    ) {
    }

    public function latestRelease(): ?string
    {
        return $this->version !== '' ? $this->version : null;
    }
}
