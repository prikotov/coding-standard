<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\DirectoryRemover;

final class MetricsComparisonCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/metrics-comparison-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
        file_put_contents($this->directory . '/paths.txt', '');
    }

    protected function tearDown(): void
    {
        (new DirectoryRemover())->remove($this->directory);
    }

    public function testCreatesDeterministicJsonAndMarkdownFromCanonicalMirrors(): void
    {
        $baseline = $this->directory . '/baseline';
        $current = $this->directory . '/current';
        $output = $this->directory . '/output';
        $this->snapshot($baseline, 4, 30, 1, false);
        $this->snapshot($current, 2, 35, 2, true);
        file_put_contents($this->directory . '/paths.txt', "src/Main/Foo.php\n");

        $first = $this->execute($baseline, $current, $output);
        self::assertSame(0, $first['code'], $first['output']);
        $json = (string) file_get_contents($output . '/comparison.json');
        $markdown = (string) file_get_contents($output . '/summary.md');
        self::assertStringContainsString('"direction": "improved"', $json);
        self::assertStringContainsString('"direction": "regressed"', $json);
        self::assertStringContainsString('"direction": "neutral"', $json);
        self::assertStringContainsString('Регрессии в изменённой области', $markdown);
        self::assertStringContainsString('`App\\Foo`', $markdown);
        self::assertStringContainsString('файл `src/Main/Foo.php`', $markdown);

        $hashes = [hash('sha256', $json), hash('sha256', $markdown)];
        $this->reverseJsonKeys($current . '/report.json');
        $this->reverseJsonKeys($current . '/src/report.json');
        $this->reverseJsonKeys($current . '/src/Main/report.json');
        $this->reverseJsonKeys($current . '/src/Main/Foo.php.json');
        $second = $this->execute($baseline, $current, $output);
        self::assertSame(0, $second['code'], $second['output']);
        self::assertSame($hashes, [
            hash_file('sha256', $output . '/comparison.json'),
            hash_file('sha256', $output . '/summary.md'),
        ]);
    }

    public function testRejectsUnreferencedCanonicalReportButIgnoresIntermediateFiles(): void
    {
        $baseline = $this->directory . '/baseline';
        $current = $this->directory . '/current';
        $this->snapshot($baseline, 4, 30, 1, false);
        $this->snapshot($current, 4, 30, 1, false);
        file_put_contents($baseline . '/index.html', '<html></html>');
        file_put_contents($baseline . '/collector.json', '{}');
        mkdir($baseline . '/orphan', 0777, true);
        file_put_contents($baseline . '/orphan/Unused.php.json', '{}');

        $result = $this->execute($baseline, $current, $this->directory . '/output');

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('unreferenced canonical reports', $result['output']);
    }

    private function snapshot(string $directory, int $methodCc, int $classLoc, int $churn, bool $addMethod): void
    {
        mkdir($directory . '/src/Main', 0777, true);
        $metadata = [
            'source_versions' => [
                'test_statistics' => '1.0',
                'scc' => '3.7.0',
                'deptrac' => '1.0',
                'coverage' => 'clover',
                'analyzer' => 'metrics-collector/1.0',
            ],
            'project' => 'example/project',
            'metric_definitions_version' => '1.0',
            'input_hash' => 'sha256:input-' . $classLoc,
            'configuration_hash' => 'sha256:config',
        ];
        $this->json($directory . '/report.json', [
            'scope' => ['source_path' => '.', 'kind' => 'project'],
            'schema_version' => '1.0',
            'metrics' => [
                'project' => ['loc' => $classLoc, 'class_count' => 1],
                'coverage' => ['lines' => ['percent' => 80]],
            ],
            'metadata' => $metadata,
            'children' => [['kind' => 'directory', 'path' => 'src/report.json']],
            'findings' => [],
        ]);
        $this->json($directory . '/src/report.json', [
            'metrics' => ['directory' => ['class_count' => 1]],
            'children' => [['path' => 'Main/report.json', 'kind' => 'directory']],
            'scope' => ['kind' => 'directory', 'source_path' => 'src'],
            'schema_version' => '1.0',
            'findings' => [],
        ]);
        $this->json($directory . '/src/Main/report.json', [
            'schema_version' => '1.0',
            'scope' => ['source_path' => 'src/Main', 'kind' => 'directory', 'module' => 'Main'],
            'children' => [['kind' => 'file', 'path' => 'Foo.php.json']],
            'metrics' => [
                'module' => ['id' => 'Main', 'cohesion' => $methodCc === 4 ? 0.8 : 0.7],
                'directory' => ['class_count' => 1],
            ],
            'findings' => [],
        ]);
        $methods = [['id' => 'App\\Foo::run', 'loc' => 10, 'cc' => $methodCc]];
        if ($addMethod) {
            $methods[] = ['id' => 'App\\Foo::added', 'loc' => 3, 'cc' => 1];
        }
        $this->json($directory . '/src/Main/Foo.php.json', [
            'findings' => [],
            'metrics' => [
                'methods' => $methods,
                'classes' => [[
                    'id' => 'App\\Foo',
                    'kind' => 'class',
                    'file' => 'src/Main/Foo.php',
                    'module' => 'Main',
                    'loc' => $classLoc,
                    'churn' => ['commits' => $churn],
                ]],
            ],
            'scope' => [
                'module' => 'Main',
                'source_path' => 'src/Main/Foo.php',
                'kind' => 'file',
            ],
            'schema_version' => '1.0',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function json(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function reverseJsonKeys(string $path): void
    {
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $this->json($path, $this->reverseKeys($data));
    }

    /** @param array<mixed> $data @return array<mixed> */
    private function reverseKeys(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(
                fn (mixed $value): mixed => is_array($value) ? $this->reverseKeys($value) : $value,
                $data,
            );
        }
        $data = array_reverse($data, true);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->reverseKeys($value);
            }
        }
        unset($value);

        return $data;
    }

    /** @return array{code: int, output: string} */
    private function execute(string $baseline, string $current, string $output): array
    {
        $command = sprintf(
            '%s %s --baseline=%s --current=%s --output=%s --changed-paths=%s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__, 2) . '/bin/coding-standard-metrics-compare'),
            escapeshellarg($baseline),
            escapeshellarg($current),
            escapeshellarg($output),
            escapeshellarg($this->directory . '/paths.txt'),
        );
        exec($command, $lines, $code);

        return ['code' => $code, 'output' => implode("\n", $lines)];
    }
}
