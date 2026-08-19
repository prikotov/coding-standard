<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Verify;

/**
 * Source of the latest released version of prikotov/coding-standard.
 */
interface LatestReleaseProvider
{
    /**
     * @return string|null latest released version, normalized (no "v" prefix);
     *                     null when the version cannot be determined
     */
    public function latestRelease(): ?string;
}
