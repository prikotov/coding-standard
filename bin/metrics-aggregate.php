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
use PrikotovCodingStandard\Metrics\MetricsSnapshotMetadata;

$options = getopt('', [
    'project-root::', 'config::', 'deptrac-config::', 'phpunit-config::', 'analyzer::', 'deptrac::',
    'scc::', 'scc-version::', 'tests::', 'clover::', 'output::',
]);
$projectRoot = $options['project-root'] ?? getcwd();
$configPath = $options['config'] ?? '.coding-standard.php';
$deptracConfig = $options['deptrac-config'] ?? 'depfile.yaml';
$phpunitConfig = $options['phpunit-config'] ?? 'phpunit.xml.dist';
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
    $analyzerData = json_decode((string) file_get_contents($analyzer), true, flags: JSON_THROW_ON_ERROR);
    $sccData = json_decode((string) file_get_contents($scc), true, flags: JSON_THROW_ON_ERROR);
    $full = (new MetricsAggregator())->aggregate(
        $analyzerData,
        json_decode((string) file_get_contents($deptrac), true, flags: JSON_THROW_ON_ERROR),
        $config['metrics'] ?? [],
        $sccData,
        json_decode((string) file_get_contents($tests), true, flags: JSON_THROW_ON_ERROR),
        (string) file_get_contents($clover),
        $version,
    );
    if (!is_string($projectRoot) || !is_string($deptracConfig) || !is_string($phpunitConfig)) {
        throw new RuntimeException('Fingerprint paths must be strings.');
    }
    $full = (new MetricsSnapshotMetadata($projectRoot))->addFingerprints(
        $full,
        $analyzerData,
        $sccData,
        $config,
        $deptracConfig,
        $phpunitConfig,
    );
    (new MetricsReportWriter())->writeMirror($output, $full);
    fwrite(STDOUT, "Metrics reports written to " . dirname($output) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-aggregate: {$exception->getMessage()}\n");
    exit(1);
}
