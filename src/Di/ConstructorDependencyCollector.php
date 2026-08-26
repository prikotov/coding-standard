<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Scans PHP files under the given roots and reports every class together with
 * the class types injected through its constructor. Names are resolved to
 * fully qualified ones with php-parser's NameResolver (use statements and
 * namespaces included).
 */
final class ConstructorDependencyCollector
{
    /**
     * @param list<string> $roots
     *
     * @return list<ScannedClass>
     */
    public function collect(array $roots): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $classes = [];
        foreach ($this->findPhpFiles($roots) as $file) {
            try {
                $statements = $parser->parse((string) file_get_contents($file));
            } catch (Error) {
                continue;
            }

            if ($statements === null) {
                continue;
            }

            $visitor = new ClassCollectorVisitor($file);
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $traverser->addVisitor($visitor);
            $traverser->traverse($statements);

            $classes = [...$classes, ...$visitor->classes()];
        }

        return $classes;
    }

    /**
     * @param list<string> $roots
     *
     * @return list<string>
     */
    private function findPhpFiles(array $roots): array
    {
        $files = [];
        foreach (array_unique($roots) as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $directory = new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME,
            );
            $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($iterator as $path) {
                if (is_string($path) && str_ends_with($path, '.php')) {
                    // The same file can be reachable through several resource
                    // roots (application and module level) — index by real path
                    // to scan every class only once.
                    $files[(string) realpath($path)] = true;
                }
            }
        }

        $unique = array_keys($files);
        sort($unique);

        return $unique;
    }
}
