#!/usr/bin/env php
<?php
// phpcs:ignoreFile

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
require_once $packageRoot . '/vendor/autoload.php';
require_once $packageRoot . '/vendor/squizlabs/php_codesniffer/autoload.php';
require_once $packageRoot . '/vendor/squizlabs/php_codesniffer/src/Util/Tokens.php';

if (defined('PHP_CODESNIFFER_VERBOSITY') === false) {
    define('PHP_CODESNIFFER_VERBOSITY', 0);
}

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;

$config = new Config(['--standard=' . $packageRoot . '/ruleset.xml']);
$config->cache    = false;
$config->sniffs   = [
    'PrikotovCodingStandard.Application.CommandQueryStructure',
    'PrikotovCodingStandard.Application.CommandHandlerStructure',
    'PrikotovCodingStandard.Application.UseCaseNaming',
    'PrikotovCodingStandard.Namespaces.GlobalFunctionCallStyle',
    'PrikotovCodingStandard.Structure.DtoStructure',
    'PrikotovCodingStandard.Structure.EnumStructure',
];
$config->tabWidth = 4;

$ruleset  = new Ruleset($config);
$fixtures = require $packageRoot . '/tests/fixtures.php';

$failed = false;
foreach ($fixtures as $fixture) {
    $phpcsFile = new LocalFile($fixture['file'], $ruleset, $config);
    $phpcsFile->process();

    $errors   = normalizeMessages($phpcsFile->getErrors());
    $warnings = normalizeMessages($phpcsFile->getWarnings());

    $expectedErrors   = $fixture['errors'] ?? [];
    $expectedWarnings = $fixture['warnings'] ?? [];

    $errorDiffs   = diffMessages($fixture['file'], 'error', $expectedErrors, $errors);
    $warningDiffs = diffMessages($fixture['file'], 'warning', $expectedWarnings, $warnings);

    foreach (array_merge($errorDiffs, $warningDiffs) as $message) {
        fwrite(STDERR, $message . PHP_EOL);
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "PHPCS sniff tests passed.\n");

/**
 * @param array<int, array<int, array<int, array{message:string, source:string, severity:int, fixable:bool, type:string}>>> $messages
 * @return array<int, int>
 */
function normalizeMessages(array $messages): array
{
    $result = [];
    foreach ($messages as $line => $columns) {
        $count = 0;
        foreach ($columns as $columnErrors) {
            $count += count($columnErrors);
        }

        $result[(int) $line] = $count;
    }

    ksort($result);
    return $result;
}

/**
 * @param array<int, int> $expected
 * @param array<int, int> $actual
 * @return list<string>
 */
function diffMessages(string $file, string $type, array $expected, array $actual): array
{
    $messages = [];
    $allLines = array_unique(array_merge(array_keys($expected), array_keys($actual)));
    sort($allLines);

    foreach ($allLines as $line) {
        $expectedCount = $expected[$line] ?? 0;
        $actualCount   = $actual[$line] ?? 0;
        if ($expectedCount === $actualCount) {
            continue;
        }

        $messages[] = sprintf(
            '[%s:%d] Expected %d %s(s), found %d.',
            $file,
            $line,
            $expectedCount,
            $type,
            $actualCount
        );
    }

    return $messages;
}
