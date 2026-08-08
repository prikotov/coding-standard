<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsAggregator;

final class MetricsAggregatorTest extends TestCase
{
    public function testAggregatesDistributionsDependenciesCyclesAndFindings(): void
    {
        $report = (new MetricsAggregator())->aggregate([
            'toolVersion' => '2.11.3',
            'classes' => [$this->class('App\\Alpha', 'src/Alpha/A.php', 10, 1), $this->class('App\\Beta', 'src/Beta/B.php', 20, 0), $this->class('App\\Gamma', 'src/Gamma/C.php', 30, 0)],
            'functions' => [$this->method('App\\Alpha', 'run', 4, 2), $this->method('App\\Beta', 'run', 8, 4), $this->method('App\\Gamma', 'run', 12, 6)],
        ], ['schema_version' => '1.0', 'dependencies' => [
            ['source' => 'App\\Alpha', 'target' => 'App\\Beta'], ['source' => 'App\\Beta', 'target' => 'App\\Alpha'], ['source' => 'App\\Alpha', 'target' => 'App\\Gamma'],
        ]], ['thresholds' => ['class' => ['loc' => 25], 'module' => ['cycles' => 0]]], 'abc');

        self::assertSame('1.0', $report['schema_version']);
        self::assertSame(3, $report['metrics']['project']['class_count']);
        self::assertSame(20.0, $report['metrics']['project']['class_loc']['median']);
        self::assertSame(28.0, $report['metrics']['project']['class_loc']['p90']);
        self::assertSame(['App\\Beta', 'App\\Gamma'], $report['metrics']['classes'][0]['ce']['types']);
        $alpha = $report['metrics']['modules'][0];
        self::assertSame(2, $alpha['outgoing_dependencies']);
        self::assertSame(0, $alpha['cohesion']);
        self::assertSame(1, $alpha['cycles']['count']);
        self::assertSame(1, $alpha['external_interface_size']);
        self::assertSame(['class.loc', 'module.cycles', 'module.cycles'], array_column($report['findings'], 'rule_id'));
    }

    public function testRejectsIncompatibleInputs(): void
    {
        $this->expectExceptionMessage('Analyzer JSON');
        (new MetricsAggregator())->aggregate([], ['schema_version' => '1.0', 'dependencies' => []]);
    }

    /** @return array<string, mixed> */
    private function class(string $name, string $file, int $loc, int $lcom): array
    {
        return ['name' => $name, 'metrics' => ['filePath' => $file, 'loc' => $loc, 'methodCount' => 1, 'propertyCount' => 2, 'lcom' => $lcom]];
    }

    /** @return array<string, mixed> */
    private function method(string $class, string $name, int $loc, int $cc): array
    {
        return ['name' => $name, 'type' => 'method', 'metrics' => ['classInfo' => 'x, ' . $class, 'loc' => $loc, 'cc' => $cc]];
    }
}
