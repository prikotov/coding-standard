<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Собирает references на классы из общего пула DTO (`...\Application\Dto\...`)
 * и контекст: из файла какого use case'а идёт reference (null = shared/общий).
 *
 * Контекст — namespace содержащего файла: если он в `...\UseCase\{Query|Command}\{Case}\...`,
 * то это использование конкретным use case'ом; иначе (Mapper/Service/Integration/...)
 * — shared использование, делающее DTO общим.
 *
 * @implements Collector<Name, array{dto: non-falsy-string, useCase: string|null}>
 */
final class DtoReferenceCollector implements Collector
{
    public function getNodeType(): string
    {
        return Name::class;
    }

    /**
     * @return array{dto: non-falsy-string, useCase: string|null}|null
     */
    public function processNode(Node $node, Scope $scope)
    {
        $fqcn = $scope->resolveName($node);
        if (str_contains($fqcn, '\\') === false) {
            return null;
        }

        if (str_contains(strtolower($fqcn), '\\application\\dto\\') === false) {
            return null;
        }

        return [
            'dto' => $fqcn,
            'useCase' => self::extractUseCaseId($scope->getNamespace()),
        ];
    }

    public static function extractUseCaseId(?string $namespace): ?string
    {
        if ($namespace === null) {
            return null;
        }

        $lower = strtolower($namespace);
        if (preg_match('/\\\\usecase\\\\(query|command)\\\\([^\\\\]+)/', $lower, $matches) === 1) {
            return $matches[1] . '\\' . $matches[2];
        }

        return null;
    }
}
