<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        $output = $this->reviewOutputPath($output);
        $snapshot = $this->projectRoot . '/' . MetricsSnapshotManager::SNAPSHOT_PATH;
        $this->checkSnapshot();
        (new MetricsSnapshotReader())->read($snapshot);

        $baseCommit = $this->commit($base);
        $headCommit = $this->commit($head);
        $checkedOutCommit = $this->commit('HEAD');
        if ($headCommit !== $checkedOutCommit) {
            throw new RuntimeException('--head must resolve to the currently checked out HEAD.');
        }
        $mergeBase = trim($this->git(['merge-base', $baseCommit, $headCommit]));
        if (!preg_match('/^[0-9a-f]{40}$/', $mergeBase)) {
            throw new RuntimeException('Cannot determine a unique merge-base for metrics review.');
        }
        $changedPaths = $this->changedPaths($mergeBase, $output);
        $workingTreeClean = $this->workingTreeClean($output);
        $parent = dirname($output);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new RuntimeException("Cannot create metrics review parent directory: $parent");
        }
        $staging = $parent . '/.metrics-review-staging-' . bin2hex(random_bytes(8));
        $baseline = $staging . '/baseline/' . MetricsSnapshotManager::SNAPSHOT_PATH;
        $current = $staging . '/current/' . MetricsSnapshotManager::SNAPSHOT_PATH;

        try {
            $this->extractSnapshot($mergeBase, $baseline);
            $this->copySnapshot($snapshot, $current);
            $reader = new MetricsSnapshotReader();
            $baselineData = $reader->read($baseline);
            $currentData = $reader->read($current);
            $comparison = (new MetricsComparison())->compare($baselineData, $currentData, $changedPaths);
            (new MetricsComparisonWriter())->write($staging, $comparison);
            $this->writeJson($staging . '/reproduction.json', [
                'schema_version' => '1.0',
                'base_ref' => $base,
                'base_commit' => $baseCommit,
                'baseline_commit' => $mergeBase,
                'head_ref' => $head,
                'current_commit' => $headCommit,
                'merge_base' => $mergeBase,
                'working_tree_clean' => $workingTreeClean,
                'baseline_input_hash' => $baselineData['metadata']['input_hash'],
                'current_input_hash' => $currentData['metadata']['input_hash'],
                'changed_paths' => $changedPaths,
            ]);

            $snapshots = new MetricsSnapshotManager();
            if (is_dir($output)) {
                $snapshots->removeDirectory($output);
            } elseif (file_exists($output)) {
                throw new RuntimeException("Metrics review output exists and is not a directory: $output");
            }
            if (!rename($staging, $output)) {
                throw new RuntimeException("Cannot publish metrics review artifact: $output");
            }
        } finally {
            if (is_dir($staging)) {
                (new MetricsSnapshotManager())->removeDirectory($staging);
            }
        }

        fwrite(STDOUT, "Metrics review artifact: " . $this->relativePath($output) . "\n");
    }

    private function checkSnapshot(): void
    {
        $command = [PHP_BINARY, $this->packageRoot . '/bin/coding-standard-metrics', '--check-snapshot'];
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the current metrics snapshot check.');
        }
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException("Current metrics snapshot check failed with exit code $code.");
        }
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
        $paths = [...$paths, ...$this->nullSeparated($this->git([
            'ls-files',
            '--others',
            '--exclude-standard',
            '-z',
        ]))];
        $ignoredOutput = $this->projectRelativePath($output);
        $unique = [];
        foreach ($paths as $path) {
            $path = str_replace('\\', '/', $path);
            if (
                $path === ''
                || ($ignoredOutput !== null
                    && ($path === $ignoredOutput || str_starts_with($path, $ignoredOutput . '/')))
            ) {
                continue;
            }
            $unique[$path] = true;
        }
        $paths = array_keys($unique);
        sort($paths);

        return $paths;
    }

    private function extractSnapshot(string $commit, string $destination): void
    {
        $prefix = MetricsSnapshotManager::SNAPSHOT_PATH . '/';
        $files = $this->nullSeparated($this->git([
            'ls-tree',
            '-r',
            '-z',
            '--name-only',
            $commit,
            '--',
            MetricsSnapshotManager::SNAPSHOT_PATH,
        ]));
        $files = array_values(array_filter($files, $this->canonicalReport(...)));
        sort($files);
        if (!in_array($prefix . 'report.json', $files, true)) {
            throw new RuntimeException("Baseline commit has no canonical metrics snapshot: $commit");
        }
        foreach ($files as $file) {
            if (!str_starts_with($file, $prefix)) {
                throw new RuntimeException("Baseline metrics path is outside the canonical snapshot: $file");
            }
            $relative = substr($file, strlen($prefix));
            $target = $destination . '/' . $relative;
            $this->ensureDirectory(dirname($target));
            $this->put($target, $this->git(['show', $commit . ':' . $file]));
        }
    }

    private function copySnapshot(string $source, string $destination): void
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            if ($this->canonicalReport($relative)) {
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files);
        foreach ($files as $relative => $path) {
            $target = $destination . '/' . $relative;
            $this->ensureDirectory(dirname($target));
            if (!copy($path, $target)) {
                throw new RuntimeException("Cannot copy current metrics report: $relative");
            }
        }
    }

    private function canonicalReport(string $path): bool
    {
        return basename($path) === 'report.json' || str_ends_with($path, '.php.json');
    }

    private function workingTreeClean(string $output): bool
    {
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])) !== '') {
            return false;
        }
        $ignoredOutput = $this->projectRelativePath($output);
        $untracked = $this->nullSeparated($this->git(['ls-files', '--others', '--exclude-standard', '-z']));
        foreach ($untracked as $path) {
            if (
                $ignoredOutput === null
                || ($path !== $ignoredOutput && !str_starts_with($path, $ignoredOutput . '/'))
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        [$code, $stdout, $stderr] = $this->process(['git', '-C', $this->projectRoot, ...$arguments]);
        if ($code !== 0) {
            $diagnostic = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new RuntimeException('Git command failed: ' . $diagnostic);
        }

        return $stdout;
    }

    /** @param list<string> $command @return array{int, string, string} */
    private function process(array $command): array
    {
        $process = proc_open(
            $command,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $this->projectRoot,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start metrics review command.');
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /** @return list<string> */
    private function nullSeparated(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(
            explode("\0", rtrim($output, "\0")),
            static fn (string $path): bool => $path !== '',
        ));
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $this->put($path, $json . "\n");
    }

    private function put(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Cannot write metrics review artifact: $path");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create metrics review directory: $directory");
        }
    }

    private function projectRelativePath(string $path): ?string
    {
        $absolute = str_starts_with($path, '/') ? $path : $this->projectRoot . '/' . $path;
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/') . '/';
        $absolute = str_replace('\\', '/', $absolute);

        return str_starts_with($absolute, $root) ? rtrim(substr($absolute, strlen($root)), '/') : null;
    }

    private function reviewOutputPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
        $segments = explode('/', $path);
        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('#^[A-Za-z]:/#', $path) === 1
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
        ) {
            throw new RuntimeException('Metrics review output must be a safe relative project path.');
        }
        if ($path === '.coding-standard' || str_starts_with($path, '.coding-standard/')) {
            throw new RuntimeException('Metrics review output must not overwrite the canonical snapshot.');
        }

        return $this->projectRoot . '/' . $path;
    }

    private function relativePath(string $path): string
    {
        return $this->projectRelativePath($path) ?? $path;
    }
}
