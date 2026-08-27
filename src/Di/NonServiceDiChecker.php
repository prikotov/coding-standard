<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

use PrikotovCodingStandard\Verify\VerificationResult;

/**
 * Verifies the non-service DI convention of docs/conventions/modules/configuration.md
 * from the root of the consumer project:
 *
 *  1. every auto-registered module (`resource` import in `services.yaml`)
 *     excludes non-service classes with module-wide suffix patterns, so
 *     listing individual files or single DTO directories is rejected;
 *  2. no service injects an Application `Command`/`Query`, DTO or other
 *     non-service class through its constructor.
 *
 * Common modules (`...\Common\Module\...`) must always carry the mandatory
 * exclude minimum of the convention. Application modules (`...\Web\Module\...`,
 * `...\Api\v1\Module\...`, application-wide imports) are checked against the
 * non-service types that actually exist in their tree: a mask becomes
 * required as soon as the first class of that type appears. Vendor package
 * imports are not checked.
 *
 * Symfony console commands live outside `Application\UseCase\Command|Query`
 * and therefore never trigger the checks.
 */
final class NonServiceDiChecker
{
    public function check(string $projectDir): VerificationResult
    {
        $result = new VerificationResult();
        $configs = (new ServiceConfigLocator())->locate($projectDir, $result);
        if ($configs === []) {
            $result->warn(
                'no module services.yaml with a resource import found — DI configuration check skipped',
            );

            return $result;
        }

        $classes = (new ConstructorDependencyCollector())->collect(
            array_map(static fn (ModuleConfig $config): string => $config->resourceRoot, $configs),
        );

        foreach ($configs as $config) {
            $this->checkExcludeCoverage($result, $config, $classes, $projectDir);
        }

        $this->checkConstructorDependencies($result, $classes, $configs, $projectDir);

        return $result;
    }

    /**
     * @param list<ScannedClass> $classes
     */
    private function checkExcludeCoverage(
        VerificationResult $result,
        ModuleConfig $config,
        array $classes,
        string $projectDir,
    ): void {
        $glob = new GlobMatcher();
        $relativeFile = $this->relativeTo($config->configFile, $projectDir);
        $missing = [];

        foreach ($this->coverageRequirements($config, $classes) as $suffix => $probes) {
            foreach ($probes as $probe) {
                $covered = false;
                foreach ($config->excludePatterns as $pattern) {
                    if ($glob->covers($probe, $pattern)) {
                        $covered = true;
                        break;
                    }
                }

                if ($covered === false) {
                    $missing[$suffix] = NonServiceClass::from($suffix);
                    break;
                }
            }
        }

        if ($missing !== []) {
            foreach ($missing as $category) {
                $result->fail(
                    sprintf(
                        '%s: exclude does not fully cover *%s.php of module %s',
                        $relativeFile,
                        $category->value,
                        rtrim($config->namespace, '\\'),
                    ),
                    sprintf("Add '%s' to exclude.", $this->suggestExclude($config, $category)),
                );
            }

            return;
        }

        $result->ok(sprintf(
            '%s: exclude covers non-service types of module %s',
            $relativeFile,
            rtrim($config->namespace, '\\'),
        ));
    }

