<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsAggregator;
use PrikotovCodingStandard\Metrics\MetricsReportWriter;
use PrikotovCodingStandard\Metrics\MetricsSnapshotMetadata;
use PrikotovCodingStandard\Metrics\ProjectMetricsCollector;

final class MetricsSnapshotReproducibilityTest extends TestCase
{
    public function testSnapshotBeforeCommitEqualsSnapshotAtCleanHead(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-reproducibility-' . uniqid();
        $project = $directory . '/project';
        mkdir($project, 0777, true);
        $config = ['metrics' => [
            'work_dir' => 'var/metrics',
            'module_patterns' => ['src/Module/*'],
        ]];
        $this->write($project . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_THROW_ON_ERROR));
        $this->write($project . '/.coding-standard.php', '<?php return [];');
        $this->write($project . '/depfile.yaml', 'deptrac: {}');
        $this->write($project . '/phpunit.xml', '<phpunit/>');
        $source = $project . '/src/Module/Billing/Service.php';
        $this->write($source, $this->source('return 1;'));
        $this->git($project, 'init');
        $this->git($project, 'config user.email metrics@example.com');
        $this->git($project, 'config user.name Metrics');
        $this->git($project, 'add .');
        $this->git($project, 'commit -m baseline');

        try {
            $this->write($source, $this->source("if (true) {\n            return 2;\n        }\n\n        return 1;"));
            $this->snapshot($project, $directory . '/before', $config);
            $this->git($project, 'add src/Module/Billing/Service.php');
            $this->git($project, 'commit -m change');
            $this->snapshot($project, $directory . '/after', $config);

            self::assertSame(
                $this->directoryHash($directory . '/before'),
                $this->directoryHash($directory . '/after'),
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @param array<string, mixed> $config */
    private function snapshot(string $project, string $output, array $config): void
    {
        $analyzer = (new ProjectMetricsCollector(
            (new ParserFactory())->createForNewestSupportedVersion(),
            $project,
            $config['metrics'],
        ))->collect();
        $scc = [[
            'Name' => 'PHP',
            'Count' => 1,
            'Lines' => 10,
            'Code' => 8,
            'Comment' => 0,
            'Blank' => 2,
            'Files' => [[
                'Location' => 'src/Module/Billing/Service.php',
                'Lines' => 10,
                'Code' => 8,
                'Comment' => 0,
                'Blank' => 2,
            ]],
        ]];
        $report = (new MetricsAggregator())->aggregate(
            $analyzer,
            ['schema_version' => '1.0', 'dependencies' => []],
            $config['metrics'],
            $scc,
            ['suites' => [], 'total' => ['files' => 0, 'lines' => 0, 'average_lines' => null]],
            '<coverage><project><metrics statements="0" coveredstatements="0" methods="0" coveredmethods="0" /></project></coverage>',
            '3.7.0',
        );
        $report = (new MetricsSnapshotMetadata($project))->addFingerprints(
            $report,
            $analyzer,
            $scc,
            $config,
            $project . '/depfile.yaml',
            $project . '/phpunit.xml',
        );
        (new MetricsReportWriter())->writeMirror($output . '/report.json', $report);
    }

    private function source(string $body): string
    {
        return <<<PHP
<?php

namespace App\Module\Billing;

final class Service
{
    public function run(): int
    {
        $body
    }
}
PHP;
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

    private function git(string $directory, string $arguments): void
    {
        exec('git -C ' . escapeshellarg($directory) . " $arguments 2>&1", $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
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
