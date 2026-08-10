<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsPipeline;
use RuntimeException;

final class MetricsPipelineTest extends TestCase
{
    public function testRunsAllStagesFromConsumerProjectRoot(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-pipeline-' . uniqid();
        $package = $directory . '/package';
        $project = $directory . '/project';
        $this->write($project . '/composer.json', '{}');
        $this->write($project . '/.coding-standard.php', <<<'PHP'
<?php
return ['metrics' => [
    'work_dir' => 'var/quality',
    'deptrac_config' => 'config/deptrac.yaml',
    'phpunit_config' => 'phpunit.xml.dist',
]];
PHP);
        $this->write($project . '/config/deptrac.yaml', 'deptrac: {}');
        $this->write($project . '/phpunit.xml.dist', '<phpunit/>');
        $this->executable($project . '/vendor/bin/phpunit', "#!/bin/sh\nexit 0\n");
        $this->executable($project . '/vendor/bin/deptrac', $this->phpStub(<<<'PHP'
$output = argument('--output=');
writeFile($output, json_encode(['schema_version' => '1.0', 'dependencies' => []]));
exit(1);
PHP));
        $this->write($package . '/bin/metrics-collect', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode(['classes' => [], 'functions' => []]));
PHP));
        $this->executable($package . '/bin/metrics-scc', $this->phpStub(<<<'PHP'
writeFile($argv[1], '[]');
writeFile(dirname($argv[1]) . '/scc-version.txt', '3.7.0');
PHP));
        $this->write($package . '/bin/test-stats', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode(['suites' => [], 'total' => []]));
PHP));
        $this->executable($package . '/bin/metrics-coverage', $this->phpStub(<<<'PHP'
writeFile($argv[1], '<coverage/>');
PHP));
        $this->write($package . '/bin/metrics-aggregate.php', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode([
    'schema_version' => '1.0',
    'scope' => ['kind' => 'project'],
    'metrics' => ['project' => []],
]));
PHP));
        $this->write($package . '/bin/metrics-dashboard.php', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), '<!doctype html>');
