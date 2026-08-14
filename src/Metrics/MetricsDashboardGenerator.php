<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use JsonException;
use RuntimeException;

final class MetricsDashboardGenerator
{
    private const DATA_PLACEHOLDER = '__METRICS_DASHBOARD_DATA__';

    public function __construct(
        private readonly string $template = __DIR__ . '/../../resources/metrics-dashboard.template.html',
    ) {
    }

    /** @return array{modules: int, classes: int, dependencies: int} */
    public function generate(string $input, string $output): array
    {
        $data = $this->dashboardData($input);
        $template = $this->readFile($this->template, 'Dashboard template');

        if (!str_contains($template, self::DATA_PLACEHOLDER)) {
            throw new RuntimeException('Dashboard template does not contain the data placeholder.');
        }

        $json = json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
        );
        $html = str_replace(self::DATA_PLACEHOLDER, $json, $template);
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create dashboard directory: $directory");
        }
        if (file_put_contents($output, $html) === false) {
            throw new RuntimeException("Cannot write dashboard: $output");
        }

        return [
            'modules' => count($data['modules']),
            'classes' => count($data['classes']),
            'dependencies' => array_sum(array_column($data['dependencies'], 'count')),
        ];
    }

    /** @return array<string, mixed> */
    private function dashboardData(string $input): array
    {
        $inputPath = realpath($input);
        if ($inputPath === false || !is_file($inputPath)) {
            throw new RuntimeException("Metrics report does not exist: $input");
        }

        $reportsRoot = dirname($inputPath);
        $snapshot = $this->readReport($inputPath);
        if (is_array($snapshot['objects'] ?? null)) {
            return $this->compactDashboardData($snapshot);
        }
        $reports = $this->readReportTree($inputPath, $reportsRoot);
        $root = $reports[$inputPath];
        $scope = is_array($root['scope'] ?? null) ? $root['scope'] : [];
        if (($scope['kind'] ?? null) !== 'project') {
            throw new RuntimeException('The input metrics report must have project scope.');
        }
        $rootMetrics = is_array($root['metrics'] ?? null) ? $root['metrics'] : [];
        $metadata = is_array($root['metadata'] ?? null) ? $root['metadata'] : [];

        $modules = [];
        $classes = [];
        foreach ($reports as $report) {
            $metrics = $report['metrics'] ?? [];
            if (!is_array($metrics)) {
                continue;
            }
            foreach ($this->items($metrics['modules'] ?? []) as $module) {
                $this->addModule($modules, $module);
            }
            if (is_array($metrics['module'] ?? null)) {
                $this->addModule($modules, $metrics['module']);
            }
            foreach ($this->items($metrics['classes'] ?? []) as $class) {
                if (is_string($class['id'] ?? null)) {
                    $classes[$class['id']] = $this->normalizeClass($class);
                }
            }
        }
        ksort($classes);

        $dependencies = $this->dependencies($classes, $modules);
        $cycleExamples = $this->cycleExamples($classes, $modules);
        $moduleData = $this->normalizeModules($modules, $classes, $dependencies);
        $classData = array_values(array_map(
            static fn (array $class): array => array_diff_key($class, ['_dependencies' => true]),
            $classes,
        ));

        return [
            'schema_version' => '1.0',
            'report' => [
                'generated_at' => $metadata['generated_at'] ?? null,
                'commit' => $metadata['commit'] ?? null,
            ],
            'project' => is_array($rootMetrics['project'] ?? null) ? $rootMetrics['project'] : [],
            'codebase' => is_array($rootMetrics['codebase'] ?? null) ? $rootMetrics['codebase'] : [],
            'modules' => $moduleData,
            'classes' => $classData,
            'dependencies' => $dependencies,
            'cycle_examples' => $cycleExamples,
        ];
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function compactDashboardData(array $snapshot): array
    {
        $objects = is_array($snapshot['objects'] ?? null) ? $snapshot['objects'] : [];
        $project = is_array($objects['project'] ?? null) ? reset($objects['project']) : [];
        $projectMetrics = is_array($project['metrics'] ?? null) ? $project['metrics'] : [];
        $modules = [];
        foreach ($objects['module'] ?? [] as $module) {
            if (!is_array($module)) {
                continue;
            }
            $modules[(string) ($module['id'] ?? '')] = ['id' => $module['id'] ?? '', ...($module['metrics'] ?? [])];
        }
        $classes = [];
        foreach ($objects['class'] ?? [] as $class) {
            if (!is_array($class)) {
                continue;
            }
            $classes[(string) ($class['id'] ?? '')] = $this->normalizeClass([
                'id' => $class['id'] ?? '',
                'file' => $class['source_path'] ?? '',
                ...((array) ($class['attributes'] ?? [])),
                ...((array) ($class['metrics'] ?? [])),
            ]);
        }
        ksort($classes);
        $dependencies = $this->dependencies($classes, $modules);

        return [
            'schema_version' => '1.0',
            'report' => ['generated_at' => null, 'commit' => null],
            'project' => is_array($projectMetrics['project'] ?? null) ? $projectMetrics['project'] : [],
            'codebase' => is_array($projectMetrics['codebase'] ?? null) ? $projectMetrics['codebase'] : [],
            'modules' => $this->normalizeModules($modules, $classes, $dependencies),
            'classes' => array_values(array_map(
                static fn (array $class): array => array_diff_key($class, ['_dependencies' => true]),
                $classes,
            )),
            'dependencies' => $dependencies,
            'cycle_examples' => $this->cycleExamples($classes, $modules),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function readReportTree(string $input, string $reportsRoot): array
    {
        $reports = [];
        $pending = [$input];
        while ($pending !== []) {
            $path = array_pop($pending);
            if (isset($reports[$path])) {
                continue;
            }
            $report = $this->readReport($path);
            $reports[$path] = $report;

            foreach ($this->items($report['children'] ?? []) as $child) {
                if (!is_string($child['path'] ?? null) || $child['path'] === '') {
                    throw new RuntimeException("Metrics report contains an invalid child path: $path");
                }
                $childPath = realpath(dirname($path) . '/' . $child['path']);
                if ($childPath === false || !is_file($childPath)) {
                    throw new RuntimeException("Child metrics report does not exist: {$child['path']}");
                }
                if (!$this->isWithinDirectory($childPath, $reportsRoot)) {
                    $message = "Child metrics report is outside the report directory: {$child['path']}";
                    throw new RuntimeException($message);
                }
                $pending[] = $childPath;
            }
        }

        return $reports;
    }

    /** @return array<string, mixed> */
    private function readReport(string $path): array
    {
        try {
            $report = json_decode($this->readFile($path, 'Metrics report'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Metrics report contains invalid JSON: $path", previous: $exception);
        }
        if (!is_array($report) || ($report['schema_version'] ?? null) !== '1.0') {
            throw new RuntimeException("Metrics report must use schema_version 1.0: $path");
        }

        return $report;
    }

    private function readFile(string $path, string $name): string
    {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException("$name is missing or empty: $path");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Cannot read $name: $path");
        }

        return $contents;
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }

    /** @param array<string, array<string, mixed>> $modules @param array<string, mixed> $module */
    private function addModule(array &$modules, array $module): void
    {
        if (is_string($module['id'] ?? null) && $module['id'] !== '') {
            $modules[$module['id']] = $module;
        }
    }

    /** @param array<string, mixed> $class @return array<string, mixed> */
    private function normalizeClass(array $class): array
    {
        $lcom = is_array($class['lcom4'] ?? null) ? $class['lcom4'] : [];
        $ce = is_array($class['ce'] ?? null) ? $class['ce'] : [];
        $churn = is_array($class['churn'] ?? null) ? $class['churn'] : [];
        $id = (string) $class['id'];

        return [
            'id' => $id,
            'name' => $this->shortName($id),
            'module' => is_string($class['module'] ?? null) ? $class['module'] : 'Unassigned',
            'file' => is_string($class['file'] ?? null) ? $class['file'] : '',
            'loc' => $this->integer($class['loc'] ?? null),
            'lcom' => $this->integer($lcom['components'] ?? null),
            'lcom_normalized' => $this->nullableFloat($lcom['normalized'] ?? null),
            'cbo' => $this->integer($ce['count'] ?? null),
            'cc' => $this->integer($class['max_cc'] ?? null),
            'churn' => $this->nullableInteger($churn['commits'] ?? null),
            '_dependencies' => array_values(array_filter(
                is_array($ce['types'] ?? null) ? $ce['types'] : [],
                'is_string',
            )),
        ];
    }

    private function shortName(string $id): string
    {
        $position = strrpos($id, '\\');

        return $position === false ? $id : substr($id, $position + 1);
    }

    /**
     * @param array<string, array<string, mixed>> $classes
     * @param array<string, array<string, mixed>> $modules
     * @return list<array{
     *     source: string,
     *     target: string,
     *     count: int,
     *     cycle: bool,
     *     examples: list<array{source: string, target: string}>
     * }>
     */
    private function dependencies(array $classes, array $modules): array
    {
        $counts = [];
        $examples = [];
        foreach ($classes as $sourceId => $class) {
            foreach ($class['_dependencies'] as $targetId) {
                if (!isset($classes[$targetId])) {
                    continue;
                }
                $source = $class['module'];
                $target = $classes[$targetId]['module'];
                if ($source === $target) {
                    continue;
                }
                $key = $source . "\0" . $target;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $examples[$key][$sourceId . "\0" . $targetId] = [
                    'source' => $sourceId,
                    'target' => $targetId,
                    'loc' => $class['loc'] + $classes[$targetId]['loc'],
                ];
            }
        }

        $cycleGroups = $this->cycleGroups($modules);
        $dependencies = [];
        foreach ($counts as $key => $count) {
            [$source, $target] = explode("\0", $key, 2);
            $relationExamples = array_values($examples[$key] ?? []);
            usort(
                $relationExamples,
                static fn (array $left, array $right): int => $right['loc'] <=> $left['loc']
                    ?: [$left['source'], $left['target']] <=> [$right['source'], $right['target']],
            );
            $dependencies[] = [
                'source' => $source,
                'target' => $target,
                'count' => $count,
                'cycle' => $this->isCyclicPair($source, $target, $cycleGroups),
                'examples' => array_map(
                    static fn (array $example): array => [
                        'source' => $example['source'],
                        'target' => $example['target'],
                    ],
                    array_slice($relationExamples, 0, 3),
                ),
            ];
        }
        usort(
            $dependencies,
            static fn (array $left, array $right): int => [$left['source'], $left['target']]
                <=> [$right['source'], $right['target']],
        );

        return $dependencies;
    }

    /** @param array<string, array<string, mixed>> $modules @return list<list<string>> */
    private function cycleGroups(array $modules): array
    {
        $groups = [];
        foreach ($modules as $module) {
            $cycles = is_array($module['cycles'] ?? null) ? $module['cycles'] : [];
            $components = is_array($cycles['components'] ?? null) ? $cycles['components'] : [];
            foreach ($components as $component) {
                if (!is_string($component)) {
                    continue;
                }
                $group = array_values(array_filter(array_map('trim', explode(',', $component))));
                sort($group);
                if (count($group) > 1) {
                    $groups[implode("\0", $group)] = $group;
                }
            }
        }

        return array_values($groups);
    }

    /** @param list<list<string>> $groups */
    private function isCyclicPair(string $source, string $target, array $groups): bool
    {
        foreach ($groups as $group) {
            if (in_array($source, $group, true) && in_array($target, $group, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $classes
     * @param array<string, array<string, mixed>> $modules
     * @return list<array{classes: list<string>, edge_count: int, loc: int, churn: int|null}>
     */
    private function cycleExamples(array $classes, array $modules): array
    {
        $groups = $this->cycleGroups($modules);
        $adjacency = [];
        foreach ($classes as $id => $class) {
            $adjacency[$id] = array_values(array_filter(
                $class['_dependencies'],
                static fn (string $target): bool => isset($classes[$target]),
            ));
            sort($adjacency[$id]);
        }

        $cycles = [];
        foreach ($classes as $sourceId => $source) {
            foreach ($adjacency[$sourceId] as $targetId) {
                $target = $classes[$targetId];
                if ($source['module'] === $target['module']) {
                    continue;
                }
                $group = $this->cycleGroupForPair($source['module'], $target['module'], $groups);
                if ($group === null) {
                    continue;
                }
                $allowedModules = array_fill_keys($group, true);
                $returnPath = $this->shortestClassPath(
                    $targetId,
                    $sourceId,
                    $adjacency,
                    $classes,
                    $allowedModules,
                );
                if ($returnPath === null) {
                    continue;
                }
                $path = [$sourceId, ...$returnPath];
                $key = $this->cycleKey($path);
                $ids = array_values(array_unique(array_slice($path, 0, -1)));
                $churnValues = array_values(array_filter(
                    array_map(static fn (string $id): ?int => $classes[$id]['churn'], $ids),
                    static fn (?int $value): bool => $value !== null,
                ));
                $cycles[$key] ??= [
                    'classes' => $path,
                    'edge_count' => count($path) - 1,
                    'loc' => array_sum(array_map(static fn (string $id): int => $classes[$id]['loc'], $ids)),
                    'churn' => $churnValues === [] ? null : array_sum($churnValues),
                ];
            }
        }

        $cycles = array_values($cycles);
        usort($cycles, static fn (array $left, array $right): int => $left['edge_count'] <=> $right['edge_count']
            ?: ($right['churn'] ?? -1) <=> ($left['churn'] ?? -1)
            ?: $right['loc'] <=> $left['loc']
            ?: $left['classes'] <=> $right['classes']);

        return array_slice($cycles, 0, 10);
    }

    /** @param list<list<string>> $groups @return list<string>|null */
    private function cycleGroupForPair(string $source, string $target, array $groups): ?array
    {
        foreach ($groups as $group) {
            if (in_array($source, $group, true) && in_array($target, $group, true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param array<string, list<string>> $adjacency
     * @param array<string, array<string, mixed>> $classes
     * @param array<string, bool> $allowedModules
     * @return list<string>|null
     */
    private function shortestClassPath(
        string $start,
        string $target,
        array $adjacency,
        array $classes,
        array $allowedModules,
    ): ?array {
        $queue = [$start];
        $offset = 0;
        $parents = [$start => null];
        while (isset($queue[$offset]) && !array_key_exists($target, $parents)) {
            $current = $queue[$offset++];
            foreach ($adjacency[$current] as $next) {
                if (!isset($allowedModules[$classes[$next]['module']]) || array_key_exists($next, $parents)) {
                    continue;
                }
                $parents[$next] = $current;
                $queue[] = $next;
                if ($next === $target) {
                    break;
                }
            }
        }
        if (!array_key_exists($target, $parents)) {
            return null;
        }

        $path = [];
        for ($current = $target; $current !== null; $current = $parents[$current]) {
            $path[] = $current;
        }

        return array_reverse($path);
    }

    /** @param list<string> $path */
    private function cycleKey(array $path): string
    {
        $nodes = array_slice($path, 0, -1);
        $keys = [];
        foreach (array_keys($nodes) as $offset) {
            $rotation = [...array_slice($nodes, $offset), ...array_slice($nodes, 0, $offset)];
            $keys[] = implode("\0", $rotation);
        }
        sort($keys);

        return $keys[0];
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     * @param array<string, array<string, mixed>> $classes
     * @param list<array{
     *     source: string,
     *     target: string,
     *     count: int,
     *     cycle: bool,
     *     examples: list<array{source: string, target: string}>
     * }> $dependencies
     * @return list<array<string, mixed>>
     */
    private function normalizeModules(array $modules, array $classes, array $dependencies): array
    {
        $statistics = [];
        foreach ($classes as $class) {
            $id = $class['module'];
            $statistics[$id] ??= ['class_count' => 0, 'loc' => 0, 'churn' => 0, 'has_churn' => false];
            $statistics[$id]['class_count']++;
            $statistics[$id]['loc'] += $class['loc'];
            if ($class['churn'] !== null) {
                $statistics[$id]['churn'] += $class['churn'];
                $statistics[$id]['has_churn'] = true;
            }
            $modules[$id] ??= ['id' => $id];
        }
        foreach ($dependencies as $dependency) {
            $statistics[$dependency['source']]['outgoing'] =
                ($statistics[$dependency['source']]['outgoing'] ?? 0) + $dependency['count'];
        }

        ksort($modules);
        $result = [];
        foreach ($modules as $id => $module) {
            $cycles = is_array($module['cycles'] ?? null) ? $module['cycles'] : [];
            $stats = $statistics[$id] ?? ['class_count' => 0, 'loc' => 0, 'churn' => 0, 'has_churn' => false];
            $result[] = [
                'id' => $id,
                'class_count' => $this->integer($module['class_count'] ?? $stats['class_count']),
                'loc' => $this->integer($module['loc'] ?? $stats['loc']),
                'external_dependency_share' => $this->nullableFloat(
                    $module['external_dependency_share'] ?? null,
                ),
                'cohesion' => $this->nullableFloat($module['cohesion'] ?? null),
                'internal_dependencies' => $this->integer(
                    $module['internal_dependencies'] ?? null,
                ),
                'outgoing_dependencies' => $this->integer(
                    $module['outgoing_dependencies'] ?? ($stats['outgoing'] ?? 0),
                ),
                'cycles' => $this->integer($cycles['count'] ?? null),
                'churn' => $stats['has_churn'] ? $stats['churn'] : null,
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function items(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private function integer(mixed $value): int
    {
        return is_int($value) || is_float($value) ? (int) $value : 0;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_int($value) || is_float($value) ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
