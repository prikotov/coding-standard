<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<MethodCall>
 */
final class ForbiddenExplicitHandlerInvokeRule implements Rule
{
    private const DOC_REF = ' See: docs/conventions/layers/application/use-case.md';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name instanceof Identifier === false || $node->name->toString() !== '__invoke') {
            return [];
        }

        $errors = [];
        foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
            if (HandlerClassName::isUseCaseHandler($className) === false) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s must not be invoked directly. Dispatch its message through CommandBus or QueryBus.' . self::DOC_REF,
                $className,
            ))
                ->line($node->getLine())
                ->identifier('prikotov.useCase.directHandlerInvocation')
                ->build();
        }

        return $errors;
    }
}
