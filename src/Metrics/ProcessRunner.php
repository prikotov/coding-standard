<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

/**
 * Runs a child process with piped stdout and stderr, reading both pipes concurrently.
 *
 * Sequential pipe reads deadlock when a child fills the buffer of the unread pipe
 * (typically ~64 KiB) and blocks on writing into it. Both pipes are drained via
 * stream_select until EOF, mirroring the approach of Symfony Process.
 */
final class ProcessRunner
{
    /**
     * @param list<string> $command
     * @param array{0: string, 1?: string, 2?: string} $stdin descriptor specification for standard input,
     *        for example ['pipe', 'r'] or ['file', '/dev/null', 'r']
     * @param string|null $workingDirectory null inherits the current working directory
     * @return array{int, string, string}|null exit code, stdout and stderr; null when the process cannot start
     */
    public function run(array $command, array $stdin, ?string $workingDirectory = null): ?array
    {
        $process = @proc_open(
            $command,
            [$stdin, ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $workingDirectory,
        );        if (!is_resource($process)) {
            return null;
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        [$stdout, $stderr] = $this->drain($pipes[1], $pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     * @return array{string, string}
     */
    private function drain($stdout, $stderr): array
    {
        $open = [$stdout, $stderr];
        $output = ['', ''];
        while ($open !== []) {
            $read = $open;
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, null) === false) {
                break;
            }
            foreach ($read as $stream) {
                $index = $stream === $stdout ? 0 : 1;
                $chunk = stream_get_contents($stream);
                $output[$index] .= $chunk === false ? '' : $chunk;
                if (feof($stream)) {
                    fclose($stream);
                    $open = array_values(array_filter(
                        $open,
                        static fn ($pipe): bool => $pipe !== $stream,
                    ));
                }
            }
        }

        return $output;
    }
}
