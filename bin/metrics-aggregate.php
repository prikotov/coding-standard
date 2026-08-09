#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoloadPath = $GLOBALS['_composer_autoload_path'] ?? getcwd() . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
}
require $autoloadPath;

use PrikotovCodingStandard\Metrics\MetricsAggregator;
use PrikotovCodingStandard\Metrics\MetricsReportWriter;

$options = getopt('', [
    'config::', 'analyzer::', 'deptrac::', 'scc::', 'scc-version::', 'tests::', 'clover::', 'output::',
]);
$configPath = $options['config'] ?? '.coding-standard.php';
$analyzer = $options['analyzer'] ?? 'var/metrics/collector.json';
$deptrac = $options['deptrac'] ?? 'var/metrics/deptrac.json';
$output = $options['output'] ?? 'var/metrics/report.json';
$scc = $options['scc'] ?? 'var/metrics/scc.json';
$sccVersion = $options['scc-version'] ?? dirname($scc) . '/scc-version.txt';
$tests = $options['tests'] ?? 'var/metrics/test-stats.json';
$clover = $options['clover'] ?? 'var/metrics/clover.xml';

try {
    $sources = [
        'Analyzer' => $analyzer,
        'Deptrac' => $deptrac,
        'Test statistics' => $tests,
        'SCC' => $scc,
        'SCC version' => $sccVersion,
        'Clover' => $clover,
    ];
    foreach ($sources as $name => $path) {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException(sprintf('%s report is missing or empty: %s', $name, $path));
        }
    }
    $version = trim((string) file_get_contents($sccVersion));
    if (!is_file($configPath)) {
        throw new RuntimeException("Project configuration is missing: $configPath");
    }
    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException("Project configuration must return an array: $configPath");
    }
    $commit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: null;
    $full = (new MetricsAggregator())->aggregate(
        json_decode((string) file_get_contents($analyzer), true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) file_get_contents($deptrac), true, flags: JSON_THROW_ON_ERROR),
        $config['metrics'] ?? [],
        $commit,
        json_decode((string) file_get_contents($scc), true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) file_get_contents($tests), true, flags: JSON_THROW_ON_ERROR),
        (string) file_get_contents($clover),
        $version,
    );
    (new MetricsReportWriter())->writeMirror($output, $full);
    fwrite(STDOUT, "Metrics reports written to " . dirname($output) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-aggregate: {$exception->getMessage()}\n");
    exit(1);
}
