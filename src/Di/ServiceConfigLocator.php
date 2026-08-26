<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

use PrikotovCodingStandard\Verify\VerificationResult;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds module `services.yaml` files in the consumer project and extracts
 * auto-registered namespace imports (`resource` + `exclude`) with all paths
 * resolved against the file location and its `%parameter%` values.
 */
final class ServiceConfigLocator
{
    private const CONFIG_FILENAMES = ['services.yaml', 'services.yml'];

    private const SKIP_DIRECTORIES = [
        'vendor', 'node_modules', 'var', 'cache', 'build', 'dist',
        'tests',
    ];

    /** @return list<ModuleConfig> */
    public function locate(string $projectDir, VerificationResult $result): array
    {
        $configs = [];
        foreach ($this->findConfigFiles($projectDir) as $file) {
            $configs = [...$configs, ...$this->parseConfigFile($file, $projectDir, $result)];
        }

        return $configs;
    }

    /** @return list<string> */
    private function findConfigFiles(string $projectDir): array
    {
        $directory = new \RecursiveDirectoryIterator(
            $projectDir,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME,
        );
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            function (string $path): bool {
                if (is_dir($path)) {
                    $name = basename($path);

                    return !str_starts_with($name, '.') && !in_array($name, self::SKIP_DIRECTORIES, true);
                }

                return is_file($path) && in_array(basename($path), self::CONFIG_FILENAMES, true);
            },
        );

        $files = [];
        foreach (new \RecursiveIteratorIterator($filter) as $path) {
            if (is_string($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<ModuleConfig> */
    private function parseConfigFile(string $file, string $projectDir, VerificationResult $result): array
    {
        try {
            $parsed = Yaml::parseFile($file, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $exception) {
            $result->fail(
                $this->relativeTo($file, $projectDir) . ' is not valid YAML: ' . $exception->getMessage(),
            );

            return [];
        }

        if (!is_array($parsed)) {
            return [];
        }

        $parameters = $this->extractParameters($parsed);
        $services = $parsed['services'] ?? null;
        if (!is_array($services)) {
            return [];
        }

        $configs = [];
        foreach ($services as $namespace => $definition) {
            if (!is_string($namespace) || !str_ends_with($namespace, '\\') || !is_array($definition)) {
                continue;
            }

            $resource = $definition['resource'] ?? null;
            if (!is_string($resource) || $resource === '') {
                continue;
            }

            $config = $this->buildModuleConfig(
                $file,
                $projectDir,
                $namespace,
                $resource,
                is_array($definition['exclude'] ?? null) ? array_values($definition['exclude']) : [],
                $parameters,
                $result,
            );
            if ($config !== null) {
                $configs[] = $config;
            }
        }

        return $configs;
    }

    /** @return array<string, string> */
    /**
     * @param array<mixed> $parsed
     *
     * @return array<string, string>
     */
    private function extractParameters(array $parsed): array
    {
        $parameters = ['kernel.project_dir' => ''];
        $declared = $parsed['parameters'] ?? null;
        if (is_array($declared)) {
            foreach ($declared as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $parameters[$name] = $value;
                }
            }
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $parameters
     * @param list<mixed> $rawExcludes
     */
    private function buildModuleConfig(
        string $file,
        string $projectDir,
        string $namespace,
        string $resource,
        array $rawExcludes,
        array $parameters,
        VerificationResult $result,
    ): ?ModuleConfig {
        $parameters['kernel.project_dir'] = $projectDir;
        $pathResolver = new ConfigPathResolver(dirname($file), new ParameterResolver($parameters));

        $resourceRoot = $pathResolver->resolve($resource);
        if ($resourceRoot === null) {
            $result->warn(
                sprintf(
                    '%s: cannot resolve resource "%s" of module %s',
                    $this->relativeTo($file, $projectDir),
                    $resource,
                    $namespace,
                ),
                'Use string parameters or literal paths so the check can resolve the resource directory.',
            );

            return null;
        }

        $excludePatterns = [];
        foreach ($rawExcludes as $rawExclude) {
            if (!is_string($rawExclude) || $rawExclude === '') {
                continue;
            }

            $resolved = $pathResolver->resolve($rawExclude);
            if ($resolved === null) {
                $result->warn(
                    sprintf(
                        '%s: cannot resolve exclude "%s" of module %s',
                        $this->relativeTo($file, $projectDir),
                        $rawExclude,
                        $namespace,
                    ),
                );
                continue;
            }

            $excludePatterns[] = $resolved;
        }

        return new ModuleConfig($file, $namespace, $resourceRoot, $resource, $excludePatterns);
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
