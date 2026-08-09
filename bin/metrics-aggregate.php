#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PrikotovCodingStandard\Metrics\MetricsAggregator;
use PrikotovCodingStandard\Metrics\MetricsReportWriter;

$options = getopt('', ['analyzer::', 'deptrac::', 'scc::', 'scc-version::', 'tests::', 'clover::', 'output::']);
$analyzer = $options['analyzer'] ?? 'var/metrics/collector.json';
$deptrac = $options['deptrac'] ?? 'var/metrics/deptrac.json';
$output = $options['output'] ?? 'var/metrics/report.json';
$scc = $options['scc'] ?? 'var/metrics/scc.json';
$sccVersion = $options['scc-version'] ?? dirname($scc) . '/scc-version.txt';
$tests = $options['tests'] ?? 'var/metrics/test-stats.json';
$clover = $options['clover'] ?? 'var/metrics/clover.xml';

try {
    foreach (['Analyzer' => $analyzer, 'Deptrac' => $deptrac, 'Test statistics' => $tests] as $name => $path) {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException(sprintf('%s report is missing or empty: %s', $name, $path));
        }
    }
    $optional = [];
    foreach (['SCC' => $scc, 'Clover' => $clover] as $name => $path) {
        if (is_file($path) && filesize($path) > 0) {
            $optional[$name] = (string) file_get_contents($path);
        } else {
            fwrite(STDERR, sprintf("metrics-aggregate: optional %s report is unavailable: %s\n", $name, $path));
        }
    }
    $version = isset($optional['SCC']) && is_file($sccVersion) ? trim((string) file_get_contents($sccVersion)) : null;
    $config = require dirname(__DIR__) . '/.coding-standard.php';
    $commit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: null;
    $full = (new MetricsAggregator())->aggregate(
        json_decode((string) file_get_contents($analyzer), true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) file_get_contents($deptrac), true, flags: JSON_THROW_ON_ERROR),
        $config['metrics'] ?? [],
        $commit,
        isset($optional['SCC']) ? json_decode($optional['SCC'], true, flags: JSON_THROW_ON_ERROR) : null,
        json_decode((string) file_get_contents($tests), true, flags: JSON_THROW_ON_ERROR),
        $optional['Clover'] ?? null,
        $version !== '' ? $version : null,
    );
    (new MetricsReportWriter())->writeMirror($output, $full);
    fwrite(STDOUT, "Metrics reports written to " . dirname($output) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-aggregate: {$exception->getMessage()}\n");
    exit(1);
}
