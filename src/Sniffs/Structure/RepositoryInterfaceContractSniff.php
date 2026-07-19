<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PrikotovCodingStandard\Config\CodingStandardConfig;

/**
 * Enforces the conventional contract on Domain repository interfaces.
 *
 * An interface located in Domain/Repository/ and ending with
 * `RepositoryInterface` (incl. `ReadRepositoryInterface` /
 * `WriteRepositoryInterface`) must declare the conventional methods:
 *
 *  - Read/Full:  getById, getOneByCriteria, getByCriteria, getCountByCriteria
 *  - Write:      save
 *
 * Signatures are type-locked to the convention (entity model in/out, criteria
 * in, scalars/array/int out).
 *
 * Read-model / aggregate interfaces (those whose methods never accept or return
 * a domain entity, e.g. summaries returning VOs) are intentionally skipped —
 * they are a different kind of contract.
 *
 * See: docs/conventions/layers/domain/repository.md
 */
final class RepositoryInterfaceContractSniff implements Sniff
{
    private const ERROR_MISSING = 'MissingTypicalMethod';
    private const ERROR_GET_BY_ID = 'GetByIdMustReturnEntity';
    private const ERROR_GET_ONE = 'GetOneByCriteriaMustReturnNullableEntity';
    private const ERROR_GET_BY_CRITERIA = 'GetByCriteriaMustReturnCollection';
    private const ERROR_GET_COUNT = 'GetCountByCriteriaMustReturnInt';
    private const ERROR_SAVE = 'SaveMustTakeEntityReturnVoid';
    private const ERROR_DELETE = 'DeleteMustTakeEntityReturnVoid';

    private const DOMAIN_REPOSITORY_PATH = 'Domain/Repository/';
    private const READ_INTERFACE_SUFFIX = 'ReadRepositoryInterface';
    private const WRITE_INTERFACE_SUFFIX = 'WriteRepositoryInterface';
    private const INTERFACE_SUFFIX = 'RepositoryInterface';

    private string $docRef = '';

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

        $kind = $this->kind($interfaceName);

        // Read-model / aggregate interfaces (no domain entity in any signature) are a
        // different contract — only entity interfaces are type-locked. Write
        // interfaces are always entity-oriented, so they are never skipped here.
        if ($kind !== 'write' && $this->isEntityInterface($methods) === false) {
            return;
        }

        $this->assertRequiredMethods($phpcsFile, $stackPtr, $interfaceName, $methods);
        $this->assertSignatures($phpcsFile, $methods);
    }

    /**
     * @param array<string, array{props: array<string, mixed>, params: list<mixed>}> $methods
     */
    private function assertRequiredMethods(
        File $phpcsFile,
        int $interfacePtr,
        string $interfaceName,
        array $methods,
    ): void {
        $required = $this->requiredMethods($interfaceName);

        foreach ($required as $methodName) {
            if (isset($methods[$methodName]) === true) {
                continue;
            }

            $phpcsFile->addError(
                sprintf(
                    'Repository interface "%s" must declare the conventional method %s().' . $this->docRef,
                    $interfaceName,
                    $methodName,
                ),
                $interfacePtr,
                self::ERROR_MISSING,
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
                case 'save':
                case 'delete':
                    $this->assertMutationSignature($phpcsFile, $method['ptr'], $name, $method['params'], $props);

                    break;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function requiredMethods(string $interfaceName): array
    {
        return $this->kind($interfaceName) === 'write'
            ? ['save']
            : ['getById', 'getOneByCriteria', 'getByCriteria', 'getCountByCriteria'];
    }

    private function kind(string $interfaceName): string
    {
        if (str_ends_with($interfaceName, self::WRITE_INTERFACE_SUFFIX) === true) {
            return 'write';
        }

        if (str_ends_with($interfaceName, self::READ_INTERFACE_SUFFIX) === true) {
            return 'read';
        }

        return 'full';
    }

    /**
     * @param array<string, array{props: array<string, mixed>, params: list<mixed>}> $methods
     */
    private function isEntityInterface(array $methods): bool
    {
        foreach ($methods as $method) {
            $returnType = (string) ($method['props']['return_type'] ?? '');
            foreach ($this->extractClassNames($returnType) as $name) {
                if (str_ends_with($name, 'Model') === true) {
                    return true;
                }
            }

            foreach ($method['params'] as $parameter) {
                $typeHint = (string) ($parameter['type_hint'] ?? '');
                foreach ($this->extractClassNames($typeHint) as $name) {
                    if (str_ends_with($name, 'Model') === true) {
                        return true;
                    }
                }
            }
        }

        return false;
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
        $isEntity   = $this->returnTypeLooksLikeEntity($returnType);

        if ($isEntity === true && $isNullable === $nullable) {
            return;
        }

        $methodName = $phpcsFile->getDeclarationName($methodPtr);
        $phpcsFile->addError(
            sprintf(
                '%s() must return %sdomain entity (*Model).' . $this->docRef,
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

    private function returnTypeLooksLikeEntity(string $returnType): bool
    {
        foreach ($this->extractClassNames($returnType) as $name) {
            if (isset(self::PRIMITIVE_TYPES[strtolower($name)]) === true) {
                continue;
            }

            if (str_ends_with($name, 'Model') === true) {
                return true;
            }
        }

        return false;
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
