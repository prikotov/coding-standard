<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PrikotovCodingStandard\Config\CodingStandardConfig;

/**
 * Type-locks the signatures of conventional repository methods declared on
 * Domain repository interfaces.
 *
 * An interface located in Domain/Repository/ and ending with `RepositoryInterface`
 * (incl. `ReadRepositoryInterface` / `WriteRepositoryInterface`) is NOT required
 * to declare every conventional method — a domain may need only a subset
 * (Interface Segregation). But every method that IS declared must follow the
 * conventional contract — only `getById`, `getOneByCriteria`, `getByCriteria`,
 * `getCountByCriteria`, `exists`, `save`, `delete` are allowed; any other method
 * name is an error. The element type may be a domain entity (`*Model`) or a
 * domain Value Object (`*Vo`) — a VO repository follows the same contract, it
 * just omits `save`/`delete` and returns `*Vo`.
 *
 * Type-locked contract:
 *  - getById(?int, ?Uuid): Model|Vo (non-nullable)
 *  - getOneByCriteria(Criteria): ?Model|?Vo
 *  - getByCriteria(Criteria): array / list<*>
 *  - getCountByCriteria(Criteria): int
 *  - exists(Criteria): bool
 *  - save(Model)/delete(Model): void
 *
 * Mixing `*Model` and `*Vo` in one interface is forbidden — a VO belongs in its
 * own repository.
 *
 * See: docs/conventions/layers/domain/repository.md
 */
final class RepositoryInterfaceContractSniff implements Sniff
{
    private const ERROR_GET_BY_ID = 'GetByIdMustReturnDomainType';
    private const ERROR_NON_CONVENTIONAL_METHOD = 'NonConventionalMethodName';
    private const ERROR_GET_ONE = 'GetOneByCriteriaMustReturnNullableDomainType';
    private const ERROR_GET_BY_CRITERIA = 'GetByCriteriaMustReturnCollection';
    private const ERROR_GET_COUNT = 'GetCountByCriteriaMustReturnInt';
    private const ERROR_EXISTS = 'ExistsMustReturnBool';
    private const ERROR_SAVE = 'SaveMustTakeEntityReturnVoid';
    private const ERROR_DELETE = 'DeleteMustTakeEntityReturnVoid';
    private const ERROR_MIXED_TYPES = 'MixedModelAndValueObject';

    private const DOMAIN_REPOSITORY_PATH = 'Domain/Repository/';
    private const INTERFACE_SUFFIX = 'RepositoryInterface';

    private string $docRef = '';

    /** @var array<string, true> Conventional repository operations allowed in an interface. */
    private const CONVENTIONAL_METHODS = [
        'getById'            => true,
        'getOneByCriteria'   => true,
        'getByCriteria'      => true,
        'getCountByCriteria' => true,
        'exists'             => true,
        'save'               => true,
        'delete'             => true,
    ];

    /** @var array<string, true> */
    private const PRIMITIVE_TYPES = [
        'array'    => true,
        'bool'     => true,
        'callable' => true,
        'false'    => true,
        'float'    => true,
        'int'      => true,
        'iterable' => true,
        'mixed'    => true,
        'never'    => true,
        'null'     => true,
        'object'   => true,
        'parent'   => true,
        'self'     => true,
        'static'   => true,
        'string'   => true,
        'true'     => true,
        'void'     => true,
    ];

    /** @var array<string, true> */
    private const READ_METHODS = [
        'getById'            => true,
        'getOneByCriteria'   => true,
        'getByCriteria'      => true,
        'getCountByCriteria' => true,
    ];

