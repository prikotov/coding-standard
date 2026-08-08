<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;

final class MetricsCollectorTest extends TestCase
{
    public function testCollectsClassesAndMethodComplexityFromPhpSource(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-collector-' . uniqid();
        mkdir($directory);
        $source = $directory . '/Sample.php';
        $output = $directory . '/report.json';
        file_put_contents($source, <<<'PHP_SOURCE'
<?php
namespace App;
final class Sample {
    private string $value;
    public function decide(bool $condition): string {
        if ($condition) { return $this->value; }
        return 'no';
    }
}
PHP_SOURCE);

        exec(sprintf('%s bin/metrics-collect --source=%s --output=%s', escapeshellarg(PHP_BINARY), escapeshellarg($directory), escapeshellarg($output)), $lines, $code);
        $report = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        unlink($source);
        unlink($output);
        rmdir($directory);

        self::assertSame(0, $code);
        self::assertSame('App\\Sample', $report['classes'][0]['name']);
        self::assertSame(1, $report['classes'][0]['metrics']['propertyCount']);
        self::assertSame(2, $report['functions'][0]['metrics']['cc']);
    }
}
