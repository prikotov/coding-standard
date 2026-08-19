<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Verify;

/**
 * Latest release from the GitHub API (the package is not published on Packagist).
 *
 * Responses are cached in the system temp directory for one hour to stay within
 * the unauthenticated rate limit of the GitHub API.
 */
final class GitHubLatestReleaseProvider implements LatestReleaseProvider
{
    private const RELEASE_URL = 'https://api.github.com/repos/prikotov/coding-standard/releases/latest';

    private const CACHE_FILE = 'prikotov-coding-standard-latest-release.json';

    private const CACHE_TTL_SECONDS = 3600;

    public function latestRelease(): ?string
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached;
        }

        $tag = $this->fetchLatestReleaseTag();
        if ($tag === null) {
            return null;
        }

        $this->writeCache($tag);

        return ltrim($tag, 'v');
    }

    private function readCache(): ?string
    {
        $cacheFile = sys_get_temp_dir() . '/' . self::CACHE_FILE;
        if (!is_file($cacheFile)) {
            return null;
        }

        $cache = json_decode((string) file_get_contents($cacheFile), true);
        if (!is_array($cache) || !is_string($cache['version'] ?? null) || !is_int($cache['fetched_at'] ?? null)) {
            return null;
        }

        if (time() - $cache['fetched_at'] > self::CACHE_TTL_SECONDS) {
            return null;
        }

        return ltrim($cache['version'], 'v');
    }

    private function writeCache(string $tag): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . self::CACHE_FILE;
        $payload = json_encode(['version' => $tag, 'fetched_at' => time()]);
        if ($payload !== false) {
            @file_put_contents($cacheFile, $payload);
        }
    }

    private function fetchLatestReleaseTag(): ?string
    {
        $context = stream_context_create(['http' => [
            'header' => "User-Agent: coding-standard-verify\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents(self::RELEASE_URL, false, $context);
        if ($body === false) {
            return null;
        }

        $release = json_decode($body, true);
        if (!is_array($release) || !is_string($release['tag_name'] ?? null)) {
            return null;
        }

        return $release['tag_name'];
    }
}
