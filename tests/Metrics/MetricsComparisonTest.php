<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsComparison;
use RuntimeException;

final class MetricsComparisonTest extends TestCase
{
    public function testClassifiesMetricChangesAndObjectLifecycle(): void
    {
        $baseline = $this->snapshot([
            'Stable' => $this->object('Stable', 'src/Stable.php', [
                'loc' => 20,
                'lcom4' => ['components' => 2],
                'churn' => ['commits' => 1],
            ]),
            'Removed' => $this->object('Removed', 'src/Removed.php', ['loc' => 5]),
        ]);
        $current = $this->snapshot([
            'Stable' => $this->object('Stable', 'src/Stable.php', [
                'loc' => 24,
                'lcom4' => ['components' => 1],
                'churn' => ['commits' => 2],
            ]),
            'Added' => $this->object('Added', 'src/Added.php', ['loc' => 5]),
        ]);

        $result = (new MetricsComparison())->compare($baseline, $current, ['src/Stable.php']);
        $classes = $result['scopes']['class'];

        self::assertSame('Added', $classes['added'][0]['id']);
        self::assertSame('Removed', $classes['removed'][0]['id']);
        self::assertTrue($classes['changed'][0]['changed_area']);
        self::assertSame(
            ['neutral', 'improved', 'regressed'],
            array_column($classes['changed'][0]['metric_changes'], 'direction'),
        );
        self::assertSame(4, $this->metric($classes['changed'][0], 'loc')['delta']);
        self::assertTrue($this->metric($classes['changed'][0], 'churn.commits')['informational']);
        self::assertSame(1, $result['summary']['improved_metric_count']);
        self::assertSame(1, $result['summary']['regressed_metric_count']);
        self::assertSame(1, $result['summary']['neutral_metric_count']);
    }

    #[DataProvider('incompatibleSnapshots')]
    public function testRejectsIncompatibleSnapshots(string $field, mixed $value): void
    {
        $baseline = $this->snapshot([]);
        $current = $this->snapshot([]);
        if ($field === 'schema_version') {
            $current[$field] = $value;
        } else {
            $current['metadata'][$field] = $value;
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($field);
        (new MetricsComparison())->compare($baseline, $current);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function incompatibleSnapshots(): iterable
    {
        yield 'project' => ['project', 'another/project'];
        yield 'schema' => ['schema_version', '2.0'];
        yield 'definitions' => ['metric_definitions_version', '2.0'];
        yield 'configuration' => ['configuration_hash', 'sha256:other'];
        yield 'sources' => ['source_versions', ['analyzer' => '2.0']];
    }

    /**
     * @param array<string, array<string, mixed>> $classes
     * @return array<string, mixed>
     */
    private function snapshot(array $classes): array
    {
        return [
            'schema_version' => '1.0',
            'metadata' => [
                'project' => 'example/project',
                'metric_definitions_version' => '1.0',
                'configuration_hash' => 'sha256:config',
                'source_versions' => ['analyzer' => '1.0'],
            ],
            'objects' => [
                'project' => ['example/project' => $this->object('example/project', '.', [])],
                'module' => [],
                'class' => $classes,
                'method' => [],
            ],
        ];
    }

    /** @param array<string, mixed> $metrics @return array<string, mixed> */
    private function object(string $id, string $sourcePath, array $metrics): array
    {
        return ['id' => $id, 'source_path' => $sourcePath, 'attributes' => [], 'metrics' => $metrics];
    }

    /** @param array<string, mixed> $object @return array<string, mixed> */
    private function metric(array $object, string $name): array
    {
        foreach ($object['metric_changes'] as $metric) {
            if ($metric['metric'] === $name) {
                return $metric;
            }
        }

        self::fail("Metric change not found: $name");
    }
}
