#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpParser\ParserFactory;
use PrikotovCodingStandard\Metrics\AstMetricsCollector;

$options = getopt('', ['source::', 'output::']);
$source = rtrim($options['source'] ?? 'src', '/');
$output = $options['output'] ?? 'var/metrics/collector.json';

if (!is_dir($source)) {
    fwrite(STDERR, "metrics-collect: Source directory does not exist: $source\n");
    exit(1);
}

try {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $report = (new AstMetricsCollector($parser, $source))->collect();
    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Cannot create output directory: $directory");
    }
    file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    fwrite(STDOUT, "Metrics source report written to $output\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-collect: {$exception->getMessage()}\n");
    exit(1);
}
