<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Собирает DTO-классы в общем пуле модуля (namespace ...\Module\...\Application\Dto\...).
 * Возвращает FQCN + позицию объявления класса для выдачи ошибок.
 *
 * Корневой общий пул Common\Application\Dto не собирается — он заведомо общий.
 *
 * @implements Collector<Class_, array{dto: non-empty-string, file: string, line: int}>
 */
final class DtoLocationCollector implements Collector
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return array{dto: non-empty-string, file: string, line: int}|null
     */
    public function processNode(Node $node, Scope $scope)
    {
        $name = $node->namespacedName ?? null;
        if ($name instanceof Name) {
            $className = $name->toString();
        } elseif ($node->name !== null) {
            $className = $node->name->toString();
        } else {
            return null;
        }

        if (self::isModuleSharedDto($className) === false) {
            return null;
        }

        $file = $scope->getFile();

        return [
            'dto' => $className,
            'file' => $file,
            'line' => $node->getLine(),
        ];
    }

    public static function isModuleSharedDto(string $className): bool
    {
        $lower = strtolower($className);

        return str_contains($lower, '\\module\\')
            && str_contains($lower, '\\application\\dto\\');
    }
}
