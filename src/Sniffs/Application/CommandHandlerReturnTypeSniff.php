<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Application;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class CommandHandlerReturnTypeSniff implements Sniff
{
    private const ERROR_FORBIDDEN_RETURN_TYPE = 'ForbiddenReturnType';

    private const DOC_REF = ' See: docs/conventions/layers/application/command-handler.md';

    private const FORBIDDEN_RETURN_TYPE_SUFFIXES = [
        'Entity',
        'Model',
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
        if ($className === '' || str_ends_with($className, 'CommandHandler') === false) {
            return;
        }

        if ($this->isHandlerPath($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $this->assertInvokeReturnType($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
    }

    private function isHandlerPath(string $filename): bool
    {
        $normalizedPath = str_replace('\\', '/', $filename);

        if (str_contains($normalizedPath, '/tests/Application/')) {
            return true;
        }

        return str_contains($normalizedPath, '/Application/UseCase/Command/');
    }

    private function assertInvokeReturnType(
        File $phpcsFile,
        int $classPtr,
        int $scopeStart,
        int $scopeEnd,
    ): void {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $methodName = strtolower($phpcsFile->getDeclarationName($pointer));
            if ($methodName !== '__invoke') {
                continue;
            }

            $returnType = $this->getReturnTypeContent($phpcsFile, $pointer);
            if ($returnType === null) {
                return;
            }

            $this->assertReturnTypeAllowed($phpcsFile, $pointer, $returnType);

            return;
        }
    }

    private function assertReturnTypeAllowed(File $phpcsFile, int $pointer, string $returnType): void
    {
        preg_match_all('/[A-Z][a-zA-Z0-9]*/', $returnType, $matches);
        $typeNames = $matches[0] ?? [];

        foreach ($typeNames as $typeName) {
            foreach (self::FORBIDDEN_RETURN_TYPE_SUFFIXES as $suffix) {
                if (
                    str_ends_with($typeName, $suffix)
                    && !str_ends_with($typeName, 'Dto')
                    && !str_ends_with($typeName, 'Vo')
                ) {
                    $phpcsFile->addError(
                        sprintf(
                            'CommandHandler __invoke must not return %s.'
                            . ' Allowed: void, identifier (int/Uuid), or IdDto.' . self::DOC_REF,
                            $typeName,
                        ),
                        $pointer,
                        self::ERROR_FORBIDDEN_RETURN_TYPE,
                    );

                    return;
                }
            }
        }
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
}
