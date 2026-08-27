<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Di;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * @internal Collects declared classes with their constructor parameter types.
 */
final class ClassCollectorVisitor extends NodeVisitorAbstract
{
    /** @var list<ScannedClass> */
    private array $classes = [];

    public function __construct(
        private readonly string $file,
    ) {
    }

    /** @return list<ScannedClass> */
    public function classes(): array
    {
        return $this->classes;
    }

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        // Named classes are collected in leaveNode, after the whole subtree
        // has been traversed, so that NameResolver (running before this
        // visitor) has already rewritten parameter types to fully qualified
        // names. Anonymous classes are skipped entirely.
        if ($node->isAnonymous() || $node->name === null || $node->namespacedName === null) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if (
            !$node instanceof Node\Stmt\Class_
            || $node->isAnonymous()
            || $node->name === null
            || $node->namespacedName === null
        ) {
            return null;
        }

        $this->classes[] = new ScannedClass(
            $node->namespacedName->toString(),
            $this->file,
            $node->getStartLine(),
            $this->collectConstructorParams($node),
        );

        return null;
    }

    /**
     * @return list<array{name: string, typeFqcns: list<string>, line: int}>
     */
    private function collectConstructorParams(Node\Stmt\Class_ $class): array
    {
        $constructor = $class->getMethod('__construct');
        if ($constructor === null) {
            return [];
        }

        $params = [];
        foreach ($constructor->params as $param) {
            $typeFqcns = [];
            $this->collectTypeNames($param->type, $typeFqcns);
            if ($typeFqcns === []) {
                continue;
            }

            $params[] = [
                'name' => $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                    ? $param->var->name
                    : '?',
                'typeFqcns' => $typeFqcns,
                'line' => $param->getStartLine(),
            ];
        }

        return $params;
    }

    /** @param list<string> $names */
    private function collectTypeNames(?Node $type, array &$names): void
    {
        if ($type === null) {
            return;
        }

        if ($type instanceof Node\NullableType) {
            $this->collectTypeNames($type->type, $names);

            return;
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $inner) {
                $this->collectTypeNames($inner, $names);
            }

            return;
        }

        if ($type instanceof Node\Name) {
            $resolved = $type->getAttribute('resolvedName');
            $name = $resolved instanceof Node\Name ? $resolved->toString() : $type->toString();
            if (!in_array($name, ['self', 'static', 'parent'], true)) {
                $names[] = ltrim($name, '\\');
            }
        }
    }
}
