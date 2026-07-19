<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PrikotovCodingStandard\Config\CodingStandardConfig;

/**
 * Validates the structure of Doctrine repository implementations against the
 * project conventions: placement in Infrastructure/Repository/, the
 * `{Entity}Repository` naming (incl. CQRS `ReadRepository`/`WriteRepository`),
 * implementation of a Domain repository interface. Also forbids `flush()`
 * inside repositories — the transaction boundary belongs to the
 * CommandHandler/UseCase — and rejects a direct dependency on
 * `Doctrine\DBAL\Connection`, which forces raw SQL / column operations instead
 * of ORM persistence and CriteriaMapper-driven queries.
 *
 * Note: inheritance of `ServiceEntityRepository` is intentionally NOT enforced —
 * write-only repositories (EntityManager-based), DBAL/SQL read-models, filesystem
 * and in-memory repositories legitimately use other bases.
 *
 * See: docs/conventions/layers/infrastructure/repository.md
 *      docs/conventions/layers/domain/repository.md
 */
final class RepositoryStructureSniff implements Sniff
{
    private const ERROR_OUTSIDE_DIRECTORY = 'RepositoryOutsideRepositoryDirectory';
    private const ERROR_DOMAIN_IMPL_OUTSIDE_DIRECTORY = 'DomainRepositoryImplOutsideRepositoryDirectory';
    private const ERROR_MISSING_SUFFIX = 'MissingRepositorySuffix';
    private const ERROR_NO_INTERFACE = 'MustImplementDomainRepositoryInterface';
    private const ERROR_FLUSH_FORBIDDEN = 'FlushForbidden';
    private const ERROR_DBAL_CONNECTION = 'ForbiddenDbalConnectionDependency';

    private const REPOSITORY_DIRECTORY_SEGMENT = 'Infrastructure/Repository/';
    private const DOMAIN_REPOSITORY_FQCN_FRAGMENT = '/Domain/Repository/';
    private const REPOSITORY_INTERFACE_SUFFIX = 'RepositoryInterface';

    private string $docRef = '';
    private string $readModelRef = '';

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $docsPath = CodingStandardConfig::docsPath($phpcsFile);
        if ($docsPath === null) {
            return;
        }

        $this->docRef = $this->buildRef($docsPath, 'layers/infrastructure/repository.md');
        $this->readModelRef = $this->buildReadModelRef($docsPath);

