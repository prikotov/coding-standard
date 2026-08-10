<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;

final class CodingStandardInitMetricsTest extends TestCase
{
    public function testCreatesWorkDirectoryConfigAndTracksCanonicalSnapshot(): void
    {
        $directory = sys_get_temp_dir() . '/coding-standard-init-' . uniqid();
        mkdir($directory);
        file_put_contents($directory . '/.gitignore', "/.coding-standard/\n");
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/bin/coding-standard-init',
            $directory,
            '--no-deptrac',
            '--no-exceptions',
        ];

        try {
            $process = proc_open($command, [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string) $output . (string) $error);

            $config = require $directory . '/.coding-standard.php';
            self::assertSame('var/metrics', $config['metrics']['work_dir']);
            self::assertArrayNotHasKey('report_dir', $config['metrics']);
            $gitignore = (string) file_get_contents($directory . '/.gitignore');
            self::assertStringContainsString("/var/\n", $gitignore);
            self::assertStringContainsString("!/.coding-standard/metrics/**\n", $gitignore);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
