<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsDashboardGenerator;
use RuntimeException;

final class MetricsDashboardGeneratorTest extends TestCase
{
    public function testGeneratesOfflineDashboardFromMirrorReports(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-dashboard-' . uniqid();
        $input = $directory . '/report.json';
        $output = $directory . '/index.html';
        $this->writeReport($input, [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'project', 'source_path' => '.'],
            'metadata' => ['generated_at' => '2026-08-09T10:00:00Z', 'commit' => 'abc123'],
            'metrics' => [
                'project' => ['class_count' => 2, 'loc' => 50, 'cycles' => ['count' => 1]],
                'codebase' => ['languages' => ['PHP' => ['files' => 4, 'lines' => 120]]],
            ],
            'children' => [['path' => 'src/report.json', 'kind' => 'directory']],
        ]);
        $this->writeReport($directory . '/src/report.json', $this->directoryReport('src', [
            ['path' => 'Alpha/report.json', 'kind' => 'directory'],
            ['path' => 'Beta/report.json', 'kind' => 'directory'],
        ]));
        $this->writeReport(
            $directory . '/src/Alpha/report.json',
            $this->moduleReport('Alpha', 30, 0.5, [['path' => 'A.php.json', 'kind' => 'file']]),
        );
        $this->writeReport(
            $directory . '/src/Beta/report.json',
            $this->moduleReport('Beta', 20, 1.0, [['path' => 'B.php.json', 'kind' => 'file']]),
        );
        $this->writeReport(
            $directory . '/src/Alpha/A.php.json',
            $this->fileReport('Alpha', $this->classMetrics('App\\Alpha', 'Alpha', 30, 3, 4, 'App\\Beta')),
        );
        $this->writeReport(
            $directory . '/src/Beta/B.php.json',
            $this->fileReport('Beta', $this->classMetrics('App\\Beta', 'Beta', 20, 1, 2, 'App\\Alpha')),
        );

        try {
            $summary = (new MetricsDashboardGenerator())->generate($input, $output);
            $html = (string) file_get_contents($output);

            self::assertSame(['modules' => 2, 'classes' => 2, 'dependencies' => 2], $summary);
            self::assertStringContainsString('<!doctype html>', $html);
            self::assertStringContainsString('id="bubble-chart"', $html);
            self::assertStringContainsString('id="scatter-chart"', $html);
            self::assertStringContainsString('id="treemap-chart"', $html);
            self::assertStringContainsString('id="matrix-chart"', $html);
            self::assertStringContainsString('LOC внутри классов модулей', $html);
            self::assertStringContainsString('Все строки проекта (все файлы)', $html);
            self::assertStringContainsString('Как читать метрики', $html);
            self::assertStringContainsString('Дашборд помогает оценивать качество и поддерживаемость кода', $html);
            self::assertStringContainsString('Отдельная метрика показывает, куда смотреть', $html);
            self::assertStringContainsString('типу класса', $html);
            self::assertStringContainsString('Common или модуль приложения Web, Console, Api', $html);
            self::assertStringContainsString('Значение сравнивается с классами того же типа', $html);
            self::assertStringContainsString('Churn</code> — изменчивость кода', $html);
            self::assertStringContainsString('Сколько Git-коммитов затрагивало файл класса', $html);
            self::assertStringContainsString('LCOM4</code> — недостаток связности методов', $html);
            self::assertStringContainsString('Lack of Cohesion in Methods 4', $html);
            self::assertStringContainsString('<em>Lack</em> — недостаток', $html);
            self::assertStringContainsString('Ca / Ce</code> — входящая и исходящая связанность', $html);
            self::assertStringContainsString('Afferent Coupling', $html);
            self::assertStringContainsString('Efferent Coupling', $html);
            self::assertStringContainsString('CC / max CC</code> — цикломатическая сложность', $html);
            self::assertStringContainsString('Cyclomatic Complexity', $html);
            self::assertStringContainsString('Maximum Cyclomatic Complexity', $html);
            self::assertStringContainsString('Lines of Code', $html);
            self::assertStringContainsString('<code>namespace</code> и <code>use</code> перед объявлением не входят', $html);
            self::assertStringContainsString('Исключённый shared-код', $html);
            self::assertStringNotContainsString('fetch(', $html);
            self::assertMatchesRegularExpression(
                '/<script type="application\/json" id="dashboard-data">(.+)<\/script>/sU',
                $html,
            );
            preg_match('/<script type="application\/json" id="dashboard-data">(.+)<\/script>/sU', $html, $match);
            $dashboardData = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(['Alpha', 'Beta'], array_column($dashboardData['modules'], 'id'));
            self::assertSame(['App\\Alpha', 'App\\Beta'], array_column($dashboardData['classes'], 'id'));
            self::assertTrue($dashboardData['dependencies'][0]['cycle']);
            self::assertSame('abc123', $dashboardData['report']['commit']);
            self::assertSame(120, $dashboardData['codebase']['languages']['PHP']['lines']);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testRejectsMissingChildReport(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-dashboard-' . uniqid();
        $input = $directory . '/report.json';
        $this->writeReport($input, [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'project', 'source_path' => '.'],
            'metrics' => ['project' => []],
            'children' => [['path' => 'missing/report.json', 'kind' => 'directory']],
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Child metrics report does not exist');
            (new MetricsDashboardGenerator())->generate($input, $directory . '/index.html');
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @param list<array{path: string, kind: string}> $children @return array<string, mixed> */
    private function directoryReport(string $path, array $children): array
    {
        return [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'directory', 'source_path' => $path],
            'metrics' => ['project' => []],
            'children' => $children,
        ];
    }

    /** @param list<array{path: string, kind: string}> $children @return array<string, mixed> */
    private function moduleReport(string $id, int $loc, float $externalShare, array $children): array
    {
        return [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'directory', 'source_path' => "src/$id", 'module' => $id],
            'metrics' => ['module' => [
                'id' => $id,
                'class_count' => 1,
                'loc' => $loc,
                'external_dependency_share' => $externalShare,
                'cohesion' => 1 - $externalShare,
                'outgoing_dependencies' => 1,
                'cycles' => ['count' => 1, 'components' => ['Alpha,Beta']],
            ]],
            'children' => $children,
        ];
    }

    /** @param array<string, mixed> $class @return array<string, mixed> */
    private function fileReport(string $module, array $class): array
    {
        return [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'file', 'source_path' => $class['file'], 'module' => $module],
            'metrics' => ['classes' => [$class], 'methods' => []],
        ];
    }

    /** @return array<string, mixed> */
    private function classMetrics(
        string $id,
        string $module,
        int $loc,
        int $lcom,
        int $cc,
        string $dependency,
    ): array {
        return [
            'id' => $id,
            'file' => "src/$module/" . substr($id, strrpos($id, '\\') + 1) . '.php',
            'module' => $module,
            'loc' => $loc,
            'max_cc' => $cc,
            'lcom4' => ['components' => $lcom, 'normalized' => ($lcom - 1) / 3],
            'ce' => ['count' => 1, 'types' => [$dependency]],
            'churn' => ['commits' => $loc / 10],
        ];
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $path, array $report): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($report, JSON_THROW_ON_ERROR));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