        $relativePath = $this->resolveRelativeSrcPath(str_replace('\\', '/', $phpcsFile->getFilename()));
        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '') {
            return;
        }

        $inRepositoryDirectory = str_contains($relativePath, self::REPOSITORY_DIRECTORY_SEGMENT);
        $hasSuffix             = str_ends_with($className, 'Repository');
        $implementedDomainRepo = $this->getImplementedDomainRepositoryInterfaces($phpcsFile, $stackPtr) !== [];

        if ($inRepositoryDirectory === false) {
            if ($hasSuffix === true) {
                $phpcsFile->addError(
                    sprintf(
                        'Repository class "%s" must be placed in an Infrastructure/Repository/ directory.'
                        . ' Move it to .../Infrastructure/Repository/{Entity}/%s.' . $this->docRef,
                        $className,
                        $className,
                    ),
                    $stackPtr,
                    self::ERROR_OUTSIDE_DIRECTORY,
                );
            } elseif ($implementedDomainRepo === true) {
                $phpcsFile->addError(
                    sprintf(
                        'Class "%s" implements a Domain repository interface'
                        . ' but is not in an Infrastructure/Repository/ directory.'
                        . ' Move it to .../Infrastructure/Repository/{Entity}/%s.' . $this->docRef,
                        $className,
                        $className,
                    ),
                    $stackPtr,
                    self::ERROR_DOMAIN_IMPL_OUTSIDE_DIRECTORY,
                );
            }

            return;
        }

        // From here the class is inside Infrastructure/Repository/.
        if ($hasSuffix === false) {
            if ($implementedDomainRepo === true) {
                $phpcsFile->addError(
                    sprintf(
                        'Class "%s" is in an Infrastructure/Repository/ directory and implements a Domain repository'
                        . ' interface, but does not have a "Repository" suffix.'
                        . ' Rename to "%sRepository" (or "...ReadRepository"/"...WriteRepository" for CQRS).'
                        . $this->docRef,
                        $className,
                        $className,
                    ),
                    $stackPtr,
                    self::ERROR_MISSING_SUFFIX,
                );
            }

            return;
        }

        // inRepositoryDirectory && hasSuffix — full validation applies.
        if ($implementedDomainRepo === false) {
            $phpcsFile->addError(
                sprintf(
                    'Repository class "%s" must implement a corresponding Domain repository interface'
                    . ' ({Entity}RepositoryInterface).' . $this->docRef,
                    $className,
                ),
                $stackPtr,
                self::ERROR_NO_INTERFACE,
            );
        }

        $this->assertNoFlush($phpcsFile, $stackPtr);
        $this->assertNoDbalConnection($phpcsFile, $stackPtr);
    }

    /**
     * Rejects a constructor dependency on Doctrine\DBAL\Connection — repositories
     * must persist entities via ORM and read via CriteriaMapper/QueryBuilder, not
     * via raw DBAL column operations or hand-written SQL.
     */
    private function assertNoDbalConnection(File $phpcsFile, int $classPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$classPtr]['scope_opener'];
        $scopeEnd   = $tokens[$classPtr]['scope_closer'];
        $useMap     = $this->buildUseMap($phpcsFile, $classPtr);

        $pointer = $scopeStart;
        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            if ($phpcsFile->getDeclarationName($pointer) !== '__construct') {
                continue;
            }

            foreach ($phpcsFile->getMethodParameters($pointer) as $parameter) {
                $typeHint = (string) ($parameter['type_hint'] ?? '');
                foreach ($this->extractClassNames($typeHint) as $name) {
                    $fqcn = $this->resolveFqcn($name, $useMap);
                    if (str_starts_with($fqcn, 'Doctrine\\DBAL\\') === true) {
                        $phpcsFile->addError(
                            sprintf(
                                'Repository must not depend on Doctrine\\DBAL\\Connection ("%s");'
                                . ' persist via ORM and read via CriteriaMapper/QueryBuilder.' . $this->docRef
                                . $this->readModelRef,
                                $fqcn,
                            ),
                            $classPtr,
                            self::ERROR_DBAL_CONNECTION,
                        );

                        return;
                    }
                }
            }

            return;
        }
    }

    private function assertNoFlush(File $phpcsFile, int $classPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$classPtr]['scope_opener'];
        $scopeEnd   = $tokens[$classPtr]['scope_closer'];

        $pointer = $scopeStart;
        while (($pointer = $phpcsFile->findNext(T_OBJECT_OPERATOR, $pointer + 1, $scopeEnd)) !== false) {
            $methodNamePtr = $phpcsFile->findNext(T_STRING, $pointer + 1, $scopeEnd);
            if ($methodNamePtr === false) {
                continue;
            }

            if ($tokens[$methodNamePtr]['content'] !== 'flush') {
                continue;
            }

            $openParenthesis = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $methodNamePtr + 1, $methodNamePtr + 4);
            if ($openParenthesis === false) {
                continue;
            }

            $phpcsFile->addError(
                'Repository methods must not call flush(); the transaction boundary'
                . ' (flush) belongs to the CommandHandler/UseCase.' . $this->docRef,
                $methodNamePtr,
                self::ERROR_FLUSH_FORBIDDEN,
            );
        }
    }

    /**
     * @return list<string> FQCNs of implemented repository interfaces (located in
     *                      Domain/Repository/ and ending with RepositoryInterface).
     */
    private function getImplementedDomainRepositoryInterfaces(File $phpcsFile, int $classPtr): array
    {
        $domainRepoInterfaces = [];
        foreach ($this->getImplementedInterfaceFqcns($phpcsFile, $classPtr) as $fqcn) {
            $normalized = str_replace('\\', '/', $fqcn);
            if (str_contains($normalized, self::DOMAIN_REPOSITORY_FQCN_FRAGMENT) === false) {
                continue;
            }

            if (str_ends_with($fqcn, self::REPOSITORY_INTERFACE_SUFFIX) === false) {
                continue;
            }

            $domainRepoInterfaces[] = $fqcn;
        }

        return $domainRepoInterfaces;
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

        $useMap = $this->buildUseMap($phpcsFile, $classPtr);

        $interfaces = [];
        $ptr        = $implementsPtr + 1;
        while ($ptr < $scopeOpener) {
            $name = $this->resolveName($phpcsFile, $ptr, $useMap);
            if ($name !== null) {
                $interfaces[] = $name;
            }

            $ptr++;
        }

        return $interfaces;
    }

    /**
     * @param array<string, string> $useMap
     */
    private function resolveName(File $phpcsFile, int $tokenPtr, array $useMap): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$tokenPtr]['code'];
        if ($code !== T_STRING && $code !== T_NAME_QUALIFIED && $code !== T_NAME_FULLY_QUALIFIED) {
            return null;
        }

        $content = (string) $tokens[$tokenPtr]['content'];
        if ($code === T_NAME_FULLY_QUALIFIED) {
            return ltrim($content, '\\');
        }

        if ($code === T_STRING) {
            return $useMap[$content] ?? $content;
        }

        return $content;
    }

    /**
     * Builds a map of short class names to FQCNs from use-statements.
     *
     * @return array<string, string>
     */
    private function buildUseMap(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $useMap = [];

        for ($i = 0; $i < $classPtr; $i++) {
            if ($tokens[$i]['code'] !== T_USE) {
                continue;
            }

            $useEnd = $phpcsFile->findNext(T_SEMICOLON, $i + 1);
            if ($useEnd === false) {
                continue;
            }

            $fqcn = $this->extractFqcnFromUse($phpcsFile, $i + 1, $useEnd);
            if ($fqcn === null) {
                continue;
            }

            $shortName          = $this->extractShortName($fqcn);
            $useMap[$shortName] = $fqcn;
        }

        return $useMap;
    }

    /**
     * @return list<string>
     */
    private function extractClassNames(string $type): array
    {
        if ($type === '') {
            return [];
        }

        preg_match_all('/[A-Za-z_][A-Za-z0-9_\\\\]*/', $type, $matches);

        return $matches[0];
    }

    /**
     * @param array<string, string> $useMap
     */
    private function resolveFqcn(string $name, array $useMap): string
    {
        if (str_contains($name, '\\') === true) {
            return ltrim($name, '\\');
        }

        return $useMap[$name] ?? $name;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function belongsToClass(array $tokens, int $tokenPtr, int $classPtr): bool
    {
        if (isset($tokens[$tokenPtr]['conditions']) === false || $tokens[$tokenPtr]['conditions'] === []) {
            return false;
        }

        return array_key_last($tokens[$tokenPtr]['conditions']) === $classPtr;
    }

    private function extractFqcnFromUse(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];
            if ($code === T_NAME_QUALIFIED || $code === T_NAME_FULLY_QUALIFIED) {
                return ltrim((string) $tokens[$i]['content'], '\\');
            }
        }

        $parts = [];
        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];
            if ($code === T_STRING || $code === T_NS_SEPARATOR) {
                $parts[] = (string) $tokens[$i]['content'];
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

    private function resolveRelativeSrcPath(string $normalizedPath): ?string
    {
        if (preg_match('~(^|/)(src/.*)$~', $normalizedPath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    private function buildRef(string $docsPath, string $conventionFile, string $prefix = ' See:'): string
    {
        $localPath = $docsPath . '/' . $conventionFile;

        return sprintf(
            '%1$s %2$s (https://github.com/prikotov/coding-standard/blob/master/%2$s)',
            $prefix,
            $localPath,
        );
    }

    private function buildReadModelRef(string $docsPath): string
    {
        return $this->buildRef(
            $docsPath,
            'core-patterns/read-model.md',
            ' For aggregate/read-model projections use the Read Model pattern:',
        );
    }
}
