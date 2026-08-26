<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * One auto-registered namespace import from a module `services.yaml`:
 * a namespace key with `resource` and optional `exclude` entries.
 */
final class ModuleConfig
{
    /**
     * @param list<string> $excludePatterns absolute, parameter-resolved exclude patterns
     */
    public function __construct(
        public readonly string $configFile,
        public readonly string $namespace,
        public readonly string $resourceRoot,
        public readonly string $resourceExpression,
        public readonly array $excludePatterns,
    ) {
    }
}
