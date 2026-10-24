<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

final class GitChurnCollector
{
    /** @param list<string> $files @return array<string, int>|null */
    public function collect(string $projectRoot, array $files): ?array
    {
        $history = $this->command($projectRoot, ['log', '--format=commit:%H', '--name-only']);
        if ($history === null) {
            return null;
        }

        $tracked = array_fill_keys($files, true);
        $churn = array_fill_keys($files, 0);
        foreach (preg_split('/\R/', $history) ?: [] as $line) {
            $path = trim(str_replace('\\', '/', $line));
            if ($path !== '' && !str_starts_with($path, 'commit:') && isset($tracked[$path])) {
                $churn[$path]++;
            }
        }

        foreach ($this->futureRevisionFiles($projectRoot) as $path) {
            if (isset($tracked[$path])) {
                $churn[$path]++;
            }
        }

        return $churn;
    }

    /** @return list<string> */
    private function futureRevisionFiles(string $projectRoot): array
    {
        $changed = $this->command($projectRoot, ['diff', 'HEAD', '--name-only', '-z']);
        $untracked = $this->command($projectRoot, ['ls-files', '--others', '--exclude-standard', '-z']);
        if ($changed === null || $untracked === null) {
            return [];
        }

        $files = [];
        foreach (array_merge(explode("\0", $changed), explode("\0", $untracked)) as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ($path !== '') {
                $files[$path] = true;
            }
        }

        return array_keys($files);
    }

    /** @param list<string> $arguments */
    private function command(string $projectRoot, array $arguments): ?string
    {
        $result = (new ProcessRunner())->run(
            ['git', '-C', $projectRoot, '-c', 'core.quotepath=false', ...$arguments],
            ['file', '/dev/null', 'r'],
        );
        if ($result === null || $result[0] !== 0) {
            return null;
        }

        return $result[1];
    }
}
