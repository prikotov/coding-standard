<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use PhpParser\Parser;
use RuntimeException;

final class ProjectMetricsCollector
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Parser $parser,
        private readonly string $projectRoot,
        private readonly array $config,
    ) {
    }

    /** @return array<string, mixed> */
    public function collect(): array
    {
        $classes = [];
        $functions = [];
        $dependencies = [];

        foreach ($this->autoloadRoots() as $namespace => $sources) {
            foreach ($sources as $source) {
                $report = (new AstMetricsCollector($this->parser, $source))->collect();
                foreach ($report['dependencies'] ?? [] as $dependency) {
                    if (is_string($dependency['source'] ?? null) && is_string($dependency['target'] ?? null)) {
                        $dependencies[$dependency['source'] . "\0" . $dependency['target']] = $dependency;
                    }
                }
                foreach ($report['classes'] as $class) {
                    $name = $class['name'] ?? null;
                    $metrics = $class['metrics'] ?? null;
                    if (!is_string($name) || !is_array($metrics)) {
                        continue;
                    }
                    $file = $this->relativePath((string) ($metrics['filePath'] ?? ''));
                    $module = $this->module($name, $namespace);
                    if ($module === null || $this->isExcluded($file) || !$this->matchesModulePattern($file)) {
                        continue;
                    }
                    $class['metrics']['filePath'] = $file;
                    $class['metrics']['module'] = $module;
                    $classes[$name] = $class;
                }

                foreach ($report['functions'] as $function) {
                    $metrics = $function['metrics'] ?? null;
                    if (!is_array($metrics)) {
                        continue;
                    }
                    $parts = explode(', ', (string) ($metrics['classInfo'] ?? ''), 2);
                    if (count($parts) === 2) {
                        $functions[$parts[1] . '::' . ($function['name'] ?? '')] = $function;
                    }
                }
            }
        }

        $functions = array_filter(
            $functions,
            static function (array $function) use ($classes): bool {
                $parts = explode(', ', (string) ($function['metrics']['classInfo'] ?? ''), 2);

                return count($parts) === 2 && isset($classes[$parts[1]]);
            },
        );
        $files = array_values(array_unique(array_map(
            static fn (array $class): string => (string) $class['metrics']['filePath'],
            $classes,
        )));
        $churn = (new GitChurnCollector())->collect($this->projectRoot, $files);
        if ($churn !== null) {
            foreach ($classes as &$class) {
                $file = (string) $class['metrics']['filePath'];
                $class['metrics']['gitChurnCount'] = $churn[$file] ?? 0;
            }
            unset($class);
        }
        ksort($classes);
        ksort($functions);
        $dependencies = array_values(array_filter(
            $dependencies,
            static fn (array $dependency): bool => isset(
                $classes[$dependency['source']],
                $classes[$dependency['target']],
            ),
        ));
        usort(
            $dependencies,
            static fn (array $left, array $right): int => [$left['source'], $left['target']]
                <=> [$right['source'], $right['target']],
        );

        return [
            'schema_version' => '1.0',
            'toolVersion' => 'metrics-collector/1.2',
            'classes' => array_values($classes),
            'functions' => array_values($functions),
            'dependencies' => $dependencies,
        ];
    }

    /** @return array<string, list<string>> */
    private function autoloadRoots(): array
    {
        $composerPath = $this->projectRoot . '/composer.json';
        if (!is_file($composerPath)) {
            throw new RuntimeException("Composer configuration does not exist: $composerPath");
        }
        $composer = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
        $psr4 = $composer['autoload']['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            throw new RuntimeException('composer.json must contain production autoload.psr-4 roots.');
        }

        $roots = [];
        foreach ($psr4 as $namespace => $paths) {
            if (!is_string($namespace)) {
                continue;
            }
            foreach (is_array($paths) ? $paths : [$paths] as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $source = realpath($this->projectRoot . '/' . ltrim($path, '/'));
                if ($source !== false && is_dir($source)) {
                    $roots[rtrim($namespace, '\\') . '\\'][] = $source;
                }
            }
        }
        if ($roots === []) {
            throw new RuntimeException('No existing production autoload.psr-4 directories were found.');
        }
        ksort($roots);

        return $roots;
    }

    private function module(string $class, string $autoloadNamespace): ?string
    {
        if (!str_starts_with($class, $autoloadNamespace)) {
            return null;
        }
        $relative = substr($class, strlen($autoloadNamespace));
        $segments = explode('\\', $relative);
        $moduleIndex = array_search('Module', $segments, true);
        if (!is_int($moduleIndex) || !isset($segments[$moduleIndex + 1]) || $segments[$moduleIndex + 1] === '') {
            return null;
        }

        $namespaceSegments = explode('\\', trim($autoloadNamespace, '\\'));
        $application = (string) end($namespaceSegments);
        $context = array_slice($segments, 0, $moduleIndex);
        if ($context !== []) {
            $application .= '/' . implode('/', $context);
        }

        return $application . ':' . $segments[$moduleIndex + 1];
    }

    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : ltrim($path, '/');
    }

    private function isExcluded(string $file): bool
    {
        if (str_starts_with($file, 'packages/')) {
            return true;
        }
        foreach ($this->config['exclude'] ?? [] as $exclude) {
            if (!is_string($exclude) || $exclude === '') {
                continue;
            }
            $exclude = trim(str_replace('\\', '/', $exclude), '/');
            if ($file === $exclude || str_starts_with($file, $exclude . '/')) {
                return true;
            }
        }

        return false;
    }

    private function matchesModulePattern(string $file): bool
    {
        $patterns = $this->config['module_patterns'] ?? [];
        if (!is_array($patterns) || $patterns === []) {
            return true;
        }
        $path = explode('/', trim($file, '/'));
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $this->matchesSegments(explode('/', trim($pattern, '/')), $path)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $pattern @param list<string> $path */
    private function matchesSegments(array $pattern, array $path, int $patternIndex = 0, int $pathIndex = 0): bool
    {
        if (!isset($pattern[$patternIndex])) {
            return true;
        }
        if ($pattern[$patternIndex] === '**') {
            for ($index = $pathIndex; $index <= count($path); $index++) {
                if ($this->matchesSegments($pattern, $path, $patternIndex + 1, $index)) {
                    return true;
                }
            }

            return false;
        }
        if (!isset($path[$pathIndex])) {
            return false;
        }
        if ($pattern[$patternIndex] !== '*' && $pattern[$patternIndex] !== $path[$pathIndex]) {
            return false;
        }

        return $this->matchesSegments($pattern, $path, $patternIndex + 1, $pathIndex + 1);
    }
}
