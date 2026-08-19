<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Verify;

use SimpleXMLElement;

/**
 * @phpstan-type ComposerLock array{packages: list<mixed>, packages-dev: list<mixed>}
 *
 * Verifies that the consumer project actually uses prikotov/coding-standard:
 *
 *  1. the locked package version matches the latest release;
 *  2. the package PHPStan extension is registered (includes or extension-installer);
 *  3. the project PHPCS ruleset references the package standard.
 *
 * A rule that exists in the package but is not wired into the consumer must not
 * stay unnoticed — it is reported as a failure with a fixing instruction.
 */
final class WiringVerifier
{
    private const PACKAGE_NAME = 'prikotov/coding-standard';

    private const PHPSTAN_PACKAGE = 'phpstan/phpstan';

    private const EXTENSION_INSTALLER_PACKAGE = 'phpstan/extension-installer';

    private const PHPCS_INSTALLER_PACKAGE = 'dealerdirect/phpcodesniffer-composer-installer';

    private const PHPSTAN_CONFIG_CANDIDATES = ['phpstan.neon', 'phpstan.neon.dist'];

    private const PHPCS_RULESET_CANDIDATES = ['.phpcs.xml', 'phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml.dist'];

    private const ALLOW_FILE = '.coding-standard-verify-allow.json';

    private const STANDARD_NAMES = ['PrikotovCodingStandard', 'coding-standard'];

    public function __construct(
        private readonly ?LatestReleaseProvider $latestReleaseProvider,
    ) {
    }

    public function verify(string $projectDir): VerificationResult
    {
        $result = new VerificationResult();
        $lock = $this->readComposerLock($projectDir, $result);
        if ($lock === null) {
            return $result;
        }

        $lockedVersion = $this->findPackageVersion($lock, self::PACKAGE_NAME);
        if ($lockedVersion === null) {
            $result->fail(
                'composer.lock does not contain ' . self::PACKAGE_NAME,
                'Run: composer require --dev ' . self::PACKAGE_NAME,
            );

            return $result;
        }

        $this->verifyPhpStan($result, $projectDir, $lock);
        $this->verifyPhpcs($result, $projectDir, $lock);
        $this->verifyVersion($result, $projectDir, $this->normalizeVersion($lockedVersion));

        return $result;
    }

    /**
     * @param ComposerLock $lock
     */
    private function verifyPhpStan(VerificationResult $result, string $projectDir, array $lock): void
    {
        if ($this->findPackageVersion($lock, self::PHPSTAN_PACKAGE) === null) {
            $result->fail(
                'phpstan/phpstan is not installed',
                'Run: composer require --dev phpstan/phpstan — the package PHPStan rules need it',
            );

            return;
        }

        $config = $this->findFirstExisting($projectDir, self::PHPSTAN_CONFIG_CANDIDATES);
        $includes = $config === null ? [] : $this->resolveNeonIncludes($config);
        if ($this->anyContains($includes, self::PACKAGE_NAME)) {
            $result->ok('phpstan: package rules are included from ' . basename((string) $config));

            return;
        }

        if ($this->findPackageVersion($lock, self::EXTENSION_INSTALLER_PACKAGE) !== null) {
            $generatedConfig = $projectDir . '/' . $this->vendorDir($projectDir)
                . '/phpstan/extension-installer/src/GeneratedConfig.php';
            $generated = is_file($generatedConfig) ? (string) file_get_contents($generatedConfig) : '';
            if (str_contains($generated, self::PACKAGE_NAME)) {
                $result->ok('phpstan: package rules are registered by phpstan/extension-installer');

                return;
            }

            $result->fail(
                'phpstan/extension-installer is installed but ' . self::PACKAGE_NAME
                . ' is missing from its generated config',
                'Re-run `composer install` to regenerate it, and check allow-plugins.phpstan/extension-installer',
            );

            return;
        }

        $result->fail(
            'phpstan: package rules are not registered',
            'Add to ' . ($config ?? 'phpstan.neon.dist') . ":\n"
            . "includes:\n"
            . '    - ' . $this->vendorDir($projectDir) . '/' . self::PACKAGE_NAME . '/phpstan-rules.neon',
        );
    }