    /**
     * Probe files prove that the exclude patterns cover every location of a
     * non-service class — not only the files that exist today.
     *
     * Every module requires a mask for the non-service types that actually
     * exist in its tree — the requirement appears together with the first
     * class of that type, so new types cannot leak silently. Common modules
     * additionally carry the mandatory minimum of the convention regardless
     * of their contents. Application commands and queries are required when
     * the module has the corresponding use case directories.
     *
     * @param list<ScannedClass> $classes
     *
     * @return array<string, list<string>> non-service suffix => probe file paths
     */
    private function coverageRequirements(ModuleConfig $config, array $classes): array
    {
        $present = $this->presentContentCategories($config, $classes);
        if ($config->isCommon) {
            foreach (NonServiceClass::moduleWide() as $category) {
                $present[$category->value] = $category;
            }
        }

        $requirements = [];
        foreach ($present as $category) {
            $requirements[$category->value] = [
                $config->resourceRoot . '/Probe' . $category->value . '.php',
                $config->resourceRoot . '/Nested/Probe' . $category->value . '.php',
            ];
        }

        foreach ([NonServiceClass::Command, NonServiceClass::Query] as $category) {
            $useCaseDir = $config->resourceRoot . '/Application/UseCase/' . $category->value;
            if (is_dir($useCaseDir)) {
                $requirements[$category->value] = [
                    $useCaseDir . '/Probe' . $category->value . '.php',
                    $useCaseDir . '/Group/Probe' . $category->value . '.php',
                ];
            }
        }

        return $requirements;
    }

    /**
     * Non-service suffix types whose classes exist in the module tree.
     *
     * @param list<ScannedClass> $classes
     *
     * @return array<string, NonServiceClass>
     */
    private function presentContentCategories(ModuleConfig $config, array $classes): array
    {
        $root = rtrim($config->resourceRoot, '/') . '/';
        $present = [];
        foreach ($classes as $class) {
            if (!str_starts_with($class->file, $root)) {
                continue;
            }

            $category = NonServiceClass::classify($class->fqcn);
            if ($category !== null && in_array($category, NonServiceClass::contentCategories(), true)) {
                $present[$category->value] = $category;
            }
        }

        return $present;
    }

    private function suggestExclude(ModuleConfig $config, NonServiceClass $category): string
    {
        $base = rtrim($config->resourceExpression, '/');
        if ($category === NonServiceClass::Command || $category === NonServiceClass::Query) {
            return sprintf('%s/Application/UseCase/%s/**/*%s.php', $base, $category->value, $category->value);
        }

        return sprintf('%s/**/*%s.php', $base, $category->value);
    }

    /**
     * @param list<ScannedClass> $classes
     * @param list<ModuleConfig> $configs
     */
    private function checkConstructorDependencies(
        VerificationResult $result,
        array $classes,
        array $configs,
        string $projectDir,
    ): void {
        foreach ($classes as $class) {
            if (
                NonServiceClass::classify($class->fqcn) !== null
                || $this->isExcludedFromContainer($class->file, $configs)
            ) {
                // Non-service classes and classes excluded from the container
                // (DTO, entities, ...) never get their constructor invoked by
                // the container — composing other objects there is fine.
                continue;
            }

            foreach ($class->constructorParams as $param) {
                foreach ($param['typeFqcns'] as $typeFqcn) {
                    $category = NonServiceClass::classify($typeFqcn);
                    if ($category === null) {
                        continue;
                    }

                    $result->fail(
                        sprintf(
                            '%s:%d: constructor of %s injects non-service %s (%s)',
                            $this->relativeTo($class->file, $projectDir),
                            $param['line'],
                            $class->fqcn,
                            $typeFqcn,
                            $category->label(),
                        ),
                        'Non-service classes must not be container services: pass them as method arguments'
                        . ' (e.g. into __invoke) instead of constructor dependencies.'
                        . ' See: docs/conventions/modules/configuration.md',
                    );
                }
            }
        }
    }

    /**
     * A class excluded from the container by its module configuration is not
     * a service: the container never calls its constructor, so injecting
     * non-service objects there is legitimate object composition.
     *
     * @param list<ModuleConfig> $configs
     */
    private function isExcludedFromContainer(string $file, array $configs): bool
    {
        $glob = new GlobMatcher();
        foreach ($configs as $config) {
            $root = rtrim($config->resourceRoot, '/') . '/';
            if (!str_starts_with($file, $root)) {
                continue;
            }

            foreach ($config->excludePatterns as $pattern) {
                if ($glob->covers($file, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function relativeTo(string $path, string $projectDir): string
    {
        $prefix = rtrim($projectDir, '/') . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }
}
