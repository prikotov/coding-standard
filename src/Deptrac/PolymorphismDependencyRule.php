<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Deptrac\Deptrac\Contract\Analyser\PostProcessEvent;
use Deptrac\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Deptrac\Deptrac\Contract\Ast\AstMap\DependencyType;
use Deptrac\Deptrac\Contract\Result\Violation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Filters layer violations by the kind of dependency, not by layers.
 *
 * Layer rulesets only see "class A depends on class B". Some kinds of dependencies
 * are polymorphism or error handling rather than coupling to a contract:
 *
 * - INHERIT (implements/extends): a Value Object implementing a domain interface
 *   (`StaticChainDefinitionVo implements ChainDefinitionInterface`) is the point of
 *   the interface. The VO layer does not "call" the contract — it fulfils it.
 * - PARAMETER, when the dependent layer is ApplicationCommand or ApplicationQuery:
 *   a handler delegating to strategies that receive the command as data
 *   (`execute(ChainInfoVo $info, OrchestrateChainCommand $command)`) is composition
 *   inside Application, not a service dispatching commands. Only the parameter kind
 *   is exempt — `new OrchestrateChainCommand()` or dispatching it stays a violation.
 *
 * Everything else stays: `throw`, `new`, return types, static calls, etc.
 *
 * Register in depfile.yaml:
 *   services:
 *     - class: PrikotovCodingStandard\Deptrac\PolymorphismDependencyRule
 *       tags:
 *         - { name: kernel.event_subscriber }
 */
final class PolymorphismDependencyRule implements EventSubscriberInterface
{
    private const IGNORED_TYPES = [
        DependencyType::INHERIT,
    ];

    /**
     * Command/Query objects passed as parameters are data, not coupling to dispatch.
     *
     * @var list<string>
     */
    private const DATA_OBJECT_LAYERS = [
        'ApplicationCommand',
        'ApplicationQuery',
    ];

    private function isPolymorphismOrData(Violation $violation): bool
    {
        $type = $violation->getDependency()->getContext()->dependencyType;

        if (in_array($type, self::IGNORED_TYPES, true) === true) {
            return true;
        }

        return $type === DependencyType::PARAMETER
            && in_array($violation->getDependentLayer(), self::DATA_OBJECT_LAYERS, true) === true;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostProcessEvent::class => ['onPostProcessEvent'],
        ];
    }

    public function onPostProcessEvent(PostProcessEvent $event): void
    {
        $result = $event->getResult();

        foreach ($result->rules() as $violations) {
            foreach ($violations as $violation) {
                if ($violation instanceof Violation && $this->isPolymorphismOrData($violation) === true) {
                    $result->removeRule($violation);
                }
            }
        }
    }
}
