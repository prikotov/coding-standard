<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Application;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Мутирующий CommandHandler (save/delete/persist/remove/flush) должен
 * диспетчеризовать хотя бы одно событие (*Event).
 *
 * Проверка — warning, а не error: не всякий мутирующий хендлер обязан
 * публиковать событие (исключения — см. конвенцию). Осознанное исключение
 * подавляется комментарием `phpcs:ignore` с указанием причины.
 */
final class CommandHandlerEventDispatchSniff implements Sniff
{
    private const WARNING_MISSING_EVENT_DISPATCH = 'MissingEventDispatch';

    private const DOC_REF = ' See: docs/conventions/layers/application/command-handler.md';

    /** Методы-маркеры изменения состояния (репозиторий или менеджер персистентности). */
    private const STATE_MUTATION_METHODS = [
        'save',
        'delete',
        'persist',
        'remove',
        'flush',
    ];

    private const EVENT_DISPATCH_METHOD = 'dispatch';

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
        if ($className === '' || str_ends_with($className, 'CommandHandler') === false) {
            return;
        }

        if ($this->isCommandHandlerPath($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if ($this->dispatchesEvent($phpcsFile, $stackPtr, $scopeStart, $scopeEnd)) {
            return;
        }

        $mutationPtr = $this->findStateMutationCall($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
        if ($mutationPtr === null) {
            return;
        }

        $phpcsFile->addWarning(
            sprintf(
                'CommandHandler changes state (calls %s) but dispatches no *Event.'
                . ' Mutating handler must dispatch at least one event after flush();'
                . ' if this is a documented exception, suppress with phpcs:ignore and a reason.'
                . self::DOC_REF,
                $tokens[$mutationPtr]['content'],
            ),
            $stackPtr,
            self::WARNING_MISSING_EVENT_DISPATCH,
        );
    }

    private function isCommandHandlerPath(string $filename): bool
    {
        $normalizedPath = str_replace('\\', '/', $filename);

        if (str_contains($normalizedPath, '/tests/Application/')) {
            return true;
        }

        return str_contains($normalizedPath, '/Application/UseCase/Command/');
    }

    private function dispatchesEvent(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): bool
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_STRING, $pointer + 1, $scopeEnd)) !== false) {
            if (
                $this->belongsToClass($tokens, $pointer, $classPtr)
                && $this->isObjectMethodCall($phpcsFile, $pointer)
                && strtolower($tokens[$pointer]['content']) === self::EVENT_DISPATCH_METHOD
            ) {
                return true;
            }
        }

        return false;
    }

    private function findStateMutationCall(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): ?int
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_STRING, $pointer + 1, $scopeEnd)) !== false) {
            if (
                $this->belongsToClass($tokens, $pointer, $classPtr)
                && $this->isObjectMethodCall($phpcsFile, $pointer)
                && in_array(strtolower($tokens[$pointer]['content']), self::STATE_MUTATION_METHODS, true)
            ) {
                return $pointer;
            }
        }

        return null;
    }

    private function isObjectMethodCall(File $phpcsFile, int $stringPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $prev = $phpcsFile->findPrevious(T_WHITESPACE, $stringPtr - 1, null, true);
        $next = $phpcsFile->findNext(T_WHITESPACE, $stringPtr + 1, null, true);

        if ($prev === false || $next === false) {
            return false;
        }

        $isObjectOperator = $tokens[$prev]['code'] === T_OBJECT_OPERATOR
            || $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR;

        return $isObjectOperator && $tokens[$next]['code'] === T_OPEN_PARENTHESIS;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function belongsToClass(array $tokens, int $tokenPtr, int $classPtr): bool
    {
        if (isset($tokens[$tokenPtr]['conditions']) === false || $tokens[$tokenPtr]['conditions'] === []) {
            return false;
        }

        return array_key_exists($classPtr, $tokens[$tokenPtr]['conditions']);
    }
}
