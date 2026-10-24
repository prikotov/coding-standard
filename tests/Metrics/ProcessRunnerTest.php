<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\ProcessRunner;

final class ProcessRunnerTest extends TestCase
{
    public function testDrainsStderrLargerThanPipeBufferWhileStdoutStaysOpen(): void
    {
        $this->assertCompletesWithin(60, function (): void {
            [$code, $stdout, $stderr] = (new ProcessRunner())->run(
                [PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("e", 200000)); fwrite(STDOUT, "done");'],
                ['pipe', 'r'],
            );

            self::assertSame(0, $code);
            self::assertSame('done', $stdout);
            self::assertSame(str_repeat('e', 200000), $stderr);
        });
    }

    public function testCapturesStdoutStderrAndExitCode(): void
    {
        [$code, $stdout, $stderr] = (new ProcessRunner())->run(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(3);'],
            ['pipe', 'r'],
        );

        self::assertSame(3, $code);
        self::assertSame('out', $stdout);
        self::assertSame('err', $stderr);
    }

    public function testReturnsNullWhenProcessCannotStart(): void
    {
        self::assertNull((new ProcessRunner())->run(['/nonexistent-binary-for-tests'], ['pipe', 'r']));
    }

    public function testInheritsWorkingDirectoryWhenNullIsPassed(): void
    {
        [$code, $stdout] = (new ProcessRunner())->run(
            [PHP_BINARY, '-r', 'echo getcwd();'],
            ['pipe', 'r'],
        );

        self::assertSame(0, $code);
        self::assertSame(getcwd(), $stdout);
    }

    /**
     * Runs the assertions in a dedicated process killed after the deadline, so a reintroduced
     * sequential pipe read fails fast instead of hanging the whole suite forever.
     *
     * @param callable(): void $assertion
     */
    private function assertCompletesWithin(int $seconds, callable $assertion): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required to bound the test duration.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::markTestSkipped('Cannot fork a child process.');
        }
        if ($pid === 0) {
            try {
                $assertion();
                exit(0);
            } catch (Throwable) {
                exit(1);
            }
        }
        $deadline = microtime(true) + $seconds;
        do {
            $exited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($exited === $pid) {
                if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                    self::fail('Assertions failed in the forked process.');
                }
                // The assertions themselves ran inside the forked child.
                self::addToAssertionCount(1);

                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);
        posix_kill($pid, SIGKILL);
        pcntl_waitpid($pid, $status);
        self::fail(
            'Deadlock: a child blocked on a full pipe because stdout and stderr are not drained concurrently.',
        );
    }
}
