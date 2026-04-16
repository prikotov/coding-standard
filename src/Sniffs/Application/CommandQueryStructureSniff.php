<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Application;

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class CommandQueryStructureSniff implements Sniff
{
    private const ERROR_ONLY_CONSTRUCTOR = 'OnlyConstructorAllowed';
    private const ERROR_CONSTRUCTOR_REQUIRED = 'ConstructorRequired';
    private const ERROR_CONSTRUCTOR_NOT_EMPTY = 'ConstructorMustBeEmpty';
    private const ERROR_FORBIDDEN_MEMBERS = 'ForbiddenMembers';

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

        if (
            $this->isCommandOrQuery($className) === false
            || $this->isUseCaseMessage($phpcsFile->getFilename()) === false
        ) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $constructorPtr = $this->assertOnlyConstructor($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);

        if ($constructorPtr !== null) {
            $this->assertConstructorIsEmpty($phpcsFile, $constructorPtr);
        }

        $this->assertNoMembers($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
    }

    private function isCommandOrQuery(string $className): bool
    {
        return str_ends_with($className, 'Command')
            || str_ends_with($className, 'Query');
    }

    private function isUseCaseMessage(string $filename): bool
    {
        $normalizedPath = str_replace('\\', '/', $filename);

        if (str_contains($normalizedPath, '/tests/Application/')) {
            return true;
        }

        return str_contains($normalizedPath, '/Application/UseCase/Command/')
            || str_contains($normalizedPath, '/Application/UseCase/Query/');
    }

    private function assertOnlyConstructor(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): ?int
    {
        $constructorPtr = null;
        $tokens         = $phpcsFile->getTokens();
        $pointer        = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $methodName = strtolower($phpcsFile->getDeclarationName($pointer));
            if ($methodName !== '__construct') {
                $phpcsFile->addError(
                    'Command/Query classes must not declare methods other than the constructor.',
                    $pointer,
                    self::ERROR_ONLY_CONSTRUCTOR,
                );
                continue;
            }

            $constructorPtr = $pointer;
        }

        if ($constructorPtr === null) {
            $phpcsFile->addError(
                'Command/Query classes must declare a constructor with promoted properties.',
                $classPtr,
                self::ERROR_CONSTRUCTOR_REQUIRED,
            );
        }

        return $constructorPtr;
    }

    private function assertConstructorIsEmpty(File $phpcsFile, int $constructorPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$constructorPtr]['scope_opener'], $tokens[$constructorPtr]['scope_closer']) === false) {
            return;
        }

        $bodyStart = $tokens[$constructorPtr]['scope_opener'];
        $bodyEnd   = $tokens[$constructorPtr]['scope_closer'];

        $nextToken = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            $bodyStart + 1,
            $bodyEnd,
            true,
        );

        if ($nextToken !== false) {
            $phpcsFile->addError(
                'Constructor of Command/Query classes must not contain executable code.',
                $nextToken,
                self::ERROR_CONSTRUCTOR_NOT_EMPTY,
            );
        }
    }

    private function assertNoMembers(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext([T_VARIABLE], $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            try {
                $phpcsFile->getMemberProperties($pointer);
            } catch (RuntimeException $exception) {
                continue;
            }

            $phpcsFile->addError(
                'Command/Query classes must not declare properties; use promoted constructor parameters instead.',
                $pointer,
                self::ERROR_FORBIDDEN_MEMBERS,
            );
        }

        $this->assertNoTokens($phpcsFile, $classPtr, $scopeStart, $scopeEnd, [T_CONST], 'Command/Query classes must not declare constants.');
        $this->assertNoTokens($phpcsFile, $classPtr, $scopeStart, $scopeEnd, [T_USE], 'Command/Query classes must not use traits.');
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
}
