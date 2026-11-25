<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class ValueObjectStructureSniff implements Sniff
{
    private const ERROR_FINAL_READONLY_REQUIRED = 'FinalReadonlyRequired';
    private const ERROR_FORBIDDEN_MEMBERS = 'ForbiddenMembers';
    private const ERROR_FORBIDDEN_MAGIC_METHOD = 'ForbiddenMagicMethod';
    private const ERROR_FORBIDDEN_STATIC_METHOD = 'ForbiddenStaticMethod';
    private const ERROR_TO_RETURN_SELF = 'ToReturnSelfForbidden';
    private const ERROR_VOID_RETURN = 'VoidReturnForbidden';
    private const ERROR_NON_PRIVATE_PROPERTY = 'NonPrivateProperty';
    private const ERROR_NON_READONLY_PROPERTY = 'NonReadonlyProperty';
    private const ERROR_FORBIDDEN_PROPERTY_TYPE = 'ForbiddenPropertyType';
    private const WARNING_NAMESPACE_MISMATCH = 'NamespaceMismatch';
    private const ERROR_MISSING_VO_SUFFIX = 'MissingVoSuffix';

    private const DOC_REF = ' See: docs/conventions/core-patterns/value-object.md';

    private const FORBIDDEN_MAGIC_METHODS = [
        '__set',
        '__unset',
        '__clone',
        '__sleep',
        '__wakeup',
    ];

    private const FORBIDDEN_PROPERTY_TYPE_SUFFIXES = [
        'Entity',
        'Repository',
        'Service',
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $className = $phpcsFile->getDeclarationName($stackPtr);
        if ($className === '') {
            return;
        }

        $hasSuffix = str_ends_with($className, 'Vo');

        if ($hasSuffix === false && str_ends_with($className, 'Test')) {
            return;
        }

        if ($hasSuffix === false && $this->isInValueObjectNamespace($phpcsFile)) {
            $phpcsFile->addError(
                sprintf(
                    'Class "%s" is in a ValueObject/Vo namespace but does not have a "Vo" suffix.'
                    . ' Rename to "%sVo".' . self::DOC_REF,
                    $className,
                    $className,
                ),
                $stackPtr,
                self::ERROR_MISSING_VO_SUFFIX,
            );

            return;
        }

        if ($hasSuffix === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $this->assertFinalReadonly($phpcsFile, $stackPtr);
        $this->assertNoTraits($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
        $this->assertProperties($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
        $this->assertMethods($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
        $this->assertNamespace($phpcsFile, $stackPtr);
    }

    private function assertFinalReadonly(File $phpcsFile, int $classPtr): void
    {
        $properties = $phpcsFile->getClassProperties($classPtr);
        if (($properties['is_final'] ?? false) === false || ($properties['is_readonly'] ?? false) === false) {
            $phpcsFile->addError(
                'ValueObject classes must be declared as final readonly.' . self::DOC_REF,
                $classPtr,
                self::ERROR_FINAL_READONLY_REQUIRED,
            );
        }
    }

    private function assertNoTraits(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $this->assertNoTokens(
            $phpcsFile,
            $classPtr,
            $scopeStart,
            $scopeEnd,
            [T_USE],
            'ValueObject classes must not use traits.' . self::DOC_REF,
        );
    }

    private function assertProperties(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens          = $phpcsFile->getTokens();
        $isReadonlyClass = $this->isReadonlyClass($phpcsFile, $classPtr);
        $constructorPtr  = $this->findConstructorPtr($phpcsFile, $tokens, $classPtr, $scopeStart, $scopeEnd);
        $pointer         = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_VARIABLE, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $promoted = $this->findPromotedProperty($phpcsFile, $constructorPtr, $pointer);
            if ($promoted !== null) {
                $this->assertProperty(
                    $phpcsFile,
                    $pointer,
                    $promoted['visibility'],
                    $promoted['is_readonly'],
                    $promoted['type'],
                    $isReadonlyClass,
                );

                continue;
            }

            if ($this->isClassProperty($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            try {
                $member = $phpcsFile->getMemberProperties($pointer);
            } catch (RuntimeException) {
                continue;
            }

            if ($member === []) {
                continue;
            }

            $scope      = $member['scope'] ?? 'public';
            $memberType = $member['type'] ?? null;

            $this->assertProperty(
                $phpcsFile,
                $pointer,
                is_string($scope) === true ? $scope : 'public',
                ($member['is_readonly'] ?? false) === true,
                is_string($memberType) === true && $memberType !== '' ? $memberType : null,
                $isReadonlyClass,
            );
        }
    }

    private function isReadonlyClass(File $phpcsFile, int $classPtr): bool
    {
        $properties = $phpcsFile->getClassProperties($classPtr);

        return ($properties['is_readonly'] ?? false) === true;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function findConstructorPtr(
        File $phpcsFile,
        array $tokens,
        int $classPtr,
        int $scopeStart,
        int $scopeEnd,
    ): ?int {
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            if ($phpcsFile->getDeclarationName($pointer) === '__construct') {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Returns promoted constructor property info; `null` for plain parameters.
     *
     * @return array{visibility: string, is_readonly: bool, type: ?string}|null
     */
    private function findPromotedProperty(File $phpcsFile, ?int $constructorPtr, int $variablePtr): ?array
    {
        if ($constructorPtr === null) {
            return null;
        }

        foreach ($phpcsFile->getMethodParameters($constructorPtr) as $param) {
            if (is_array($param) === false || ($param['token'] ?? null) !== $variablePtr) {
                continue;
            }

            $visibility = $param['property_visibility'] ?? null;
            if (is_string($visibility) === false) {
                return null;
            }

            $typeHint = $param['type_hint'] ?? '';
            $type     = is_string($typeHint) === true && $typeHint !== '' ? $typeHint : null;

            return [
                'visibility'  => $visibility,
                'is_readonly' => ($param['property_readonly'] ?? false) === true,
                'type'        => $type,
            ];
        }

        return null;
    }

    /**
     * A real class property declaration: nested directly in the class scope, not
     * inside a method (method body variables and parameters also satisfy
     * belongsToClass, but getMemberProperties() would throw on them).
     *
     * @param array<int, array<string, mixed>> $tokens
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

        // Exclude parameters — they sit in parentheses owned by T_FUNCTION
        // and are handled separately as promoted properties.
        if (isset($tokens[$tokenPtr]['nested_parenthesis']) === true) {
            return false;
        }

        return true;
    }

    private function assertProperty(
        File $phpcsFile,
        int $propertyPtr,
        string $visibility,
        bool $isReadonly,
        ?string $type,
        bool $isReadonlyClass,
    ): void {
        if ($visibility !== 'private') {
            $phpcsFile->addError(
                sprintf(
                    'ValueObject properties must be private, found %s.',
                    $visibility,
                ) . self::DOC_REF,
                $propertyPtr,
                self::ERROR_NON_PRIVATE_PROPERTY,
            );
        }

        // In a readonly class every property is implicitly readonly (PHP),
        // so the explicit keyword is not required there.
        if ($isReadonlyClass === false && $isReadonly === false) {
            $phpcsFile->addError(
                'ValueObject properties must be readonly.' . self::DOC_REF,
                $propertyPtr,
                self::ERROR_NON_READONLY_PROPERTY,
            );
        }

        $this->assertPropertyTypeAllowed($phpcsFile, $propertyPtr, $type);
    }

    private function assertPropertyTypeAllowed(File $phpcsFile, int $propertyPtr, ?string $type): void
    {
        if ($type === null || $type === '') {
            return;
        }

        foreach (self::FORBIDDEN_PROPERTY_TYPE_SUFFIXES as $suffix) {
            if (str_ends_with($type, $suffix)) {
                $phpcsFile->addError(
                    sprintf(
                        'ValueObject properties must not depend on %s.'
                        . ' Only primitives, DateTimeImmutable, Enum, and other VO are allowed.' . self::DOC_REF,
                        $type,
                    ),
                    $propertyPtr,
                    self::ERROR_FORBIDDEN_PROPERTY_TYPE,
                );

                return;
            }
        }
    }

    private function assertMethods(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        // First pass: collect methods
        $methodPtrs = [];
        $scanPtr    = $scopeStart;

        while (($scanPtr = $phpcsFile->findNext(T_FUNCTION, $scanPtr + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $scanPtr, $classPtr) === false) {
                continue;
            }

            $methodName = $phpcsFile->getDeclarationName($scanPtr);
            if ($methodName === '') {
                continue;
            }

            $methodPtrs[] = $scanPtr;
        }

        // Second pass: validate each method
        foreach ($methodPtrs as $methodPointer) {
            $methodName = $phpcsFile->getDeclarationName($methodPointer);

            if (in_array($methodName, self::FORBIDDEN_MAGIC_METHODS, true)) {
                $phpcsFile->addError(
                    sprintf('ValueObject classes must not declare %s().' . self::DOC_REF, $methodName),
                    $methodPointer,
                    self::ERROR_FORBIDDEN_MAGIC_METHOD,
                );

                continue;
            }

            if ($methodName === '__construct') {
                continue;
            }

            if ($methodName === '__toString') {
                continue;
            }

            $isStatic = $this->isMethodStatic($phpcsFile, $methodPointer);

            if ($isStatic) {
                if ($this->isStaticFactory($methodName) === false) {
                    $phpcsFile->addError(
                        sprintf(
                            'ValueObject static methods must be named create*().'
                            . ' Found static %s().' . self::DOC_REF,
                            $methodName,
                        ),
                        $methodPointer,
                        self::ERROR_FORBIDDEN_STATIC_METHOD,
                    );
                }

                continue;
            }

            // Non-static methods
            if ($this->methodReturnsType($phpcsFile, $methodPointer, 'void')) {
                $phpcsFile->addError(
                    'ValueObject methods must not have void return type.' . self::DOC_REF,
                    $methodPointer,
                    self::ERROR_VOID_RETURN,
                );

                continue;
            }

            // to*() returning self/static is forbidden — to* is a converter to another type
            if (str_starts_with($methodName, 'to') && strlen($methodName) > strlen('to')) {
                if ($this->methodReturnsSelf($phpcsFile, $methodPointer)) {
                    $phpcsFile->addError(
                        sprintf(
                            'ValueObject to*() must return a different type, not self/static.'
                            . ' Found %s(): self.' . self::DOC_REF,
                            $methodName,
                        ),
                        $methodPointer,
                        self::ERROR_TO_RETURN_SELF,
                    );
                }
            }
        }
    }

    private function isStaticFactory(string $methodName): bool
    {
        return str_starts_with($methodName, 'create')
            && strlen($methodName) > strlen('create');
    }

    private function isMethodStatic(File $phpcsFile, int $methodPtr): bool
    {
        try {
            $props = $phpcsFile->getMethodProperties($methodPtr);
        } catch (RuntimeException) {
            return false;
        }

        return ($props['is_static'] ?? false) === true;
    }

    private function assertNamespace(File $phpcsFile, int $classPtr): void
    {
        $namespace = $this->resolveNamespace($phpcsFile);

        if ($namespace['name'] === null) {
            return;
        }

        if ($this->isInValueObjectNamespace($phpcsFile)) {
            return;
        }

        $phpcsFile->addWarning(
            'ValueObject class with "Vo" suffix should be in a ValueObject or Vo namespace.' . self::DOC_REF,
            $namespace['ptr'] ?? $classPtr,
            self::WARNING_NAMESPACE_MISMATCH,
        );
    }

    private function isInValueObjectNamespace(File $phpcsFile): bool
    {
        $namespace = $this->resolveNamespace($phpcsFile);

        if ($namespace['name'] === null) {
            return false;
        }

        return str_contains($namespace['name'], '\ValueObject')
            || preg_match('/\\\Vo\\\/', $namespace['name']) === 1;
    }

    private function methodReturnsType(File $phpcsFile, int $methodPtr, string $type): bool
    {
        $content = $this->getReturnTypeContent($phpcsFile, $methodPtr);

        return $content !== null && str_contains($content, $type);
    }

    private function methodReturnsSelf(File $phpcsFile, int $methodPtr): bool
    {
        $content = $this->getReturnTypeContent($phpcsFile, $methodPtr);

        return $content !== null
            && (str_contains($content, 'self') || str_contains($content, 'static'));
    }

    private function getReturnTypeContent(File $phpcsFile, int $methodPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        $closeParen  = $tokens[$methodPtr]['parenthesis_closer'] ?? null;
        $scopeOpener = $tokens[$methodPtr]['scope_opener'] ?? null;

        if ($closeParen === null || $scopeOpener === null) {
            return null;
        }

        $content = '';
        for ($i = $closeParen + 1; $i < $scopeOpener; $i++) {
            $content .= $tokens[$i]['content'];
        }

        return $content;
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

    private function assertNoTokens(
        File $phpcsFile,
        int $classPtr,
        int $scopeStart,
        int $scopeEnd,
        array $tokenTypes,
        string $message,
    ): void {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext($tokenTypes, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $phpcsFile->addError(
                $message,
                $pointer,
                self::ERROR_FORBIDDEN_MEMBERS,
            );
        }
    }

    /**
     * @return array{name: ?string, ptr: ?int}
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
            'ptr'  => $namespacePtr,
        ];
    }
}
