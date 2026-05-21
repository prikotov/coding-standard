<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class PresentationLayerNamespaceSniff implements Sniff
{
    private const ERROR_INNER_LAYER_IN_PRESENTATION = 'InnerLayerInPresentation';

    /**
     * Presentation app groups — entry points, not shared code.
     *
     * @var list<string>
     */
    private const APP_GROUPS = ['Api', 'Blog', 'Console', 'Docs', 'Web'];

    /**
     * Inner layers that must not appear inside Presentation namespaces.
     *
     * @var list<string>
     */
    private const INNER_LAYERS = ['Application', 'Domain', 'Infrastructure', 'Integration'];

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

        $appGroup = $this->resolveAppGroup($namespace);
        if ($appGroup === null) {
            return;
        }

        $innerLayer = $this->findForbiddenInnerLayer($namespace);
        if ($innerLayer === null) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Presentation namespace "%s" must not contain inner layer "%s". '
                . 'Move it to Common\\Module\\...\\%s\\....',
                $namespace,
                $innerLayer,
                $innerLayer,
            ) . self::DOC_REF,
            $stackPtr,
            self::ERROR_INNER_LAYER_IN_PRESENTATION,
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

    private function resolveAppGroup(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);

        foreach ($parts as $part) {
            if (in_array($part, self::APP_GROUPS, true)) {
                return $part;
            }
        }

        return null;
    }

    private function findForbiddenInnerLayer(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);
        $moduleIndex = $this->findModuleIndex($parts);

        if ($moduleIndex === null) {
            return null;
        }

        // Check parts after Module segment for forbidden inner layers
        for ($i = $moduleIndex + 2; $i < count($parts); $i++) {
            if (in_array($parts[$i], self::INNER_LAYERS, true)) {
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

        // Need at least one segment after Module (the module name)
        if (!isset($parts[$moduleIndex + 1])) {
            return null;
        }

        return $moduleIndex;
    }
}
