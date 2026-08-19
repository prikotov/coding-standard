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
        ]], ['thresholds' => ['class' => ['loc' => 25], 'module' => ['cycles' => 0]]], $this->scc(), $this->testStatistics(), $this->clover(), '3.7.0');

        self::assertSame('1.0', $report['schema_version']);
        self::assertSame('1.0', $report['metadata']['metric_definitions_version']);
        self::assertSame('2.11.3', $report['metadata']['source_versions']['analyzer']);
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

    public function testAddsCodebaseTestAndCoverageMetricsWhenSourcesAreAvailable(): void
    {
        $report = (new MetricsAggregator())->aggregate(
            ['classes' => [], 'functions' => []],
            ['schema_version' => '1.0', 'dependencies' => []],
            [],
            [[
                'Name' => 'PHP', 'Count' => 2, 'Lines' => 30, 'Code' => 20, 'Comment' => 5, 'Blank' => 5,
                'Files' => [
                    ['Location' => 'src/Metrics/Example.php', 'Lines' => 10, 'Code' => 7, 'Comment' => 2, 'Blank' => 1],
                    ['Location' => 'tests/Metrics/ExampleTest.php', 'Lines' => 20, 'Code' => 13, 'Comment' => 3, 'Blank' => 4],
                ],
            ]],
            ['suites' => [['name' => 'Unit', 'files' => 2, 'lines' => 20, 'average_lines' => 10]], 'total' => ['files' => 2, 'lines' => 20, 'average_lines' => 10]],
            '<coverage><project><metrics statements="10" coveredstatements="8" methods="4" coveredmethods="3" /></project></coverage>',
            '3.7.0',
        );

        self::assertSame('3.7.0', $report['metadata']['scc_version']);
        self::assertSame(20, $report['metrics']['codebase']['languages']['PHP']['code']);
        self::assertSame(7, $report['metrics']['codebase']['modules']['src/Metrics']['code']);
        self::assertSame(13, $report['metrics']['codebase']['modules']['tests']['code']);
        self::assertSame(2, $report['metrics']['tests']['total']['files']);
        self::assertSame(80.0, $report['metrics']['coverage']['lines']['percent']);
        self::assertSame(75.0, $report['metrics']['coverage']['methods']['percent']);
    }

    public function testAddsAstDependenciesMissingFromDeptracLayerResults(): void
    {
        $report = (new MetricsAggregator())->aggregate([
            'classes' => [
                $this->class('App\\Alpha\\Service', 'src/Alpha/Service.php', 10, 1),
                $this->class('App\\Alpha\\Helper', 'src/Alpha/Helper.php', 10, 1),
                $this->class('App\\Beta\\Target', 'src/Beta/Target.php', 10, 1),
            ],
            'functions' => [],
            'dependencies' => [
                ['source' => 'App\\Alpha\\Service', 'target' => 'App\\Alpha\\Helper'],
                ['source' => 'App\\Alpha\\Service', 'target' => 'App\\Beta\\Target'],
            ],
        ], ['schema_version' => '1.0', 'dependencies' => []], [], $this->scc(), $this->testStatistics(), $this->clover(), '3.7.0');

        $alpha = $report['metrics']['modules'][0];
        self::assertSame(1, $alpha['internal_dependencies']);
        self::assertSame(1, $alpha['outgoing_dependencies']);
        self::assertSame(0.5, $alpha['external_dependency_share']);
        self::assertSame(0.5, $alpha['cohesion']);
    }

    public function testCountsCommandHandlersWithoutEventDispatch(): void
    {
        $report = (new MetricsAggregator())->aggregate([
            'toolVersion' => '2.11.3',
            'classes' => [
                $this->handler('App\\CreateHandler', 'src/CreateHandler.php', true, false),
                $this->handler('App\\DeleteHandler', 'src/DeleteHandler.php', true, true),
                $this->handler('App\\EnqueueHandler', 'src/EnqueueHandler.php', false, false),
                $this->class('App\\Plain', 'src/Plain.php', 10, 1),
            ],
            'functions' => [],
        ], ['schema_version' => '1.0', 'dependencies' => []], [], $this->scc(), $this->testStatistics(), $this->clover(), '3.7.0');

        self::assertSame(3, $report['metrics']['project']['command_handlers']);
        self::assertSame(1, $report['metrics']['project']['command_handlers_without_event']);
        self::assertSame(1, $report['metrics']['classes'][0]['missing_event_dispatch']);
        self::assertSame(0, $report['metrics']['classes'][1]['missing_event_dispatch']);
        self::assertSame(0, $report['metrics']['classes'][2]['missing_event_dispatch']);
        self::assertNull($report['metrics']['classes'][3]['missing_event_dispatch']);
    }

    public function testRejectsIncompatibleInputs(): void
    {
        $this->expectExceptionMessage('Analyzer JSON');
        (new MetricsAggregator())->aggregate([], ['schema_version' => '1.0', 'dependencies' => []]);
    }

    public function testRejectsMissingTestStatistics(): void
    {
        $this->expectExceptionMessage('Test statistics are required');
        (new MetricsAggregator())->aggregate(['classes' => [], 'functions' => []], ['schema_version' => '1.0', 'dependencies' => []]);
    }

    public function testRejectsMissingSccStatistics(): void
    {
        $this->expectExceptionMessage('SCC statistics are required');
        (new MetricsAggregator())->aggregate(['classes' => [], 'functions' => []], ['schema_version' => '1.0', 'dependencies' => []], [], null, $this->testStatistics());
    }

    public function testRejectsMissingSccVersion(): void
    {
        $this->expectExceptionMessage('SCC version is required');
        (new MetricsAggregator())->aggregate(['classes' => [], 'functions' => []], ['schema_version' => '1.0', 'dependencies' => []], [], $this->scc(), $this->testStatistics());
    }

    public function testRejectsMissingCoverage(): void
    {
        $this->expectExceptionMessage('Clover coverage is required');
        (new MetricsAggregator())->aggregate(['classes' => [], 'functions' => []], ['schema_version' => '1.0', 'dependencies' => []], [], $this->scc(), $this->testStatistics(), null, '3.7.0');
    }

    /** @return list<array<string, mixed>> */
    private function scc(): array
    {
        return [['Name' => 'PHP', 'Count' => 0, 'Lines' => 0, 'Code' => 0, 'Comment' => 0, 'Blank' => 0, 'Files' => []]];
    }

    /** @return array<string, mixed> */
    private function testStatistics(): array
    {
        return ['suites' => [], 'total' => ['files' => 0, 'lines' => 0, 'average_lines' => null]];
    }

    private function clover(): string
    {
        return '<coverage><project><metrics statements="0" coveredstatements="0" methods="0" coveredmethods="0" /></project></coverage>';
    }

    /** @return array<string, mixed> */
    private function class(string $name, string $file, int $loc, int $lcom): array
    {
        return ['name' => $name, 'metrics' => ['filePath' => $file, 'loc' => $loc, 'methodCount' => 1, 'propertyCount' => 2, 'lcom' => $lcom]];
    }

    /** @return array<string, mixed> */
    private function handler(string $name, string $file, bool $mutatesState, bool $dispatchesEvent): array
    {
        return ['name' => $name, 'metrics' => ['filePath' => $file, 'loc' => 10, 'methodCount' => 1, 'propertyCount' => 0, 'lcom' => 1, 'commandHandler' => ['mutates_state' => $mutatesState, 'dispatches_event' => $dispatchesEvent]]];
    }

    /** @return array<string, mixed> */
    private function method(string $class, string $name, int $loc, int $cc): array
    {
        return ['name' => $name, 'type' => 'method', 'metrics' => ['classInfo' => 'x, ' . $class, 'loc' => $loc, 'cc' => $cc]];
    }
}
