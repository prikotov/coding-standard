#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PrikotovCodingStandard\Metrics\MetricsDashboardGenerator;

$options = getopt('', ['input::', 'output::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php bin/metrics-dashboard.php [--input=report.json] [--output=index.html]\n");
    exit(0);
}

$input = is_string($options['input'] ?? null) ? $options['input'] : 'var/metrics/report.json';
$output = is_string($options['output'] ?? null) ? $options['output'] : 'var/metrics/index.html';

try {
    $summary = (new MetricsDashboardGenerator())->generate($input, $output);
    fwrite(
        STDOUT,
        sprintf(
            "Metrics dashboard written to %s (%d modules, %d classes, %d dependencies)\n",
            $output,
            $summary['modules'],
            $summary['classes'],
            $summary['dependencies'],
        ),
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "metrics-dashboard: {$exception->getMessage()}\n");
    exit(1);
}
