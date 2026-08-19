<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Verify;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\DirectoryRemover;

final class CodingStandardVerifyCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/coding-standard-verify-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testPassesWhenWiredAndUpToDate(): void
    {
        $this->createProject();

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('phpstan: package rules are included from phpstan.neon.dist', $result['output']);
        self::assertStringContainsString('phpcs: ruleset phpcs.xml.dist references the package standard', $result['output']);
        self::assertStringContainsString('composer.lock: 0.29.3 is not behind the latest release 0.29.3', $result['output']);
    }

    public function testFailsWhenLockedVersionIsBehindLatest(): void
    {
        $this->createProject(['lockedVersion' => '0.29.2']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('composer.lock has 0.29.2, latest release is 0.29.3', $result['output']);
        self::assertStringContainsString('composer update prikotov/coding-standard --with-all-dependencies', $result['output']);
    }

    public function testWarnsInsteadOfFailingWhenVersionIsPinnedViaAllowFile(): void
    {
        $this->createProject([
            'lockedVersion' => '0.29.2',
            'allow' => ['version' => '0.29.2', 'until' => date('Y-m-d', strtotime('+2 days')), 'reason' => 'waiting for a fix'],
        ]);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('0.29.2 is pinned until', $result['output']);
        self::assertStringContainsString('waiting for a fix', $result['output']);
    }

    public function testFailsWhenPinInAllowFileHasExpired(): void
    {
        $this->createProject([
            'lockedVersion' => '0.29.2',
            'allow' => ['version' => '0.29.2', 'until' => date('Y-m-d', strtotime('-1 day'))],
        ]);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('composer.lock has 0.29.2', $result['output']);
    }

    public function testFailsWhenPhpStanRulesAreNotIncluded(): void
    {
        $this->createProject(['phpstan' => 'plain']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('phpstan: package rules are not registered', $result['output']);
        self::assertStringContainsString('includes:', $result['output']);
        self::assertStringContainsString('vendor/prikotov/coding-standard/phpstan-rules.neon', $result['output']);
    }

    public function testPassesWhenRulesAreRegisteredByExtensionInstaller(): void
    {
        $this->createProject(['phpstan' => 'installer', 'phpstanGeneratedConfig' => true]);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('phpstan: package rules are registered by phpstan/extension-installer', $result['output']);
    }

    public function testFailsWhenPackageIsMissingFromGeneratedExtensionConfig(): void
    {
        $this->createProject(['phpstan' => 'installer', 'phpstanGeneratedConfig' => false]);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('missing from its generated config', $result['output']);
    }

    public function testPassesWhenIncludesAreChainedThroughAnotherConfig(): void
    {
        $this->createProject(['phpstan' => 'chained']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('phpstan: package rules are included from phpstan.neon.dist', $result['output']);
    }

    public function testFailsWhenPhpcsRulesetMissesThePackage(): void
    {
        $this->createProject(['phpcs' => 'plain']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('does not reference the package standard', $result['output']);
        self::assertStringContainsString('installed_paths', $result['output']);
    }

    public function testFailsWhenPhpcsRulesetIsMissing(): void
    {
        $this->createProject(['phpcs' => 'none']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('phpcs: project ruleset not found', $result['output']);
    }

    public function testPassesWhenDealerdirectInstallerRegistersTheStandard(): void
    {
        $this->createProject(['phpcs' => 'dealerdirect']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('registered by dealerdirect/phpcodesniffer-composer-installer', $result['output']);
    }

    public function testOfflineSkipsTheVersionCheck(): void
    {
        $this->createProject(['lockedVersion' => '0.29.2']);

        $result = $this->execute(['--offline']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('version check skipped', $result['output']);
    }

    public function testDevVersionSkipsTheReleaseComparison(): void
    {
        $this->createProject(['lockedVersion' => 'dev-main']);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('dev version; release comparison skipped', $result['output']);
    }

    public function testFailsWhenPackageIsNotInstalled(): void
    {
        $this->createProject(['lockedVersion' => null]);

        $result = $this->execute(['--latest=0.29.3']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('composer.lock does not contain prikotov/coding-standard', $result['output']);
    }

    /**
     * @param array{lockedVersion?: string|null, phpstan?: string, phpstanGeneratedConfig?: bool, phpcs?: string, allow?: array{version: string, until: string, reason?: string}} $options
     */
    private function createProject(array $options = []): void
    {
        $lockedVersion = array_key_exists('lockedVersion', $options)
            ? $options['lockedVersion']
            : '0.29.3';

        $packages = [
            ['name' => 'phpstan/phpstan', 'version' => '2.1.0'],
        ];
        if ($lockedVersion !== null) {
            $packages[] = ['name' => 'prikotov/coding-standard', 'version' => $lockedVersion];
        }
        if (($options['phpstan'] ?? 'includes') === 'installer') {
            $packages[] = ['name' => 'phpstan/extension-installer', 'version' => '1.0.0'];
        }
        if (($options['phpcs'] ?? 'wired') === 'dealerdirect') {
            $packages[] = ['name' => 'dealerdirect/phpcodesniffer-composer-installer', 'version' => '1.0.0'];
        }

        file_put_contents(
            $this->directory . '/composer.lock',
            (string) json_encode(['packages' => $packages, 'packages-dev' => []]),
        );

        match ($options['phpstan'] ?? 'includes') {
            'plain' => file_put_contents(
                $this->directory . '/phpstan.neon.dist',
                "parameters:\n    level: 8\n",
            ),
            'chained' => $this->createChainedPhpStanConfig(),
            'installer' => $this->createExtensionInstallerConfig($options['phpstanGeneratedConfig'] ?? false),
            default => file_put_contents(
                $this->directory . '/phpstan.neon.dist',
                "parameters:\n    level: 8\nincludes:\n    - vendor/prikotov/coding-standard/phpstan-rules.neon\n",
            ),
        };

        match ($options['phpcs'] ?? 'wired') {
            'plain' => file_put_contents(
                $this->directory . '/phpcs.xml.dist',
                "<?xml version=\"1.0\"?>\n<ruleset name=\"Test\"><rule ref=\"PSR12\"/></ruleset>\n",
            ),
            'none' => null,
            'dealerdirect' => file_put_contents(
                $this->directory . '/phpcs.xml.dist',
                "<?xml version=\"1.0\"?>\n<ruleset name=\"Test\"><rule ref=\"PSR12\"/></ruleset>\n",
            ),
            default => file_put_contents(
                $this->directory . '/phpcs.xml.dist',
                "<?xml version=\"1.0\"?>\n<ruleset name=\"Test\">\n"
                . "    <config name=\"installed_paths\" value=\"vendor/prikotov/coding-standard\"/>\n"
                . "    <rule ref=\"PrikotovCodingStandard\"/>\n"
                . "</ruleset>\n",
            ),
        };

        if (array_key_exists('allow', $options)) {
            file_put_contents(
                $this->directory . '/.coding-standard-verify-allow.json',
                (string) json_encode($options['allow']),
            );
        }
    }

    private function createChainedPhpStanConfig(): void
    {
        file_put_contents(
            $this->directory . '/phpstan.neon.dist',
            "includes:\n    - phpstan.local.neon\n",
        );
        file_put_contents(
            $this->directory . '/phpstan.local.neon',
            "includes:\n    - vendor/prikotov/coding-standard/phpstan-rules.neon\n",
        );
    }

    private function createExtensionInstallerConfig(bool $withPackage): void
    {
        file_put_contents($this->directory . '/phpstan.neon.dist', "parameters:\n    level: 8\n");
        $generatedDir = $this->directory . '/vendor/phpstan/extension-installer/src';
        mkdir($generatedDir, 0777, true);
        $extension = $withPackage
            ? "'vendor/prikotov/coding-standard/phpstan-rules.neon'"
            : "'vendor/other/package/extension.neon'";
        file_put_contents(
            $generatedDir . '/GeneratedConfig.php',
            "<?php\n// generated\nreturn ['extensions' => [$extension]];\n",
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{code: int, output: string}
     */
    private function execute(array $arguments): array
    {
        $command = array_merge(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/coding-standard-verify', $this->directory],
            $arguments,
        );
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output . $error];
    }

    private function removeDirectory(string $directory): void
    {
        (new DirectoryRemover())->remove($directory);
    }
}
