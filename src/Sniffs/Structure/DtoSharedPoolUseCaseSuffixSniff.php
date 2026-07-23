<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Structure;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Запрещает use-case-специфичные DTO (суффикс Request/Result/Response)
 * в общем пуле модуля `Module\{M}\Application\Dto`.
 *
 * Enforcement конвенции dto.md: «Use-case-специфичные DTO
 * (*RequestDto/*ResultDto/*ResponseDto) в общий пул не кладём — их место
 * рядом с use case'ом». Корневой общий пул `Common\Application\Dto`
 * (PaginationDto и т.п.) не проверяется — он заведомо общий.
 */
final class DtoSharedPoolUseCaseSuffixSniff implements Sniff
{
    private const ERROR_USE_CASE_SUFFIX_IN_SHARED_POOL = 'UseCaseSuffixInSharedPool';

    private const DOC_REF = ' See: docs/conventions/core-patterns/dto.md';

    private const USE_CASE_SUFFIX_PATTERN = '/(?:Request|Result|Response)Dto$/';

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

        if (preg_match(self::USE_CASE_SUFFIX_PATTERN, $className) !== 1) {
            return;
        }

        $namespace = $this->getNamespace($phpcsFile);
        if ($this->isModuleSharedPool($namespace) === false) {
            return;
        }

        $phpcsFile->addError(
            'Use-case-specific DTO (%s) must not be placed in the module shared pool'
            . ' (Application\Dto); move it next to the use case in UseCase\{Case}\.' . self::DOC_REF,
            $stackPtr,
            self::ERROR_USE_CASE_SUFFIX_IN_SHARED_POOL,
            [$className],
        );
    }

    private function isModuleSharedPool(string $namespace): bool
    {
        return str_ends_with($namespace, '\\Application\\Dto')
            && str_contains($namespace, '\\Module\\');
    }

    private function getNamespace(File $phpcsFile): string
    {
        $namespacePtr = $phpcsFile->findNext(T_NAMESPACE, 0);
        if ($namespacePtr === false) {
            return '';
        }

        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespacePtr + 1);
        if ($end === false) {
            return '';
        }

        return trim($phpcsFile->getTokensAsString($namespacePtr + 1, $end - $namespacePtr - 1));
    }
}
