<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Helpers\UseStatementHelper;

final class GlobalFunctionCallStyleSniff implements Sniff
{
    private const ERROR_GLOBAL_FUNCTION_IMPORT = 'GlobalFunctionImport';
    private const ERROR_FULLY_QUALIFIED_GLOBAL_FUNCTION_CALL = 'FullyQualifiedGlobalFunctionCall';

    public function register(): array
    {
        return [T_USE, T_NAME_FULLY_QUALIFIED];
    }

    /**
     * @param int $stackPtr Pointer to the token under inspection.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] === T_USE) {
            $this->assertGlobalFunctionsAreNotImported($phpcsFile, $stackPtr);

            return;
        }

        $this->assertGlobalFunctionsAreNotFullyQualified($phpcsFile, $stackPtr);
    }

    private function assertGlobalFunctionsAreNotImported(File $phpcsFile, int $usePointer): void
    {
        if (!UseStatementHelper::isImportUse($phpcsFile, $usePointer)) {
            return;
        }

        $nextPointer = TokenHelper::findNextEffective($phpcsFile, $usePointer + 1);
        if ($nextPointer === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (
            $tokens[$nextPointer]['code'] !== T_STRING
            || strtolower($tokens[$nextPointer]['content']) !== 'function'
        ) {
            return;
        }

        $functionName = UseStatementHelper::getFullyQualifiedTypeNameFromUse($phpcsFile, $usePointer);
        if (str_contains($functionName, '\\')) {
            return;
        }

        $isFixable = $phpcsFile->addFixableError(
            sprintf(
                'Global function imports via "use function" are forbidden; call %s() directly.',
                $functionName,
            ),
            $usePointer,
            self::ERROR_GLOBAL_FUNCTION_IMPORT,
        );

        if (!$isFixable) {
            return;
        }

        $semicolonPointer = TokenHelper::findNext($phpcsFile, T_SEMICOLON, $usePointer + 1);
        if ($semicolonPointer === null) {
            return;
        }

        $fixer = $phpcsFile->fixer;
        $fixer->beginChangeset();
        for ($pointer = $usePointer; $pointer <= $semicolonPointer; ++$pointer) {
            $fixer->replaceToken($pointer, '');
        }
        $fixer->endChangeset();
    }

    private function assertGlobalFunctionsAreNotFullyQualified(File $phpcsFile, int $namePointer): void
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$namePointer]['content'];

        if (!preg_match('/^\\\\[A-Za-z_][A-Za-z0-9_]*$/', $content)) {
            return;
        }

        $nextPointer = TokenHelper::findNextEffective($phpcsFile, $namePointer + 1);
        if ($nextPointer === null || $tokens[$nextPointer]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $previousPointer = TokenHelper::findPreviousEffective($phpcsFile, $namePointer - 1);
        if (
            $previousPointer !== null
            && in_array(
                $tokens[$previousPointer]['code'],
                [
                    T_NEW,
                    T_FUNCTION,
                    T_FN,
                    T_USE,
                    T_CONST,
                    T_DOUBLE_COLON,
                    T_OBJECT_OPERATOR,
                    T_NULLSAFE_OBJECT_OPERATOR,
                    T_EXTENDS,
                    T_IMPLEMENTS,
                    T_INSTANCEOF,
                    T_ATTRIBUTE,
                ],
                true,
            )
        ) {
            return;
        }

        $functionName = ltrim($content, '\\');

        $isFixable = $phpcsFile->addFixableError(
            sprintf(
                'Global functions must be called without a leading backslash; use %1$s() instead of \\%1$s().',
                $functionName,
            ),
            $namePointer,
            self::ERROR_FULLY_QUALIFIED_GLOBAL_FUNCTION_CALL,
        );

        if (!$isFixable) {
            return;
        }

        $phpcsFile->fixer->replaceToken($namePointer, $functionName);
    }
}
