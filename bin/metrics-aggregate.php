#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PrikotovCodingStandard\Metrics\MetricsAggregator;

$options = getopt('', ['analyzer::', 'deptrac::', 'output::']);
$analyzer = $options['analyzer'] ?? 'var/metrics/collector.json';
$deptrac = $options['deptrac'] ?? 'var/metrics/deptrac.json';
$output = $options['output'] ?? 'var/metrics/report.json';

try {
    foreach (['Analyzer' => $analyzer, 'Deptrac' => $deptrac] as $name => $path) {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException(sprintf('%s report is missing or empty: %s', $name, $path));
        }
    }
    $config = require dirname(__DIR__) . '/.coding-standard.php';
    $commit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: null;
    $full = (new MetricsAggregator())->aggregate(
        json_decode((string) file_get_contents($analyzer), true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) file_get_contents($deptrac), true, flags: JSON_THROW_ON_ERROR),
        $config['metrics'] ?? [],
        $commit,
    );
    writeMirror($output, $full);
    fwrite(STDOUT, "Metrics reports written to " . dirname($output) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-aggregate: {$exception->getMessage()}\n");
    exit(1);
}

/** @param array<string, mixed> $full */
function writeMirror(string $output, array $full): void
{
    $root = dirname($output);
    $metadata = $full['metadata'];
    $findings = $full['findings'];
    $classes = $full['metrics']['classes'];
    $methods = $full['metrics']['methods'];
    $methodsByFile = [];
    foreach ($methods as $method) {
        $class = strtok($method['id'], '::');
        foreach ($classes as $item) {
            if ($item['id'] === $class) {
                $methodsByFile[$item['file']][] = $method;
                break;
            }
        }
    }
    $directories = [];
    foreach ($classes as $class) {
        $file = $class['file'];
        writeJson($root . '/' . $file . '.json', [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'file', 'source_path' => $file, 'module' => $class['module']],
            'metadata' => $metadata,
            'metrics' => ['classes' => array_values(array_filter($classes, static fn (array $item): bool => $item['file'] === $file)), 'methods' => $methodsByFile[$file] ?? []],
            'findings' => array_values(array_filter($findings, static fn (array $finding): bool => ($finding['subject']['id'] ?? null) === $class['id'])),
        ]);
        $directory = dirname($file);
        while ($directory !== '.' && $directory !== '') {
            $directories[$directory] = true;
            $directory = dirname($directory);
        }
    }
    $directories = array_keys($directories);
    usort($directories, static fn (string $left, string $right): int => substr_count($right, '/') <=> substr_count($left, '/') ?: $left <=> $right);
    $modules = [];
    foreach ($full['metrics']['modules'] as $module) $modules[$module['id']] = $module;
    foreach ($directories as $directory) {
        $children = [];
        foreach ($directories as $candidate) {
            if (dirname($candidate) === $directory) $children[] = ['path' => basename($candidate) . '/report.json', 'kind' => 'directory'];
        }
        foreach ($classes as $class) if (dirname($class['file']) === $directory) $children[] = ['path' => basename($class['file']) . '.json', 'kind' => 'file'];
        usort($children, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        $module = null;
        if (preg_match('#^src/([^/]+)$#', $directory, $match)) $module = $modules[$match[1]] ?? null;
        writeJson($root . '/' . $directory . '/report.json', [
            'schema_version' => '1.0',
            'scope' => array_filter(['kind' => 'directory', 'source_path' => $directory, 'module' => $module['id'] ?? null]),
            'metadata' => $metadata,
            'metrics' => $module === null ? ['project' => []] : ['module' => $module],
            'children' => $children,
            'findings' => [],
        ]);
    }
    $children = [];
    foreach ($directories as $directory) if (dirname($directory) === '.') $children[] = ['path' => $directory . '/report.json', 'kind' => 'directory'];
    usort($children, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
    writeJson($output, ['schema_version' => '1.0', 'scope' => ['kind' => 'project', 'source_path' => '.'], 'metadata' => $metadata, 'metrics' => ['project' => $full['metrics']['project']], 'children' => $children, 'findings' => $findings]);
}

/** @param array<string, mixed> $data */
function writeJson(string $path, array $data): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) throw new RuntimeException("Cannot create metrics directory: $directory");
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}
