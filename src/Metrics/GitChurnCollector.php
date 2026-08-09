<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

final class GitChurnCollector
{
    /** @param list<string> $files @return array<string, int>|null */
    public function collect(string $projectRoot, array $files): ?array
    {
        $process = proc_open(
            ['git', '-C', $projectRoot, '-c', 'core.quotepath=false', 'log', '--format=commit:%H', '--name-only'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        if (!is_resource($process)) {
            return null;
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $output === false || $error === false) {
            return null;
        }

        $tracked = array_fill_keys($files, true);
        $churn = array_fill_keys($files, 0);
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $path = trim(str_replace('\\', '/', $line));
            if ($path !== '' && !str_starts_with($path, 'commit:') && isset($tracked[$path])) {
                $churn[$path]++;
            }
        }

        return $churn;
    }
}
