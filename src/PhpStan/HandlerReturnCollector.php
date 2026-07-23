<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\Type;

/**
 * Собирает return-тип метода __invoke() query/command-handler'ов
 * (классы в namespace ...\UseCase\{Query|Command}\...).
 *
 * Return-тип резолвится через Scope в FQCN. Must Have — прямой и nullable
 * FQCN (: XxxDto, : ?XxxDto). Collections и параметры — Won't Have.
 *
 * @implements Collector<ClassMethod, array{handler: non-empty-string, returns: list<non-empty-string>}>
 */
final class HandlerReturnCollector implements Collector
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @return array{handler: non-empty-string, returns: list<non-empty-string>}|null
     */
    public function processNode(Node $node, Scope $scope)
    {
        if (($node->name->name ?? null) !== '__invoke') {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return null;
        }

        $className = $classReflection->getName();
        if (self::isUseCaseHandler($className) === false) {
            return null;
        }

        $returns = $this->extractReturnFqcns($node, $scope);
        if ($returns === []) {
            return null;
        }

        return ['handler' => $className, 'returns' => $returns];
    }

    public static function isUseCaseHandler(string $className): bool
    {
        $lower = strtolower($className);

        return str_contains($lower, '\\usecase\\query\\')
            || str_contains($lower, '\\usecase\\command\\');
    }

    /**
     * @return list<non-empty-string>
     */
    private function extractReturnFqcns(ClassMethod $node, Scope $scope): array
    {
        $typeNode = $node->getReturnType();
        if ($typeNode === null) {
            return [];
        }

        // Must Have: прямой FQCN или nullable FQCN. Union/intersection — Won't Have.
        if ($typeNode instanceof NullableType) {
            $typeNode = $typeNode->type;
        }

        if ($typeNode instanceof Name) {
            return $this->fqcnsFromType($scope->resolveTypeByName($typeNode));
        }

        return [];
    }

    /**
     * @return list<non-empty-string>
     */
    private function fqcnsFromType(Type $type): array
    {
        $classNames = $type->getObjectClassNames();

        return $classNames === [] ? [] : $classNames;
    }
}
