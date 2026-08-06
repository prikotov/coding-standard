<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PrikotovCodingStandard\PhpStan\ForbiddenInvokableHandlerCallRule;

/**
 * @extends RuleTestCase<ForbiddenInvokableHandlerCallRule>
 */
final class ForbiddenInvokableHandlerCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ForbiddenInvokableHandlerCallRule();
    }

    public function testDirectCommandAndQueryHandlerCallsAreForbidden(): void
    {
        $this->analyse([
            __DIR__ . '/data/direct-handler-call/MarkReadyCommandHandler.php',
            __DIR__ . '/data/direct-handler-call/FindDocumentQueryHandler.php',
            __DIR__ . '/data/direct-handler-call/DirectInvokableCalls.php',
        ], [
            [
                'Test\DirectHandlerInvocation\Module\Document\Application\UseCase\Command\MarkReady'
                . '\MarkReadyCommandHandler must not be invoked directly.'
                . ' Dispatch its message through CommandBus or QueryBus.'
                . ' See: docs/conventions/layers/application/use-case.md',
                20,
            ],
            [
                'Test\DirectHandlerInvocation\Module\Document\Application\UseCase\Query\FindDocument'
                . '\FindDocumentQueryHandler must not be invoked directly.'
                . ' Dispatch its message through CommandBus or QueryBus.'
                . ' See: docs/conventions/layers/application/use-case.md',
                21,
            ],
        ]);
    }
}
