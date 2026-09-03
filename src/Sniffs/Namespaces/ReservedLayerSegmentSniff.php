<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Layer names (Domain, Application, Infrastructure, Integration) are reserved
 * path segments: the layer is the segment right after the module name.
 * A namespace of one layer must not contain another layer name as a nested
 * segment — for example, Domain\Service\Integration\*Interface is forbidden.
 */
final class ReservedLayerSegmentSniff implements Sniff
{
    private const ERROR_NESTED_LAYER_NAME = 'NestedLayerName';

    /**
     * Reserved layer names.
     *
     * @var list<string>
     */
    private const LAYERS = ['Domain', 'Application', 'Infrastructure', 'Integration'];

    private const DOC_REF = ' See: docs/conventions/layers/layers.md';

    public function register(): array
    {
        return [T_NAMESPACE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $namespace = $this->extractNamespace($phpcsFile, $stackPtr);
        if ($namespace === null) {
            return;
        }

        $layer = $this->resolveLayer($namespace);
        if ($layer === null) {
            return;
        }

        $nestedLayer = $this->findNestedLayerName($namespace);
        if ($nestedLayer === null) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Namespace "%s" contains reserved layer name "%s" inside the %s layer.'
                . ' Layer names are reserved path segments — rename the group'
                . ' or move the code to the %s layer.',
                $namespace,
                $nestedLayer,
                $layer,
                $nestedLayer,
            ) . self::DOC_REF,
            $stackPtr,
            self::ERROR_NESTED_LAYER_NAME,
        );
    }

    private function extractNamespace(File $phpcsFile, int $stackPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $stackPtr + 1);
        if ($end === false) {
            return null;
        }

        $namespace = trim($phpcsFile->getTokensAsString($stackPtr + 1, $end - $stackPtr - 1));

        return $namespace !== '' ? $namespace : null;
    }

    /**
     * Resolves the layer segment — the one right after Module\{ModuleName}.
     */
    private function resolveLayer(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);
        $layerIndex = $this->findModuleIndex($parts);

        if ($layerIndex === null || !isset($parts[$layerIndex + 2])) {
            return null;
        }

        $layer = $parts[$layerIndex + 2];

        return in_array($layer, self::LAYERS, true) ? $layer : null;
    }

    private function findNestedLayerName(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);
        $layerIndex = $this->findModuleIndex($parts);

        if ($layerIndex === null) {
            return null;
        }

        // Check segments after the layer segment for reserved layer names
        for ($i = $layerIndex + 3; $i < count($parts); $i++) {
            if (in_array($parts[$i], self::LAYERS, true)) {
                return $parts[$i];
            }
        }

        return null;
    }

    /**
     * @param list<string> $parts
     */
    private function findModuleIndex(array $parts): ?int
    {
        $moduleIndex = array_search('Module', $parts, true);
        if ($moduleIndex === false) {
            return null;
        }

        // Need at least Module\{ModuleName}\{Layer}
        if (!isset($parts[$moduleIndex + 2])) {
            return null;
        }

        return $moduleIndex;
    }
}
