<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\PhpantomComparison;

final class PhpantomComparisonTest extends TestCase
{
    public function testComparesDiagnosticsWithoutTreatingLineMovesAsChanges(): void
    {
        $baseline = $this->report([
            $this->diagnostic('src/Foo.php', 10, 'error', 'unknown_member', 'Unknown method.'),
            $this->diagnostic('src/Foo.php', 20, 'warning', 'deprecated_usage', 'Deprecated.'),
            $this->diagnostic('src/Old.php', 5, 'error', 'unknown_class', 'Unknown class.'),
        ]);
        $current = $this->report([
            $this->diagnostic('src/Foo.php', 15, 'error', 'unknown_member', 'Unknown method.'),
            $this->diagnostic('src/Foo.php', 25, 'warning', 'deprecated_usage', 'Deprecated.'),
            $this->diagnostic('src/Foo.php', 30, 'warning', 'deprecated_usage', 'Deprecated.'),
            $this->diagnostic('src/New.php', 8, 'error', 'type_mismatch_return', 'Wrong return.'),
        ]);

        $comparison = (new PhpantomComparison())->compare($baseline, $current, ['src/Foo.php', 'src/New.php']);

        self::assertSame(2, $comparison['unchanged_count']);
        self::assertCount(2, $comparison['added']);
        self::assertSame(['src/Foo.php', 'src/New.php'], array_column($comparison['added'], 'path'));
        self::assertTrue($comparison['added'][0]['changed_area']);
        self::assertSame(30, $comparison['added'][0]['line']);
        self::assertCount(1, $comparison['resolved']);
        self::assertSame('src/Old.php', $comparison['resolved'][0]['path']);
        self::assertFalse($comparison['resolved'][0]['changed_area']);
        self::assertSame('0.11.1', $comparison['tool_version']);
    }

    /** @param list<array<string, mixed>> $diagnostics @return array<string, mixed> */
    private function report(array $diagnostics): array
    {
        return ['schema_version' => '1.0', 'tool_version' => '0.11.1', 'diagnostics' => $diagnostics];
    }

    /** @return array<string, mixed> */
    private function diagnostic(
        string $path,
        int $line,
        string $severity,
        string $identifier,
        string $message,
    ): array {
        return [
            'path' => $path,
            'line' => $line,
            'severity' => $severity,
            'identifier' => $identifier,
            'message' => $message,
        ];
    }
}
