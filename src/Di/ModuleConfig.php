<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * One auto-registered namespace import from a `services.yaml`:
 * a namespace key with `resource` and optional `exclude` entries.
 *
 * The `isCommon` flag distinguishes Common modules (`...\Common\Module\...`)
 * from application modules and component imports: the convention prescribes
 * a mandatory exclude minimum for Common modules regardless of their
 * contents, while application modules are checked against the non-service
 * types they actually contain.
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
        public readonly bool $isCommon,
    ) {
    }
}
