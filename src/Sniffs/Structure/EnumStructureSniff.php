<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class EnumStructureSniff implements Sniff
{
    private const ERROR_FORBIDDEN_MEMBERS = 'ForbiddenMembers';
    private const ERROR_CAMEL_CASE = 'CaseNameMustBeCamelCase';

    public function register(): array
    {
        return [T_ENUM];
    }

    /**
     * @param int $stackPtr Pointer to the enum token.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $this->assertNoTokens($phpcsFile, $stackPtr, $scopeStart, $scopeEnd, [T_FUNCTION], 'Enums must not declare methods.');
        $this->assertNoTokens($phpcsFile, $stackPtr, $scopeStart, $scopeEnd, [T_CONST], 'Enums must not declare constants.');
        $this->assertNoTokens($phpcsFile, $stackPtr, $scopeStart, $scopeEnd, [T_USE], 'Enums must not use traits.');

        $this->assertCasesCamelCase($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
    }

    private function assertCasesCamelCase(File $phpcsFile, int $enumPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens      = $phpcsFile->getTokens();
        $pointer     = $scopeStart;
        $caseTokens  = [T_CASE];
        if (defined('T_ENUM_CASE')) {
            $caseTokens[] = T_ENUM_CASE;
        }

        while (($pointer = $phpcsFile->findNext($caseTokens, $pointer + 1, $scopeEnd)) !== false) {
            $caseNamePtr = $phpcsFile->findNext([T_STRING], $pointer + 1, $scopeEnd);
            if ($caseNamePtr === false) {
                continue;
            }

            $caseName = $tokens[$caseNamePtr]['content'];
            if ($this->isCamelCase($caseName) === false) {
                $phpcsFile->addError(
                    'Enum case names must follow camelCase.',
                    $caseNamePtr,
                    self::ERROR_CAMEL_CASE,
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function belongsToEnum(array $tokens, int $tokenPtr, int $enumPtr): bool
    {
        if (isset($tokens[$tokenPtr]['conditions']) === false || $tokens[$tokenPtr]['conditions'] === []) {
            return false;
        }

        return array_key_last($tokens[$tokenPtr]['conditions']) === $enumPtr;
    }

    private function assertNoTokens(
        File $phpcsFile,
        int $enumPtr,
        int $scopeStart,
        int $scopeEnd,
        array $tokenTypes,
        string $message,
    ): void {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext($tokenTypes, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToEnum($tokens, $pointer, $enumPtr) === false) {
                continue;
            }

            $phpcsFile->addError(
                $message,
                $pointer,
                self::ERROR_FORBIDDEN_MEMBERS,
            );
        }
    }

    private function isCamelCase(string $value): bool
    {
        return (bool) preg_match('/^[a-z][a-zA-Z0-9]*$/', $value);
    }
}
