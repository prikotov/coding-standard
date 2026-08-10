<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class MetricsSnapshotManager
{
    public const SNAPSHOT_PATH = '.coding-standard/metrics';

    /**
     * @return array{created: list<string>, changed: list<string>, extra: list<string>}
     */
    public function differences(string $candidate, string $snapshot): array
    {
        $expected = $this->files($candidate);
        $actual = is_dir($snapshot) ? $this->files($snapshot) : [];
        $created = [];
        $changed = [];
        $extra = [];

        foreach ($expected as $path => $hash) {
            if (!isset($actual[$path])) {
                $created[] = $path;
            } elseif ($actual[$path] !== $hash) {
                $changed[] = $path;
            }
        }
        foreach ($actual as $path => $_hash) {
            if (!isset($expected[$path])) {
                $extra[] = $path;
            }
        }

        return ['created' => $created, 'changed' => $changed, 'extra' => $extra];
    }

    /**
     * @return array{created: list<string>, changed: list<string>, extra: list<string>}
     */
    public function update(string $candidate, string $snapshot): array
    {
        $differences = $this->differences($candidate, $snapshot);
        if ($differences === ['created' => [], 'changed' => [], 'extra' => []]) {
            return $differences;
        }

        $parent = dirname($snapshot);
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new RuntimeException("Cannot create snapshot directory: $parent");
        }
        $suffix = bin2hex(random_bytes(8));
        $staging = $parent . '/.metrics-staging-' . $suffix;
        $backup = $parent . '/.metrics-backup-' . $suffix;
        $hasSnapshot = is_dir($snapshot);
        try {
            $this->copyDirectory($candidate, $staging);
            if ($hasSnapshot && !rename($snapshot, $backup)) {
                throw new RuntimeException("Cannot prepare metrics snapshot replacement: $snapshot");
            }
            if (!rename($staging, $snapshot)) {
                throw new RuntimeException("Cannot publish metrics snapshot: $snapshot");
            }
            if ($hasSnapshot) {
                $this->removeDirectory($backup);
            }
        } catch (\Throwable $exception) {
            if (is_dir($backup) && !is_dir($snapshot)) {
                rename($backup, $snapshot);
            }
            throw $exception;
        } finally {
            if (is_dir($staging)) {
                $this->removeDirectory($staging);
            }
        }

        return $differences;
    }

    /** @param array{created: list<string>, changed: list<string>, extra: list<string>} $differences */
    public function diagnostic(array $differences): string
    {
        $lines = ['Metrics snapshot is outdated:'];
        foreach (['created' => 'created', 'changed' => 'changed', 'extra' => 'extra'] as $key => $label) {
            foreach ($differences[$key] as $path) {
                $lines[] = sprintf('  %s: %s', $label, $path);
            }
        }

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException("Metrics candidate directory does not exist: $directory");
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException("Cannot read metrics report: {$file->getPathname()}");
            }
            $files[$relative] = $hash;
        }
        ksort($files);

        return $files;
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!mkdir($target, 0777, true) && !is_dir($target)) {
            throw new RuntimeException("Cannot create metrics staging directory: $target");
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
                    throw new RuntimeException("Cannot copy metrics directory: $destination");
                }
            } elseif (!$item->isLink() && !copy($item->getPathname(), $destination)) {
                throw new RuntimeException("Cannot copy metrics report: $destination");
            }
        }
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
