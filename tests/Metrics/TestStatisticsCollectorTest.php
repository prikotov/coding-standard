<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\TestStatisticsCollector;

final class TestStatisticsCollectorTest extends TestCase
{
    public function testCollectsStatisticsForConfiguredSuites(): void
    {
        $root = sys_get_temp_dir() . '/test-statistics-' . uniqid();
        mkdir($root . '/tests/Unit', 0777, true);
        mkdir($root . '/tests/Integration', 0777, true);
        file_put_contents($root . '/tests/Unit/FirstTest.php', "<?php\n\necho 'first';\n");
        file_put_contents($root . '/tests/Unit/SecondTest.php', "<?php\necho 'second';");
        file_put_contents($root . '/tests/Unit/readme.txt', "ignored\n");
        file_put_contents($root . '/tests/Integration/ApiTest.php', "<?php\n");
        file_put_contents($root . '/phpunit.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <testsuites>
                    <testsuite name="Unit">
                        <directory>tests/Unit</directory>
                    </testsuite>
                    <testsuite name="Integration">
                        <directory>tests/Integration</directory>
                    </testsuite>
                    <testsuite name="Empty">
                        <directory>tests/Missing</directory>
                    </testsuite>
                </testsuites>
            </phpunit>
            XML);

        $report = (new TestStatisticsCollector())->collect($root . '/phpunit.xml');

        unlink($root . '/tests/Unit/FirstTest.php');
        unlink($root . '/tests/Unit/SecondTest.php');
        unlink($root . '/tests/Unit/readme.txt');
        unlink($root . '/tests/Integration/ApiTest.php');
        unlink($root . '/phpunit.xml');
        rmdir($root . '/tests/Unit');
        rmdir($root . '/tests/Integration');
        rmdir($root . '/tests');
        rmdir($root);

        self::assertSame(['Unit', 'Integration', 'Empty'], array_column($report['suites'], 'name'));
        self::assertSame(['files' => 2, 'lines' => 5, 'average_lines' => 2.5], array_diff_key($report['suites'][0], ['name' => true]));
        self::assertSame(['files' => 1, 'lines' => 1, 'average_lines' => 1.0], array_diff_key($report['suites'][1], ['name' => true]));
        self::assertSame(['files' => 0, 'lines' => 0, 'average_lines' => null], array_diff_key($report['suites'][2], ['name' => true]));
        self::assertSame(['files' => 3, 'lines' => 6, 'average_lines' => 2.0], $report['total']);
    }
}
