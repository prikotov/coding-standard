<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsSnapshotManager;

final class MetricsSnapshotManagerTest extends TestCase
{
    public function testSynchronizesCreatedChangedAndExtraReportsIdempotently(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-snapshot-' . uniqid();
        $candidate = $directory . '/candidate';
        $snapshot = $directory . '/snapshot';
        $this->write($candidate . '/report.json', "root\n");
        $this->write($candidate . '/src/Example.php.json', "file\n");
        $manager = new MetricsSnapshotManager();

        try {
            self::assertSame([
                'created' => ['report.json', 'src/Example.php.json'],
                'changed' => [],
                'extra' => [],
            ], $manager->differences($candidate, $snapshot));
            $manager->update($candidate, $snapshot);
            self::assertSame([
                'created' => [],
                'changed' => [],
                'extra' => [],
            ], $manager->update($candidate, $snapshot));

            $this->write($candidate . '/src/Added.php.json', "added\n");
            $this->write($snapshot . '/src/Example.php.json', "manual edit\n");
            $this->write($snapshot . '/src/Removed.php.json', "stale\n");
            self::assertSame([
                'created' => ['src/Added.php.json'],
                'changed' => ['src/Example.php.json'],
                'extra' => ['src/Removed.php.json'],
            ], $manager->differences($candidate, $snapshot));

            $manager->update($candidate, $snapshot);
            self::assertFileDoesNotExist($snapshot . '/src/Removed.php.json');
            self::assertSame("file\n", file_get_contents($snapshot . '/src/Example.php.json'));
        } finally {
            $manager->removeDirectory($directory);
        }
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }
}
