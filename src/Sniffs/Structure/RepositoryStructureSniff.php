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
 * CommandHandler/UseCase — and rejects any dependency on `Doctrine\DBAL\*`
 * (in constructor parameters or class properties), which forces raw SQL /
 * column operations instead of ORM persistence and CriteriaMapper-driven
 * queries. A repository is an orchestrator: SQL is built outside it.
 *
 * Note: inheritance of `ServiceEntityRepository` is intentionally NOT enforced –
 * write-only repositories (EntityManager-based), filesystem and in-memory
 * repositories legitimately use other bases. DBAL, however, is forbidden in any
 * repository (read via CriteriaMapper/QueryBuilder, write via ORM persist).
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
     * Rejects any dependency on Doctrine\DBAL\* — in constructor parameters or
     * class properties. Repositories must persist entities via ORM and read via
     * CriteriaMapper/QueryBuilder, never via raw DBAL column operations or
     * hand-written SQL. A repository is an orchestrator; SQL is built outside it.
     *
     * Properties are checked too so the dependency cannot be smuggled in via the
     * constructor body (e.g. `$this->connection = $registry->getManagerForClass(...)
     * ->getConnection();`).
     */
    private function assertNoDbalConnection(File $phpcsFile, int $classPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = (int) $tokens[$classPtr]['scope_opener'];
        $scopeEnd   = (int) $tokens[$classPtr]['scope_closer'];
        $useMap     = $this->buildUseMap($phpcsFile, $classPtr);

        $dbalFqcn = $this->findDbalInConstructor($phpcsFile, $classPtr, $scopeStart, $scopeEnd, $useMap)
            ?? $this->findDbalInProperties($phpcsFile, $classPtr, $scopeStart, $scopeEnd, $useMap);

        if ($dbalFqcn === null) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Repository must not depend on Doctrine\\DBAL\\* ("%s"); persist via ORM and read via'
                . ' CriteriaMapper/QueryBuilder. A repository is an orchestrator — SQL is built outside it.'
                . $this->docRef,
                $dbalFqcn,
            ),
            $classPtr,
            self::ERROR_DBAL_CONNECTION,
        );
    }

    /**
     * @param array<string, string> $useMap
     */
    private function findDbalInConstructor(
        File $phpcsFile,
        int $classPtr,
        int $scopeStart,
        int $scopeEnd,
        array $useMap,
    ): ?string {
        $tokens = $phpcsFile->getTokens();

        $pointer = $scopeStart;
        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            if ($phpcsFile->getDeclarationName($pointer) !== '__construct') {
                continue;
            }

            foreach ($phpcsFile->getMethodParameters($pointer) as $parameter) {
                foreach ($this->extractClassNames((string) ($parameter['type_hint'] ?? '')) as $name) {
                    $fqcn = $this->resolveFqcn($name, $useMap);
                    if (str_starts_with($fqcn, 'Doctrine\\DBAL\\') === true) {
                        return $fqcn;
                    }
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param array<string, string> $useMap
     */
    private function findDbalInProperties(
        File $phpcsFile,
        int $classPtr,
        int $scopeStart,
        int $scopeEnd,
        array $useMap,
    ): ?string {
        $tokens = $phpcsFile->getTokens();

        $pointer = $scopeStart;
        while (($pointer = $phpcsFile->findNext(T_VARIABLE, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->isClassProperty($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $properties = $phpcsFile->getMemberProperties($pointer);
            foreach ($this->extractClassNames((string) ($properties['type'] ?? '')) as $name) {
                $fqcn = $this->resolveFqcn($name, $useMap);
                if (str_starts_with($fqcn, 'Doctrine\\DBAL\\') === true) {
                    return $fqcn;
                }
            }
        }

        return null;
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

    /**
     * A real class property declaration: nested directly in the class scope, not
     * inside a method/closure (which also makes its T_VARIABLE satisfy
     * belongsToClass, but getMemberProperties() would throw on it).
     */
    private function isClassProperty(array $tokens, int $tokenPtr, int $classPtr): bool
    {
        $conditions = $tokens[$tokenPtr]['conditions'] ?? null;
        if (is_array($conditions) === false || $conditions === []) {
            return false;
        }

        if (array_key_last($conditions) !== $classPtr) {
            return false;
        }

        foreach ($conditions as $scopePtr => $code) {
            if (in_array($tokens[$scopePtr]['code'] ?? null, [T_FUNCTION, T_CLOSURE, T_FN], true) === true) {
                return false;
            }
        }

        // Exclude method/closure parameters — they sit in parentheses owned by T_FUNCTION
        // and getMemberProperties() throws on them.
        if (empty($tokens[$tokenPtr]['nested_parenthesis']) === false) {
            return false;
        }

        return true;
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
}
