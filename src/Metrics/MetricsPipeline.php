<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsPipeline
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly string $projectRoot,
    ) {
    }

    public function run(): void
    {
        $previousDirectory = getcwd();
        if (!is_file($this->projectRoot . '/composer.json')) {
            throw new RuntimeException('Run coding-standard-metrics from a project root containing composer.json.');
        }
        $configPath = $this->projectRoot . '/.coding-standard.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Project configuration is missing: .coding-standard.php');
        }
        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Project configuration must return an array: .coding-standard.php');
        }
        $metrics = is_array($config['metrics'] ?? null) ? $config['metrics'] : [];
        $reportDirectory = $this->projectPath((string) ($metrics['report_dir'] ?? 'var/metrics'));
        $deptracConfig = $this->configurationPath(
            $metrics['deptrac_config'] ?? null,
            ['deptrac.yaml', 'depfile.yaml'],
            'Deptrac',
        );
        $phpunitConfig = $this->configurationPath(
            $metrics['phpunit_config'] ?? null,
            ['phpunit.xml', 'phpunit.xml.dist'],
            'PHPUnit',
        );
        $vendorBin = $this->projectRoot . '/vendor/bin';
        $deptrac = $vendorBin . '/deptrac';
        $phpunit = $vendorBin . '/phpunit';
        foreach ([$deptrac => 'Deptrac', $phpunit => 'PHPUnit'] as $binary => $name) {
            if (!is_file($binary)) {
                throw new RuntimeException("$name is not installed in the project: $binary");
            }
        }

        if (!chdir($this->projectRoot)) {
            throw new RuntimeException("Cannot enter project directory: $this->projectRoot");
        }

        try {
            $this->stage('Structural metrics', [
                PHP_BINARY,
                $this->packageRoot . '/bin/metrics-collect',
                '--project-root=' . $this->projectRoot,
                '--config=' . $configPath,
                '--output=' . $reportDirectory . '/collector.json',
            ]);

            $deptracOutput = $reportDirectory . '/deptrac.json';
            if (is_file($deptracOutput) && !unlink($deptracOutput)) {
                throw new RuntimeException("Cannot replace dependency graph report: $deptracOutput");
            }
            $deptracCode = $this->command([
                $deptrac,
                '--config-file=' . $deptracConfig,
                '--formatter=metrics-json',
                '--output=' . $deptracOutput,
            ], 'Dependency graph');
            if ($deptracCode !== 0 && !$this->validDeptracReport($deptracOutput)) {
                throw new RuntimeException("Dependency graph collection failed with exit code $deptracCode.");
            }
            if ($deptracCode !== 0) {
                fwrite(STDOUT, "Dependency graph contains Deptrac violations; metrics collection continues.\n");
            }

            $this->stage('Codebase size', [
                $this->packageRoot . '/bin/metrics-scc',
                $reportDirectory . '/scc.json',
            ]);
            $this->stage('Test statistics', [
                PHP_BINARY,
                $this->packageRoot . '/bin/test-stats',
                '--configuration=' . $phpunitConfig,
                '--format=json',
                '--output=' . $reportDirectory . '/test-stats.json',
            ]);
            $this->stage('Test coverage', [
                $this->packageRoot . '/bin/metrics-coverage',
                $reportDirectory . '/clover.xml',
                $phpunitConfig,
            ]);
            $this->stage('Report aggregation', [
                PHP_BINARY,
                $this->packageRoot . '/bin/metrics-aggregate.php',
                '--config=' . $configPath,
                '--analyzer=' . $reportDirectory . '/collector.json',
                '--deptrac=' . $deptracOutput,
                '--scc=' . $reportDirectory . '/scc.json',
                '--scc-version=' . $reportDirectory . '/scc-version.txt',
                '--tests=' . $reportDirectory . '/test-stats.json',
                '--clover=' . $reportDirectory . '/clover.xml',
                '--output=' . $reportDirectory . '/report.json',
            ]);
            $this->stage('HTML dashboard', [
                PHP_BINARY,
                $this->packageRoot . '/bin/metrics-dashboard.php',
                '--input=' . $reportDirectory . '/report.json',
                '--output=' . $reportDirectory . '/index.html',
            ]);

            fwrite(STDOUT, "Metrics report: " . $this->relativePath($reportDirectory . '/report.json') . "\n");
            fwrite(STDOUT, "Metrics dashboard: " . $this->relativePath($reportDirectory . '/index.html') . "\n");
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    }

    /** @param list<string> $command */
    private function stage(string $name, array $command): void
    {
        $code = $this->command($command, $name);
        if ($code !== 0) {
            throw new RuntimeException("$name failed with exit code $code.");
        }
    }

    /** @param list<string> $command */
    private function command(array $command, string $name): int
    {
        fwrite(STDOUT, "\n==> $name\n");
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            throw new RuntimeException("Cannot start stage: $name");
        }

        return proc_close($process);
    }

    /** @param list<string> $fallbacks */
    private function configurationPath(mixed $configured, array $fallbacks, string $name): string
    {
        $candidates = is_string($configured) && $configured !== '' ? [$configured] : $fallbacks;
        foreach ($candidates as $candidate) {
            $path = $this->projectPath($candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("$name configuration was not found. Checked: " . implode(', ', $candidates));
    }

    private function projectPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    private function validDeptracReport(string $path): bool
    {
        if (!is_file($path) || filesize($path) === 0) {
            return false;
        }
        try {
            $report = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($report)
            && ($report['schema_version'] ?? null) === '1.0'
            && is_array($report['dependencies'] ?? null);
    }

    private function relativePath(string $path): string
    {
        $prefix = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
