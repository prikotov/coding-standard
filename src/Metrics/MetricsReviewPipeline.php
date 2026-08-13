<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsReviewPipeline
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly string $projectRoot,
    ) {
    }

    public function run(string $base, string $head, string $output): void
    {
        if (!is_file($this->projectRoot . '/composer.json')) {
            throw new RuntimeException(
                'Run coding-standard-metrics-review from a project root containing composer.json.',
            );
        }
        $output = $this->projectPath($output);
        $baseCommit = $this->commit($base);
        $headCommit = $this->commit($head);
        if ($headCommit !== $this->commit('HEAD')) {
            throw new RuntimeException('--head must resolve to the currently checked out HEAD.');
        }
        $mergeBase = trim($this->git(['merge-base', $baseCommit, $headCommit]));
        $changedPaths = $this->changedPaths($mergeBase, $output);
        $worktree = $this->projectRoot . '/var/metrics-worktree-' . bin2hex(random_bytes(8));

        try {
            $this->buildSnapshot($this->projectRoot);
            $current = $this->snapshotPath($this->projectRoot);
            $this->git(['worktree', 'add', '--detach', $worktree, $mergeBase]);
            $this->copyMetricsConfiguration($worktree);
            $this->installDependencies($worktree);
            $this->buildSnapshot($worktree);
            $baseline = $this->snapshotPath($worktree);
            $comparison = (new MetricsComparison())->compare(
                (new MetricsSnapshotReader())->read($baseline),
                (new MetricsSnapshotReader())->read($current),
                $changedPaths,
            );
            (new MetricsComparisonWriter())->write($output, $comparison);
            $this->writeJson($output . '/reproduction.json', [
                'schema_version' => '1.0',
                'base_ref' => $base,
                'base_commit' => $baseCommit,
                'baseline_commit' => $mergeBase,
                'head_ref' => $head,
                'current_commit' => $headCommit,
                'merge_base' => $mergeBase,
                'changed_paths' => $changedPaths,
            ]);
        } finally {
            if (is_dir($worktree)) {
                $this->git(['worktree', 'remove', '--force', $worktree]);
            }
        }

        fwrite(STDOUT, 'Metrics delta: ' . $this->relativePath($output . '/comparison.json') . "\n");
    }

    private function copyMetricsConfiguration(string $worktree): void
    {
        $source = $this->projectRoot . '/.coding-standard.php';
        $target = $worktree . '/.coding-standard.php';
        if (!copy($source, $target)) {
            throw new RuntimeException('Cannot copy the current metrics configuration to the baseline worktree.');
        }
    }

    private function installDependencies(string $worktree): void
    {
        [$code, $stdout, $stderr] = $this->process(
            ['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress'],
            $worktree,
        );
        if ($code !== 0) {
            throw new RuntimeException('Cannot install baseline dependencies: ' . trim($stderr ?: $stdout));
        }
        $installedPackage = $worktree . '/vendor/prikotov/coding-standard';
        if (is_dir($installedPackage)) {
            (new MetricsSnapshotManager())->removeDirectory($installedPackage);
        }
        $parent = dirname($installedPackage);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new RuntimeException('Cannot create the baseline package directory.');
        }
        if (!symlink($this->packageRoot, $installedPackage)) {
            throw new RuntimeException('Cannot expose the current coding-standard package to the baseline worktree.');
        }
    }

    private function buildSnapshot(string $projectRoot): void
    {
        $process = proc_open(
            [PHP_BINARY, $this->packageRoot . '/bin/coding-standard-metrics'],
            [STDIN, STDOUT, STDERR],
            $pipes,
            $projectRoot,
            [...getenv(), 'CODING_STANDARD_METRICS_PROJECT_ROOT' => $projectRoot],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start metrics snapshot generation.');
        }
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException("Metrics snapshot generation failed with exit code $code.");
        }
    }

    private function snapshotPath(string $projectRoot): string
    {
        $config = require $projectRoot . '/.coding-standard.php';
        $workDirectory = is_array($config) && is_array($config['metrics'] ?? null)
            ? ($config['metrics']['work_dir'] ?? 'var/metrics')
            : 'var/metrics';
        if (!is_string($workDirectory)) {
            throw new RuntimeException('metrics.work_dir must be a string.');
        }

        return $projectRoot . '/' . trim($workDirectory, '/') . '/snapshot.json';
    }

    private function commit(string $revision): string
    {
        $commit = trim($this->git(['rev-parse', '--verify', '--end-of-options', $revision . '^{commit}']));
        if (!preg_match('/^[0-9a-f]{40}$/', $commit)) {
            throw new RuntimeException("Git revision does not resolve to a commit: $revision");
        }

        return $commit;
    }

    /** @return list<string> */
    private function changedPaths(string $mergeBase, string $output): array
    {
        $paths = $this->nullSeparated($this->git(['diff', '--name-only', '-z', $mergeBase, '--']));
        $ignoredOutput = $this->relativePath($output);
        $paths = array_values(array_filter($paths, static fn (string $path): bool => $path !== $ignoredOutput));
        sort($paths);

        return $paths;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        [$code, $stdout, $stderr] = $this->process(['git', '-C', $this->projectRoot, ...$arguments]);
        if ($code !== 0) {
            throw new RuntimeException('Git command failed: ' . trim($stderr ?: $stdout));
        }

        return $stdout;
    }

    /** @param list<string> $command @return array{int, string, string} */
    private function process(array $command, ?string $workingDirectory = null): array
    {
        $process = proc_open(
            $command,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $workingDirectory ?? $this->projectRoot,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start process.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /** @return list<string> */
    private function nullSeparated(string $output): array
    {
        return array_values(array_filter(
            explode("\0", rtrim($output, "\0")),
            static fn (string $path): bool => $path !== '',
        ));
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
            throw new RuntimeException("Cannot create metrics delta directory: " . dirname($path));
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($path, $json . "\n");
    }

    private function projectPath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->projectRoot . '/' . trim($path, '/');
    }

    private function relativePath(string $path): string
    {
        $prefix = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
