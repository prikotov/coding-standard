<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class MetricsSnapshotReader
{
    /**
     * @return array{
     *     schema_version: string,
     *     metadata: array<string, mixed>,
     *     objects: array<string, array<string, array<string, mixed>>>
     * }
     */
    public function read(string $directory): array
    {
        $directory = rtrim($directory, '/');
        if (!is_dir($directory)) {
            throw new RuntimeException("Metrics snapshot directory does not exist: $directory");
        }

        $queue = [['path' => 'report.json', 'kind' => 'project']];
        $visited = [];
        $root = null;
        $objects = ['project' => [], 'module' => [], 'class' => [], 'method' => []];

        while ($queue !== []) {
            $next = array_shift($queue);
            if (!is_array($next)) {
                continue;
            }
            $relativePath = (string) $next['path'];
            $expectedKind = (string) $next['kind'];
            if (isset($visited[$relativePath])) {
                throw new RuntimeException("Metrics snapshot contains a duplicate child reference: $relativePath");
            }
            $report = $this->report($directory . '/' . $relativePath);
            $scope = $this->map($report, 'scope', $relativePath);
            if (($scope['kind'] ?? null) !== $expectedKind) {
                throw new RuntimeException("Metrics report has an unexpected scope kind: $relativePath");
            }
            if (($report['schema_version'] ?? null) !== '1.0') {
                throw new RuntimeException("Metrics report must use schema_version 1.0: $relativePath");
            }
            if ($expectedKind !== 'project') {
                $sourcePath = $this->sourcePath($scope, $relativePath);
                $expectedPath = $expectedKind === 'directory'
                    ? $sourcePath . '/report.json'
                    : $sourcePath . '.json';
                if ($relativePath !== $expectedPath) {
                    throw new RuntimeException("Metrics report path does not match its source scope: $relativePath");
                }
            }
            $visited[$relativePath] = true;

            if ($expectedKind === 'project') {
                $root = $report;
                $metadata = $this->rootMetadata($report, $relativePath);
                $identifier = $metadata['project'] ?? null;
                if (!is_string($identifier) || $identifier === '') {
                    throw new RuntimeException('Metrics root metadata must contain a non-empty project identifier.');
                }
                $objects['project'][$identifier] = $this->object(
                    $identifier,
                    '.',
                    $this->map($report, 'metrics', $relativePath),
                );
            } elseif ($expectedKind === 'directory') {
                $metrics = $this->map($report, 'metrics', $relativePath);
                if (is_array($metrics['module'] ?? null)) {
                    $module = $metrics['module'];
                    $identifier = $module['id'] ?? null;
                    if (!is_string($identifier) || $identifier === '') {
                        throw new RuntimeException("Module report has no stable identifier: $relativePath");
                    }
                    $this->addObject(
                        $objects['module'],
                        $identifier,
                        $this->object(
                            $identifier,
                            $this->sourcePath($scope, $relativePath),
                            array_diff_key($module, ['id' => true]),
                        ),
                        'module',
                    );
                }
            } else {
                $metrics = $this->map($report, 'metrics', $relativePath);
                $sourcePath = $this->sourcePath($scope, $relativePath);
                foreach (['classes' => 'class', 'methods' => 'method'] as $key => $kind) {
                    foreach ($this->records($metrics, $key, $relativePath) as $record) {
                        $identifier = $record['id'] ?? null;
                        if (!is_string($identifier) || $identifier === '') {
                            throw new RuntimeException("Metrics $kind has no stable identifier: $relativePath");
                        }
                        $attributes = $kind === 'class'
                            ? array_intersect_key($record, ['kind' => true, 'module' => true])
                            : [];
                        $metricKeys = $kind === 'class'
                            ? ['id' => true, 'kind' => true, 'file' => true, 'module' => true]
                            : ['id' => true];
                        $this->addObject(
                            $objects[$kind],
                            $identifier,
                            $this->object(
                                $identifier,
                                $sourcePath,
                                array_diff_key($record, $metricKeys),
                                $attributes,
                            ),
                            $kind,
                        );
                    }
                }
            }

            if ($expectedKind !== 'file') {
                foreach ($this->children($report, $relativePath) as $child) {
                    $queue[] = $child;
                }
            }
        }

        if (!is_array($root)) {
            throw new RuntimeException('Metrics snapshot root report is missing.');
        }
        $this->assertComplete($directory, array_keys($visited));
        foreach ($objects as &$items) {
            ksort($items);
        }
        unset($items);

        return [
            'schema_version' => (string) $root['schema_version'],
            'metadata' => $this->rootMetadata($root, 'report.json'),
            'objects' => $objects,
        ];
    }

    /** @return array<string, mixed> */
    private function report(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Referenced metrics report is missing: $path");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Metrics report is not valid JSON: $path", previous: $exception);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException("Metrics report must be a JSON object: $path");
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $report
     * @return list<array{path: string, kind: string}>
     */
    private function children(array $report, string $parent): array
    {
        $children = $report['children'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            throw new RuntimeException("Metrics children must be a list: $parent");
        }
        $directory = dirname($parent);
        $result = [];
        foreach ($children as $child) {
            if (!is_array($child) || !is_string($child['path'] ?? null)) {
                throw new RuntimeException("Metrics child reference is invalid: $parent");
            }
            $kind = $child['kind'] ?? null;
            if (!in_array($kind, ['directory', 'file'], true)) {
                throw new RuntimeException("Metrics child kind is invalid: $parent");
            }
            $path = $child['path'];
            if (!$this->safeRelativePath($path)) {
                throw new RuntimeException("Metrics child path is not safe: $path");
            }
            $relativePath = $directory === '.' ? $path : $directory . '/' . $path;
            if ($kind === 'directory' && basename($relativePath) !== 'report.json') {
                throw new RuntimeException("Directory metrics child must point to report.json: $relativePath");
            }
            if ($kind === 'file' && !str_ends_with($relativePath, '.php.json')) {
                throw new RuntimeException("File metrics child must point to a PHP JSON report: $relativePath");
            }
            $result[] = ['path' => $relativePath, 'kind' => $kind];
        }
        usort($result, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

        return $result;
    }

    /** @param list<string> $referenced */
    private function assertComplete(string $directory, array $referenced): void
    {
        sort($referenced);
        $canonical = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if (basename($relative) === 'report.json' || str_ends_with($relative, '.php.json')) {
                $canonical[] = $relative;
            }
        }
        sort($canonical);
        $missingReferences = array_values(array_diff($canonical, $referenced));
        if ($missingReferences !== []) {
            throw new RuntimeException(
                'Metrics snapshot contains unreferenced canonical reports: ' . implode(', ', $missingReferences),
            );
        }
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function map(array $report, string $key, string $path): array
    {
        $value = $report[$key] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException("Metrics $key must be an object: $path");
        }

        return $value;
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function rootMetadata(array $report, string $path): array
    {
        $metadata = $this->map($report, 'metadata', $path);
        foreach (['project', 'metric_definitions_version', 'configuration_hash', 'input_hash'] as $key) {
            if (!is_string($metadata[$key] ?? null) || $metadata[$key] === '') {
                throw new RuntimeException("Metrics root metadata must contain a non-empty $key.");
            }
        }
        $versions = $metadata['source_versions'] ?? null;
        if (!is_array($versions) || array_is_list($versions) || $versions === []) {
            throw new RuntimeException('Metrics root metadata must contain source_versions.');
        }
        foreach ($versions as $source => $version) {
            if (!is_string($source) || !is_string($version) || $version === '') {
                throw new RuntimeException('Metrics source_versions must contain non-empty string versions.');
            }
        }

        return $metadata;
    }

    /** @param array<string, mixed> $metrics @return list<array<string, mixed>> */
    private function records(array $metrics, string $key, string $path): array
    {
        $records = $metrics[$key] ?? [];
        if (!is_array($records) || !array_is_list($records)) {
            throw new RuntimeException("Metrics $key must be a list: $path");
        }
        foreach ($records as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new RuntimeException("Metrics $key must contain objects: $path");
            }
        }

        return $records;
    }

    /** @param array<string, mixed> $scope */
    private function sourcePath(array $scope, string $reportPath): string
    {
        $sourcePath = $scope['source_path'] ?? null;
        if (!is_string($sourcePath) || !$this->safeRelativePath($sourcePath)) {
            throw new RuntimeException("Metrics source path is invalid: $reportPath");
        }

        return $sourcePath;
    }

    private function safeRelativePath(string $path): bool
    {
        return $path !== ''
            && !str_starts_with($path, '/')
            && preg_match('#^[A-Za-z]:[\\\\/]#', $path) !== 1
            && !in_array('', explode('/', str_replace('\\', '/', $path)), true)
            && !in_array('.', explode('/', str_replace('\\', '/', $path)), true)
            && !in_array('..', explode('/', str_replace('\\', '/', $path)), true);
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function object(string $id, string $sourcePath, array $metrics, array $attributes = []): array
    {
        return ['id' => $id, 'source_path' => $sourcePath, 'attributes' => $attributes, 'metrics' => $metrics];
    }

    /**
     * @param array<string, array<string, mixed>> $objects
     * @param array<string, mixed> $object
     */
    private function addObject(array &$objects, string $identifier, array $object, string $kind): void
    {
        if (isset($objects[$identifier])) {
            throw new RuntimeException("Metrics snapshot contains a duplicate $kind identifier: $identifier");
        }
        $objects[$identifier] = $object;
    }
}
