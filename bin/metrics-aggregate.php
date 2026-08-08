#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PrikotovCodingStandard\Metrics\MetricsAggregator;
use PrikotovCodingStandard\Metrics\MetricsReportWriter;

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
    (new MetricsReportWriter())->writeMirror($output, $full);
    fwrite(STDOUT, "Metrics reports written to " . dirname($output) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-aggregate: {$exception->getMessage()}\n");
    exit(1);
}

