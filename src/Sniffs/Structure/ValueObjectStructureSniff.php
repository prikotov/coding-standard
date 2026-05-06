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
    private const ERROR_FORBIDDEN_METHOD = 'ForbiddenMethod';
    private const ERROR_FORBIDDEN_STATIC_METHOD = 'ForbiddenStaticMethod';
    private const ERROR_TO_RETURN_SELF = 'ToReturnSelfForbidden';
    private const ERROR_WITH_METHOD = 'WithMethodForbidden';
    private const ERROR_VOID_RETURN = 'VoidReturnForbidden';
    private const ERROR_STATIC_WITHOUT_PRIVATE_CONSTRUCTOR = 'StaticWithoutPrivateConstructor';
    private const ERROR_NON_READONLY_PROPERTY = 'NonReadonlyProperty';
    private const ERROR_FORBIDDEN_PROPERTY_TYPE = 'ForbiddenPropertyType';
    private const WARNING_NAMESPACE_MISMATCH = 'NamespaceMismatch';

    private const DOC_REF = ' See: docs/conventions/core-patterns/value-object.md';

    private const FORBIDDEN_MAGIC_METHODS = [
        '__set',
        '__unset',
        '__clone',
        '__sleep',
        '__wakeup',
    ];

    private const ALLOWED_GETTER_PREFIXES = ['get', 'is', 'has', 'to'];

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
        if ($className === '' || str_ends_with($className, 'Vo') === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $this->assertFinalReadonly($phpcsFile, $stackPtr);
        $this->assertNoConstOrTraits($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
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

    private function assertNoConstOrTraits(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $this->assertNoTokens(
            $phpcsFile,
            $classPtr,
            $scopeStart,
            $scopeEnd,
            [T_CONST],
            'ValueObject classes must not declare constants.' . self::DOC_REF,
        );
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
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_VARIABLE, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
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

            if (($member['is_readonly'] ?? false) === false) {
                $phpcsFile->addError(
                    'ValueObject properties must be readonly.' . self::DOC_REF,
                    $pointer,
                    self::ERROR_NON_READONLY_PROPERTY,
                );
            }

            $this->assertPropertyTypeAllowed($phpcsFile, $pointer, $member['type'] ?? null);
        }
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

        $hasStaticFactory = false;
        $constructorPtr   = null;

        // First pass: collect constructor and static factory info
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

            if ($methodName === '__construct') {
                $constructorPtr = $scanPtr;
            }

            if ($this->isStaticFactory($methodName)) {
                $hasStaticFactory = true;
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

            // with*() is forbidden — use explicit new SomeVo(...) instead
            if (str_starts_with($methodName, 'with') && strlen($methodName) > strlen('with')) {
                $phpcsFile->addError(
                    sprintf(
                        'ValueObject must not declare with*() methods.'
                        . ' Create a new instance explicitly instead. Found %s().' . self::DOC_REF,
                        $methodName,
                    ),
                    $methodPointer,
                    self::ERROR_WITH_METHOD,
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

                    continue;
                }
            }

            if ($this->isAllowedInstanceMethod($phpcsFile, $methodPointer, $methodName)) {
                continue;
            }

            $phpcsFile->addError(
                sprintf(
                    'ValueObject method "%s()" is not allowed.'
                    . ' Allowed: getters (get*, is*, has*, to*), predicates returning bool,'
                    . ' static factories (create*), __toString.' . self::DOC_REF,
                    $methodName,
                ),
                $methodPointer,
                self::ERROR_FORBIDDEN_METHOD,
            );
        }

        // Static factory requires private constructor
        if ($hasStaticFactory && $constructorPtr !== null) {
            $this->assertConstructorIsPrivate($phpcsFile, $constructorPtr);
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

    private function assertConstructorIsPrivate(File $phpcsFile, int $constructorPtr): void
    {
        try {
            $props = $phpcsFile->getMethodProperties($constructorPtr);
        } catch (RuntimeException) {
            return;
        }

        if (($props['scope'] ?? '') !== 'private') {
            $phpcsFile->addError(
                'ValueObject with static factory (create*) must have a private constructor.' . self::DOC_REF,
                $constructorPtr,
                self::ERROR_STATIC_WITHOUT_PRIVATE_CONSTRUCTOR,
            );
        }
    }

    private function isAllowedInstanceMethod(File $phpcsFile, int $methodPtr, string $methodName): bool
    {
        foreach (self::ALLOWED_GETTER_PREFIXES as $prefix) {
            if (str_starts_with($methodName, $prefix) && strlen($methodName) > strlen($prefix)) {
                return true;
            }
        }

        if ($this->methodReturnsType($phpcsFile, $methodPtr, 'bool')) {
            return true;
        }

        return false;
    }

    private function assertNamespace(File $phpcsFile, int $classPtr): void
    {
        $namespace = $this->resolveNamespace($phpcsFile);

        if ($namespace['name'] === null) {
            return;
        }

        if (str_contains($namespace['name'], 'ValueObject') || preg_match('/\\\\Vo\\\\/', $namespace['name']) === 1) {
            return;
        }

        $phpcsFile->addWarning(
            'ValueObject class with "Vo" suffix should be in a ValueObject or Vo namespace.' . self::DOC_REF,
            $namespace['ptr'] ?? $classPtr,
            self::WARNING_NAMESPACE_MISMATCH,
        );
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
