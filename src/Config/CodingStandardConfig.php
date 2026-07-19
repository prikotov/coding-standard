<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Config;

use PHP_CodeSniffer\Files\File;

/**
 * Reads the project-level configuration `.coding-standard.php` (a file that
 * returns an array) located at the project root.
 *
 * The configuration is mandatory for this package's sniffs to operate: it tells
 * them where the conventions documentation was copied (`docs_path`) so error
 * messages can point the agent/developer at the exact document. Without it the
 * convention sniffs stay silent — {@see \PrikotovCodingStandard\Sniffs\Config\ConfigRequiredSniff}
 * reports the missing/invalid configuration once.
 *
 * Resolved once per PHPCS process and cached statically (a single run targets
 * a single project root).
 *
 * Minimal schema (stage 1):
 *
 *     <?php
 *     declare(strict_types=1);
 *     return [
 *         'docs_path' => 'docs/conventions',
 *     ];
 */
final class CodingStandardConfig
{
    private const CONFIG_FILENAME = '.coding-standard.php';

    private static bool $loaded = false;
    private static ?string $projectRoot = null;
    private static ?string $configPath = null;

    /** @var array<mixed>|null null when the config file is missing or invalid */
    private static ?array $config = null;

    /**
     * Loads the config (once) relative to the given file and returns it, or
     * null when no `.coding-standard.php` was found upwards from the file.
     *
     * @return array<mixed>|null
     */
    public static function load(File $phpcsFile): ?array
    {
        if (self::$loaded === false) {
            self::$loaded = true;
            self::resolve($phpcsFile);
        }

        return self::$config;
    }

    /**
     * Returns the configured conventions documentation path relative to the
     * project root (e.g. `docs/conventions`), or null when the config is
     * missing or does not declare `docs_path`.
     */
    public static function docsPath(File $phpcsFile): ?string
    {
        $config = self::load($phpcsFile);
        if ($config === null) {
            return null;
        }

        $docsPath = $config['docs_path'] ?? null;
        if (is_string($docsPath) === false || $docsPath === '') {
            return null;
        }

        return rtrim($docsPath, '/');
    }

    public static function projectRoot(File $phpcsFile): ?string
    {
        self::load($phpcsFile);

        return self::$projectRoot;
    }

    public static function configPath(File $phpcsFile): ?string
    {
        self::load($phpcsFile);

        return self::$configPath;
    }

    private static function resolve(File $phpcsFile): void
    {
        $filename = $phpcsFile->getFilename();
        $dir = is_dir($filename) === true ? $filename : dirname($filename);

        $previous = '';
        for ($depth = 0; $depth < 40 && $dir !== $previous; $depth++) {
            $candidate = $dir . '/' . self::CONFIG_FILENAME;
            if (is_file($candidate) === true) {
                self::$configPath = $candidate;
                self::$projectRoot = $dir;
                self::$config = self::read($candidate);

                return;
            }

            $previous = $dir;
            $dir = dirname($dir);
        }
    }

    /**
     * @return array<mixed>|null
     */
    private static function read(string $path): ?array
    {
        try {
            $value = require $path;
        } catch (\Throwable) {
            return null;
        }

        return is_array($value) === true ? $value : null;
    }
}
