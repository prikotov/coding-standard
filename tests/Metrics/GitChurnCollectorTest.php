<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\GitChurnCollector;

final class GitChurnCollectorTest extends TestCase
{
    public function testCountsCommitsTouchingEachFile(): void
    {
        $directory = sys_get_temp_dir() . '/git-churn-' . uniqid();
        mkdir($directory);

        try {
            $this->git($directory, 'init');
            $this->git($directory, 'config user.email metrics@example.com');
            $this->git($directory, 'config user.name Metrics');
            file_put_contents($directory . '/First.php', '<?php');
            file_put_contents($directory . '/Second.php', '<?php');
            $this->git($directory, 'add First.php Second.php');
            $this->git($directory, 'commit -m first');
            file_put_contents($directory . '/First.php', "<?php\n");
            $this->git($directory, 'add First.php');
            $this->git($directory, 'commit -m second');

            $churn = (new GitChurnCollector())->collect($directory, ['First.php', 'Second.php', 'Untracked.php']);

            self::assertSame(['First.php' => 2, 'Second.php' => 1, 'Untracked.php' => 0], $churn);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testTreatsStagedAndWorkingChangesAsOneFutureRevision(): void
    {
        $directory = sys_get_temp_dir() . '/git-churn-future-' . uniqid();
        mkdir($directory);

        try {
            $this->git($directory, 'init');
            $this->git($directory, 'config user.email metrics@example.com');
            $this->git($directory, 'config user.name Metrics');
            file_put_contents($directory . '/Existing.php', "<?php\n");
            $this->git($directory, 'add Existing.php');
            $this->git($directory, 'commit -m first');

            file_put_contents($directory . '/Existing.php', "<?php\n// staged\n");
            $this->git($directory, 'add Existing.php');
            file_put_contents($directory . '/Existing.php', "<?php\n// working\n");
            file_put_contents($directory . '/Added.php', "<?php\n");
            $beforeCommit = (new GitChurnCollector())->collect(
                $directory,
                ['Existing.php', 'Added.php'],
            );

            $this->git($directory, 'add Existing.php Added.php');
            $this->git($directory, 'commit -m future');
            $afterCommit = (new GitChurnCollector())->collect(
                $directory,
                ['Existing.php', 'Added.php'],
            );

            self::assertSame(['Existing.php' => 2, 'Added.php' => 1], $beforeCommit);
            self::assertSame($beforeCommit, $afterCommit);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function git(string $directory, string $arguments): void
    {
        exec("git -C " . escapeshellarg($directory) . " $arguments 2>&1", $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
