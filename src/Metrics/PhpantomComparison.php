<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class PhpantomComparison
{
    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $current
     * @param list<string> $changedPaths
     * @return array<string, mixed>
     */
    public function compare(array $baseline, array $current, array $changedPaths): array
    {
        $version = $current['tool_version'] ?? null;
        if (!is_string($version) || $version === '' || $baseline['tool_version'] !== $version) {
            throw new RuntimeException('PHPantom snapshots must use the same non-empty tool version.');
        }
        $baselineGroups = $this->group($this->diagnostics($baseline));
        $currentGroups = $this->group($this->diagnostics($current));
        $changed = array_fill_keys($changedPaths, true);
        $added = [];
        $resolved = [];
        $unchanged = 0;
        foreach (array_unique([...array_keys($baselineGroups), ...array_keys($currentGroups)]) as $key) {
            $before = $baselineGroups[$key] ?? [];
            $after = $currentGroups[$key] ?? [];
            $unchanged += min(count($before), count($after));
            foreach (array_slice($after, count($before)) as $diagnostic) {
                $diagnostic['changed_area'] = isset($changed[$diagnostic['path']]);
                $added[] = $diagnostic;
            }
            foreach (array_slice($before, count($after)) as $diagnostic) {
                $diagnostic['changed_area'] = isset($changed[$diagnostic['path']]);
                $resolved[] = $diagnostic;
            }
        }
        $sorter = static fn (array $left, array $right): int => [
            $left['path'], $left['line'], $left['identifier'], $left['message'],
        ] <=> [
            $right['path'], $right['line'], $right['identifier'], $right['message'],
        ];
        usort($added, $sorter);
        usort($resolved, $sorter);

        return [
            'schema_version' => '1.0',
            'tool' => 'phpantom_lsp',
            'tool_version' => $version,
            'added' => $added,
            'resolved' => $resolved,
            'unchanged_count' => $unchanged,
        ];
    }

    /** @param array<string, mixed> $report @return list<array<string, mixed>> */
    private function diagnostics(array $report): array
    {
        $diagnostics = $report['diagnostics'] ?? null;
        if (!is_array($diagnostics)) {
            throw new RuntimeException('PHPantom snapshot does not contain diagnostics.');
        }

        return array_values($diagnostics);
    }

    /**
     * Diagnostics are matched without line numbers so unchanged findings survive edits above them.
     *
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, list<array<string, mixed>>>
     */
    private function group(array $diagnostics): array
    {
        $groups = [];
        foreach ($diagnostics as $diagnostic) {
            $key = implode("\0", [
                (string) ($diagnostic['path'] ?? ''),
                (string) ($diagnostic['severity'] ?? ''),
                (string) ($diagnostic['identifier'] ?? ''),
                (string) ($diagnostic['message'] ?? ''),
            ]);
            $groups[$key][] = $diagnostic;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (array $left, array $right): int => $left['line'] <=> $right['line']);
        }
        unset($group);
        ksort($groups);

        return $groups;
    }
}
