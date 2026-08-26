<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

/**
 * One scanned class with the fully qualified names referenced by its
 * constructor parameters.
 */
final class ScannedClass
{
    /**
     * @param list<array{name: string, typeFqcns: list<string>, line: int}> $constructorParams
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $file,
        public readonly int $line,
        public readonly array $constructorParams,
    ) {
    }
}
