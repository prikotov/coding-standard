<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsPipeline
{
    public const MODE_UPDATE = 'update';
    public const MODE_CHECK = 'check';

    public function __construct(
        private readonly string $packageRoot,
        private readonly string $projectRoot,
    ) {
    }

    public function run(string $mode = self::MODE_UPDATE): void
    {
        $previousDirectory = getcwd();
        if (!in_array($mode, [self::MODE_UPDATE, self::MODE_CHECK], true)) {
            throw new RuntimeException("Unsupported metrics snapshot mode: $mode");
        }
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
        if (array_key_exists('report_dir', $metrics)) {
            throw new RuntimeException(
                'metrics.report_dir is no longer supported; rename it to metrics.work_dir. '
                . 'Use metrics.work_dir for local metrics files.',
            );
        }
        if (array_key_exists('snapshot_dir', $metrics)) {
            throw new RuntimeException(
                'metrics.snapshot_dir is no longer supported; the snapshot is always stored in metrics.work_dir.',
            );
        }
        $workDirectorySetting = $metrics['work_dir'] ?? 'var/metrics';
        if (!is_string($workDirectorySetting)) {
            throw new RuntimeException('metrics.work_dir must be a relative project path.');
        }
        $workDirectorySetting = $this->relativeDirectory($workDirectorySetting, 'metrics.work_dir');
        $workDirectory = $this->projectPath($workDirectorySetting);
        $snapshot = $workDirectory . '/snapshot.json';
        $candidateSnapshot = $workDirectory . '/.snapshot-' . bin2hex(random_bytes(8)) . '.json';
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
        $phpunitSuite = $metrics['phpunit_suite'] ?? 'unit';
        if (!is_string($phpunitSuite) || $phpunitSuite === '') {
            throw new RuntimeException('metrics.phpunit_suite must be a non-empty PHPUnit suite name.');
        }
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
                '--output=' . $workDirectory . '/collector.json',
            ]);

            $deptracOutput = $workDirectory . '/deptrac.json';
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
                $workDirectory . '/scc.json',
                $workDirectorySetting,
            ]);
            $this->stage('Test statistics', [
                PHP_BINARY,
                $this->packageRoot . '/bin/test-stats',
                '--configuration=' . $phpunitConfig,
                '--format=json',
                '--output=' . $workDirectory . '/test-stats.json',
            ]);
            $this->stage('Test coverage', [
                $this->packageRoot . '/bin/metrics-coverage',
                $workDirectory . '/clover.xml',
                $phpunitConfig,
                $phpunitSuite,
            ]);
            $this->stage('Report aggregation', [
                PHP_BINARY,
                $this->packageRoot . '/bin/metrics-aggregate.php',
                '--project-root=' . $this->projectRoot,
                '--config=' . $configPath,
                '--deptrac-config=' . $deptracConfig,
                '--phpunit-config=' . $phpunitConfig,
                '--analyzer=' . $workDirectory . '/collector.json',
                '--deptrac=' . $deptracOutput,
                '--scc=' . $workDirectory . '/scc.json',
                '--scc-version=' . $workDirectory . '/scc-version.txt',
                '--tests=' . $workDirectory . '/test-stats.json',
                '--clover=' . $workDirectory . '/clover.xml',
                '--output=' . $candidateSnapshot,
            ]);

            $currentHash = is_file($snapshot) ? hash_file('sha256', $snapshot) : null;
            $candidateHash = hash_file('sha256', $candidateSnapshot);
            if ($mode === self::MODE_CHECK) {
                if ($currentHash !== $candidateHash) {
                    throw new RuntimeException(
                        "Metrics snapshot is outdated: $workDirectorySetting/snapshot.json"
                        . "\nRun vendor/bin/coding-standard-metrics --update-snapshot.",
                    );
                }
                fwrite(STDOUT, "Metrics snapshot is current: $workDirectorySetting/snapshot.json\n");

                return;
            }

            if (!is_dir(dirname($snapshot)) && !mkdir(dirname($snapshot), 0777, true) && !is_dir(dirname($snapshot))) {
                throw new RuntimeException('Cannot create metrics work directory.');
            }
            if (!rename($candidateSnapshot, $snapshot)) {
                throw new RuntimeException("Cannot publish metrics snapshot: $snapshot");
            }
            $this->stage('HTML dashboard', [
                PHP_BINARY,
                $this->packageRoot . '/bin/metrics-dashboard.php',
                '--input=' . $snapshot,
                '--output=' . $workDirectory . '/index.html',
            ]);

            fwrite(STDOUT, "Metrics snapshot: " . $this->relativePath($snapshot) . "\n");
            fwrite(STDOUT, "Metrics dashboard: " . $this->relativePath($workDirectory . '/index.html') . "\n");
        } finally {
            if (isset($candidateSnapshot) && is_file($candidateSnapshot)) {
                unlink($candidateSnapshot);
            }
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

    private function relativeDirectory(string $path, string $name): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            throw new RuntimeException("$name must be a non-empty relative project path.");
        }

        return rtrim($path, '/');
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
