<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class ForbiddenInvokableHandlerCallRule implements Rule
{
    private const DOC_REF = ' See: docs/conventions/layers/application/use-case.md';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name instanceof Node\Name) {
            return [];
        }

        return $this->buildErrors($scope->getType($node->name)->getObjectClassNames(), $node->getLine());
    }

    /**
     * @param list<non-empty-string> $classNames
     * @return list<RuleError>
     */
    private function buildErrors(array $classNames, int $line): array
    {
        $errors = [];

        foreach ($classNames as $className) {
            if (HandlerClassName::isUseCaseHandler($className) === false) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s must not be invoked directly. Dispatch its message through CommandBus or QueryBus.' . self::DOC_REF,
                $className,
            ))
                ->line($line)
                ->identifier('prikotov.useCase.directHandlerInvocation')
                ->build();
        }

        return $errors;
    }
}
