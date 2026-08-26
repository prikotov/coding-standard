<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * Matches paths against exclude patterns with Symfony glob semantics:
 *
 *  - a `**` segment matches zero or more directories, so the module-wide
 *    pattern `**` + `*Dto.php` covers both `src/FooDto.php` and
 *    `src/A/B/FooDto.php`;
 *  - `*` and `?` never cross a segment boundary;
 *  - `{A,B}` expands to literal alternatives;
 *  - a pattern without glob characters is a directory prefix: it covers
 *    the directory itself and everything below it.
 */
final class GlobMatcher
{
    public function covers(string $path, string $pattern): bool
    {
        $path = $this->normalize($path);
        foreach ($this->expandBraces($this->normalize($pattern)) as $expanded) {
            if ($this->matchesExpanded($path, $expanded)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /** @return list<string> */
    private function expandBraces(string $pattern): array
    {
        $open = strpos($pattern, '{');
        if ($open === false) {
            return [$pattern];
        }

        $close = strpos($pattern, '}', $open);
        if ($close === false) {
            return [$pattern];
        }

        $prefix = substr($pattern, 0, $open);
        $suffix = substr($pattern, $close + 1);
        $expanded = [];
        foreach (explode(',', substr($pattern, $open + 1, $close - $open - 1)) as $alternative) {
            foreach ($this->expandBraces($prefix . $alternative . $suffix) as $candidate) {
                $expanded[] = $candidate;
            }
        }

        return $expanded;
    }

    private function matchesExpanded(string $path, string $pattern): bool
    {
        if ($this->hasGlobCharacters($pattern) === false) {
            return $path === $pattern || str_starts_with($path, $pattern . '/');
        }

        return preg_match($this->buildRegex($pattern), $path) === 1;
    }

    private function hasGlobCharacters(string $pattern): bool
    {
        return strpbrk($pattern, '*?[{') !== false;
    }

    private function buildRegex(string $pattern): string
    {
        $segments = explode('/', $pattern);
        $lastIndex = count($segments) - 1;
        $parts = [];

        foreach ($segments as $index => $segment) {
            if ($segment === '**') {
                $parts[] = $index === $lastIndex ? '(?:[^/]+/)*[^/]*' : '(?:[^/]+/)*';
            } elseif ($index < $lastIndex) {
                $parts[] = $this->segmentToRegex($segment) . '/';
            } else {
                $parts[] = $this->segmentToRegex($segment);
            }
        }

        return '#^' . implode('', $parts) . '$#';
    }

    private function segmentToRegex(string $segment): string
    {
        return strtr(preg_quote($segment, '#'), ['\*' => '[^/]*', '\?' => '[^/]']);
    }
}
