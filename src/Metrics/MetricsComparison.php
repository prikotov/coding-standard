<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsComparison
{
    private const DEFINITIONS_VERSION = '1.0';

    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $current
     * @param list<string> $changedPaths
     * @return array<string, mixed>
     */
    public function compare(array $baseline, array $current, array $changedPaths = []): array
    {
        $compatibility = $this->compatibility($baseline, $current);
        $paths = $this->normalizePaths($changedPaths);
        $scopes = [];
        $counts = ['improved' => 0, 'regressed' => 0, 'neutral' => 0];

        foreach (['project', 'module', 'class', 'method'] as $kind) {
            $baselineObjects = $this->objects($baseline, $kind);
            $currentObjects = $this->objects($current, $kind);
            $scopes[$kind] = $this->compareObjects($kind, $baselineObjects, $currentObjects, $paths, $counts);
        }

        return $this->normalize([
            'schema_version' => '1.0',
            'metric_definitions_version' => self::DEFINITIONS_VERSION,
            'compatibility' => $compatibility,
            'changed_paths' => $paths,
            'summary' => [
                'improved_metric_count' => $counts['improved'],
                'regressed_metric_count' => $counts['regressed'],
                'neutral_metric_count' => $counts['neutral'],
            ],
            'scopes' => $scopes,
        ]);
    }

    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    private function compatibility(array $baseline, array $current): array
    {
        $baselineMetadata = $this->metadata($baseline);
        $currentMetadata = $this->metadata($current);
        $fields = [
            'project' => [$baselineMetadata['project'] ?? null, $currentMetadata['project'] ?? null],
            'schema_version' => [$baseline['schema_version'] ?? null, $current['schema_version'] ?? null],
            'metric_definitions_version' => [
                $baselineMetadata['metric_definitions_version'] ?? null,
                $currentMetadata['metric_definitions_version'] ?? null,
            ],
            'configuration_hash' => [
                $baselineMetadata['configuration_hash'] ?? null,
                $currentMetadata['configuration_hash'] ?? null,
            ],
            'source_versions' => [
                $baselineMetadata['source_versions'] ?? null,
                $currentMetadata['source_versions'] ?? null,
            ],
        ];

        $differences = [];
        foreach ($fields as $field => [$before, $after]) {
            if ($before === null || $after === null) {
                $differences[] = "$field is missing";
            } elseif ($this->normalize($before) !== $this->normalize($after)) {
                $differences[] = sprintf(
                    '%s differs (baseline: %s, current: %s)',
                    $field,
                    $this->display($before),
                    $this->display($after),
                );
            }
        }
        if (($baselineMetadata['metric_definitions_version'] ?? null) !== self::DEFINITIONS_VERSION) {
            $differences[] = sprintf(
                'metric_definitions_version %s is not supported by this comparator',
                $this->display($baselineMetadata['metric_definitions_version'] ?? null),
            );
        }
        if ($differences !== []) {
            throw new RuntimeException("Incompatible metrics snapshots:\n - " . implode("\n - ", $differences));
        }

        return [
            'project' => $baselineMetadata['project'],
            'metrics_schema_version' => $baseline['schema_version'],
            'metric_definitions_version' => $baselineMetadata['metric_definitions_version'],
            'configuration_hash' => $baselineMetadata['configuration_hash'],
            'source_versions' => $this->normalize($baselineMetadata['source_versions']),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $baseline
     * @param array<string, array<string, mixed>> $current
     * @param list<string> $changedPaths
     * @param array{improved: int, regressed: int, neutral: int} $counts
     * @return array<string, mixed>
     */
    private function compareObjects(
        string $kind,
        array $baseline,
        array $current,
        array $changedPaths,
        array &$counts,
    ): array {
        $added = [];
        $removed = [];
        $changed = [];
        $unchanged = 0;

        foreach (array_diff_key($current, $baseline) as $object) {
            $added[] = $this->objectReference($kind, $object, $changedPaths);
        }
        foreach (array_diff_key($baseline, $current) as $object) {
            $removed[] = $this->objectReference($kind, $object, $changedPaths);
        }
        foreach (array_intersect_key($current, $baseline) as $identifier => $currentObject) {
            $baselineObject = $baseline[$identifier];
            $metricChanges = $this->metricChanges(
                $kind,
                $this->map($baselineObject, 'metrics'),
                $this->map($currentObject, 'metrics'),
                $counts,
            );
            $attributeChanges = $this->valueChanges(
                $this->attributes($baselineObject),
                $this->attributes($currentObject),
            );
            if (($baselineObject['source_path'] ?? null) !== ($currentObject['source_path'] ?? null)) {
                $attributeChanges['source_path'] = [
                    'before' => $baselineObject['source_path'] ?? null,
                    'after' => $currentObject['source_path'] ?? null,
                ];
            }
            if ($metricChanges === [] && $attributeChanges === []) {
                $unchanged++;
                continue;
            }
            $sourcePath = (string) ($currentObject['source_path'] ?? $baselineObject['source_path'] ?? '');
            $sourcePaths = array_values(array_unique([
                ...$this->sourcePaths($baselineObject),
                ...$this->sourcePaths($currentObject),
            ]));
            sort($sourcePaths);
            $changedObject = [
                'id' => $identifier,
                'source_path' => $sourcePath,
                'changed_area' => $this->changedArea($kind, $sourcePath, $sourcePaths, $changedPaths),
                'attribute_changes' => $attributeChanges,
                'metric_changes' => $metricChanges,
            ];
            if ($kind === 'module') {
                $changedObject['matched_changed_paths'] = $this->matchedChangedPaths(
                    $sourcePath,
                    $sourcePaths,
                    $changedPaths,
                );
            }
            $changed[] = $changedObject;
        }

        foreach ([$added, $removed, $changed] as &$items) {
            usort($items, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        }
        unset($items);

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged_count' => $unchanged,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array{improved: int, regressed: int, neutral: int} $counts
     * @return list<array<string, mixed>>
     */
    private function metricChanges(string $kind, array $before, array $after, array &$counts): array
    {
        $baseline = $this->flatten($before);
        $current = $this->flatten($after);
        $metrics = array_unique([...array_keys($baseline), ...array_keys($current)]);
        sort($metrics);
        $changes = [];
        foreach ($metrics as $metric) {
            $oldValue = $baseline[$metric] ?? null;
            $newValue = $current[$metric] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }
            $direction = $this->direction($kind, $metric, $oldValue, $newValue);
            $change = [
                'metric' => $metric,
                'before' => $oldValue,
                'after' => $newValue,
                'direction' => $direction,
                'informational' => $direction === 'neutral',
            ];
            if ((is_int($oldValue) || is_float($oldValue)) && (is_int($newValue) || is_float($newValue))) {
                $change['delta'] = $newValue - $oldValue;
            }
            $changes[] = $change;
            $counts[$direction]++;
        }

        return $changes;
    }

    private function direction(string $kind, string $metric, mixed $before, mixed $after): string
    {
        if (!(is_int($before) || is_float($before)) || !(is_int($after) || is_float($after))) {
            return 'neutral';
        }
        $preference = $this->preference($kind, $metric);
        if ($preference === null || $before === $after) {
            return 'neutral';
        }
        $increased = $after > $before;

        return $increased === ($preference === 'higher') ? 'improved' : 'regressed';
    }

    private function preference(string $kind, string $metric): ?string
    {
        if ($kind === 'method' && in_array($metric, ['loc', 'cc'], true)) {
            return 'lower';
        }
        if (
            $kind === 'class' && preg_match(
                '/^(loc|wmc|max_cc|lcom4\.(components|normalized)|ca\.count|ce\.count|missing_event_dispatch)$/',
                $metric,
            ) === 1
        ) {
            return 'lower';
        }
        if ($kind === 'module') {
            if ($metric === 'cohesion') {
                return 'higher';
            }
            if (
                preg_match(
                    '/^(external_dependency_share|outgoing_dependencies|external_interface_size|cycles\.count)$/',
                    $metric,
                ) === 1 || preg_match('/^(class_loc|wmc|max_cc)\.(median|max|p90|p95)$/', $metric) === 1
            ) {
                return 'lower';
            }
        }
        if ($kind === 'project') {
            if (preg_match('/^coverage\.(lines|methods)\.percent$/', $metric) === 1) {
                return 'higher';
            }
            if (
                $metric === 'project.inter_module_dependencies'
                || $metric === 'project.command_handlers_without_event'
                || $metric === 'project.cycles.count'
                || preg_match('/^project\.(class_loc|wmc|max_cc)\.(median|max|p90|p95)$/', $metric) === 1
            ) {
                return 'lower';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function flatten(array $value, string $prefix = ''): array
    {
        $flat = [];
        ksort($value);
        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($item) && !array_is_list($item)) {
                $flat += $this->flatten($item, $path);
            } else {
                $flat[$path] = $this->normalize($item);
            }
        }

        return $flat;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function valueChanges(array $before, array $after): array
    {
        $changes = [];
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        sort($keys);
        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;
            if ($this->normalize($oldValue) !== $this->normalize($newValue)) {
                $changes[$key] = ['before' => $oldValue, 'after' => $newValue];
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $object
     * @param list<string> $changedPaths
     * @return array<string, mixed>
     */
    private function objectReference(string $kind, array $object, array $changedPaths): array
    {
        $sourcePath = (string) ($object['source_path'] ?? '');
        $sourcePaths = $this->sourcePaths($object);
        $reference = [
            'id' => $object['id'] ?? null,
            'source_path' => $sourcePath,
            'changed_area' => $this->changedArea($kind, $sourcePath, $sourcePaths, $changedPaths),
        ];
        if ($kind === 'module') {
            $reference['matched_changed_paths'] = $this->matchedChangedPaths(
                $sourcePath,
                $sourcePaths,
                $changedPaths,
            );
        }

        return $reference;
    }

    /**
     * @param list<string> $sourcePaths
     * @param list<string> $changedPaths
     */
    private function changedArea(string $kind, string $sourcePath, array $sourcePaths, array $changedPaths): bool
    {
        if ($changedPaths === []) {
            return false;
        }
        if ($kind === 'project') {
            return true;
        }
        if ($kind === 'module') {
            return $this->matchedChangedPaths($sourcePath, $sourcePaths, $changedPaths) !== [];
        }

        return in_array($sourcePath, $changedPaths, true);
    }

    /**
     * @param list<string> $sourcePaths
     * @param list<string> $changedPaths
     * @return list<string>
     */
    private function matchedChangedPaths(string $sourcePath, array $sourcePaths, array $changedPaths): array
    {
        if ($sourcePaths !== []) {
            return array_values(array_intersect($changedPaths, $sourcePaths));
        }
        if ($sourcePath === '') {
            return [];
        }

        return array_values(array_filter(
            $changedPaths,
            static fn (string $path): bool => $path === $sourcePath
                || str_starts_with($path, rtrim($sourcePath, '/') . '/'),
        ));
    }

    /** @param array<string, mixed> $object @return list<string> */
    private function sourcePaths(array $object): array
    {
        $sourcePaths = $this->attributes($object)['source_paths'] ?? [];
        if (!is_array($sourcePaths)) {
            return [];
        }
        $paths = array_values(array_filter(
            $sourcePaths,
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
        sort($paths);

        return array_values(array_unique($paths));
    }

    /** @param list<string> $paths @return list<string> */
    private function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $path = str_replace('\\', '/', trim($path));
            $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
            $path = ltrim($path, '/');
            if ($path === '') {
                continue;
            }
            $normalized[$path] = true;
        }
        $paths = array_keys($normalized);
        sort($paths);

        return $paths;
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function metadata(array $snapshot): array
    {
        return $this->map($snapshot, 'metadata');
    }

    /** @param array<string, mixed> $snapshot @return array<string, array<string, mixed>> */
    private function objects(array $snapshot, string $kind): array
    {
        $objects = $this->map($snapshot, 'objects');
        $items = $objects[$kind] ?? null;
        if (!is_array($items)) {
            throw new RuntimeException("Metrics snapshot has no $kind objects.");
        }

        return $items;
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function map(array $value, string $key): array
    {
        $map = $value[$key] ?? null;
        if (!is_array($map)) {
            throw new RuntimeException("Metrics comparison input has no $key object.");
        }

        return $map;
    }

    /** @param array<string, mixed> $object @return array<string, mixed> */
    private function attributes(array $object): array
    {
        $attributes = $object['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($this->normalize(...), $value);
        }
        ksort($value);
        foreach ($value as &$item) {
            $item = $this->normalize($item);
        }
        unset($item);

        return $value;
    }

    private function display(mixed $value): string
    {
        return json_encode($this->normalize($value), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
