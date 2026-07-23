<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Помечает общие DTO модуля, используемые меньшим числом use case'ов, чем порог.
 *
 * Данные: из HandlerReturnCollector (handler → возвращаемые DTO) и
 * DtoLocationCollector (DTO в Application\Dto + позиция).
 * Cross-file aggregation делает PHPStan (collectors → rule на CollectedDataNode).
 *
 * Не зависит от имени DTO — только от фактического переиспользования.
 *
 * @implements Rule<CollectedDataNode>
 */
final class DtoReuseRule implements Rule
{
    private const DOC_REF = ' See: docs/conventions/core-patterns/dto.md';

    /**
     * @param positive-int $minUses Минимум use case'ов для «общего» DTO.
     */
    public function __construct(
        private readonly int $minUses,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $usages = $this->collectUsages($node);

        $errors = [];
        foreach ($node->get(DtoLocationCollector::class) as $fileItems) {
            foreach ($fileItems as $item) {
                $uses = count($usages[$item['dto']] ?? []);

                if ($uses >= $this->minUses) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'DTO %s в общем пуле Application\Dto используется %d use case\'ом(ами),'
                    . ' порог %d. Перенесите рядом с владельцем в UseCase\{Case}\.'
                    . self::DOC_REF,
                    $item['dto'],
                    $uses,
                    $this->minUses,
                ))
                    ->file($item['file'])
                    ->line($item['line'])
                    ->identifier('prikotov.dtoReuse.underused')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param CollectedDataNode $node
     *
     * @return array<non-empty-string, array<non-empty-string, true>>
     */
    private function collectUsages(Node $node): array
    {
        $usages = [];
        foreach ($node->get(HandlerReturnCollector::class) as $fileItems) {
            foreach ($fileItems as $item) {
                foreach ($item['returns'] as $dtoFqcn) {
                    $usages[$dtoFqcn][$item['handler']] = true;
                }
            }
        }

        return $usages;
    }
}
