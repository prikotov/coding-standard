<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsReportWriter
{
    /** @param array<string, mixed> $full */
    public function writeSnapshot(string $output, array $full): void
    {
        $metrics = $this->map($full, 'metrics');
        $classes = $this->records($metrics, 'classes');
        $methods = $this->records($metrics, 'methods');
        $modules = $this->modules($this->records($metrics, 'modules'));
        $objects = ['project' => [], 'module' => [], 'class' => [], 'method' => []];
        $metadata = $this->metadata($full);
        $project = (string) ($metadata['project'] ?? '');

        $objects['project'][$project] = [
            'id' => $project,
            'source_path' => '.',
            'metrics' => array_filter([
                'project' => $metrics['project'] ?? null,
                'codebase' => $metrics['codebase'] ?? null,
                'tests' => $metrics['tests'] ?? null,
                'coverage' => $metrics['coverage'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'attributes' => [],
        ];
        foreach ($modules as $identifier => $module) {
            $objects['module'][$identifier] = [
                'id' => $identifier,
                'source_path' => (string) ($module['path'] ?? ''),
                'metrics' => array_diff_key($module, ['id' => true, 'path' => true]),
                'attributes' => [],
            ];
        }
        foreach ($classes as $class) {
            $identifier = (string) ($class['id'] ?? '');
            $objects['class'][$identifier] = [
                'id' => $identifier,
                'source_path' => $this->sourcePath($class['file'] ?? null),
                'metrics' => array_diff_key($class, ['id' => true, 'kind' => true, 'file' => true, 'module' => true]),
                'attributes' => array_intersect_key($class, ['kind' => true, 'module' => true]),
            ];
        }
        $classPaths = [];
        foreach ($classes as $class) {
            $classPaths[(string) ($class['id'] ?? '')] = $this->sourcePath($class['file'] ?? null);
        }
        foreach ($methods as $method) {
            $identifier = (string) ($method['id'] ?? '');
            $class = substr($identifier, 0, (int) strrpos($identifier, '::'));
            $objects['method'][$identifier] = [
                'id' => $identifier,
                'source_path' => $classPaths[$class] ?? '',
                'metrics' => array_diff_key($method, ['id' => true]),
                'attributes' => [],
            ];
        }
        foreach ($objects as &$items) {
            ksort($items);
        }
        unset($items);

        $this->writeJson($output, [
            'schema_version' => '1.0',
            'metadata' => $metadata,
            'objects' => $objects,
        ]);
    }

    /** @param array<string, mixed> $full */
    public function writeMirror(string $output, array $full): void
    {
        $root = dirname($output);
        $findings = $this->records($full, 'findings');
        $metrics = $this->map($full, 'metrics');
        $classes = $this->records($metrics, 'classes');
        $methods = $this->records($metrics, 'methods');
        $modules = $this->modules($this->records($metrics, 'modules'));
        $classesByFile = $this->classesByFile($classes);
        $methodsByFile = $this->methodsByFile($methods, $classes);
        $directories = $this->directories(array_keys($classesByFile));
        $moduleDirectories = $this->moduleDirectories($classes);

        foreach ($classesByFile as $file => $fileClasses) {
            $module = (string) ($fileClasses[0]['module'] ?? '');
            $classIdentifiers = array_fill_keys(array_column($fileClasses, 'id'), true);
            $methodIdentifiers = array_fill_keys(array_column($methodsByFile[$file] ?? [], 'id'), true);
            $this->writeJson($root . '/' . $file . '.json', [
                'schema_version' => '1.0',
                'scope' => [
                    'kind' => 'file',
                    'source_path' => $file,
                    'module' => $module,
                ],
                'metrics' => [
                    'classes' => $fileClasses,
                    'methods' => $methodsByFile[$file] ?? [],
                ],
                'findings' => array_values(array_filter(
                    $findings,
                    static function (array $finding) use ($classIdentifiers, $methodIdentifiers): bool {
                        $subject = is_array($finding['subject'] ?? null) ? $finding['subject'] : [];
                        $identifier = $subject['id'] ?? null;

                        return is_string($identifier)
                            && (isset($classIdentifiers[$identifier]) || isset($methodIdentifiers[$identifier]));
                    },
                )),
            ]);
        }

        foreach ($directories as $directory) {
            $directoryClasses = array_values(array_filter(
                $classes,
                static fn (array $class): bool => str_starts_with(
                    (string) ($class['file'] ?? ''),
                    $directory . '/',
                ),
            ));
            $moduleIdentifier = $moduleDirectories[$directory] ?? null;
            $module = $moduleIdentifier === null ? null : ($modules[$moduleIdentifier] ?? null);
            $directoryMetrics = ['directory' => $this->directoryMetrics($directoryClasses)];
            if ($module !== null) {
                $directoryMetrics['module'] = $module;
            }

            $this->writeJson($root . '/' . $directory . '/report.json', [
                'schema_version' => '1.0',
                'scope' => array_filter([
                    'kind' => 'directory',
                    'source_path' => $directory,
                    'module' => $moduleIdentifier,
                ], static fn (mixed $value): bool => $value !== null),
                'metrics' => $directoryMetrics,
                'children' => $this->children($directory, $directories, array_keys($classesByFile)),
                'findings' => $moduleIdentifier === null ? [] : array_values(array_filter(
                    $findings,
                    static fn (array $finding): bool => ($finding['subject']['kind'] ?? null) === 'module'
                        && ($finding['subject']['id'] ?? null) === $moduleIdentifier,
                )),
            ]);
        }

        $this->writeJson($output, [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'project', 'source_path' => '.'],
            'metadata' => $this->metadata($full),
            'metrics' => array_filter([
                'project' => $metrics['project'] ?? null,
                'codebase' => $metrics['codebase'] ?? null,
                'tests' => $metrics['tests'] ?? null,
                'coverage' => $metrics['coverage'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'children' => $this->children('.', $directories, []),
            'findings' => $findings,
        ]);
    }

    /** @param list<array<string, mixed>> $classes @return array<string, list<array<string, mixed>>> */
    private function classesByFile(array $classes): array
    {
        $byFile = [];
        foreach ($classes as $class) {
            $file = $this->sourcePath($class['file'] ?? null);
            $byFile[$file][] = $class;
        }
        ksort($byFile);
        foreach ($byFile as &$items) {
            usort($items, static fn (array $left, array $right): int => ($left['id'] ?? '') <=> ($right['id'] ?? ''));
        }
        unset($items);

        return $byFile;
    }

    /**
     * @param list<array<string, mixed>> $methods
     * @param list<array<string, mixed>> $classes
     * @return array<string, list<array<string, mixed>>>
     */
    private function methodsByFile(array $methods, array $classes): array
    {
        $classFiles = [];
        foreach ($classes as $class) {
            if (is_string($class['id'] ?? null)) {
                $classFiles[$class['id']] = $this->sourcePath($class['file'] ?? null);
            }
        }

        $byFile = [];
        foreach ($methods as $method) {
            $identifier = $method['id'] ?? null;
            if (!is_string($identifier)) {
                continue;
            }
            $separator = strrpos($identifier, '::');
            $class = $separator === false ? $identifier : substr($identifier, 0, $separator);
            if (isset($classFiles[$class])) {
                $byFile[$classFiles[$class]][] = $method;
            }
        }
        foreach ($byFile as &$items) {
            usort($items, static fn (array $left, array $right): int => ($left['id'] ?? '') <=> ($right['id'] ?? ''));
        }
        unset($items);

        return $byFile;
    }

    /** @param list<string> $files @return list<string> */
    private function directories(array $files): array
    {
        $directories = [];
        foreach ($files as $file) {
            $directory = dirname($file);
            while ($directory !== '.' && $directory !== '') {
                $directories[$directory] = true;
                $directory = dirname($directory);
            }
        }
        $result = array_keys($directories);
        usort(
            $result,
            static fn (string $left, string $right): int => substr_count($right, '/') <=> substr_count($left, '/')
                ?: $left <=> $right,
        );

        return $result;
    }

    /** @param list<array<string, mixed>> $classes @return array<string, string> */
    private function moduleDirectories(array $classes): array
    {
        $directories = [];
        foreach ($classes as $class) {
            $file = $this->sourcePath($class['file'] ?? null);
            $segments = explode('/', $file);
            $moduleIndex = array_search('Module', $segments, true);
            if (is_int($moduleIndex) && isset($segments[$moduleIndex + 1]) && is_string($class['module'] ?? null)) {
                $directories[implode('/', array_slice($segments, 0, $moduleIndex + 2))] = $class['module'];
                continue;
            }
            $directory = dirname($file);
            if (preg_match('#^src/[^/]+$#', $directory) === 1 && is_string($class['module'] ?? null)) {
                $directories[$directory] = $class['module'];
            }
        }

        return $directories;
    }

    /**
     * @param list<string> $directories
     * @param list<string> $files
     * @return list<array{path: string, kind: string}>
     */
    private function children(string $directory, array $directories, array $files): array
    {
        $children = [];
        foreach ($directories as $candidate) {
            if (dirname($candidate) === $directory) {
                $children[] = ['path' => basename($candidate) . '/report.json', 'kind' => 'directory'];
            }
        }
        foreach ($files as $file) {
            if (dirname($file) === $directory) {
                $children[] = ['path' => basename($file) . '.json', 'kind' => 'file'];
            }
        }
        usort($children, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

        return $children;
    }

    /** @param list<array<string, mixed>> $classes @return array<string, mixed> */
    private function directoryMetrics(array $classes): array
    {
        $files = array_values(array_unique(array_column($classes, 'file')));
        $modules = array_values(array_unique(array_column($classes, 'module')));

        return [
            'class_count' => count($classes),
            'file_count' => count($files),
            'method_count' => array_sum(array_map(
                static fn (array $class): int => (int) ($class['method_count'] ?? 0),
                $classes,
            )),
            'module_count' => count($modules),
            'loc' => array_sum(array_map(static fn (array $class): int => (int) ($class['loc'] ?? 0), $classes)),
            'class_loc' => $this->distribution($classes, 'loc'),
            'wmc' => $this->distribution($classes, 'wmc'),
            'max_cc' => $this->distribution($classes, 'max_cc'),
        ];
    }

    /** @param list<array<string, mixed>> $items @return array<string, int|float|null> */
    private function distribution(array $items, string $key): array
    {
        $values = array_map(static fn (array $item): int => (int) ($item[$key] ?? 0), $items);
        sort($values);

        return [
            'median' => $this->percentile($values, 0.5),
            'max' => $values === [] ? null : max($values),
            'p90' => $this->percentile($values, 0.9),
            'p95' => $this->percentile($values, 0.95),
        ];
    }

    /** @param list<int> $values */
    private function percentile(array $values, float $percentile): int|float|null
    {
        if ($values === []) {
            return null;
        }
        $index = (count($values) - 1) * $percentile;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        return $values[$lower] + ($values[$upper] - $values[$lower]) * ($index - $lower);
    }

    /** @param list<array<string, mixed>> $modules @return array<string, array<string, mixed>> */
    private function modules(array $modules): array
    {
        $indexed = [];
        foreach ($modules as $module) {
            if (is_string($module['id'] ?? null)) {
                $indexed[$module['id']] = $module;
            }
        }

        return $indexed;
    }

    /** @param array<string, mixed> $data @return list<array<string, mixed>> */
    private function records(array $data, string $key): array
    {
        $records = $data[$key] ?? [];

        return is_array($records) && array_is_list($records) ? $records : [];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function map(array $data, string $key): array
    {
        $map = $data[$key] ?? [];

        return is_array($map) ? $map : [];
    }

    /** @param array<string, mixed> $full @return array<string, mixed> */
    private function metadata(array $full): array
    {
        $metadata = $this->map($full, 'metadata');
        unset($metadata['generated_at'], $metadata['commit']);

        return $metadata;
    }

    private function sourcePath(mixed $path): string
    {
        if (
            !is_string($path)
            || $path === ''
            || str_starts_with($path, '/')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1
        ) {
            throw new RuntimeException('Metrics source path must be a non-empty relative path.');
        }
        $path = str_replace('\\', '/', $path);
        $segments = explode('/', $path);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new RuntimeException("Metrics source path is not safe: $path");
        }

        return $path;
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create metrics directory: $directory");
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException("Cannot write metrics report: $path");
        }
    }
}