PHP));

        try {
            (new MetricsPipeline($package, $project))->run();

            self::assertFileExists($project . '/.coding-standard/metrics/report.json');
            self::assertFileExists($project . '/var/quality/index.html');
            self::assertSame('<!doctype html>', file_get_contents($project . '/var/quality/index.html'));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testChecksAdditionModificationMoveDeletionAndManualSnapshotEdit(): void
    {
        [$directory, $package, $project] = $this->sourceMirrorPipeline();
        $pipeline = new MetricsPipeline($package, $project);
        $source = $project . '/src/Module/Billing/Example.php';
        $this->write($source, "<?php\nfinal class Example {}\n");

        try {
            $pipeline->run();
            $snapshotFile = $project . '/.coding-standard/metrics/src/Module/Billing/Example.php.json';
            self::assertFileExists($snapshotFile);
            $snapshotHash = $this->directoryHash($project . '/.coding-standard/metrics');
            $pipeline->run(MetricsPipeline::MODE_CHECK);
            self::assertSame($snapshotHash, $this->directoryHash($project . '/.coding-standard/metrics'));

            file_put_contents($snapshotFile, "manual edit\n");
            $this->assertOutdated($pipeline, 'changed: src/Module/Billing/Example.php.json');
            $pipeline->run();

            $added = $project . '/src/Module/Billing/Added.php';
            $this->write($added, "<?php\nfinal class Added {}\n");
            $this->assertOutdated($pipeline, 'created: src/Module/Billing/Added.php.json');
            $pipeline->run();
            $unrelatedHash = hash_file('sha256', $snapshotFile);

            file_put_contents($added, "<?php\nfinal class Added { public int \$value = 1; }\n");
            $this->assertOutdated($pipeline, 'changed: src/Module/Billing/Added.php.json');
            $pipeline->run();
            self::assertSame($unrelatedHash, hash_file('sha256', $snapshotFile));

            $moved = $project . '/src/Module/Billing/Moved.php';
            rename($added, $moved);
            $this->assertOutdated($pipeline, 'created: src/Module/Billing/Moved.php.json');
            $this->assertOutdated($pipeline, 'extra: src/Module/Billing/Added.php.json');
            $pipeline->run();

            unlink($moved);
            $this->assertOutdated($pipeline, 'extra: src/Module/Billing/Moved.php.json');
            $pipeline->run();
            self::assertFileDoesNotExist(
                $project . '/.coding-standard/metrics/src/Module/Billing/Moved.php.json',
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testRejectsLegacyReportDirectoryConfiguration(): void
    {
        $directory = sys_get_temp_dir() . '/metrics-pipeline-legacy-' . uniqid();
        $this->write($directory . '/composer.json', '{}');
        $this->write($directory . '/.coding-standard.php', "<?php return ['metrics' => ['report_dir' => 'var/metrics']];");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('rename it to metrics.work_dir');
            (new MetricsPipeline($directory, $directory))->run();
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @return array{string, string, string} */
    private function sourceMirrorPipeline(): array
    {
        $directory = sys_get_temp_dir() . '/metrics-pipeline-mirror-' . uniqid();
        $package = $directory . '/package';
        $project = $directory . '/project';
        $this->write($project . '/composer.json', '{}');
        $this->write($project . '/.coding-standard.php', <<<'PHP'
<?php
return ['metrics' => [
    'work_dir' => 'var/metrics',
    'deptrac_config' => 'depfile.yaml',
    'phpunit_config' => 'phpunit.xml',
]];
PHP);
        $this->write($project . '/depfile.yaml', 'deptrac: {}');
        $this->write($project . '/phpunit.xml', '<phpunit/>');
        $this->executable($project . '/vendor/bin/phpunit', "#!/bin/sh\nexit 0\n");
        $this->executable($project . '/vendor/bin/deptrac', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode(['schema_version' => '1.0', 'dependencies' => []]));
PHP));
        $this->write($package . '/bin/metrics-collect', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode(['classes' => [], 'functions' => []]));
PHP));
        $this->executable($package . '/bin/metrics-scc', $this->phpStub(<<<'PHP'
writeFile($argv[1], '[]');
writeFile(dirname($argv[1]) . '/scc-version.txt', '3.7.0');
PHP));
        $this->write($package . '/bin/test-stats', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), json_encode(['suites' => [], 'total' => []]));
PHP));
        $this->executable($package . '/bin/metrics-coverage', $this->phpStub(<<<'PHP'
writeFile($argv[1], '<coverage/>');
PHP));
        $this->write($package . '/bin/metrics-aggregate.php', $this->phpStub(<<<'PHP'
$project = argument('--project-root=');
$output = argument('--output=');
$reports = dirname($output);
$source = $project . '/src';
$files = [];
if (is_dir($source)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($project) + 1));
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
}
ksort($files);
foreach ($files as $relative => $hash) {
    writeFile($reports . '/' . $relative . '.json', json_encode(['path' => $relative, 'hash' => $hash]));
}
writeFile($output, json_encode(['schema_version' => '1.0', 'files' => $files]));
PHP));
        $this->write($package . '/bin/metrics-dashboard.php', $this->phpStub(<<<'PHP'
writeFile(argument('--output='), '<!doctype html>');
PHP));

        return [$directory, $package, $project];
    }

    private function assertOutdated(MetricsPipeline $pipeline, string $expected): void
    {
        try {
            $pipeline->run(MetricsPipeline::MODE_CHECK);
            self::fail('Snapshot check was expected to fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($expected, $exception->getMessage());
        }
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

    private function phpStub(string $body): string
    {
        return <<<PHP
#!/usr/bin/env php
<?php
function argument(string \$prefix): string {
    global \$argv;
    foreach (\$argv as \$argument) {
        if (str_starts_with(\$argument, \$prefix)) {
            return substr(\$argument, strlen(\$prefix));
        }
    }
    throw new RuntimeException("Missing argument: \$prefix");
}
function writeFile(string \$path, string \$contents): void {
    if (!is_dir(dirname(\$path))) {
        mkdir(dirname(\$path), 0777, true);
    }
    file_put_contents(\$path, \$contents);
}
$body
PHP;
    }

    private function executable(string $path, string $contents): void
    {
        $this->write($path, $contents);
        chmod($path, 0755);
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
