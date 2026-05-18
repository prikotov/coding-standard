#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * validate-md-links — validate internal Markdown links.
 *
 * Checks that internal links (relative paths and anchors) in Markdown files
 * point to existing files and valid anchors. External URLs, mailto: and
 * images are skipped. Links inside fenced code blocks are ignored.
 *
 * Usage:
 *   php bin/validate-md-links.php [path...] [--no-fail]
 *
 *   paths    — files or directories to scan (default: docs/ README.md AGENTS.md).
 *   --no-fail — exit 0 even if errors found (useful for gradual adoption).
 *
 * Exit codes:
 *   0 — no errors (or --no-fail)
 *   1 — broken links found
 */

// ── Config ─────────────────────────────────────────────────────────────────

$DEFAULT_PATHS = ['docs/', 'README.md', 'AGENTS.md'];
$SKIP_DIRS = ['vendor/', '.git/', 'var/', 'tmp/', 'cache/', 'node_modules/'];
$NO_FAIL = false;
$EXCLUDE_PATTERNS = [];

// Parse arguments
$paths = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--no-fail') {
        $NO_FAIL = true;
    } elseif (str_starts_with($arg, '--exclude=')) {
        $EXCLUDE_PATTERNS[] = substr($arg, strlen('--exclude='));
    } else {
        $paths[] = $arg;
    }
}
if ($paths === []) {
    $paths = $DEFAULT_PATHS;
}

$errors = [];

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Strip fenced code blocks (``` and ~~~) from content.
 * Preserves line count so line numbers remain accurate.
 */
function stripFencedCodeBlocks(string $content): string
{
    return preg_replace_callback(
        '/^([`~]{3,})([^\n]*\n)(.*?)^\1/ms',
        static function (array $m): string {
            $lines = substr_count($m[0], "\n");
            return str_repeat("\n", $lines);
        },
        $content,
    ) ?? $content;
}

/**
 * Strip YAML front matter (---...---) from content.
 * Preserves line count so line numbers remain accurate.
 */
function stripFrontMatter(string $content): string
{
    if (!str_starts_with($content, "---\n")) {
        return $content;
    }
    $end = strpos($content, "\n---\n", 4);
    if ($end === false) {
        return $content;
    }
    $matterLines = substr_count(substr($content, 0, $end + 5), "\n");

    return str_repeat("\n", $matterLines) . substr($content, $end + 5);
}

/**
 * Extract reference-style link definitions from content.
 * Returns map: [id => target].
 *
 * @return array<string, string>
 */
function extractReferenceDefinitions(string $content): array
{
    $refs = [];
    if (!preg_match_all('/^\[([^\]]+)\]:\s+(\S+)/m', $content, $matches, PREG_SET_ORDER)) {
        return $refs;
    }
    foreach ($matches as $match) {
        $id = mb_strtolower(trim($match[1]));
        $refs[$id] = trim($match[2]);
    }

    return $refs;
}

/**
 * Extract inline links `[text](target)` from a single line.
 * Skips images `![alt](src)`.
 *
 * @return array<int, array{text: string, target: string, col: int}>
 */
function extractInlineLinksFromLine(string $line): array
{
    // Skip inline code: `...`
    $line = preg_replace('/`[^`]*`/', '', $line);
    // Skip image links: ![alt](src)
    $line = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '', $line);

    $links = [];
    if (!preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $links;
    }
    foreach ($matches as $match) {
        $target = trim($match[2][0]);
        // Skip external URLs
        if (preg_match('/^(https?:|mailto:|tel:|ftp:|\/\/)/', $target)) {
            continue;
        }
        // Skip template variables and vendor paths
        if (str_contains($target, '{') || str_starts_with($target, 'vendor/')) {
            continue;
        }
        $links[] = [
            'text' => $match[1][0],
            'target' => $target,
            'col' => $match[0][1],
        ];
    }

    return $links;
}

/**
 * Extract reference-style link usages `[text][id]` from a single line.
 *
 * @return array<int, array{text: string, ref_id: string, col: int}>
 */
