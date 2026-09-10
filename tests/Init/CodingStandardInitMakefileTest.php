<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Init;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\DirectoryRemover;

final class CodingStandardInitMakefileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/coding-standard-init-makefile-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testWiresCheckCommandsIntoExistingCheckTarget(): void
    {
        $makefile = $this->directory . '/Makefile';
        $original = "# comment\n\n.PHONY: check\ncheck: ## Run all checks\n\t@echo running\n\n.PHONY: other\nother:\n\t@echo other\n";
        file_put_contents($makefile, $original);

        $output = $this->runInit();

        $updated = (string) file_get_contents($makefile);
        self::assertStringContainsString(
            "check: ## Run all checks\n\tvendor/bin/coding-standard-verify\n\tvendor/bin/coding-standard-di-check",
            $updated,
        );
        self::assertStringContainsString("\t@echo running", $updated);
        self::assertStringContainsString('.PHONY: other', $updated);
    }

    public function testDoesNotDuplicateVerifyWhenAlreadyWired(): void
    {
        $makefile = $this->directory . '/Makefile';
        $original = "check:\n\tvendor/bin/coding-standard-verify\n";
        file_put_contents($makefile, $original);

        $output = $this->runInit();

        $updated = (string) file_get_contents($makefile);
        self::assertSame(1, substr_count($updated, 'coding-standard-verify'));
        self::assertStringContainsString("\tvendor/bin/coding-standard-di-check\n", $updated);
    }

    public function testSkipsWhenBothCheckCommandsAreAlreadyWired(): void
    {
        $makefile = $this->directory . '/Makefile';
        $original = "check:\n\tvendor/bin/coding-standard-verify\n\tvendor/bin/coding-standard-di-check\n";
        file_put_contents($makefile, $original);

        $output = $this->runInit();

        self::assertStringContainsString('already wired', $output);
        self::assertSame($original, (string) file_get_contents($makefile));
    }

    public function testLeavesMakefileWithoutCheckTargetUntouched(): void
    {
        $makefile = $this->directory . '/Makefile';
        $original = "build:\n\t@echo build\n";
        file_put_contents($makefile, $original);

        $output = $this->runInit();

        self::assertStringContainsString('no `check` target found', $output);
        self::assertSame($original, (string) file_get_contents($makefile));
    }

    public function testSuggestsManualWiringWhenMakefileIsMissing(): void
    {
        $output = $this->runInit();

        self::assertStringContainsString('coding-standard-verify', $output);
        self::assertStringContainsString('coding-standard-di-check', $output);
        self::assertStringContainsString('make check', $output);
    }

    public function testCopiesConcurrencyConventionAndRefreshesItsLinks(): void
    {
        $relativePaths = [
            'architecture/concurrency-control.md',
            'architecture/index.md',
            'architecture/events/transactions.md',
            'index.md',
        ];
        $sourceDocs = dirname(__DIR__, 2) . '/docs/conventions';
        $targetDocs = $this->directory . '/docs/conventions';

        $this->runInit();

        foreach ($relativePaths as $path) {
            self::assertFileEquals($sourceDocs . '/' . $path, $targetDocs . '/' . $path);
        }
        self::assertStringContainsString(
            '(concurrency-control.md)',
            (string) file_get_contents($targetDocs . '/architecture/index.md'),
        );
        self::assertStringContainsString(
            '(architecture/concurrency-control.md)',
            (string) file_get_contents($targetDocs . '/index.md'),
        );
        self::assertStringContainsString(
            '(../concurrency-control.md)',
            (string) file_get_contents($targetDocs . '/architecture/events/transactions.md'),
        );

        unlink($targetDocs . '/architecture/concurrency-control.md');
        file_put_contents($targetDocs . '/architecture/index.md', '# Old index');
        file_put_contents($targetDocs . '/index.md', '# Old index');
        $this->runInit(['--force']);
        $this->runInit(['--force']);

        foreach ($relativePaths as $path) {
            self::assertFileEquals($sourceDocs . '/' . $path, $targetDocs . '/' . $path);
        }
    }

    /** @param list<string> $arguments */
    private function runInit(array $arguments = []): string
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/bin/coding-standard-init',
            '--no-deptrac',
            '--no-exceptions',
            ...$arguments,
        ];
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->directory);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output . $error);

        return $output . $error;
    }

    private function removeDirectory(string $directory): void
    {
        (new DirectoryRemover())->remove($directory);
    }
}
