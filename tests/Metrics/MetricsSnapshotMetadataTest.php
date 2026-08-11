<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsSnapshotMetadata;

final class MetricsSnapshotMetadataTest extends TestCase
{
    public function testBuildsStableConfigurationAndInputFingerprintsWithoutSnapshotFiles(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-metadata-' . uniqid();
        $this->write($directory . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":"src/"}}}');
        $this->write($directory . '/depfile.yaml', 'deptrac: {}');
        $this->write($directory . '/phpunit.xml', '<phpunit/>');
        $this->write($directory . '/src/Example.php', '<?php final class Example {}');
        $this->write($directory . '/.coding-standard/metrics/report.json', 'old snapshot');
        $metadata = new MetricsSnapshotMetadata($directory);
        $report = ['metadata' => ['analyzer_version' => '1.0'], 'metrics' => ['project' => []], 'findings' => []];
        $analyzer = ['classes' => [[
            'metrics' => ['filePath' => 'src/Example.php'],
        ]]];
        $scc = [[
            'Files' => [
                ['Location' => './src/Example.php'],
                ['Location' => './.coding-standard/metrics/report.json'],
            ],
        ]];
        $config = ['metrics' => ['work_dir' => 'var/metrics']];

        try {
            $first = $metadata->addFingerprints(
                $report,
                $analyzer,
                $scc,
                $config,
                $directory . '/depfile.yaml',
                $directory . '/phpunit.xml',
            );
            $second = $metadata->addFingerprints(
                $report,
                $analyzer,
                $scc,
                $config,
                $directory . '/depfile.yaml',
                $directory . '/phpunit.xml',
            );
            self::assertSame($first['metadata'], $second['metadata']);
            self::assertSame(basename($directory), $first['metadata']['project']);
            self::assertStringStartsWith('sha256:', $first['metadata']['configuration_hash']);
            self::assertStringStartsWith('sha256:', $first['metadata']['input_hash']);

            $this->write($directory . '/.coding-standard/metrics/report.json', 'manual snapshot change');
            $snapshotChanged = $metadata->addFingerprints(
                $report,
                $analyzer,
                $scc,
                $config,
                $directory . '/depfile.yaml',
                $directory . '/phpunit.xml',
            );
            self::assertSame($first['metadata']['input_hash'], $snapshotChanged['metadata']['input_hash']);

            $this->write($directory . '/src/Example.php', '<?php final class Renamed {}');
            $sourceChanged = $metadata->addFingerprints(
                $report,
                $analyzer,
                $scc,
                $config,
                $directory . '/depfile.yaml',
                $directory . '/phpunit.xml',
            );
            self::assertNotSame($first['metadata']['input_hash'], $sourceChanged['metadata']['input_hash']);
            self::assertSame(
                $first['metadata']['configuration_hash'],
                $sourceChanged['metadata']['configuration_hash'],
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
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
