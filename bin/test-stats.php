#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['format::', 'output:']);
$format = $options['format'] ?? 'text';
$output = $options['output'] ?? null;
if (!in_array($format, ['text', 'json'], true)) {
    fwrite(STDERR, "test-stats: --format must be text or json\n");
    exit(1);
}

$config = dirname(__DIR__) . '/phpunit.xml';
$xml = simplexml_load_file($config);
if ($xml === false) {
    fwrite(STDERR, "test-stats: cannot read phpunit.xml\n");
    exit(1);
}

$suites = [];
foreach ($xml->testsuites->testsuite as $suite) {
    $name = (string) $suite['name'];
    $files = [];
    foreach ($suite->directory as $directory) {
        $path = dirname($config) . '/' . trim((string) $directory);
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = true;
            }
        }
    }
    $lineCount = array_sum(array_map(static fn (string $file): int => count(file($file)), array_keys($files)));
    $fileCount = count($files);
    $suites[] = [
        'name' => $name,
        'files' => $fileCount,
        'lines' => $lineCount,
        'average_lines' => $fileCount === 0 ? null : round($lineCount / $fileCount, 2),
    ];
}

$totalFiles = array_sum(array_column($suites, 'files'));
$totalLines = array_sum(array_column($suites, 'lines'));
$report = [
    'suites' => $suites,
    'total' => [
        'files' => $totalFiles,
        'lines' => $totalLines,
        'average_lines' => $totalFiles === 0 ? null : round($totalLines / $totalFiles, 2),
    ],
];

if ($format === 'json') {
    $result = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (is_string($output)) {
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            fwrite(STDERR, "test-stats: cannot create output directory: $directory\n");
            exit(1);
        }
        file_put_contents($output, $result);
    } else {
        fwrite(STDOUT, $result);
    }
    exit(0);
}

foreach ($suites as $suite) {
    $average = $suite['average_lines'] === null ? 'n/a' : number_format($suite['average_lines'], 2, '.', '');
    printf("%s: %d files, %d lines, %s lines/file\n", $suite['name'], $suite['files'], $suite['lines'], $average);
}
$average = $report['total']['average_lines'] === null ? 'n/a' : number_format($report['total']['average_lines'], 2, '.', '');
printf("Total: %d files, %d lines, %s lines/file\n", $totalFiles, $totalLines, $average);
