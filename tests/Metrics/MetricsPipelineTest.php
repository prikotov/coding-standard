<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\MetricsPipeline;

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
    'report_dir' => 'var/quality',
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

            self::assertFileExists($project . '/var/quality/report.json');
            self::assertFileExists($project . '/var/quality/index.html');
            self::assertSame('<!doctype html>', file_get_contents($project . '/var/quality/index.html'));
        } finally {
            $this->removeDirectory($directory);
        }
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
