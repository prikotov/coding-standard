<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Application;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class UseCaseNamingSniff implements Sniff
{
    private const ERROR_INVALID_SUFFIX = 'InvalidUseCaseSuffix';
    private const ERROR_NAME_MISMATCH = 'UseCaseNameMismatch';
    private const ERROR_FILENAME_MISMATCH = 'FilenameMismatch';
    private const ERROR_NAMESPACE_MISMATCH = 'NamespaceMismatch';

    private const LEGACY_NAME_MISMATCH_ALLOWLIST = [];

    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr Pointer to the class token.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '') {
            return;
        }

        $normalizedPath = str_replace('\\', '/', $phpcsFile->getFilename());
        $relativePath   = $this->resolveRelativePath($normalizedPath);

        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        $useCaseType = $this->resolveUseCaseType($relativePath);
        if ($useCaseType === null) {
            return;
        }

        $classType = $this->resolveClassType($className);
        if ($classType === null) {
            return;
        }

        if ($this->isTypeAllowed($useCaseType, $classType) === false) {
            $phpcsFile->addError(
                sprintf(
                    'UseCase class in %s path must use %s suffix, got %s.',
                    $useCaseType,
                    $useCaseType === 'Command' ? 'Command/CommandHandler' : 'Query/QueryHandler',
                    $classType,
                ),
                $stackPtr,
                self::ERROR_INVALID_SUFFIX,
            );
            return;
        }

        $useCaseName = basename(dirname($normalizedPath));
        $baseName    = $this->stripSuffix($className, $classType);

        if ($this->isLegacyAllowlisted($relativePath) === false && $baseName !== $useCaseName) {
            $phpcsFile->addError(
                sprintf(
                    'UseCase class name must match directory "%s"; expected %s%s, found %s.',
                    $useCaseName,
                    $useCaseName,
                    $classType,
                    $className,
                ),
                $stackPtr,
                self::ERROR_NAME_MISMATCH,
            );
        }

        $fileBase = pathinfo($normalizedPath, PATHINFO_FILENAME);
        if ($fileBase !== $className) {
            $phpcsFile->addError(
                sprintf(
                    'UseCase filename must match class name; expected %s.php, found %s.php.',
                    $className,
                    $fileBase,
                ),
                $stackPtr,
                self::ERROR_FILENAME_MISMATCH,
            );
        }

        $expectedNamespace = $this->expectedNamespace($relativePath);
        if ($expectedNamespace !== null) {
            $namespaceInfo   = $this->resolveNamespace($phpcsFile);
            $actualNamespace = $namespaceInfo['name'];

            if ($actualNamespace !== $expectedNamespace) {
                $phpcsFile->addError(
                    sprintf(
                        'Namespace must match path; expected %s, found %s.',
                        $expectedNamespace,
                        $actualNamespace ?? '(none)',
                    ),
                    $namespaceInfo['ptr'] ?? $stackPtr,
                    self::ERROR_NAMESPACE_MISMATCH,
                );
            }
        }
    }

    private function resolveUseCaseType(string $relativePath): ?string
    {
        if (str_contains($relativePath, '/Application/UseCase/Command/')) {
            return 'Command';
        }

        if (str_contains($relativePath, '/Application/UseCase/Query/')) {
            return 'Query';
        }

        return null;
    }

    private function resolveClassType(string $className): ?string
    {
        $suffixes = ['CommandHandler', 'Command', 'QueryHandler', 'Query'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return $suffix;
            }
        }

        return null;
    }

    private function isTypeAllowed(string $useCaseType, string $classType): bool
    {
        if ($useCaseType === 'Command') {
            return in_array($classType, ['Command', 'CommandHandler'], true);
        }

        return in_array($classType, ['Query', 'QueryHandler'], true);
    }

    private function stripSuffix(string $className, string $classType): string
    {
        return substr($className, 0, -strlen($classType));
    }

    private function resolveRelativePath(string $normalizedPath): ?string
    {
        if (preg_match('~(^|/)(src/.*)$~', $normalizedPath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    private function expectedNamespace(string $relativePath): ?string
    {
        if (str_starts_with($relativePath, 'src/Module/') === false) {
            return null;
        }

        $relativeWithoutSrc = substr($relativePath, strlen('src/'));
        $namespacePath      = str_replace('/', '\\', dirname($relativeWithoutSrc));

        return 'Common\\' . $namespacePath;
    }

    private function isLegacyAllowlisted(string $relativePath): bool
    {
        return in_array($relativePath, self::LEGACY_NAME_MISMATCH_ALLOWLIST, true);
    }

    /**
     * @return array{name:?string, ptr:?int}
     */
    private function resolveNamespace(File $phpcsFile): array
    {
        $namespacePtr = $phpcsFile->findNext(T_NAMESPACE, 0);
        if ($namespacePtr === false) {
            return ['name' => null, 'ptr' => null];
        }

        $namespaceEnd = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespacePtr + 1);
        if ($namespaceEnd === false) {
            return ['name' => null, 'ptr' => $namespacePtr];
        }

        $name = trim($phpcsFile->getTokensAsString(
            $namespacePtr + 1,
            $namespaceEnd - $namespacePtr - 1,
        ));

        return [
            'name' => $name !== '' ? $name : null,
            'ptr' => $namespacePtr,
        ];
    }
}
