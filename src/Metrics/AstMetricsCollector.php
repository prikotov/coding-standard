<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength

namespace PrikotovCodingStandard\Metrics;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AstMetricsCollector
{
    public function __construct(private readonly \PhpParser\Parser $parser, private readonly string $source)
    {
    }

    /** @return array<string, mixed> */
    public function collect(): array
    {
        $classes = [];
        $functions = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->source));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            [$fileClasses, $fileFunctions] = $this->collectFile($file->getPathname());
            array_push($classes, ...$fileClasses);
            array_push($functions, ...$fileFunctions);
        }
        usort($classes, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        usort($functions, static fn (array $left, array $right): int => $left['metrics']['classInfo'] <=> $right['metrics']['classInfo']);

        return ['schema_version' => '1.0', 'toolVersion' => 'metrics-collector/1.0', 'classes' => $classes, 'functions' => $functions];
    }

    /** @return array{list<array<string,mixed>>,list<array<string,mixed>>} */
    private function collectFile(string $path): array
    {
        $ast = $this->parser->parse((string) file_get_contents($path));
        if ($ast === null) {
            return [[], []];
        }
        $traverser = new NodeTraverser();
        $visitor = new AstNamespaceVisitor();
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);
        $finder = new NodeFinder();
        $classes = [];
        $functions = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
            if ($class instanceof Node\Stmt\Class_ && $class->isAnonymous()) {
                continue;
            }
            $name = $class->getAttribute('metrics_name');
            if (!is_string($name)) {
                continue;
            }
            $methods = $class->getMethods();
            $classes[] = ['name' => $name, 'metrics' => [
                'filePath' => $this->relativePath($path),
                'loc' => $class->getEndLine() - $class->getStartLine() + 1,
                'methodCount' => count($methods),
                'propertyCount' => count($class->getProperties()),
                'lcom' => $this->lcom4($methods),
                'interface' => $class instanceof Node\Stmt\Interface_,
                'trait' => $class instanceof Node\Stmt\Trait_,
                'enum' => $class instanceof Node\Stmt\Enum_,
            ]];
            foreach ($methods as $method) {
                $functions[] = ['name' => $method->name->toString(), 'type' => 'method', 'metrics' => [
                    'classInfo' => 'collector, ' . $name,
                    'loc' => $method->getEndLine() - $method->getStartLine() + 1,
                    'cc' => $this->complexity($method),
                ]];
            }
        }

        return [$classes, $functions];
    }

    /** @param list<Node\Stmt\ClassMethod> $methods */
    private function lcom4(array $methods): int
    {
        $methods = array_values(array_filter($methods, static fn (Node\Stmt\ClassMethod $method): bool => !$method->isStatic() && $method->name->toString() !== '__construct'));
        if ($methods === []) {
            return 0;
        }
        $finder = new NodeFinder();
        $uses = [];
        foreach ($methods as $index => $method) {
            $uses[$index] = [];
            foreach ($finder->findInstanceOf($method->stmts ?? [], Node\Expr\PropertyFetch::class) as $fetch) {
                if ($fetch->var instanceof Node\Expr\Variable && $fetch->var->name === 'this' && $fetch->name instanceof Node\Identifier) {
                    $uses[$index][$fetch->name->toString()] = true;
                }
            }
            foreach ($finder->findInstanceOf($method->stmts ?? [], Node\Expr\MethodCall::class) as $call) {
                if ($call->var instanceof Node\Expr\Variable && $call->var->name === 'this' && $call->name instanceof Node\Identifier) {
                    $uses[$index]['@' . $call->name->toString()] = true;
                }
            }
        }
        $seen = [];
        $components = 0;
        foreach (array_keys($methods) as $index) {
            if (isset($seen[$index])) {
                continue;
            }
            $components++;
            $stack = [$index];
            while ($stack !== []) {
                $current = array_pop($stack);
                if (isset($seen[$current])) {
                    continue;
                }
                $seen[$current] = true;
                foreach ($uses as $other => $otherUses) {
                    if (!isset($seen[$other]) && array_intersect_key($uses[$current], $otherUses) !== []) {
                        $stack[] = $other;
                    }
                }
            }
        }
        return $components;
    }

    private function complexity(Node\Stmt\ClassMethod $method): int
    {
        $branches = [Node\Stmt\If_::class, Node\Stmt\ElseIf_::class, Node\Stmt\For_::class, Node\Stmt\Foreach_::class, Node\Stmt\While_::class, Node\Stmt\Do_::class, Node\Stmt\Catch_::class, Node\Stmt\Case_::class, Node\Expr\Ternary::class, Node\Expr\BinaryOp\BooleanAnd::class, Node\Expr\BinaryOp\BooleanOr::class, Node\Expr\BinaryOp\LogicalAnd::class, Node\Expr\BinaryOp\LogicalOr::class, Node\MatchArm::class];
        $finder = new NodeFinder();
        return 1 + count($finder->find($method->stmts ?? [], static fn (Node $node): bool => in_array($node::class, $branches, true)));
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
