<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsReviewPipeline;
use PrikotovCodingStandard\Metrics\MetricsSnapshotManager;
use RuntimeException;

final class MetricsReviewPipelineTest extends TestCase
{
    private string $directory;
    private string $project;
    private string $package;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/metrics-review-' . bin2hex(random_bytes(6));
        $this->project = $this->directory . '/consumer';
        $this->package = $this->directory . '/package';
        mkdir($this->project . '/src', 0777, true);
        mkdir($this->package . '/bin', 0777, true);
        file_put_contents($this->project . '/composer.json', '{"name":"example/consumer"}');
        file_put_contents($this->project . '/.gitignore', "/var/\n");
        file_put_contents($this->package . '/bin/coding-standard-metrics', <<<'PHP'
<?php
file_put_contents(dirname(__DIR__) . '/snapshot-check-called', 'yes');
PHP);
        $this->git('init');
        $this->git('config user.email metrics@example.com');
        $this->git('config user.name Metrics');
    }

    protected function tearDown(): void
    {
        (new MetricsSnapshotManager())->removeDirectory($this->directory);
    }

    public function testBuildsAReproducibleReviewArtifactFromMergeBaseAndCurrentSnapshot(): void
    {
        file_put_contents($this->project . '/src/Foo.php', '<?php final class Foo {}');
        $this->snapshot(10, 1, 'sha256:baseline');
        $this->git('add .');
        $this->git('commit -m baseline');
        $baselineCommit = $this->git('rev-parse HEAD');

        file_put_contents($this->project . '/src/Foo.php', '<?php final class Foo { public function run() {} }');
        $this->snapshot(16, 2, 'sha256:current');
        $this->git('add .');
        $this->git('commit -m current');
        $currentCommit = $this->git('rev-parse HEAD');

        $pipeline = new MetricsReviewPipeline($this->package, $this->project);
        $pipeline->run('HEAD^', 'HEAD', 'var/metrics-review');
        $output = $this->project . '/var/metrics-review';

        self::assertFileExists($this->package . '/snapshot-check-called');
        self::assertFileExists($output . '/baseline/.coding-standard/metrics/report.json');
        self::assertFileExists($output . '/current/.coding-standard/metrics/report.json');
        self::assertFileExists($output . '/comparison.json');
        self::assertFileExists($output . '/summary.md');
        self::assertFileExists($output . '/reproduction.json');
        $reproduction = $this->json($output . '/reproduction.json');
        self::assertSame($baselineCommit, $reproduction['base_commit']);
        self::assertSame($baselineCommit, $reproduction['baseline_commit']);
        self::assertSame($baselineCommit, $reproduction['merge_base']);
        self::assertSame($currentCommit, $reproduction['current_commit']);
        self::assertTrue($reproduction['working_tree_clean']);
        self::assertContains('src/Foo.php', $reproduction['changed_paths']);
        $comparison = $this->json($output . '/comparison.json');
        self::assertTrue($comparison['scopes']['class']['changed'][0]['changed_area']);
        self::assertSame('regressed', $comparison['scopes']['method']['changed'][0]['metric_changes'][0]['direction']);

        $firstHash = $this->directoryHash($output);
        $pipeline->run('HEAD^', 'HEAD', 'var/metrics-review');
        self::assertSame($firstHash, $this->directoryHash($output));
    }

    public function testRejectsOutputInsideCanonicalSnapshot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not overwrite the canonical snapshot');

        (new MetricsReviewPipeline($this->package, $this->project))->run(
            'HEAD',
            'HEAD',
            '.coding-standard/metrics-review',
        );
    }

    private function snapshot(int $classLoc, int $methodCc, string $inputHash): void
    {
        $root = $this->project . '/.coding-standard/metrics';
        if (!is_dir($root . '/src')) {
            mkdir($root . '/src', 0777, true);
        }
        $this->writeJson($root . '/report.json', [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'project', 'source_path' => '.'],
            'metadata' => [
                'project' => 'example/consumer',
                'metric_definitions_version' => '1.0',
                'configuration_hash' => 'sha256:configuration',
                'input_hash' => $inputHash,
                'source_versions' => ['analyzer' => 'metrics-collector/1.0'],
            ],
            'metrics' => ['project' => ['loc' => $classLoc, 'class_count' => 1]],
            'children' => [['path' => 'src/report.json', 'kind' => 'directory']],
            'findings' => [],
        ]);
        $this->writeJson($root . '/src/report.json', [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'directory', 'source_path' => 'src', 'module' => 'Main'],
            'metrics' => [
                'directory' => ['class_count' => 1],
                'module' => ['id' => 'Main', 'cohesion' => 1.0],
            ],
            'children' => [['path' => 'Foo.php.json', 'kind' => 'file']],
            'findings' => [],
        ]);
        $this->writeJson($root . '/src/Foo.php.json', [
            'schema_version' => '1.0',
            'scope' => ['kind' => 'file', 'source_path' => 'src/Foo.php', 'module' => 'Main'],
            'metrics' => [
                'classes' => [[
                    'id' => 'App\\Foo',
                    'kind' => 'class',
                    'file' => 'src/Foo.php',
                    'module' => 'Main',
                    'loc' => $classLoc,
                ]],
                'methods' => [['id' => 'App\\Foo::run', 'loc' => 5, 'cc' => $methodCc]],
            ],
            'findings' => [],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function git(string $arguments): string
    {
        exec('git -C ' . escapeshellarg($this->project) . " $arguments 2>&1", $output, $code);
        self::assertSame(0, $code, implode("\n", $output));

        return trim(implode("\n", $output));
    }

    private function directoryHash(string $directory): string
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($files);

        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }
}
