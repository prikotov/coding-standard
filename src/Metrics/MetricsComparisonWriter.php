<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsComparisonWriter
{
    /** @param array<string, mixed> $comparison */
    public function write(string $directory, array $comparison): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create metrics comparison directory: $directory");
        }
        $json = json_encode(
            $comparison,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $this->put($directory . '/comparison.json', $json . "\n");
        $this->put($directory . '/summary.md', $this->summary($comparison));
    }

    /** @param array<string, mixed> $comparison */
    private function summary(array $comparison): string
    {
        $summary = is_array($comparison['summary'] ?? null) ? $comparison['summary'] : [];
        $lines = [
            '# Изменение метрик качества',
            '',
            sprintf(
                '- Улучшено: **%d**; ухудшено: **%d**; информационных изменений: **%d**.',
                (int) ($summary['improved_metric_count'] ?? 0),
                (int) ($summary['regressed_metric_count'] ?? 0),
                (int) ($summary['neutral_metric_count'] ?? 0),
            ),
        ];
        $scopes = is_array($comparison['scopes'] ?? null) ? $comparison['scopes'] : [];
        $titles = ['project' => 'Проект', 'module' => 'Модули', 'class' => 'Классы', 'method' => 'Методы'];
        foreach ($titles as $kind => $title) {
            $scope = is_array($scopes[$kind] ?? null) ? $scopes[$kind] : [];
            $lines[] = sprintf(
                '- %s: добавлено %d, удалено %d, изменено %d.',
                $title,
                count(is_array($scope['added'] ?? null) ? $scope['added'] : []),
                count(is_array($scope['removed'] ?? null) ? $scope['removed'] : []),
                count(is_array($scope['changed'] ?? null) ? $scope['changed'] : []),
            );
        }
        $lines[] = '';
        $this->changeSection($lines, 'Регрессии в изменённой области', $scopes, 'regressed');
        $this->changeSection($lines, 'Улучшения в изменённой области', $scopes, 'improved');

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $scopes
     */
    private function changeSection(array &$lines, string $title, array $scopes, string $direction): void
    {
        $changes = [];
        foreach (['project', 'module', 'class', 'method'] as $kind) {
            $scope = is_array($scopes[$kind] ?? null) ? $scopes[$kind] : [];
            $objects = is_array($scope['changed'] ?? null) ? $scope['changed'] : [];
            foreach ($objects as $object) {
                if (!is_array($object) || ($object['changed_area'] ?? false) !== true) {
                    continue;
                }
                $metrics = is_array($object['metric_changes'] ?? null) ? $object['metric_changes'] : [];
                foreach ($metrics as $metric) {
                    if (is_array($metric) && ($metric['direction'] ?? null) === $direction) {
                        $changes[] = [
                            'kind' => $kind,
                            'id' => (string) ($object['id'] ?? ''),
                            'source_path' => (string) ($object['source_path'] ?? ''),
                            'metric' => (string) ($metric['metric'] ?? ''),
                            'before' => $metric['before'] ?? null,
                            'after' => $metric['after'] ?? null,
                            'delta' => $metric['delta'] ?? null,
                        ];
                    }
                }
            }
        }
        usort(
            $changes,
            static fn (array $left, array $right): int => [$left['kind'], $left['id'], $left['metric']]
                <=> [$right['kind'], $right['id'], $right['metric']],
        );
        $lines[] = "## $title";
        $lines[] = '';
        if ($changes === []) {
            $lines[] = '- Нет.';
            $lines[] = '';

            return;
        }
        foreach (array_slice($changes, 0, 20) as $change) {
            $delta = $change['delta'] === null ? '' : sprintf(', Δ %s', $this->value($change['delta']));
            $lines[] = sprintf(
                '- `%s` `%s` (`%s`, файл `%s`): `%s` → `%s`%s.',
                $change['kind'],
                $change['id'],
                $change['metric'],
                $change['source_path'],
                $this->value($change['before']),
                $this->value($change['after']),
                $delta,
            );
        }
        if (count($changes) > 20) {
            $lines[] = sprintf('- Ещё изменений: %d; полный список — в `comparison.json`.', count($changes) - 20);
        }
        $lines[] = '';
    }

    private function value(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function put(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Cannot write metrics comparison: $path");
        }
    }
}
