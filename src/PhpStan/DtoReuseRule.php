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
 * Помечает общие DTO модуля, которые referenced только файлами одного use case'а
 * (и ничем общим) — use-case-специфичный DTO лежит в общем пуле не на своём месте.
 *
 * Данные: из DtoReferenceCollector (dto → useCase|null) и DtoLocationCollector
 * (DTO в Application\Dto + позиция). Cross-file aggregation делает PHPStan.
 *
 * Не зависит от имени DTO — только от фактических ссылок по коду.
 *
 * @implements Rule<CollectedDataNode>
 */
final class DtoReuseRule implements Rule
{
    private const DOC_REF = ' See: docs/conventions/core-patterns/dto.md';

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
                $data = $usages[$item['dto']] ?? ['useCases' => [], 'shared' => false];

                // use-case-специфичный: ровно один use case ссылается и нет shared.
                if (count($data['useCases']) !== 1 || $data['shared'] === true) {
                    continue;
                }

                $useCase = array_key_first($data['useCases']);
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'DTO %s в общем пуле Application\Dto используется только одним use case\'ом (%s).'
                    . ' Перенесите рядом с владельцем в UseCase\{Case}\.' . self::DOC_REF,
                    $item['dto'],
                    $useCase,
                ))
                    ->file($item['file'])
                    ->line($item['line'])
                    ->identifier('prikotov.dtoReuse.singleUseCase')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param CollectedDataNode $node
     *
     * @return array<non-empty-string, array{useCases: array<string, true>, shared: bool}>
     */
    private function collectUsages(Node $node): array
    {
        $usages = [];
        foreach ($node->get(DtoReferenceCollector::class) as $fileItems) {
            foreach ($fileItems as $item) {
                $dto = $item['dto'];
                if (isset($usages[$dto]) === false) {
                    $usages[$dto] = ['useCases' => [], 'shared' => false];
                }

                if ($item['useCase'] === null) {
                    $usages[$dto]['shared'] = true;
                } else {
                    $usages[$dto]['useCases'][$item['useCase']] = true;
                }
            }
        }

        return $usages;
    }
}
