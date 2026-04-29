<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Qossmic\Deptrac\Contract\Analyser\ProcessEvent;
use Qossmic\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Qossmic\Deptrac\Contract\Ast\DependencyType;
use Qossmic\Deptrac\Contract\Result\Violation;

/**
 * Custom Deptrac rule that enforces service contract boundaries.
 *
 * Rules:
 * 1. Services must stay inside their module — cross-module service dependencies are forbidden.
 * 2. Service interfaces must only be referenced/implemented by allowed layers:
 *
 *    Layer         | Can reference (use)         | Can implement (inherit)
 *    --------------|-----------------------------|--------------------------------
 *    Domain        | Domain                      | Domain
 *    Application   | Domain, Application         | Domain, Application
 *    Infrastructure| Domain, Infrastructure      | Domain, Infrastructure
 *    Integration   | Domain, Application,        | Domain, Integration
 *                  |   Integration               |
 *
 * 3. `use` imports (DependencyType::USE) are ignored — only actual dependencies are checked.
 *
 * Register in depfile.yaml:
 *   services:
 *     - class: PrikotovCodingStandard\Deptrac\ServiceContractDependencyRule
 *       tags:
 *         - { name: kernel.event_subscriber }
 */
final class ServiceContractDependencyRule implements ViolationCreatingInterface
{
    private const int EVENT_PRIORITY = 3;
    private const string LAYER_APPLICATION = 'Application';
    private const string LAYER_DOMAIN = 'Domain';
    private const string LAYER_INFRASTRUCTURE = 'Infrastructure';
    private const string LAYER_INTEGRATION = 'Integration';
    private const string SERVICE_DIRECTORY_PREFIX = 'Service\\';
    private const string SERVICE_CLASS_SUFFIX = 'Service';
    private const string SERVICE_INTERFACE_SUFFIX = 'ServiceInterface';

    public static function getSubscribedEvents(): array
    {
        return [
            ProcessEvent::class => ['onProcessEvent', self::EVENT_PRIORITY],
        ];
    }

    public function onProcessEvent(ProcessEvent $event): void
    {
        $depender = $this->parseModuleClass($event->dependerReference->getToken()->toString());
        $dependent = $this->parseModuleClass($event->dependentReference->getToken()->toString());

        if ($depender === null || $dependent === null || !$this->isService($dependent)) {
            return;
        }

        if ($event->dependency->getContext()->dependencyType === DependencyType::USE) {
            return;
        }

        if ($this->usesForbiddenService($depender, $dependent, $event->dependency->getContext()->dependencyType)) {
            $this->addViolation($event, $dependent);
        }
    }

    public function ruleName(): string
    {
        return 'ServiceContractDependencyRule';
    }

    public function ruleDescription(): string
    {
        return 'Service dependencies must stay inside the module; '
            . 'service interfaces must also be referenced or implemented only by allowed layers.';
    }

    /**
     * @return array{module: string, layer: string, path: string}|null
     */
    private function parseModuleClass(string $className): ?array
    {
        $moduleClassPattern = '/^(?:[A-Za-z_]+\\\\)?Common\\\\Module\\\\'
            . '(?P<module>[A-Za-z][A-Za-z0-9]*)\\\\'
            . '(?P<layer>Domain|Application|Infrastructure|Integration)\\\\'
            . '(?P<path>.+)$/';

        if (1 !== preg_match($moduleClassPattern, $className, $matches)) {
            return null;
        }

        return [
            'module' => $matches['module'],
            'layer' => $matches['layer'],
            'path' => $matches['path'],
        ];
    }

    /**
     * @param array{module: string, layer: string, path: string} $class
     */
    private function isServiceInterface(array $class): bool
    {
        return str_starts_with($class['path'], self::SERVICE_DIRECTORY_PREFIX)
            && str_ends_with($class['path'], self::SERVICE_INTERFACE_SUFFIX);
    }

    /**
     * @param array{module: string, layer: string, path: string} $class
     */
    private function isService(array $class): bool
    {
        return str_starts_with($class['path'], self::SERVICE_DIRECTORY_PREFIX)
            && (
                str_ends_with($class['path'], self::SERVICE_CLASS_SUFFIX)
                || str_ends_with($class['path'], self::SERVICE_INTERFACE_SUFFIX)
            );
    }

    /**
     * @param array{module: string, layer: string, path: string} $depender
     * @param array{module: string, layer: string, path: string} $dependent
     */
    private function usesForbiddenService(
        array $depender,
        array $dependent,
        DependencyType $dependencyType,
    ): bool {
        if ($depender['module'] !== $dependent['module']) {
            return true;
        }

        if (!$this->isServiceInterface($dependent)) {
            return false;
        }

        $allowedLayers = $dependencyType === DependencyType::INHERIT
            ? $this->allowedImplementedServiceInterfaceLayers($depender['layer'])
            : $this->allowedReferencedServiceInterfaceLayers($depender['layer']);

        return !in_array($dependent['layer'], $allowedLayers, true);
    }

    /**
     * @return list<string>
     */
    private function allowedReferencedServiceInterfaceLayers(string $dependerLayer): array
    {
        return match ($dependerLayer) {
            self::LAYER_DOMAIN => [self::LAYER_DOMAIN],
            self::LAYER_APPLICATION => [self::LAYER_DOMAIN, self::LAYER_APPLICATION],
            self::LAYER_INFRASTRUCTURE => [self::LAYER_DOMAIN, self::LAYER_INFRASTRUCTURE],
            self::LAYER_INTEGRATION => [self::LAYER_DOMAIN, self::LAYER_APPLICATION, self::LAYER_INTEGRATION],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function allowedImplementedServiceInterfaceLayers(string $dependerLayer): array
    {
        return match ($dependerLayer) {
            self::LAYER_DOMAIN => [self::LAYER_DOMAIN],
            self::LAYER_APPLICATION => [self::LAYER_DOMAIN, self::LAYER_APPLICATION],
            self::LAYER_INFRASTRUCTURE => [self::LAYER_DOMAIN, self::LAYER_INFRASTRUCTURE],
            self::LAYER_INTEGRATION => [self::LAYER_DOMAIN, self::LAYER_INTEGRATION],
            default => [],
        };
    }

    /**
     * @param array{module: string, layer: string, path: string} $dependent
     */
    private function addViolation(ProcessEvent $event, array $dependent): void
    {
        $event->getResult()->addRule(new Violation(
            $event->dependency,
            $event->dependerLayer,
            $this->dependentLayerName($event, $dependent),
            $this,
        ));
    }

    /**
     * @param array{module: string, layer: string, path: string} $dependent
     */
    private function dependentLayerName(ProcessEvent $event, array $dependent): string
    {
        $dependentLayer = array_key_first($event->dependentLayers);

        return is_string($dependentLayer) ? $dependentLayer : $dependent['layer'];
    }
}
