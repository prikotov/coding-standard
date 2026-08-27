<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * Resolves `%parameter%` placeholders in Symfony configuration values.
 * Supports parameters referencing other parameters; unknown or circular
 * references resolve to null so the caller can report and skip them.
 */
final class ParameterResolver
{
    private const MAX_DEPTH = 10;

    /** @param array<string, string> $parameters */
    public function __construct(
        private readonly array $parameters,
    ) {
    }

    public function resolve(string $value): ?string
    {
        $resolved = $value;
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $unknownFound = false;
            $replaced = (string) preg_replace_callback(
                '/%([^%\s]+)%/',
                function (array $match) use (&$unknownFound): string {
                    $parameter = $this->parameters[$match[1]] ?? null;
                    if ($parameter === null) {
                        $unknownFound = true;

                        return $match[0];
                    }

                    return $parameter;
                },
                $resolved,
            );

            if ($unknownFound) {
                return null;
            }

            if ($replaced === $resolved) {
                return $resolved;
            }

            $resolved = $replaced;
        }

        return null;
    }
}
