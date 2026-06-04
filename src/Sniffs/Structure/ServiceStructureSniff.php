<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class ServiceStructureSniff implements Sniff
{
    private const ERROR_NO_SUFFIX = 'NoServiceSuffix';
    private const ERROR_NO_INTERFACE = 'NoInterface';
    private const ERROR_SERVICE_OUTSIDE_SERVICE_DIR = 'ServiceOutsideServiceDirectory';
    private const ERROR_DOMAIN_SERVICE_IMPL_OUTSIDE_SERVICE_DIR = 'DomainServiceImplOutsideServiceDirectory';
    private const ERROR_DOMAIN_SERVICE_LAYER_SEGMENT = 'DomainServiceLayerSegment';

    private const DOC_REF = ' See: docs/conventions/core-patterns/service.md';
    private const LAYER_NAMES = [
        'Domain',
        'Application',
        'Infrastructure',
        'Integration',
        'Presentation',
    ];

    public function register(): array
    {
        return [T_CLASS, T_INTERFACE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $normalizedPath = str_replace('\\', '/', $phpcsFile->getFilename());
        $relativePath   = $this->resolveRelativeSrcPath($normalizedPath);

        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '') {
            return;
        }

        $this->assertDomainServicePathSegmentIsNotLayerName($phpcsFile, $stackPtr, $relativePath);

        if ($this->isInterfaceDeclaration($phpcsFile, $stackPtr)) {
            return;
        }

        $inServiceDir = $this->isInServiceDirectory($relativePath);
        $hasSuffix    = str_ends_with($className, 'Service');

        if ($inServiceDir && $hasSuffix) {
            $this->assertImplementsInterface($phpcsFile, $stackPtr, $className);

            return;
        }

        if ($inServiceDir) {
            $this->assertCompanionClassAllowed($phpcsFile, $stackPtr, $className, $normalizedPath);

            return;
        }

        if ($hasSuffix) {
            $this->assertServiceInCorrectDirectory($phpcsFile, $stackPtr, $className);

            return;
        }

        $this->assertDomainServiceImplInServiceDirectory($phpcsFile, $stackPtr, $className);
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

    private function assertDomainServicePathSegmentIsNotLayerName(
        File $phpcsFile,
        int $classPtr,
        string $relativePath,
    ): void {
        if (
            preg_match(
                '~^src/Module/[^/]+/Domain/Service/(?P<segment>[^/]+)/~',
                $relativePath,
                $matches,
            ) !== 1
        ) {
            return;
        }

        $segment = $matches['segment'];
        if (in_array($segment, self::LAYER_NAMES, true) === false) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Domain Service path segment "%s" must describe domain area, not a layer name.'
                . ' Use Domain/Service/{DomainArea?}/..., not Domain/Service/%s/....' . self::DOC_REF,
                $segment,
                $segment,
            ),
            $classPtr,
            self::ERROR_DOMAIN_SERVICE_LAYER_SEGMENT,
        );
    }

    private function isInterfaceDeclaration(File $phpcsFile, int $stackPtr): bool
    {
        return $phpcsFile->getTokensAsString($stackPtr, 1) === 'interface';
    }

    private function assertCompanionClassAllowed(
        File $phpcsFile,
        int $classPtr,
        string $className,
        string $normalizedPath,
    ): void {
        $allowedSuffixes = ['Helper', 'Factory', 'Dto', 'Mapper'];
        foreach ($allowedSuffixes as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return;
            }
        }

        $directory     = dirname($normalizedPath);
        $serviceFiles  = $this->findServiceClassesInDirectory($directory);

        if ($serviceFiles === []) {
            $phpcsFile->addError(
                sprintf(
                    'Class "%s" is in a Service directory but is not a Service'
                    . ' and has no companion Service class in the same directory.'
                    . ' Rename to "%sService" with an interface.' . self::DOC_REF,
                    $className,
                    $className,
                ),
                $classPtr,
                self::ERROR_NO_SUFFIX,
            );
        }
    }

    private function assertServiceInCorrectDirectory(
        File $phpcsFile,
        int $classPtr,
        string $className,
    ): void {
        $phpcsFile->addError(
            sprintf(
                'Service class "%s" must be placed in a Service/ directory.'
                . ' Move it to .../Service/{Context?}/%s.' . self::DOC_REF,
                $className,
                $className,
            ),
            $classPtr,
            self::ERROR_SERVICE_OUTSIDE_SERVICE_DIR,
        );
    }

    /**
     * Checks that a class implementing a Domain Service interface is placed in a Service/ directory.
     * This prevents bypassing the sniff by moving the implementation out of Service/.
     */
    private function assertDomainServiceImplInServiceDirectory(
        File $phpcsFile,
        int $classPtr,
        string $className,
    ): void {
        $implementedInterfaces = $this->getImplementedInterfaceFqcns($phpcsFile, $classPtr);

        foreach ($implementedInterfaces as $fqcn) {
            $normalizedFqcn = str_replace('\\', '/', $fqcn);
            if (str_contains($normalizedFqcn, '/Domain/Service/') === true) {
                $phpcsFile->addError(
                    sprintf(
                        'Class "%s" implements Domain Service interface "%s"'
                        . ' but is not in a Service/ directory.'
                        . ' Move it to .../Service/{Context?}/%s.' . self::DOC_REF,
                        $className,
                        $fqcn,
                        $className,
                    ),
                    $classPtr,
                    self::ERROR_DOMAIN_SERVICE_IMPL_OUTSIDE_SERVICE_DIR,
                );

                return;
            }
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
     * Resolves FQCNs of all interfaces listed in the `implements` clause.
     *
     * @return list<string>
     */
    private function getImplementedInterfaceFqcns(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        $scopeOpener = $tokens[$classPtr]['scope_opener'] ?? null;
        if ($scopeOpener === null) {
            return [];
        }

        $implementsPtr = $phpcsFile->findNext(T_IMPLEMENTS, $classPtr + 1, $scopeOpener);
        if ($implementsPtr === false) {
            return [];
        }

        $useMap      = $this->buildUseMap($phpcsFile, $classPtr);
        $interfaces  = [];

        $ptr = $implementsPtr + 1;
        while ($ptr < $scopeOpener) {
            $token = $tokens[$ptr];

            if ($token['code'] === T_NAME_FULLY_QUALIFIED) {
                $interfaces[] = ltrim($token['content'], '\\');
                $ptr++;
                continue;
            }

            if ($token['code'] === T_STRING) {
                $name         = $token['content'];
                $interfaces[] = $useMap[$name] ?? $name;
                $ptr++;
                continue;
            }

            $ptr++;
        }

        return $interfaces;
    }

    /**
     * Builds a map of short class names to FQCNs from use-statements.
     *
     * @return array<string, string>
     */
    private function buildUseMap(File $phpcsFile, int $classPtr): array
    {
        $tokens  = $phpcsFile->getTokens();
        $useMap  = [];

        for ($i = 0; $i < $classPtr; $i++) {
            if ($tokens[$i]['code'] !== T_USE) {
                continue;
            }

            $useEnd = $phpcsFile->findNext(T_SEMICOLON, $i + 1);
            if ($useEnd === false) {
                continue;
            }

            $fqcn = $this->extractFqcnFromUse($tokens, $i + 1, $useEnd);
            if ($fqcn === null) {
                continue;
            }

            $shortName          = $this->extractShortName($fqcn);
            $useMap[$shortName] = $fqcn;
        }

        return $useMap;
    }

    /**
     * Extracts the FQCN from tokens between `use` and `;`.
     *
     * @param array<int, array{code: int|string, content: string}> $tokens
     */
    private function extractFqcnFromUse(array $tokens, int $start, int $end): ?string
    {
        $parts = [];

        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_NAME_QUALIFIED || $code === T_NAME_FULLY_QUALIFIED) {
                return ltrim($tokens[$i]['content'], '\\');
            }

            if ($code === T_STRING || $code === T_NS_SEPARATOR) {
                $parts[] = $tokens[$i]['content'];
            }
        }

        if ($parts === []) {
            return null;
        }

        $fqcn = implode('', $parts);
        if (str_starts_with($fqcn, '\\')) {
            $fqcn = substr($fqcn, 1);
        }

        return $fqcn;
    }

    private function extractShortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        if ($pos === false) {
            return $fqcn;
        }

        return substr($fqcn, $pos + 1);
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
