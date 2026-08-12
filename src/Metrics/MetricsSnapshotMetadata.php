<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class MetricsSnapshotMetadata
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $analyzer
     * @param array<string|int, mixed> $scc
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function addFingerprints(
        array $report,
        array $analyzer,
        array $scc,
        array $config,
        string $deptracConfig,
        string $phpunitConfig,
    ): array {
        $composer = $this->jsonFile($this->projectRoot . '/composer.json');
        $configuration = [
            'metrics' => is_array($config['metrics'] ?? null) ? $config['metrics'] : [],
            'composer_autoload' => is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [],
            'deptrac' => $this->fileHash($deptracConfig),
            'phpunit' => $this->fileHash($phpunitConfig),
        ];
        $workDirectory = (string) (($config['metrics']['work_dir'] ?? null) ?: 'var/metrics');
        $inputs = [
            'files' => $this->inputFiles($analyzer, $scc, $workDirectory),
            'metrics' => is_array($report['metrics'] ?? null) ? $report['metrics'] : [],
            'findings' => is_array($report['findings'] ?? null) ? $report['findings'] : [],
        ];
        $metadata = is_array($report['metadata'] ?? null) ? $report['metadata'] : [];
        $project = $this->projectIdentifier($composer);
        if ($project === '') {
            throw new RuntimeException('Cannot determine the metrics snapshot project identifier.');
        }
        $metadata['project'] = $project;
        $metadata['configuration_hash'] = $this->hash($configuration);
        $metadata['input_hash'] = $this->hash($inputs);
        $report['metadata'] = $metadata;

        return $report;
    }

    /**
     * @param array<string, mixed> $analyzer
     * @param array<string|int, mixed> $scc
     * @return array<string, string>
     */
    private function inputFiles(array $analyzer, array $scc, string $workDirectory): array
    {
        $paths = [];
        foreach ($analyzer['classes'] ?? [] as $class) {
            if (is_array($class) && is_string($class['metrics']['filePath'] ?? null)) {
                $paths[$this->relativePath($class['metrics']['filePath'])] = true;
            }
        }
        foreach ($scc as $language) {
            if (!is_array($language)) {
                continue;
            }
            foreach ($language['Files'] ?? [] as $file) {
                if (is_array($file) && is_string($file['Location'] ?? null)) {
                    $paths[$this->relativePath($file['Location'])] = true;
                }
            }
        }

        $excluded = [
            rtrim(str_replace('\\', '/', $workDirectory), '/'),
            MetricsSnapshotManager::SNAPSHOT_PATH,
        ];
        $files = [];
        foreach (array_keys($paths) as $path) {
            if ($path === '' || $this->isExcluded($path, $excluded)) {
                continue;
            }
            $absolute = $this->projectRoot . '/' . $path;
            if (is_file($absolute)) {
                $files[$path] = $this->fileHash($absolute);
            }
        }
        ksort($files);

        return $files;
    }

    /** @param list<string> $excluded */
    private function isExcluded(string $path, array $excluded): bool
    {
        foreach ($excluded as $directory) {
            if ($directory !== '' && ($path === $directory || str_starts_with($path, $directory . '/'))) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/') . '/';
        if (str_starts_with($path, $root)) {
            $path = substr($path, strlen($root));
        }

        return ltrim(preg_replace('#^(?:\./)+#', '', $path) ?? $path, '/');
    }

    /** @return array<string, mixed> */
    private function jsonFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Metrics fingerprint input is missing: $path");
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException("Metrics fingerprint input must be a JSON object: $path");
        }

        return $data;
    }

    private function fileHash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException("Cannot hash metrics input: $path");
        }

        return 'sha256:' . $hash;
    }

    private function hash(mixed $value): string
    {
        $json = json_encode($this->normalize($value), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return 'sha256:' . hash('sha256', $json);
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($this->normalize(...), $value);
        }
        ksort($value);
        foreach ($value as &$item) {
            $item = $this->normalize($item);
        }
        unset($item);

        return $value;
    }

    /** @param array<string, mixed> $composer */
    private function projectIdentifier(array $composer): string
    {
        $name = $composer['name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $remote = $this->gitValue(['config', '--get', 'remote.origin.url']);
        if ($remote !== null) {
            return $this->remoteIdentifier($remote);
        }
        $rootCommits = $this->gitValue(['rev-list', '--max-parents=0', 'HEAD']);
        if ($rootCommits !== null) {
            return 'git-root:sha256:' . hash('sha256', $rootCommits);
        }

        return basename(rtrim(str_replace('\\', '/', $this->projectRoot), '/'));
    }

    /** @param list<string> $arguments */
    private function gitValue(array $arguments): ?string
    {
        $process = proc_open(
            ['git', '-C', $this->projectRoot, ...$arguments],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        if (is_resource($process)) {
            fclose($pipes[0]);
            $value = trim((string) stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            if ($code === 0 && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function remoteIdentifier(string $remote): string
    {
        if (preg_match('#^(?:[^@]+@)?([^:/]+):(?!//)(.+)$#', $remote, $matches) === 1) {
            $host = strtolower($matches[1]);
            $path = $matches[2];
        } else {
            $parts = parse_url($remote);
            $host = is_array($parts) && is_string($parts['host'] ?? null)
                ? strtolower($parts['host'])
                : '';
            $path = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : '';
        }
        $path = preg_replace('#\.git$#', '', trim($path, '/')) ?? $path;
        if ($host !== '' && $path !== '') {
            return "git:$host/$path";
        }

        return 'git:sha256:' . hash('sha256', $remote);
    }
}
