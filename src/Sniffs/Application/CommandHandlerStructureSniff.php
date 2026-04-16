<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Application;

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class CommandHandlerStructureSniff implements Sniff
{
    private const ERROR_INVOKE_REQUIRED = 'InvokeRequired';
    private const ERROR_PUBLIC_METHODS = 'ForbiddenPublicMethods';
    private const ERROR_PUBLIC_PROPERTIES = 'ForbiddenPublicProperties';

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

        $this->assertNoPublicProperties($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);

        $invokePtr = $this->assertInvokeExists($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);

        if ($invokePtr !== null) {
            $this->assertInvokeIsPublic($phpcsFile, $invokePtr);
        }

        $this->assertNoOtherPublicMethods($phpcsFile, $stackPtr, $scopeStart, $scopeEnd);
    }

    private function isCommandHandlerPath(string $filename): bool
    {
        $normalizedPath = str_replace('\\', '/', $filename);

        if (str_contains($normalizedPath, '/tests/Application/')) {
            return true;
        }

        return str_contains($normalizedPath, '/Application/UseCase/Command/');
    }

    private function assertInvokeExists(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): ?int
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;
        $invoke  = null;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $methodName = strtolower($phpcsFile->getDeclarationName($pointer));
            if ($methodName === '__invoke') {
                $invoke = $pointer;
            }
        }

        if ($invoke === null) {
            $phpcsFile->addError(
                'CommandHandler classes must declare public __invoke method.',
                $classPtr,
                self::ERROR_INVOKE_REQUIRED,
            );
        }

        return $invoke;
    }

    private function assertInvokeIsPublic(File $phpcsFile, int $invokePtr): void
    {
        $properties = $phpcsFile->getMethodProperties($invokePtr);
        if (($properties['scope'] ?? null) !== 'public') {
            $phpcsFile->addError(
                '__invoke method in CommandHandler must be public.',
                $invokePtr,
                self::ERROR_PUBLIC_METHODS,
            );
        }
    }

    private function assertNoOtherPublicMethods(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            $methodName = strtolower($phpcsFile->getDeclarationName($pointer));
            if (in_array($methodName, ['__construct', '__invoke'], true)) {
                continue;
            }

            $properties = $phpcsFile->getMethodProperties($pointer);
            if (($properties['scope'] ?? null) === 'public') {
                $phpcsFile->addError(
                    'CommandHandler classes must not declare public methods other than __invoke.',
                    $pointer,
                    self::ERROR_PUBLIC_METHODS,
                );
            }
        }
    }

    private function assertNoPublicProperties(File $phpcsFile, int $classPtr, int $scopeStart, int $scopeEnd): void
    {
        $tokens  = $phpcsFile->getTokens();
        $pointer = $scopeStart;

        while (($pointer = $phpcsFile->findNext([T_VARIABLE], $pointer + 1, $scopeEnd)) !== false) {
            if ($this->belongsToClass($tokens, $pointer, $classPtr) === false) {
                continue;
            }

            try {
                $member = $phpcsFile->getMemberProperties($pointer);
            } catch (RuntimeException $exception) {
                continue;
            }

            if ($member === [] || ($member['scope'] ?? null) !== 'public') {
                continue;
            }

            $phpcsFile->addError(
                'CommandHandler classes must not declare public properties.',
                $pointer,
                self::ERROR_PUBLIC_PROPERTIES,
            );
        }

        $constructorPtr = $phpcsFile->findNext(T_FUNCTION, $scopeStart + 1, $scopeEnd);
        while ($constructorPtr !== false) {
            $methodName = strtolower($phpcsFile->getDeclarationName($constructorPtr));
            if ($methodName === '__construct') {
                $this->assertNoPublicPromotedProperties($phpcsFile, $constructorPtr);
                break;
            }

            $constructorPtr = $phpcsFile->findNext(T_FUNCTION, $constructorPtr + 1, $scopeEnd);
        }
    }

    private function assertNoPublicPromotedProperties(File $phpcsFile, int $constructorPtr): void
    {
        $parameters = $phpcsFile->getMethodParameters($constructorPtr);

        foreach ($parameters as $parameter) {
            $visibility = $parameter['property_visibility'] ?? null;
            if ($visibility === 'public') {
                $phpcsFile->addError(
                    'CommandHandler constructor dependencies must not be public promoted properties.',
                    $constructorPtr,
                    self::ERROR_PUBLIC_PROPERTIES,
                );
            }
        }
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
