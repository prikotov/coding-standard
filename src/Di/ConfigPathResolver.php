<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * Resolves a raw path from `services.yaml` into an absolute normalized path:
 * expands `%parameter%` placeholders and resolves relative paths against the
 * directory of the configuration file.
 */
final class ConfigPathResolver
{
    public function __construct(
        private readonly string $configDir,
        private readonly ParameterResolver $parameters,
    ) {
    }

    public function resolve(string $rawPath): ?string
    {
        $resolved = $this->parameters->resolve($rawPath);
        if ($resolved === null) {
            return null;
        }

        if (!str_starts_with($resolved, '/')) {
            $resolved = rtrim($this->configDir, '/') . '/' . $resolved;
        }

        return $this->normalize($resolved);
    }

    private function normalize(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return ($isAbsolute ? '/' : '') . implode('/', $segments);
    }
}
