<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength

namespace PrikotovCodingStandard\Metrics;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
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
        $dependencies = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->source));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            [$fileClasses, $fileFunctions, $fileDependencies] = $this->collectFile($file->getPathname());
            array_push($classes, ...$fileClasses);
            array_push($functions, ...$fileFunctions);
            array_push($dependencies, ...$fileDependencies);
        }
        usort($classes, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        usort($functions, static fn (array $left, array $right): int => $left['metrics']['classInfo'] <=> $right['metrics']['classInfo']);
        usort($dependencies, static fn (array $left, array $right): int => [$left['source'], $left['target']] <=> [$right['source'], $right['target']]);

        return ['schema_version' => '1.0', 'toolVersion' => 'metrics-collector/1.2', 'classes' => $classes, 'functions' => $functions, 'dependencies' => $dependencies];
    }

    /** @return array{list<array<string,mixed>>,list<array<string,mixed>>,list<array{source:string,target:string}>} */
    private function collectFile(string $path): array
    {
        $ast = $this->parser->parse((string) file_get_contents($path));
        if ($ast === null) {
            return [[], [], []];
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $visitor = new AstNamespaceVisitor();
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);
        $finder = new NodeFinder();
        $classes = [];
        $functions = [];
        $dependencies = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
            if ($class instanceof Node\Stmt\Class_ && $class->isAnonymous()) {
                continue;
            }
            $name = $class->getAttribute('metrics_name');
            if (!is_string($name)) {
                continue;
            }
            $methods = $class->getMethods();
            foreach ($finder->findInstanceOf($class, Node\Name::class) as $target) {
                $targetName = $target->toString();
                if ($target->isSpecialClassName() || $targetName === $name) {
                    continue;
                }
                $dependencies[$name . "\0" . $targetName] = ['source' => $name, 'target' => $targetName];
            }
            $classes[] = ['name' => $name, 'metrics' => [
                'filePath' => $this->relativePath($path),
                'loc' => $class->getEndLine() - $class->getStartLine() + 1,
                'methodCount' => count($methods),
                'propertyCount' => count($class->getProperties()),
                'lcom' => $this->lcom4($methods),
                'interface' => $class instanceof Node\Stmt\Interface_,
                'trait' => $class instanceof Node\Stmt\Trait_,
                'enum' => $class instanceof Node\Stmt\Enum_,
                'commandHandlerDispatchesEvent' => $class instanceof Node\Stmt\Class_ ? $this->commandHandlerDispatchesEvent($class, $this->relativePath($path)) : null,
            ]];
            foreach ($methods as $method) {
                $functions[] = ['name' => $method->name->toString(), 'type' => 'method', 'metrics' => [
                    'classInfo' => 'collector, ' . $name,
                    'loc' => $method->getEndLine() - $method->getStartLine() + 1,
                    'cc' => $this->complexity($method),
                ]];
            }
        }

        return [$classes, $functions, array_values($dependencies)];
    }

    /**
     * Маркер CommandHandler'а из конвенции command-handler.md: класс *CommandHandler
     * в Application/UseCase/Command/. Детектор фиксирует факт наличия вызова dispatch
     * (диспетчеризация события). null — класс не является CommandHandler'ом.
     */
    private function commandHandlerDispatchesEvent(Node\Stmt\Class_ $class, string $filePath): ?bool
    {
        $name = $class->name->name;
        if (!str_ends_with($name, 'CommandHandler')) {
            return null;
        }
        if (!str_contains($filePath, 'Application/UseCase/Command/')) {
            return null;
        }

        foreach ([Node\Expr\MethodCall::class, Node\Expr\NullsafeMethodCall::class] as $callType) {
            foreach ((new NodeFinder())->findInstanceOf($class, $callType) as $call) {
                if ($call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'dispatch') {
                    return true;
                }
            }
        }

        return false;
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
