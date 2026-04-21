#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * validate-docs — validate conventions documentation.
 *
 * Checks:
 *   1. Internal markdown links point to existing files.
 *   2. Naming: files and directories use kebab-case (no underscores).
 *   3. Required sections present (depends on document category).
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

/** Documents exempt from section checks (index, README, AGENTS, meta). */
$EXEMPT_BASENAMES = ['index.md', 'readme.md', 'agents.md', '.gitkeep'];

/** Required sections per category. Key = path prefix, value = list of required ## headings. */
$REQUIRED_SECTIONS = [
    // Core patterns & most layer docs: full structure
    'default' => ['Общие правила', 'Пример'],
    // Index/overview files (e.g. layers/domain.md, layers/presentation.md)
    'overview' => ['Общие правила'],
];

/** Directories whose .md files are overview/index-like (require only minimal sections). */
$OVERVIEW_PATHS = [
    'layers/' => true,
    'layers/domain/' => false,      // domain.md is overview, but domain/*.md are not
    'layers/application/' => false,
    'layers/infrastructure/' => false,
    'layers/integration/' => false,
    'layers/presentation/' => false,
];

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Check if a file (by relative path) is an overview document.
 * Overview = directly in a category dir (e.g. layers/domain.md) not in a subdir.
 */
function isOverviewFile(string $relativePath): bool
{
    $dir = dirname($relativePath);
    if ($dir === '.') {
        return false;
    }
    // File directly in layers/, but not in layers/subdir/
    $parent = dirname($dir);
    if ($parent === '.' && basename($dir) === 'layers') {
        return true;
    }
    // e.g. layers/domain.md (dir=layers, file directly in it)
    // Already handled above. Let's check: is the file directly under a category?
    // layers.md, domain.md inside layers/ — yes
    // We check if the file sits at depth 2 from conventions root (e.g. layers/domain.md)
    $depth = substr_count(trim($relativePath, '/'), '/');
    if ($depth === 1) {
        return true;
    }

    return false;
}

/**
 * Determine required sections for a file based on its path and type.
 *
 * @return string[]
 */
function getRequiredSections(string $relativePath): array
{
    // Special cases: testing, ops, configuration, modules, symfony-*, architecture
    // These have varying structures — require only what's common.
    if (str_starts_with($relativePath, 'testing/')) {
        return []; // testing docs have free-form structure
    }
    if (str_starts_with($relativePath, 'ops/')) {
        return []; // ops docs vary (index, fixes, smoke, phpmd)
    }
    if (str_starts_with($relativePath, 'modules/')) {
        return [];
    }
    if (str_starts_with($relativePath, 'configuration/')) {
        return [];
    }
    if (str_starts_with($relativePath, 'architecture/')) {
        return [];
    }
    if (str_starts_with($relativePath, 'symfony-')) {
        return ['Общие правила', 'Пример'];
    }
    if (str_starts_with($relativePath, 'principles/')) {
        return [];
    }

    // Tiny summary / overview files
    if (str_contains($relativePath, 'use-case.md')) {
        return [];
    }

    // Handler docs use "Пример команды/запроса" instead of plain "Пример"
    if (str_contains($relativePath, 'command-handler.md') || str_contains($relativePath, 'query-handler.md')) {
        return [];
    }

    // Authorization has no code example section
    if (str_contains($relativePath, 'authorization.md')) {
        return ['Общие правила'];
    }

    // Presentation.md is an overview/index of the layer
    if (str_contains($relativePath, 'presentation/presentation.md')) {
        return ['Общие правила'];
    }

    // event.md
    if (str_contains($relativePath, '/event.md')) {
        return ['Общие правила', 'Пример'];
    }

    // Layer overview files (layers/domain.md, layers/infrastructure.md, etc.)
    if (isOverviewFile($relativePath)) {
        return [];
    }

    // Examples directory
    if (str_contains($relativePath, '/examples/')) {
        return [];
    }

    // criteria-mapper has "Пример: ..." headings
    if (str_contains($relativePath, 'criteria-mapper.md')) {
        return ['Общие правила'];
    }

    return ['Общие правила', 'Пример'];
}

/**
 * Extract all internal markdown links from content.
 * Returns array of ['text' => string, 'target' => string, 'line' => int].
 *
 * @return array<int, array{text: string, target: string, line: int}>
 */
function extractInternalLinks(string $content): array
{
    $links = [];
    $lines = explode("\n", $content);

    foreach ($lines as $lineNum => $line) {
        // Match [text](target) — skip external URLs and anchors
        if (!preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $target = $match[2];
            // Skip external URLs, mailto:, anchors, images
            if (preg_match('/^(https?:|mailto:|#|tel:)/', $target)) {
                continue;
            }
            // Skip template placeholders
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
    // Remove anchor
    $path = preg_replace('/#.*/', '', $target);
    if ($path === '') {
        return ''; // pure anchor
    }

    $resolved = rtrim($sourceDir, '/') . '/' . $path;

    // Normalize ../
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
    // Check files and directories for underscores (excluding vendor-ish names)
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
            continue; // pure anchor
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
    $basename = strtolower(basename($filePath));

    // Skip exempt files
    if (in_array($basename, $EXEMPT_BASENAMES, true)) {
        continue;
    }

    $required = getRequiredSections($relativePath);
    if (empty($required)) {
        continue;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $headings = extractHeadings($content);

    foreach ($required as $heading) {
        // Check for exact match or match ignoring trailing punctuation differences
        $found = false;
        foreach ($headings as $h) {
            // Normalize: remove trailing punctuation variations
            $normalizedH = rtrim($h, ' .');
            $normalizedRequired = rtrim($heading, ' .');
            if (mb_strtolower($normalizedH) === mb_strtolower($normalizedRequired)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo "  ✗ {$relativePath}: missing section \"## {$heading}\"\n";
            $errors[] = "section: {$relativePath} missing \"## {$heading}\"";
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
    echo "  Naming:  {$namingErrors}\n";
    echo "  Links:   {$linkErrors}\n";
    echo "  Sections: {$sectionErrors}\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ All checks passed\n";
exit(0);