    public function register(): array
    {
        return [T_INTERFACE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $docsPath = CodingStandardConfig::docsPath($phpcsFile);
        if ($docsPath === null) {
            return;
        }

        $this->docRef = $this->buildRef($docsPath, 'layers/domain/repository.md');

        $relativePath = $this->resolveRelativeSrcPath(str_replace('\\', '/', $phpcsFile->getFilename()));
        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        if (str_contains($relativePath, self::DOMAIN_REPOSITORY_PATH) === false) {
            return;
        }

        $interfaceName = $phpcsFile->getDeclarationName($stackPtr);
        if ($interfaceName === '' || str_ends_with($interfaceName, self::INTERFACE_SUFFIX) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $methods = $this->collectMethods($phpcsFile, $stackPtr);
        if ($methods === []) {
            return;
        }

        // Presence of conventional methods is not required (a domain may need only a
        // subset — Interface Segregation). But every declared method must follow the
        // conventional contract: a repository — entity or VO — operates only through
        // the conventional operation set.
        $this->assertConventionalMethodNames($phpcsFile, $methods);
        $this->assertSignatures($phpcsFile, $methods);
        $this->assertNoMixedTypes($phpcsFile, $methods);
    }

    /**
     * A repository interface must expose only conventional repository operations.
     * Any other method name (Doctrine-legacy `find`/`findBy`, named queries like
     * `getBalanceOnDate`, helpers, …) fragments the contract and is rejected.
     *
     * @param array<string, array{ptr: int, props: array<string, mixed>, params: list<mixed>}> $methods
     */
    private function assertConventionalMethodNames(File $phpcsFile, array $methods): void
    {
        foreach ($methods as $methodName => $method) {
            if (isset(self::CONVENTIONAL_METHODS[$methodName]) === true) {
                continue;
            }

            $phpcsFile->addError(
                sprintf(
                    'Method %s() in a repository interface is not a conventional repository operation.'
                    . ' Allowed: getById/getOneByCriteria/getByCriteria/getCountByCriteria/exists/save/delete.'
                    . $this->docRef,
                    $methodName,
                ),
                $method['ptr'],
                self::ERROR_NON_CONVENTIONAL_METHOD,
            );
        }
    }

    /**
     * @param array<string, array{props: array<string, mixed>, params: list<mixed>}> $methods
     */
    private function assertSignatures(File $phpcsFile, array $methods): void
    {
        foreach ($methods as $name => $method) {
            $props = $method['props'];

            switch ($name) {
                case 'getById':
                    $this->assertEntityReturn($phpcsFile, $method['ptr'], $props, false, self::ERROR_GET_BY_ID);

                    break;
                case 'getOneByCriteria':
                    $this->assertEntityReturn($phpcsFile, $method['ptr'], $props, true, self::ERROR_GET_ONE);

                    break;
                case 'getByCriteria':
                    $this->assertCollectionReturn($phpcsFile, $method['ptr'], $props);

                    break;
                case 'getCountByCriteria':
                    $this->assertReturnContains(
                        $phpcsFile,
                        $method['ptr'],
                        $props,
                        'int',
                        'getCountByCriteria() must return int.',
                        self::ERROR_GET_COUNT,
                    );

                    break;
                case 'exists':
                    $this->assertReturnContains(
                        $phpcsFile,
                        $method['ptr'],
                        $props,
                        'bool',
                        'exists() must return bool.',
                        self::ERROR_EXISTS,
                    );

                    break;
                case 'save':
                case 'delete':
                    $this->assertMutationSignature($phpcsFile, $method['ptr'], $name, $method['params'], $props);

                    break;
            }
        }
    }

    /**
     * @return array<string, array{ptr: int, props: array<string, mixed>, params: list<mixed>}>
     */
    private function collectMethods(File $phpcsFile, int $interfacePtr): array
    {
        $tokens     = $phpcsFile->getTokens();
        $scopeStart = $tokens[$interfacePtr]['scope_opener'];
        $scopeEnd   = $tokens[$interfacePtr]['scope_closer'];

        $methods = [];
        $pointer = $scopeStart;
        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToInterface($tokens, $pointer, $interfacePtr) === false) {
                continue;
            }

            $methodName = $phpcsFile->getDeclarationName($pointer);
            if ($methodName === '') {
                continue;
            }

            $methods[$methodName] = [
                'ptr'    => $pointer,
                'props'  => $phpcsFile->getMethodProperties($pointer),
                'params' => $phpcsFile->getMethodParameters($pointer),
            ];
        }

        return $methods;
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertEntityReturn(
        File $phpcsFile,
        int $methodPtr,
        array $methodProps,
        bool $nullable,
        string $code,
    ): void {
        $returnType = (string) ($methodProps['return_type'] ?? '');
        $isNullable = ($methodProps['nullable_return_type'] ?? false) === true
            || str_contains($returnType, 'null') === true;
        $isDomainType = $this->returnTypeLooksLikeDomainType($returnType);

        if ($isDomainType === true && $isNullable === $nullable) {
            return;
        }

        $methodName = $phpcsFile->getDeclarationName($methodPtr);
        $phpcsFile->addError(
            sprintf(
                '%s() must return %sdomain type (*Model or *Vo).' . $this->docRef,
                $methodName,
                $nullable === true ? 'a nullable ' : 'a non-nullable ',
            ),
            $methodPtr,
            $code,
        );
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertCollectionReturn(File $phpcsFile, int $methodPtr, array $methodProps): void
    {
        $returnType = (string) ($methodProps['return_type'] ?? '');
        $isCollection = $returnType !== ''
            && (str_contains($returnType, 'array') === true || str_contains($returnType, 'list<') === true);

        if ($isCollection === true) {
            return;
        }

        $phpcsFile->addError(
            'getByCriteria() must return a collection (array or list<*>), never null.' . $this->docRef,
            $methodPtr,
            self::ERROR_GET_BY_CRITERIA,
        );
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertReturnContains(
        File $phpcsFile,
        int $methodPtr,
        array $methodProps,
        string $expected,
        string $message,
        string $code,
    ): void {
        $returnType = (string) ($methodProps['return_type'] ?? '');
        if ($returnType !== '' && str_contains($returnType, $expected) === true) {
            return;
        }

        $phpcsFile->addError($message . $this->docRef, $methodPtr, $code);
    }

    /**
     * @param list<mixed> $parameters
     * @param array<string, mixed> $methodProps
     */
    private function assertMutationSignature(
        File $phpcsFile,
        int $methodPtr,
        string $methodName,
        array $parameters,
        array $methodProps,
    ): void {
        $code          = $methodName === 'save' ? self::ERROR_SAVE : self::ERROR_DELETE;
        $returnType    = (string) ($methodProps['return_type'] ?? '');
        $entityParam   = $parameters[0] ?? null;
        $rawTypeHint   = $entityParam !== null ? (string) ($entityParam['type_hint'] ?? '') : '';
        $entityType    = ltrim($rawTypeHint, '?\\');

        $valid = count($parameters) === 1
            && $entityType !== ''
            && str_ends_with($entityType, 'Model') === true
            && $returnType === 'void';

        if ($valid === true) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Repository %s() must accept a single domain entity (*Model) and return void.' . $this->docRef,
                $methodName,
            ),
            $methodPtr,
            $code,
        );
    }

    private function returnTypeLooksLikeDomainType(string $returnType): bool
    {
        foreach ($this->extractClassNames($returnType) as $name) {
            if (isset(self::PRIMITIVE_TYPES[strtolower($name)]) === true) {
                continue;
            }

            if (str_ends_with($name, 'Model') === true || str_ends_with($name, 'Vo') === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * A repository interface must not mix `*Model` and `*Vo`: a VO belongs in its
     * own repository. A pure VO repository (only `*Vo`) is valid. Reported on the
     * first method returning a `*Vo` when a `*Model` is also present.
     *
     * @param array<string, array{ptr: int, props: array<string, mixed>, params: list<mixed>}> $methods
     */
    private function assertNoMixedTypes(File $phpcsFile, array $methods): void
    {
        $hasModel = false;
        foreach ($methods as $method) {
            foreach ($this->extractClassNames((string) ($method['props']['return_type'] ?? '')) as $name) {
                $hasModel = $hasModel || str_ends_with($name, 'Model');
            }

            foreach ($method['params'] as $parameter) {
                foreach ($this->extractClassNames((string) ($parameter['type_hint'] ?? '')) as $name) {
                    $hasModel = $hasModel || str_ends_with($name, 'Model');
                }
            }
        }

        if ($hasModel === false) {
            return;
        }

        foreach ($methods as $method) {
            foreach ($this->extractClassNames((string) ($method['props']['return_type'] ?? '')) as $name) {
                if (str_ends_with($name, 'Vo') === true) {
                    $phpcsFile->addError(
                        'Repository interface must not mix *Model and *Vo; a VO belongs in its own repository.'
                        . $this->docRef,
                        $method['ptr'],
                        self::ERROR_MIXED_TYPES,
                    );

                    return;
                }
            }
        }
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
     * @param array<int, array<string, mixed>> $tokens
     */
    private function belongsToInterface(array $tokens, int $tokenPtr, int $interfacePtr): bool
    {
        if (isset($tokens[$tokenPtr]['conditions']) === false || $tokens[$tokenPtr]['conditions'] === []) {
            return false;
        }

        return array_key_last($tokens[$tokenPtr]['conditions']) === $interfacePtr;
    }

    private function resolveRelativeSrcPath(string $normalizedPath): ?string
    {
        if (preg_match('~(^|/)(src/.*)$~', $normalizedPath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    private function buildRef(string $docsPath, string $conventionFile): string
    {
        $localPath = $docsPath . '/' . $conventionFile;

        return sprintf(
            ' See: %1$s (https://github.com/prikotov/coding-standard/blob/master/%1$s)',
            $localPath,
        );
    }
}
