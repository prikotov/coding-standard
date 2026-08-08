<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Metrics;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class AstNamespaceVisitor extends NodeVisitorAbstract
{
    private string $namespace = '';

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name?->toString() ?? '';
        }
        if ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
            $node->setAttribute('metrics_name', ltrim($this->namespace . '\\' . $node->name->toString(), '\\'));
        }
        return null;
    }
}
