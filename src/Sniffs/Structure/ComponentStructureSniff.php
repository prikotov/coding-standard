<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class ComponentStructureSniff implements Sniff
{
    private const ERROR_INTEGRATION_COMPONENT_FORBIDDEN = 'IntegrationComponentForbidden';

    private const DOC_REF = ' See: docs/conventions/core-patterns/component.md';

    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $normalizedPath = str_replace('\\', '/', $phpcsFile->getFilename());
        $relativePath   = $this->resolveRelativeSrcPath($normalizedPath);

        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        if (str_contains($relativePath, '/Integration/Component/') === false) {
            return;
        }

        $phpcsFile->addError(
            'Integration/Component is forbidden.'
            . ' Move external API/SDK/resource adapters to Infrastructure/Component,'
            . ' or use Integration/Service for local cross-module implementations.'
            . self::DOC_REF,
            $stackPtr,
            self::ERROR_INTEGRATION_COMPONENT_FORBIDDEN,
        );
    }

    private function resolveRelativeSrcPath(string $normalizedPath): ?string
    {
        if (preg_match('~(^|/)(src/.*)$~', $normalizedPath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }
}
