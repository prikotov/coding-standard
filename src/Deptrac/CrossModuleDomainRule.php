<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Qossmic\Deptrac\Contract\Analyser\ProcessEvent;
use Qossmic\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Qossmic\Deptrac\Contract\Ast\DependencyType;
use Qossmic\Deptrac\Contract\Result\Violation;

/**
 * Custom Deptrac rule that forbids cross-module access entirely,
 * except for one allowed path:
 *
 *   Integration(module A) → Application(module B)
 *
 * All other cross-module dependencies are violations, regardless of layer.
 * Shared data types (ValueObject, Enum, Dto) are excluded — safe to use across modules.
 * Domain\Entity → foreign Domain\Entity is excluded — Doctrine ORM relations require class references.
 *
 * This rule cannot be bypassed by renaming directories (Contract, Port, Gateway, etc.)
 * because it checks the entire module namespace regardless of subdirectory naming.
 *
 * Register in depfile.yaml:
 *   services:
 *     - class: PrikotovCodingStandard\Deptrac\CrossModuleDomainRule
 *       tags:
 *         - { name: kernel.event_subscriber }
 */
final class CrossModuleDomainRule implements ViolationCreatingInterface
{
    private const int EVENT_PRIORITY = 3;

    public static function getSubscribedEvents(): array
    {
        return [
            ProcessEvent::class => ['onProcessEvent', self::EVENT_PRIORITY],
        ];
    }

    public function onProcessEvent(ProcessEvent $event): void
    {
        if ($event->dependency->getContext()->dependencyType === DependencyType::USE) {
            return;
        }

        $depender = $this->parseModuleClass($event->dependerReference->getToken()->toString());
        $dependent = $this->parseModuleClass($event->dependentReference->getToken()->toString());

        if ($depender === null || $dependent === null) {
            return;
        }

        if ($depender['module'] === $dependent['module']) {
            return;
        }

        if ($this->isSharedDataType($dependent['path'])) {
            return;
        }

        // Doctrine ORM ManyToOne/OneToMany require entity class references
        if ($depender['layer'] === 'Domain' && $dependent['layer'] === 'Domain'
            && str_starts_with($depender['path'], 'Entity\\') && str_starts_with($dependent['path'], 'Entity\\')) {
            return;
        }

        // TODO: temporary — Command handlers may use foreign Domain\Repository and Domain\Entity
        // This legacy debt must be eliminated by routing through Integration → foreign Application
        if ($depender['layer'] === 'Application' && $dependent['layer'] === 'Domain'
            && str_starts_with($depender['path'], 'UseCase\\Command\\')
            && (str_starts_with($dependent['path'], 'Repository\\') || str_starts_with($dependent['path'], 'Entity\\'))
        ) {
            return;
        }

        // Integration → foreign Application is the only allowed cross-module path
        if ($depender['layer'] === 'Integration' && $dependent['layer'] === 'Application') {
            return;
        }

        $event->getResult()->addRule(new Violation(
            $event->dependency,
            $event->dependerLayer,
            $this->dependentLayerName($event),
            $this,
        ));
    }

    public function ruleName(): string
    {
        return 'CrossModuleDomainRule';
    }

    public function ruleDescription(): string
    {
        return 'Cross-module dependencies are forbidden. '
            . 'The only allowed path is Integration → foreign Application (Command/Query handlers).';
    }

    /**
     * @return array{module: string, layer: string, path: string}|null
     */
    private function parseModuleClass(string $className): ?array
    {
        if (1 !== preg_match(
            '/^(?:[A-Za-z_]+\\\\)?Common\\\\Module\\\\'
            . '(?P<module>[A-Za-z][A-Za-z0-9]*)\\\\'
            . '(?P<layer>Domain|Application|Infrastructure|Integration)\\\\'
            . '(?P<path>.+)$/',
            $className,
            $matches,
        )) {
            return null;
        }

        return [
            'module' => $matches['module'],
            'layer' => $matches['layer'],
            'path' => $matches['path'],
        ];
    }

    /**
     * ValueObject, Enum and Dto are shared data types — safe for cross-module usage.
     */
    private function isSharedDataType(string $path): bool
    {
        return str_starts_with($path, 'ValueObject\\')
            || str_starts_with($path, 'Enum\\')
            || (bool) preg_match('/Dto$/', $path);
    }

    private function dependentLayerName(ProcessEvent $event): string
    {
        $dependentLayer = array_key_first($event->dependentLayers);

        return is_string($dependentLayer) ? $dependentLayer : 'Unknown';
    }
}
