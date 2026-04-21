#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * validate-docs — validate conventions documentation.
 *
 * Checks:
 *   1. Internal markdown links point to existing files.
 *   2. Naming: files and directories use kebab-case (no underscores).
 *   3. Required sections present in rule documents (index files are exempt).
 *
 * Two document types (see AGENTS.md):
 *   - Index files (index.md) — list of links, no section requirements.
 *   - Rule documents (everything else) — must follow the template structure.
 *
 * Usage:
 *   php bin/validate-docs.php [path]
 *
 *   path — docs directory (default: docs/conventions).
 *
 * Exit codes:
 *   0 — all checks passed
 *   1 — validation errors found
 */

// ── Config ─────────────────────────────────────────────────────────────────

$docsDir = $argv[1] ?? 'docs/conventions';
$errors = [];

/** Filenames that are index/overview documents — exempt from section checks. */
const INDEX_FILES = ['index.md', 'agents.md', 'readme.md'];

/** Required ## headings in every rule document (see AGENTS.md template). */
const REQUIRED_SECTIONS = [
    'Общие правила',
    'Пример',
    'Чек-лист для проведения ревью кода',
];

// ── Helpers ────────────────────────────────────────────────────────────────

function isIndexFile(string $filePath): bool
{
    return in_array(strtolower(basename($filePath)), INDEX_FILES, true);
}

/**
 * Extract all internal markdown links from content.
 *
 * @return array<int, array{text: string, target: string, line: int}>
 */
function extractInternalLinks(string $content): array
{
    $links = [];
    $lines = explode("\n", $content);

    foreach ($lines as $lineNum => $line) {
        if (!preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $target = $match[2];
            if (preg_match('/^(https?:|mailto:|#|tel:)/', $target)) {
                continue;
            }
            if (str_contains($target, '{')) {
                continue;
            }
            $links[] = [
                'text' => $match[1],
                'target' => $target,
                'line' => $lineNum + 1,
            ];
        }
    }

    return $links;
}

/**
 * Extract ## headings from content.
 *
 * @return string[]
 */
function extractHeadings(string $content): array
{
    $headings = [];
    foreach (explode("\n", $content) as $line) {
        if (preg_match('/^## (.+)/', $line, $m)) {
            $headings[] = trim($m[1]);
        }
    }

    return $headings;
}

/**
 * Resolve a link target relative to the source file's directory.
 */
function resolveLinkTarget(string $sourceDir, string $target): string
{
    $path = preg_replace('/#.*/', '', $target);
    if ($path === '') {
        return '';
    }

    $resolved = rtrim($sourceDir, '/') . '/' . $path;

    $parts = explode('/', $resolved);
    $normalized = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($normalized);
        } elseif ($part !== '' && $part !== '.') {
            $normalized[] = $part;
        }
    }

    return implode('/', $normalized);
}

/**
 * Check if a heading matches a required section (case-insensitive).
 */
function headingMatches(string $heading, string $required): bool
{
    $h = mb_strtolower(rtrim($heading, ' .'));
    $r = mb_strtolower(rtrim($required, ' .'));

    // Exact match
    if ($h === $r) {
        return true;
    }

    // Prefix match: "Пример: ..." matches "Пример"
    if (str_starts_with($h, $r . ':') || str_starts_with($h, $r . ' ')) {
        return true;
    }

    return false;
}

// ── Collect files ──────────────────────────────────────────────────────────

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($docsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

$mdFiles = [];
foreach ($iterator as $item) {
    if (!$item->isFile() || $item->getExtension() !== 'md') {
        continue;
    }
    $mdFiles[] = $item->getPathname();
}

sort($mdFiles);

echo "Validating " . count($mdFiles) . " markdown files in {$docsDir}/\n\n";

// ── Check 1: Naming (kebab-case) ──────────────────────────────────────────

echo "## Naming (kebab-case)\n";
$namingErrors = 0;

foreach ($iterator as $item) {
    $basename = $item->getFilename();
    if (str_contains($basename, '_') && $basename !== '.gitkeep') {
        $relativePath = substr($item->getPathname(), strlen($docsDir) + 1);
        $type = $item->isDir() ? 'directory' : 'file';
        echo "  ✗ {$type} uses underscore: {$relativePath}\n";
        $errors[] = "naming: {$relativePath}";
        $namingErrors++;
    }
}

if ($namingErrors === 0) {
    echo "  ✓ All files and directories use kebab-case\n";
}
echo "\n";

// ── Check 2: Internal links ───────────────────────────────────────────────

echo "## Internal links\n";
$linkErrors = 0;

foreach ($mdFiles as $filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $sourceDir = dirname($filePath);
    $relativePath = substr($filePath, strlen($docsDir) + 1);
    $links = extractInternalLinks($content);

    foreach ($links as $link) {
        $resolved = resolveLinkTarget($sourceDir, $link['target']);
        if ($resolved === '') {
            continue;
        }

        if (!file_exists($resolved)) {
            echo "  ✗ {$relativePath}:{$link['line']} → {$link['target']} (not found)\n";
            $errors[] = "link: {$relativePath}:{$link['line']} → {$link['target']}";
            $linkErrors++;
        }
    }
}

if ($linkErrors === 0) {
    echo "  ✓ All internal links resolve\n";
}
echo "\n";

// ── Check 3: Required sections ────────────────────────────────────────────

echo "## Required sections\n";
$sectionErrors = 0;

foreach ($mdFiles as $filePath) {
    $relativePath = substr($filePath, strlen($docsDir) + 1);

    // Skip index files
    if (isIndexFile($filePath)) {
        continue;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $headings = extractHeadings($content);

    foreach (REQUIRED_SECTIONS as $required) {
        $found = false;
        foreach ($headings as $heading) {
            if (headingMatches($heading, $required)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo "  ✗ {$relativePath}: missing \"## {$required}\"\n";
            $errors[] = "section: {$relativePath} missing \"## {$required}\"";
            $sectionErrors++;
        }
    }
}

if ($sectionErrors === 0) {
    echo "  ✓ All required sections present\n";
}
echo "\n";

// ── Summary ────────────────────────────────────────────────────────────────

$totalErrors = count($errors);

if ($totalErrors > 0) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✗ Found {$totalErrors} error(s)\n";
    echo "  Naming:   {$namingErrors}\n";
    echo "  Links:    {$linkErrors}\n";
    echo "  Sections: {$sectionErrors}\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ All checks passed\n";
exit(0);
