<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class ServiceStructureSniff implements Sniff
{
    private const ERROR_NO_SUFFIX = 'NoServiceSuffix';
    private const ERROR_NO_INTERFACE = 'NoInterface';

    private const DOC_REF = ' See: docs/conventions/core-patterns/service.md';

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $normalizedPath = str_replace('\\', '/', $phpcsFile->getFilename());
        $relativePath   = $this->resolveRelativeSrcPath($normalizedPath);

        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        if ($this->isInServiceDirectory($relativePath) === false) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '') {
            return;
        }

        if (str_ends_with($className, 'Service') === true) {
            $this->assertImplementsInterface($phpcsFile, $stackPtr, $className);
        } else {
            $this->assertCompanionClassAllowed($phpcsFile, $stackPtr, $className, $normalizedPath);
        }
    }

    private function assertImplementsInterface(File $phpcsFile, int $classPtr, string $className): void
    {
        if ($this->classImplementsInterface($phpcsFile, $classPtr) === false) {
            $phpcsFile->addError(
                sprintf(
                    'Service class "%s" must implement a corresponding interface.' . self::DOC_REF,
                    $className,
                ),
                $classPtr,
                self::ERROR_NO_INTERFACE,
            );
        }
    }

    private function assertCompanionClassAllowed(
        File $phpcsFile,
        int $classPtr,
        string $className,
        string $normalizedPath,
    ): void {
        $directory     = dirname($normalizedPath);
        $serviceFiles  = $this->findServiceClassesInDirectory($directory);

        if ($serviceFiles === []) {
            $phpcsFile->addError(
                sprintf(
                    'Class "%s" is in a Service directory but is not a Service'
                    . ' and has no companion Service class in the same directory.'
                    . ' Either rename to "%sService" with an interface,'
                    . ' or move it out of the Service namespace.' . self::DOC_REF,
                    $className,
                    $className,
                ),
                $classPtr,
                self::ERROR_NO_SUFFIX,
            );
        }
    }

    private function isInServiceDirectory(string $relativePath): bool
    {
        $withoutSrc = substr($relativePath, strlen('src/'));

        $segments = explode('/', $withoutSrc);
        for ($i = 0; $i < count($segments) - 1; $i++) {
            if ($segments[$i] === 'Service') {
                return true;
            }
        }

        return false;
    }

    private function classImplementsInterface(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $scopeOpener = $tokens[$classPtr]['scope_opener'] ?? null;
        if ($scopeOpener === null) {
            return false;
        }

        $implementsPtr = $phpcsFile->findNext(T_IMPLEMENTS, $classPtr + 1, $scopeOpener);
        if ($implementsPtr === false) {
            return false;
        }

        return true;
    }

    /**
     * Scans .php files in the given directory for any class ending with "Service".
     *
     * @return list<string>
     */
    private function findServiceClassesInDirectory(string $directory): array
    {
        if (is_dir($directory) === false) {
            return [];
        }

        $serviceClasses = [];

        /** @var list<string> $files */
        $files = scandir($directory);
        foreach ($files as $file) {
            if (str_ends_with($file, '.php') === false) {
                continue;
            }

            $baseName = substr($file, 0, -4);
            if (str_ends_with($baseName, 'Service') === true) {
                $serviceClasses[] = $baseName;
            }
        }

        return $serviceClasses;
    }

    private function resolveRelativeSrcPath(string $normalizedPath): ?string
    {
        if (preg_match('~(^|/)(src/.*)$~', $normalizedPath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }
}