function extractRefLinksFromLine(string $line): array
{
    $links = [];
    // Match [text][id] but not [text][] (implicit) and not [text] (no ref)
    if (!preg_match_all('/\[([^\]]+)\]\[([^\]]+)\]/', $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $links;
    }
    foreach ($matches as $match) {
        $refId = trim($match[2][0]);
        if ($refId === '') {
            // Implicit reference: [text][] — use text as id
            $refId = trim($match[1][0]);
        }
        $links[] = [
            'text' => $match[1][0],
            'ref_id' => $refId,
            'col' => $match[0][1],
        ];
    }

    return $links;
}

/**
 * Resolve a relative path against a base directory.
 * Returns the resolved absolute path.
 */
function resolvePath(string $baseDir, string $relativePath): string
{
    $path = preg_replace('/#.*/', '', $relativePath);
    if ($path === '' || $path === false) {
        return '';
    }

    // URL-decode the path
    $path = rawurldecode($path);

    // Strip query string
    $path = preg_replace('/\?.*$/', '', $path);

    $resolved = rtrim($baseDir, '/') . '/' . $path;

    $parts = explode('/', $resolved);
    $normalized = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($normalized);
        } elseif ($part !== '' && $part !== '.') {
            $normalized[] = $part;
        }
    }

    return '/' . implode('/', $normalized);
}

/**
 * Extract the anchor (fragment) from a link target.
 * Returns empty string if no anchor.
 */
function extractAnchor(string $target): string
{
    if (!str_contains($target, '#')) {
        return '';
    }

    return rawurldecode(explode('#', $target)[1]);
}

/**
 * Generate a GitHub-compatible anchor slug from a heading.
 */
function generateSlug(string $heading): string
{
    $slug = trim($heading);
    // Lowercase
    $slug = mb_strtolower($slug);
    // Remove anything that's not a letter, number, space, or hyphen
    // \p{L} = any letter (unicode), \p{N} = any number
    $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
    // Replace whitespace with hyphens
    $slug = preg_replace('/\s+/', '-', $slug);
    // Collapse consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    // Trim hyphens
    $slug = trim($slug, '-');

    return $slug ?? '';
}

/**
 * Build an anchor index from file content.
 * Returns map: [slug => true] (all anchors including deduplicated ones).
 *
 * @return array<string, true>
 */
function buildAnchorIndex(string $content): array
{
    // Strip front matter and code blocks before extracting headings
    $content = stripFrontMatter($content);
    $content = stripFencedCodeBlocks($content);

    $anchors = [];
    $slugCounts = [];

    foreach (explode("\n", $content) as $line) {
        // Match ATX headings: # ... ######
        if (!preg_match('/^#{1,6}\s+(.+)$/', $line, $m)) {
            continue;
        }
        $slug = generateSlug($m[1]);
        if ($slug === '') {
            continue;
        }

        // Handle duplicates: first occurrence gets no suffix, subsequent get -1, -2, ...
        if (!isset($slugCounts[$slug])) {
            $slugCounts[$slug] = 0;
            $anchors[$slug] = true;
        } else {
            $slugCounts[$slug]++;
            $anchors[$slug . '-' . $slugCounts[$slug]] = true;
        }
    }

    return $anchors;
}

/**
 * Collect all .md files from given paths (files or directories).
 *
 * @param string[] $paths
 * @return string[]
 */
function collectMdFiles(array $paths): array
{
    global $SKIP_DIRS, $EXCLUDE_PATTERNS;

    $files = [];
    foreach ($paths as $path) {
        if (is_file($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === 'md') {
                $files[] = realpath($path) ?: $path;
            }
            continue;
        }
        if (!is_dir($path)) {
            echo "  Warning: path not found: {$path}\n";
            continue;
        }

        $realPath = realpath($path);
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iter as $item) {
            if (!$item->isFile() || $item->getExtension() !== 'md') {
                continue;
            }
            $fullPath = $item->getPathname();

            // Skip excluded directories
            foreach ($SKIP_DIRS as $skip) {
                if (str_contains($fullPath, "/{$skip}") || str_starts_with($fullPath, $skip)) {
                    continue 2;
                }
            }

            // Skip excluded directory patterns
            foreach ($EXCLUDE_PATTERNS as $pattern) {
                if (str_contains($fullPath, $pattern)) {
                    continue 2;
                }
            }

            $files[] = $fullPath;
        }
    }
    sort($files);

    return array_unique($files);
}

// ── Main validation logic ──────────────────────────────────────────────────

/**
 * Validate a single Markdown file. Returns array of error strings.
 *
 * @param string $filePath Absolute path to the .md file
 * @return array<int, array{file: string, line: int, type: string, message: string, target: string}>
 */
