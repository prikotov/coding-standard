<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class TestStatisticsCollector
{
    /** @return array{suites: list<array{name: string, files: int, lines: int, average_lines: float|null}>, total: array{files: int, lines: int, average_lines: float|null}} */
    public function collect(string $configurationPath): array
    {
        $xml = @simplexml_load_file($configurationPath);
        if ($xml === false) {
            throw new RuntimeException("Cannot read PHPUnit configuration: $configurationPath");
        }

        $suites = [];
        foreach ($xml->testsuites->testsuite as $suite) {
            $files = $this->suiteFiles($configurationPath, $suite->directory);
            $fileCount = count($files);
            $lineCount = array_sum(array_map($this->countLines(...), $files));
            $suites[] = [
                'name' => (string) $suite['name'],
                'files' => $fileCount,
                'lines' => $lineCount,
                'average_lines' => $this->average($lineCount, $fileCount),
            ];
        }

        $totalFiles = array_sum(array_column($suites, 'files'));
        $totalLines = array_sum(array_column($suites, 'lines'));

        return [
            'suites' => $suites,
            'total' => [
                'files' => $totalFiles,
                'lines' => $totalLines,
                'average_lines' => $this->average($totalLines, $totalFiles),
            ],
        ];
    }

    /** @return list<string> */
    private function suiteFiles(string $configurationPath, iterable $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            $path = dirname($configurationPath) . '/' . trim((string) $directory);
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[$file->getPathname()] = true;
                }
            }
        }
        $paths = array_keys($files);
        sort($paths);
        return $paths;
    }

    private function countLines(string $file): int
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Cannot read test file: $file");
        }
        if ($content === '') {
            return 0;
        }
        return substr_count($content, "\n") + (str_ends_with($content, "\n") ? 0 : 1);
    }

    private function average(int $lines, int $files): ?float
    {
        return $files === 0 ? null : round($lines / $files, 2);
    }
}
