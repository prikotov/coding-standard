#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * validate-docs — validate conventions documentation.
 *
 * Checks:
 *   1. YAML front matter present with required fields (name, description, type).
 *   2. Naming: files and directories use kebab-case (no underscores).
 *   3. Required sections present in rule documents.
 *
 * For link validation use bin/validate-md-links.
 *
 * Document types (set in front matter):
 *   - rule  — must have required sections.
 *   - index — list of links, no section checks.
 *   - meta  — package-level files, no section checks.
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

/** Required fields in YAML front matter. */
const REQUIRED_FRONT_MATTER = ['name', 'type', 'description'];

/** Valid document types. */
const VALID_TYPES = ['rule', 'index', 'meta'];

/** Required ## headings in rule documents (see AGENTS.md template). */
const REQUIRED_SECTIONS = [
    'Общие правила',
    'Расположение',
    'Чек-лист для проведения ревью кода',
];

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Parse YAML front matter from markdown content.
 * Returns ['fields' => [...], 'body' => string] or null if no front matter.
 *
 * @return array{fields: array<string, string>, body: string}|null
 */
function parseFrontMatter(string $content): ?array
{
    if (!str_starts_with($content, "---\n")) {
        return null;
    }

    $end = strpos($content, "\n---\n", 4);
    if ($end === false) {
        return null;
    }

    $yaml = substr($content, 4, $end - 4);
    $body = substr($content, $end + 5);

    $fields = [];
    foreach (explode("\n", $yaml) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $key = trim(substr($line, 0, $colon));
        $value = trim(substr($line, $colon + 1));
        // Remove surrounding quotes
        if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
            $value = $m[1];
        }
        $fields[$key] = $value;
    }

    return ['fields' => $fields, 'body' => $body];
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
 * Check if a heading matches a required section (case-insensitive, prefix).
 */
function headingMatches(string $heading, string $required): bool
{
    $h = mb_strtolower(rtrim($heading, ' .'));
    $r = mb_strtolower(rtrim($required, ' .'));

    if ($h === $r) {
        return true;
    }

    // "Чек-лист код-ревью" matches "Чек-лист для проведения ревью кода"
    // Use prefix match for "Чек-лист"
    if (str_starts_with($h, explode(' ', $r)[0])) {
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
$excludeFiles = ['AGENTS.md'];

foreach ($iterator as $item) {
    if (!$item->isFile() || $item->getExtension() !== 'md') {
        continue;
    }
    if (in_array($item->getFilename(), $excludeFiles, true)) {
        continue;
    }
    $mdFiles[] = $item->getPathname();
}

sort($mdFiles);

echo "Validating " . count($mdFiles) . " markdown files in {$docsDir}/\n\n";

// ── Check 1: Front matter ─────────────────────────────────────────────────

echo "## Front matter\n";
$fmErrors = 0;
$fileTypes = []; // filePath => type

foreach ($mdFiles as $filePath) {
    $relativePath = substr($filePath, strlen($docsDir) + 1);
    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $parsed = parseFrontMatter($content);

    if ($parsed === null) {
        echo "  ✗ {$relativePath}: missing front matter\n";
        $errors[] = "front-matter: {$relativePath} missing";
        $fmErrors++;
        $fileTypes[$filePath] = null;
        continue;
    }

    $fields = $parsed['fields'];

    // Check required fields
    foreach (REQUIRED_FRONT_MATTER as $field) {
        if (!isset($fields[$field]) || trim($fields[$field]) === '') {
            echo "  ✗ {$relativePath}: missing field \"{$field}\"\n";
            $errors[] = "front-matter: {$relativePath} missing \"{$field}\"";
            $fmErrors++;
        }
    }

    // Check type is valid
    $type = $fields['type'] ?? '';
    if (!in_array($type, VALID_TYPES, true)) {
        echo "  ✗ {$relativePath}: invalid type \"{$type}\" (valid: " . implode(', ', VALID_TYPES) . ")\n";
        $errors[] = "front-matter: {$relativePath} invalid type \"{$type}\"";
        $fmErrors++;
    }

    $fileTypes[$filePath] = $type;
}

if ($fmErrors === 0) {
    echo "  ✓ All files have valid front matter\n";
}
echo "\n";

// ── Check 2: Naming (kebab-case) ──────────────────────────────────────────

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

// ── Check 3: Required sections (rule documents only) ──────────────────────

echo "## Required sections\n";
$sectionErrors = 0;

foreach ($mdFiles as $filePath) {
    $type = $fileTypes[$filePath] ?? null;

    // Only rule documents need sections
    if ($type !== 'rule') {
        continue;
    }

    $relativePath = substr($filePath, strlen($docsDir) + 1);
    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $parsed = parseFrontMatter($content);
    $body = $parsed['body'] ?? $content;
    $headings = extractHeadings($body);

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
    echo "  Front matter: {$fmErrors}\n";
    echo "  Naming:       {$namingErrors}\n";
    echo "  Sections:     {$sectionErrors}\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ All checks passed\n";
exit(0);
