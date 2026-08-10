<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsReportWriter;

final class MetricsReportWriterTest extends TestCase
{
    public function testWritesProjectDirectoryAndFileReportsInMirror(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-writer-' . uniqid();
        $output = $directory . '/report.json';
        (new MetricsReportWriter())->writeMirror($output, [
            'metadata' => ['commit' => 'abc'],
            'findings' => [],
            'metrics' => [
                'project' => ['class_count' => 1],
                'codebase' => ['languages' => ['PHP' => ['code' => 10]]],
                'tests' => ['total' => ['files' => 1]],
                'coverage' => ['lines' => ['percent' => 80.0]],
                'modules' => [['id' => 'Metrics', 'class_count' => 1]],
                'classes' => [[
                    'id' => 'App\\Metrics\\Collector',
                    'file' => 'src/Metrics/Collector.php',
                    'module' => 'Metrics',
                ]],
                'methods' => [['id' => 'App\\Metrics\\Collector::collect', 'loc' => 5, 'cc' => 1]],
            ],
        ]);

        $root = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        $file = json_decode(
            (string) file_get_contents($directory . '/src/Metrics/Collector.php.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $module = json_decode(
            (string) file_get_contents($directory . '/src/Metrics/report.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        unlink($directory . '/src/Metrics/Collector.php.json');
        unlink($directory . '/src/Metrics/report.json');
        unlink($directory . '/src/report.json');
        unlink($output);
        rmdir($directory . '/src/Metrics');
        rmdir($directory . '/src');
        rmdir($directory);

        self::assertSame(['project', 'codebase', 'tests', 'coverage'], array_keys($root['metrics']));
        self::assertArrayNotHasKey('commit', $root['metadata']);
        self::assertSame(80, $root['metrics']['coverage']['lines']['percent']);
        self::assertSame([['path' => 'src/report.json', 'kind' => 'directory']], $root['children']);
        self::assertSame('file', $file['scope']['kind']);
        self::assertArrayNotHasKey('metadata', $file);
        self::assertSame(['App\\Metrics\\Collector::collect'], array_column($file['metrics']['methods'], 'id'));
        self::assertSame(1, $module['metrics']['directory']['class_count']);
        self::assertSame('Metrics', $module['metrics']['module']['id']);
    }

    public function testWritesApplicationModuleReportAtItsModuleDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-writer-module-' . uniqid();
        $output = $directory . '/report.json';
        (new MetricsReportWriter())->writeMirror($output, [
            'metadata' => [],
            'findings' => [],
            'metrics' => [
                'project' => ['class_count' => 1],
                'modules' => [['id' => 'Web:Billing', 'class_count' => 1]],
                'classes' => [[
                    'id' => 'Task\\Web\\Module\\Billing\\Controller',
                    'file' => 'apps/web/src/Module/Billing/Controller.php',
                    'module' => 'Web:Billing',
                ]],
                'methods' => [],
            ],
        ]);

        try {
            $module = json_decode(
                (string) file_get_contents($directory . '/apps/web/src/Module/Billing/report.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertSame('Web:Billing', $module['scope']['module']);
            self::assertSame('Web:Billing', $module['metrics']['module']['id']);
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
