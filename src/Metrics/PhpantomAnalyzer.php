<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use RuntimeException;

final class PhpantomAnalyzer
{
    private readonly string $binary;
    private ?string $version = null;

    public function __construct(?string $binary = null)
    {
        $configured = getenv('PHPANTOM_SERVER_PATH');
        $this->binary = $binary ?? ($configured !== false && $configured !== '' ? $configured : 'phpantom_lsp');
    }

    public function version(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }
        [$code, $stdout, $stderr] = $this->process([$this->binary, '--version'], null);
        $output = trim($stdout);
        if ($code !== 0 || preg_match('/\b\d+\.\d+\.\d+\b/', $output, $match) !== 1) {
            throw new RuntimeException('Cannot determine PHPantom version: ' . trim($stderr ?: $stdout));
        }

        return $this->version = $match[0];
    }

    /** @return array{schema_version: string, tool_version: string, diagnostics: list<array<string, mixed>>} */
    public function analyze(string $projectRoot): array
    {
        $paths = $this->productionPaths($projectRoot);
        [$code, $stdout, $stderr] = $this->process([
            $this->binary,
            'analyze',
            '--project-root',
            '.',
            '--format',
            'json',
            '--no-colour',
            ...$paths,
        ], $projectRoot);
        if (!in_array($code, [0, 1], true)) {
            throw new RuntimeException("PHPantom analysis failed with exit code $code: " . trim($stderr ?: $stdout));
        }
        try {
            $report = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'PHPantom returned invalid JSON: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
        if (!is_array($report) || !is_array($report['files'] ?? null)) {
            throw new RuntimeException('PHPantom JSON report does not contain files.');
        }
        if (($report['errors'] ?? []) !== []) {
            throw new RuntimeException('PHPantom could not analyze the project: ' . json_encode(
                $report['errors'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        }

        $diagnostics = [];
        foreach ($report['files'] as $path => $file) {
            if (!is_string($path) || !is_array($file) || !is_array($file['messages'] ?? null)) {
                throw new RuntimeException('PHPantom JSON report contains an invalid file entry.');
            }
            foreach ($file['messages'] as $message) {
                if (
                    !is_array($message)
                    || !is_int($message['line'] ?? null)
                    || !is_string($message['severity'] ?? null)
                    || !is_string($message['identifier'] ?? null)
                    || !is_string($message['message'] ?? null)
                ) {
                    throw new RuntimeException('PHPantom JSON report contains an invalid diagnostic.');
                }
                $diagnostics[] = [
                    'path' => $this->relativePath($projectRoot, $path),
                    'line' => $message['line'],
                    'severity' => $message['severity'],
                    'identifier' => $message['identifier'],
                    'message' => $message['message'],
                ];
            }
        }
        usort($diagnostics, self::compareDiagnostics(...));

        return [
            'schema_version' => '1.0',
            'tool_version' => $this->version(),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return list<string> */
    private function productionPaths(string $projectRoot): array
    {
        $composer = json_decode(
            (string) file_get_contents($projectRoot . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $autoload = $composer['autoload']['psr-4'] ?? null;
        if (!is_array($autoload)) {
            throw new RuntimeException('composer.json must contain production autoload.psr-4 roots for PHPantom.');
        }
        $config = require $projectRoot . '/.coding-standard.php';
        $exclude = is_array($config) && is_array($config['metrics']['exclude'] ?? null)
            ? $config['metrics']['exclude']
            : [];
        $paths = [];
        foreach ($autoload as $sources) {
            foreach (is_array($sources) ? $sources : [$sources] as $source) {
                if (!is_string($source) || $source === '') {
                    continue;
                }
                $path = trim(str_replace('\\', '/', $source), '/');
                if ($path === '' || !is_dir($projectRoot . '/' . $path) || $this->excluded($path, $exclude)) {
                    continue;
                }
                $paths[$path] = true;
            }
        }
        $paths = array_keys($paths);
        if ($paths === []) {
            throw new RuntimeException('No existing production autoload.psr-4 directories were found for PHPantom.');
        }
        sort($paths);

        return $paths;
    }

    /** @param array<mixed> $exclude */
    private function excluded(string $path, array $exclude): bool
    {
        if (str_starts_with($path, 'packages/')) {
            return true;
        }
        foreach ($exclude as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }
            $item = trim(str_replace('\\', '/', $item), '/');
            if ($path === $item || str_starts_with($path, $item . '/')) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $projectRoot, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : ltrim($path, '/');
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private static function compareDiagnostics(array $left, array $right): int
    {
        return [$left['path'], $left['line'], $left['severity'], $left['identifier'], $left['message']]
            <=> [$right['path'], $right['line'], $right['severity'], $right['identifier'], $right['message']];
    }

    /** @param list<string> $command @return array{int, string, string} */
    private function process(array $command, ?string $projectRoot): array
    {
        $result = (new ProcessRunner())->run($command, ['pipe', 'r'], $projectRoot);
        if ($result === null) {
            throw new RuntimeException('Cannot start PHPantom. Install phpantom_lsp or set PHPANTOM_SERVER_PATH.');
        }

        return $result;
    }
}
