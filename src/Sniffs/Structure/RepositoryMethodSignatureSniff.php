<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Validates method signatures inside Doctrine repository implementations.
 *
 * A repository is a Domain contract implemented against the persistence layer,
 * so its public methods must operate on domain types (entities, value objects,
 * criteria, scalars) and must not leak Doctrine infrastructure types
 * (QueryBuilder, EntityManager, Connection, …) to the outside. Raw DBAL query
 * execution (executeQuery / fetch* / prepare / …) is forbidden entirely — reads
 * go through CriteriaMapper/QueryBuilder, writes through ORM persistence.
 * The constructor is exempt — it legitimately injects ManagerRegistry /
 * CriteriaMapper.
 *
 * Type-locked signatures are checked for the conventional methods when they
 * are declared in the class: `save`, `delete`, `getCountByCriteria`,
 * `getByCriteria`, `getOneByCriteria`, `getById`.
 *
 * See: docs/conventions/layers/domain/repository.md
 *      docs/conventions/layers/infrastructure/repository.md
 */
final class RepositoryMethodSignatureSniff implements Sniff
{
    private const ERROR_DOCTRINE_LEAK = 'DoctrineInfrastructureLeak';
    private const ERROR_RAW_DBAL = 'RawDbalCallForbidden';
    private const ERROR_SAVE_SIGNATURE = 'SaveMustTakeEntityReturnVoid';
    private const ERROR_DELETE_SIGNATURE = 'DeleteMustTakeEntityReturnVoid';
    private const ERROR_GET_COUNT_RETURN = 'GetCountByCriteriaMustReturnInt';
    private const ERROR_GET_BY_CRITERIA_RETURN = 'GetByCriteriaMustReturnCollection';
    private const ERROR_GET_ONE_RETURN = 'GetOneByCriteriaMustReturnNullableEntity';
    private const ERROR_GET_BY_ID_RETURN = 'GetByIdMustReturnEntity';

    private const REPOSITORY_DIRECTORY_SEGMENT = 'Infrastructure/Repository/';

    private const DOC_REF = ' See: vendor/prikotov/coding-standard/'
        . 'docs/conventions/layers/domain/repository.md'
        . ' (https://github.com/prikotov/coding-standard/blob/master/'
        . 'docs/conventions/layers/domain/repository.md)';
    private const READ_MODEL_REF = ' For aggregate/read-model projections use the Read Model pattern:'
        . ' vendor/prikotov/coding-standard/docs/conventions/core-patterns/read-model.md';