function validateFile(string $filePath, string $projectRoot): array
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }

    $relativePath = ltrim(substr($filePath, strlen($projectRoot)), '/');
    $fileErrors = [];
    $sourceDir = dirname($filePath);

    // Strip fenced code blocks, preserving line numbers
    $stripped = stripFencedCodeBlocks($content);
    // Strip front matter, preserving line numbers
    $stripped = stripFrontMatter($stripped);

    // Build reference definitions from the full (stripped) content
    $refDefs = extractReferenceDefinitions($stripped);

    // Process line by line
    $lines = explode("\n", $stripped);
    foreach ($lines as $lineNum => $line) {
        $humanLine = $lineNum + 1;

        // Skip reference definition lines themselves
        if (preg_match('/^\[([^\]]+)\]:\s+\S+/', $line)) {
            continue;
        }

        // Inline links: [text](target)
        foreach (extractInlineLinksFromLine($line) as $link) {
            $fileErrors = array_merge($fileErrors, validateLink(
                $link['target'],
                $relativePath,
                $humanLine,
                $sourceDir,
                $filePath,
            ));
        }

        // Reference-style links: [text][id]
        foreach (extractRefLinksFromLine($line) as $link) {
            $id = mb_strtolower($link['ref_id']);
            if (!isset($refDefs[$id])) {
                $fileErrors[] = [
                    'file' => $relativePath,
                    'line' => $humanLine,
                    'type' => 'broken-ref',
                    'message' => "undefined reference [{$link['ref_id']}]",
                    'target' => "[{$link['ref_id']}]",
                ];
                continue;
            }
            $fileErrors = array_merge($fileErrors, validateLink(
                $refDefs[$id],
                $relativePath,
                $humanLine,
                $sourceDir,
                $filePath,
            ));
        }
    }

    return $fileErrors;
}

/**
 * Validate a single link target.
 *
 * @return array<int, array{file: string, line: int, type: string, message: string, target: string}>
 */
function validateLink(
    string $target,
    string $relativePath,
    int $humanLine,
    string $sourceDir,
    string $sourceFilePath,
): array {
    $anchor = extractAnchor($target);
    $resolvedPath = resolvePath($sourceDir, $target);

    // Pure local anchor (#something) — check against current file
    if ($resolvedPath === '' && $anchor !== '') {
        $anchors = buildAnchorIndex(file_get_contents($sourceFilePath) ?: '');
        if (!isset($anchors[$anchor])) {
            return [[
                'file' => $relativePath,
                'line' => $humanLine,
                'type' => 'broken-anchor',
                'message' => "anchor not found: #{$anchor}",
                'target' => "#{$anchor}",
            ]];
        }

        return [];
    }

    if ($resolvedPath === '') {
        return [];
    }

    // Check file exists
    if (!file_exists($resolvedPath)) {
        return [[
            'file' => $relativePath,
            'line' => $humanLine,
            'type' => 'broken-link',
            'message' => "file not found: {$target}",
            'target' => $target,
        ]];
    }

    // Check anchor if present
    if ($anchor !== '' && is_file($resolvedPath)) {
        $targetContent = file_get_contents($resolvedPath);
        if ($targetContent === false) {
            return [];
        }
        $anchors = buildAnchorIndex($targetContent);
        if (!isset($anchors[$anchor])) {
            return [[
                'file' => $relativePath,
                'line' => $humanLine,
                'type' => 'broken-anchor',
                'message' => "anchor not found in " . basename($resolvedPath) . ": #{$anchor}",
                'target' => $target,
            ]];
        }
    }

    // If target is a directory, that's an error (no index.md resolution in MVP)
    if (is_dir($resolvedPath)) {
        return [[
            'file' => $relativePath,
            'line' => $humanLine,
            'type' => 'broken-link',
            'message' => "target is a directory, not a file: {$target}",
            'target' => $target,
        ]];
    }

    return [];
}

// ── Run ────────────────────────────────────────────────────────────────────

$projectRoot = getcwd() ?: '.';
$mdFiles = collectMdFiles($paths);

echo "Validating internal links in " . count($mdFiles) . " markdown files\n\n";

$totalErrors = 0;
$filesWithErrors = 0;

foreach ($mdFiles as $filePath) {
    $fileErrors = validateFile($filePath, $projectRoot);
    if ($fileErrors !== []) {
        $filesWithErrors++;
        foreach ($fileErrors as $err) {
            echo "  ✗ {$err['file']}:{$err['line']} {$err['type']}: {$err['message']}\n";
            $errors[] = $err;
            $totalErrors++;
        }
    }
}

if ($totalErrors === 0) {
    echo "  ✓ All internal links valid\n";
}
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if ($totalErrors > 0) {
    echo "✗ Found {$totalErrors} broken link(s) in {$filesWithErrors} file(s)\n";
    exit($NO_FAIL ? 0 : 1);
}

echo "✓ All checks passed\n";
exit(0);
