<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Собирает DTO-параметры конструктора классов-сообщений use case (*Command/*Query):
 * DTO, переданный в поле сообщения, — часть внешнего контракта. Для таких DTO
 * проверяется расположение (см. MessageContractDtoLocationRule): они должны быть
 * shared (Application\Dto) либо use-case-scoped (UseCase\{Case}\), но не лежать
 * в service/integration-неймспейсе.
 *
 * @implements Collector<Param, array{commandFile: string, commandLine: int, dto: non-falsy-string}>
 */
final class MessageContractDtoCollector implements Collector
{
    public function getNodeType(): string
    {
        return Param::class;
    }

    /**
     * @return array{commandFile: string, commandLine: int, dto: non-falsy-string}|null
     */
    public function processNode(Node $node, Scope $scope)
    {
        if (DtoReferenceCollector::isMessageContract($scope) === false) {
            return null;
        }

        $type = $node->type;
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Name === false) {
            return null;
        }

        $fqcn = $scope->resolveName($type);
        if (str_contains($fqcn, '\\') === false || str_ends_with($fqcn, 'Dto') === false) {
            return null;
        }

        return [
            'commandFile' => $scope->getFile(),
            'commandLine' => $node->getLine(),
            'dto' => $fqcn,
        ];
    }
}
