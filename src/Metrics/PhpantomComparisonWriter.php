<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class PhpantomComparisonWriter
{
    /** @param array<string, mixed> $comparison */
    public function write(string $directory, array $comparison): void
    {
        $json = json_encode(
            $comparison,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        if (file_put_contents($directory . '/phpantom-comparison.json', $json . "\n") === false) {
            throw new RuntimeException("Cannot write PHPantom comparison: $directory");
        }
        $summary = file_put_contents(
            $directory . '/summary.md',
            $this->summary($comparison),
            FILE_APPEND,
        );
        if ($summary === false) {
            throw new RuntimeException("Cannot append PHPantom review summary: $directory");
        }
    }

    /** @param array<string, mixed> $comparison */
    private function summary(array $comparison): string
    {
        $added = is_array($comparison['added'] ?? null) ? $comparison['added'] : [];
        $resolved = is_array($comparison['resolved'] ?? null) ? $comparison['resolved'] : [];
        $lines = [
            '## PHPantom',
            '',
            sprintf(
                '- Версия: `%s`; новых диагностик: **%d**; устранено: **%d**; без изменений: %d.',
                (string) ($comparison['tool_version'] ?? ''),
                count($added),
                count($resolved),
                (int) ($comparison['unchanged_count'] ?? 0),
            ),
            '',
        ];
        $this->section($lines, 'Новые диагностики PHPantom', $added, 50);
        $this->section($lines, 'Устранённые диагностики PHPantom', $resolved, 20);

        return implode("\n", $lines);
    }

    /** @param list<string> $lines @param array<mixed> $diagnostics */
    private function section(array &$lines, string $title, array $diagnostics, int $limit): void
    {
        $lines[] = "### $title";
        $lines[] = '';
        if ($diagnostics === []) {
            $lines[] = '- Нет.';
            $lines[] = '';

            return;
        }
        foreach (array_slice($diagnostics, 0, $limit) as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $lines[] = sprintf(
                '- `%s` `%s:%d` `%s`: %s',
                strtoupper((string) ($diagnostic['severity'] ?? '')),
                $diagnostic['path'] ?? '',
                (int) ($diagnostic['line'] ?? 0),
                $diagnostic['identifier'] ?? '',
                $diagnostic['message'] ?? '',
            );
        }
        if (count($diagnostics) > $limit) {
            $lines[] = sprintf(
                '- Ещё диагностик: %d; полный список — в `phpantom-comparison.json`.',
                count($diagnostics) - $limit,
            );
        }
        $lines[] = '';
    }
}