    /**
     * @param ComposerLock $lock
     */
    private function verifyPhpcs(VerificationResult $result, string $projectDir, array $lock): void
    {
        $ruleset = $this->findFirstExisting($projectDir, self::PHPCS_RULESET_CANDIDATES);
        if ($ruleset === null) {
            $result->fail(
                'phpcs: project ruleset not found',
                'Create phpcs.xml.dist and reference the package standard, '
                . 'or run php vendor/bin/coding-standard-init',
            );

            return;
        }

        $xml = @simplexml_load_file($ruleset);
        if ($xml === false) {
            $result->fail('phpcs: cannot parse ruleset ' . basename($ruleset));

            return;
        }

        if ($this->rulesetReferencesPackage($xml)) {
            $result->ok('phpcs: ruleset ' . basename($ruleset) . ' references the package standard');

            return;
        }

        if ($this->findPackageVersion($lock, self::PHPCS_INSTALLER_PACKAGE) !== null) {
            $result->ok('phpcs: standard registered by dealerdirect/phpcodesniffer-composer-installer');

            return;
        }

        $result->fail(
            'phpcs: ruleset ' . basename($ruleset) . ' does not reference the package standard',
            'Add to ' . basename($ruleset) . ":\n"
            . '    <config name="installed_paths" value="' . $this->vendorDir($projectDir)
            . '/' . self::PACKAGE_NAME . "\"/>\n"
            . '    <rule ref="PrikotovCodingStandard"/>',
        );
    }

    private function verifyVersion(VerificationResult $result, string $projectDir, string $lockedVersion): void
    {
        if ($lockedVersion === '' || str_contains($lockedVersion, 'dev')) {
            $result->warn("composer.lock: {$lockedVersion} is a dev version; release comparison skipped");

            return;
        }

        if ($this->latestReleaseProvider === null) {
            $result->warn('latest release unknown (--offline); version check skipped');

            return;
        }

        $latest = $this->latestReleaseProvider->latestRelease();
        if ($latest === null) {
            $result->warn('latest release unknown (GitHub API unavailable); version check skipped');

            return;
        }

        if (version_compare($lockedVersion, $latest) >= 0) {
            $result->ok("composer.lock: {$lockedVersion} is not behind the latest release {$latest}");

            return;
        }

        if ($this->allowListCovers($result, $projectDir, $lockedVersion)) {
            return;
        }

        $result->fail(
            "composer.lock has {$lockedVersion}, latest release is {$latest}",
            'Run: composer update ' . self::PACKAGE_NAME . " --with-all-dependencies\n"
            . 'If the composer.json constraint blocks the update, see the "Обновление" section of '
            . $this->vendorDir($projectDir) . '/' . self::PACKAGE_NAME . '/README.md',
        );
    }

    /** @return ComposerLock|null */
    private function readComposerLock(string $projectDir, VerificationResult $result): ?array
    {
        $lockFile = $projectDir . '/composer.lock';
        if (!is_file($lockFile)) {
            $result->fail('composer.lock not found', 'Run: composer install');

            return null;
        }

        $lock = json_decode((string) file_get_contents($lockFile), true);
        if (!is_array($lock)) {
            $result->fail('composer.lock is not readable JSON with packages and packages-dev');

            return null;
        }

        $packages = $lock['packages'] ?? null;
        $packagesDev = $lock['packages-dev'] ?? null;
        $lockListsPackages = is_array($packages) && array_is_list($packages);
        $lockListsPackagesDev = is_array($packagesDev) && array_is_list($packagesDev);
        if (!$lockListsPackages || !$lockListsPackagesDev) {
            $result->fail('composer.lock is not readable JSON with packages and packages-dev');

            return null;
        }

        return ['packages' => $packages, 'packages-dev' => $packagesDev];
    }