    /** @var array<string, true> */
    private const RAW_DBAL_METHODS = [
        'executeQuery'        => true,
        'executeStatement'    => true,
        'executeUpdate'       => true,
        'prepare'             => true,
        'fetchAssociative'    => true,
        'fetchOne'            => true,
        'fetchNumeric'        => true,
        'fetchAllAssociative' => true,
        'fetchAllNumeric'     => true,
        'fetchFirstColumn'    => true,
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
    private const DOCTRINE_NAMESPACES = [
        'Doctrine\\ORM\\'        => true,
        'Doctrine\\DBAL\\'       => true,
        'Doctrine\\Persistence\\' => true,
    ];

    private const SIGNATURE_METHODS = [
        'save'                => self::ERROR_SAVE_SIGNATURE,
        'delete'              => self::ERROR_DELETE_SIGNATURE,
        'getCountByCriteria'  => self::ERROR_GET_COUNT_RETURN,
        'getByCriteria'       => self::ERROR_GET_BY_CRITERIA_RETURN,
        'getOneByCriteria'    => self::ERROR_GET_ONE_RETURN,
        'getById'             => self::ERROR_GET_BY_ID_RETURN,
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $relativePath = $this->resolveRelativeSrcPath(str_replace('\\', '/', $phpcsFile->getFilename()));
        if ($relativePath === null || str_starts_with($relativePath, 'src/Module/') === false) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '' || str_ends_with($className, 'Repository') === false) {
            return;
        }

        if (str_contains($relativePath, self::REPOSITORY_DIRECTORY_SEGMENT) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];
        $useMap      = $this->buildUseMap($phpcsFile, $stackPtr);

        $pointer = $scopeOpener;
        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeCloser)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $stackPtr) === false) {
                continue;
            }

            $this->checkMethod($phpcsFile, $pointer, $useMap);
        }

        $this->assertNoRawDbalCalls($phpcsFile, $stackPtr);
    }

    private function checkMethod(File $phpcsFile, int $methodPtr, array $useMap): void
    {
        $methodProps = $phpcsFile->getMethodProperties($methodPtr);
        $methodName  = $phpcsFile->getDeclarationName($methodPtr);
        if ($methodName === '') {
            return;
        }

        if ($methodProps['scope'] !== 'public') {
            return;
        }

        if ($methodName !== '__construct') {
            $this->assertNoDoctrineLeak($phpcsFile, $methodPtr, $methodProps, $useMap);
        }

        if (isset(self::SIGNATURE_METHODS[$methodName]) === false) {
            return;
        }

        switch ($methodName) {
            case 'save':
            case 'delete':
                $this->assertMutationSignature($phpcsFile, $methodPtr, $methodName, $methodProps);

                break;
            case 'getCountByCriteria':
                $this->assertReturnTypeContains(
                    $phpcsFile,
                    $methodPtr,
                    $methodProps,
                    'int',
                    'getCountByCriteria() must return int.',
                    self::ERROR_GET_COUNT_RETURN,
                );

                break;
            case 'getByCriteria':
                $this->assertReturnTypeIsCollection($phpcsFile, $methodPtr, $methodProps);

                break;
            case 'getOneByCriteria':
                $this->assertReturnTypeIsNullableEntity($phpcsFile, $methodPtr, $methodProps);

                break;
            case 'getById':
                $this->assertReturnTypeIsEntity($phpcsFile, $methodPtr, $methodProps);

                break;
        }
    }

    /**
     * Forbids raw DBAL query execution calls anywhere in the repository. These
     * method names are unambiguous (they do not exist on the ORM QueryBuilder),
     * so any call points at hand-written SQL that should live in a mapper.
     */
    private function assertNoRawDbalCalls(File $phpcsFile, int $classPtr): void
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

            $methodName = (string) $tokens[$methodNamePtr]['content'];
            if (isset(self::RAW_DBAL_METHODS[$methodName]) === false) {
                continue;
            }

            $openParenthesis = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $methodNamePtr + 1, $methodNamePtr + 4);
            if ($openParenthesis === false) {
                continue;
            }

            $phpcsFile->addError(
                sprintf(
                    'Repository must not call raw DBAL method %s(); read via CriteriaMapper/QueryBuilder,'
                    . ' write via ORM persistence.' . self::DOC_REF . self::READ_MODEL_REF,
                    $methodName,
                ),
                $methodNamePtr,
                self::ERROR_RAW_DBAL,
            );
        }
    }

    /**
     * @param array<string, mixed> $methodProps
     * @param array<string, string> $useMap
     */
    private function assertNoDoctrineLeak(
        File $phpcsFile,
        int $methodPtr,
        array $methodProps,
        array $useMap,
    ): void {
        $typeSources   = [];
        $typeSources[] = (string) ($methodProps['return_type'] ?? '');

        foreach ($phpcsFile->getMethodParameters($methodPtr) as $parameter) {
            $typeSources[] = (string) ($parameter['type_hint'] ?? '');
        }

        foreach ($typeSources as $typeSource) {
            foreach ($this->extractClassNames($typeSource) as $name) {
                $fqcn = $this->resolveFqcn($name, $useMap);
                if ($this->isDoctrineInfrastructure($fqcn) === true) {
                    $phpcsFile->addError(
                        sprintf(
                            'Repository public methods must not expose Doctrine infrastructure type'
                            . ' "%s"; operate on domain entities, value objects, criteria or scalars.'
                            . self::DOC_REF,
                            $fqcn,
                        ),
                        $methodPtr,
                        self::ERROR_DOCTRINE_LEAK,
                    );

                    return;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertMutationSignature(
        File $phpcsFile,
        int $methodPtr,
        string $methodName,
        array $methodProps,
    ): void {
        $code        = $methodName === 'save' ? self::ERROR_SAVE_SIGNATURE : self::ERROR_DELETE_SIGNATURE;
        $parameters  = $phpcsFile->getMethodParameters($methodPtr);
        $returnType  = (string) ($methodProps['return_type'] ?? '');

        $entityParameter = $parameters[0] ?? null;
        $rawTypeHint     = $entityParameter !== null ? (string) ($entityParameter['type_hint'] ?? '') : '';
        $entityType      = ltrim($rawTypeHint, '?\\');

        $valid = count($parameters) === 1
            && $entityType !== ''
            && str_ends_with($entityType, 'Model') === true
            && $returnType === 'void';

        if ($valid === true) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Repository %s() must accept a single domain entity (*Model) and return void.' . self::DOC_REF,
                $methodName,
            ),
            $methodPtr,
            $code,
        );
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertReturnTypeContains(
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

        $phpcsFile->addError($message . self::DOC_REF, $methodPtr, $code);
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertReturnTypeIsCollection(File $phpcsFile, int $methodPtr, array $methodProps): void
    {
        $returnType = (string) ($methodProps['return_type'] ?? '');
        $isCollection = $returnType !== ''
            && (str_contains($returnType, 'array') === true || str_contains($returnType, 'list<') === true);

        if ($isCollection === true) {
            return;
        }

        $phpcsFile->addError(
            'getByCriteria() must return a collection (array or list<*>), never null.' . self::DOC_REF,
            $methodPtr,
            self::ERROR_GET_BY_CRITERIA_RETURN,
        );
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertReturnTypeIsNullableEntity(File $phpcsFile, int $methodPtr, array $methodProps): void
    {
        $returnType   = (string) ($methodProps['return_type'] ?? '');
        $isNullable   = ($methodProps['nullable_return_type'] ?? false) === true
            || str_contains($returnType, 'null') === true
            || str_contains($returnType, 'Null') === true;
        $isEntityLike = $this->returnTypeLooksLikeEntity($returnType);

        if ($isNullable === true && $isEntityLike === true) {
            return;
        }

        $phpcsFile->addError(
            'getOneByCriteria() must return a nullable domain entity (?*Model).' . self::DOC_REF,
            $methodPtr,
            self::ERROR_GET_ONE_RETURN,
        );
    }

    /**
     * @param array<string, mixed> $methodProps
     */
    private function assertReturnTypeIsEntity(File $phpcsFile, int $methodPtr, array $methodProps): void
    {
        $returnType = (string) ($methodProps['return_type'] ?? '');
        $isNullable = ($methodProps['nullable_return_type'] ?? false) === true;

        if ($isNullable === false && $this->returnTypeLooksLikeEntity($returnType) === true) {
            return;
        }

        $phpcsFile->addError(
            'getById() must return a domain entity (*Model), not nullable and not a scalar.'
            . ' For a nullable lookup use getOneByCriteria().' . self::DOC_REF,
            $methodPtr,
            self::ERROR_GET_BY_ID_RETURN,
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
     * @param array<string, string> $useMap
     */
    private function resolveFqcn(string $name, array $useMap): string
    {
        if (str_contains($name, '\\') === true) {
            return ltrim($name, '\\');
        }

        return $useMap[$name] ?? $name;
    }

    private function isDoctrineInfrastructure(string $fqcn): bool
    {
        foreach (self::DOCTRINE_NAMESPACES as $namespace => $_) {
            if (str_starts_with($fqcn, $namespace) === true) {
                return true;
            }
        }

        return false;
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
}
