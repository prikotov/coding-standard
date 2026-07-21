<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Sniffs\Config;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PrikotovCodingStandard\Config\CodingStandardConfig;

/**
 * Requires a project-level `.coding-standard.php` configuration file at the
 * project root. The file is mandatory: the convention sniffs rely on it (at
 * least the `docs_path` setting) to point error messages at the conventions
 * documentation, and they stay silent without it.
 *
 * Reports a single, actionable error per PHPCS run when the config is missing,
 * unreadable, lacks `docs_path`, or when the declared `docs_path` does not
 * exist on disk. The error message instructs how to fix it.
 *
 * See: docs/conventions/configuration.md (project conventions index)
 */
final class ConfigRequiredSniff implements Sniff
{
    private const ERROR_MISSING = 'ConfigMissing';
    private const ERROR_UNREADABLE = 'ConfigUnreadable';
    private const ERROR_DOCS_PATH_NOT_SET = 'DocsPathNotSet';
    private const ERROR_DOCS_PATH_NOT_FOUND = 'DocsPathNotFound';

    private static bool $checked = false;

    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if (self::$checked === true) {
            return;
        }

        // Only enforce the config inside actual project source (src/...). This
        // avoids false positives on vendor/, tests/, fixtures, etc. analysed in
        // the same run.
        $normalizedPath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (preg_match('~/src/Module/~', $normalizedPath) !== 1) {
            return;
        }

        self::$checked = true;

        $configPath = CodingStandardConfig::configPath($phpcsFile);
        if ($configPath === null) {
            $phpcsFile->addError(
                'Project config ".coding-standard.php" is missing at the project root.'
                . ' Run "vendor/bin/coding-standard-init" to create it, then re-run PHPCS.'
                . ' Convention sniffs are disabled until the config exists.',
                $stackPtr,
                self::ERROR_MISSING,
            );

            return;
        }

        if (CodingStandardConfig::load($phpcsFile) === null) {
            $phpcsFile->addError(
                sprintf(
                    'Project config "%s" exists but is unreadable or does not return an array.'
                    . ' Fix the file so it returns a PHP array with at least a docs_path key'
                    . ' pointing at the copied conventions documentation (e.g. docs/conventions).',
                    $configPath,
                ),
                $stackPtr,
                self::ERROR_UNREADABLE,
            );

            return;
        }

        $docsPath = CodingStandardConfig::docsPath($phpcsFile);
        if ($docsPath === null) {
            $phpcsFile->addError(
                sprintf(
                    'Project config "%s" does not declare "docs_path".'
                    . ' Add \'docs_path\' => \'docs/conventions\' (the path where conventions'
                    . ' documentation was copied by coding-standard-init).',
                    $configPath,
                ),
                $stackPtr,
                self::ERROR_DOCS_PATH_NOT_SET,
            );

            return;
        }

        $projectRoot = CodingStandardConfig::projectRoot($phpcsFile);
        if ($projectRoot !== null && is_dir($projectRoot . '/' . $docsPath) === false) {
            $phpcsFile->addError(
                sprintf(
                    'Conventions path "%s" declared in ".coding-standard.php" does not exist'
                    . ' (resolved to "%s/%s"). Re-run "vendor/bin/coding-standard-init"'
                    . ' to copy the documentation, or correct "docs_path".',
                    $docsPath,
                    $projectRoot,
                    $docsPath,
                ),
                $stackPtr,
                self::ERROR_DOCS_PATH_NOT_FOUND,
            );
        }
    }
}