    /**
     * @param ComposerLock $lock
     */
    private function findPackageVersion(array $lock, string $name): ?string
    {
        foreach ([$lock['packages'], $lock['packages-dev']] as $packages) {
            foreach ($packages as $package) {
                if (is_array($package) && ($package['name'] ?? null) === $name) {
                    $version = $package['version'] ?? null;

                    return is_string($version) ? $version : null;
                }
            }
        }

        return null;
    }

    /** @param list<string> $candidates */
    private function findFirstExisting(string $projectDir, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $path = $projectDir . '/' . $candidate;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @param list<string> $haystacks */
    private function anyContains(array $haystacks, string $needle): bool
    {
        foreach ($haystacks as $haystack) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function vendorDir(string $projectDir): string
    {
        $composerJson = $projectDir . '/composer.json';
        if (is_file($composerJson)) {
            $composer = json_decode((string) file_get_contents($composerJson), true);
            $config = is_array($composer) ? ($composer['config'] ?? null) : null;
            $vendorDir = is_array($config) ? ($config['vendor-dir'] ?? null) : null;
            if (is_string($vendorDir) && $vendorDir !== '') {
                return $vendorDir;
            }
        }

        return 'vendor';
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim($version, 'v');
    }

    private function allowListCovers(VerificationResult $result, string $projectDir, string $lockedVersion): bool
    {
        $allowFile = $projectDir . '/' . self::ALLOW_FILE;
        if (!is_file($allowFile)) {
            return false;
        }

        $allow = json_decode((string) file_get_contents($allowFile), true);
        if (!is_array($allow)) {
            return false;
        }

        $version = $allow['version'] ?? null;
        $until = $allow['until'] ?? null;
        if (!is_string($version) || !is_string($until) || $this->normalizeVersion($version) !== $lockedVersion) {
            return false;
        }

        if (strtotime($until) < strtotime('today')) {
            return false;
        }

        $reason = is_string($allow['reason'] ?? null) ? ' — ' . $allow['reason'] : '';
        $result->warn("composer.lock: {$lockedVersion} is pinned until {$until}{$reason}");

        return true;
    }

    /**
     * Resolves the transitive closure of `includes:` from a NEON config.
     *
     * @return list<string> include paths as written or resolved against the including file
     */
    private function resolveNeonIncludes(string $configFile): array
    {
        $resolved = [];
        $queue = [$configFile];
        $visited = [];

        while ($queue !== []) {
            $file = array_shift($queue);
            $real = realpath($file) ?: $file;
            if (isset($visited[$real]) || !is_file($real)) {
                continue;
            }
            $visited[$real] = true;

            foreach ($this->parseNeonIncludes((string) file_get_contents($real)) as $include) {
                $resolved[] = $include;
                $isRelative = !str_starts_with($include, '/')
                    && !str_starts_with($include, 'phar://')
                    && !str_starts_with($include, '%');
                if ($isRelative) {
                    $queue[] = dirname($real) . '/' . $include;
                }
            }
        }

        return $resolved;
    }

    /** @return list<string> */
    private function parseNeonIncludes(string $content): array
    {
        $includes = [];
        $inSection = false;

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9_.-]+):/', $line, $match)) {
                $inSection = $match[1] === 'includes';

                continue;
            }

            if ($inSection && preg_match('/^\s+-\s+(.+?)\s*$/', $line, $match)) {
                $path = trim($match[1], "'\"");
                if ($path !== '' && !str_starts_with($path, '#')) {
                    $includes[] = $path;
                }
            }
        }

        return $includes;
    }

    private function rulesetReferencesPackage(SimpleXMLElement $xml): bool
    {
        foreach ($xml->config as $config) {
            $name = (string) $config['name'];
            $value = (string) $config['value'];
            if ($name === 'installed_paths' && str_contains($value, self::PACKAGE_NAME)) {
                return true;
            }
        }

        foreach ($xml->rule as $rule) {
            $ref = (string) $rule['ref'];
            if (str_contains($ref, self::PACKAGE_NAME) || in_array($ref, self::STANDARD_NAMES, true)) {
                return true;
            }
        }

        return false;
    }
}
