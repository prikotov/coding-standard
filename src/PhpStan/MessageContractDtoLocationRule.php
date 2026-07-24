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
 * Проверяет расположение DTO, переданных в поле класса-сообщения use case
 * (*Command/*Query): DTO внешнего контракта должен быть shared (Application\Dto)
 * либо use-case-scoped (UseCase\{Case}\). DTO, лежащий в service/integration-
 * неймспейсе, не может быть частью внешнего контракта — это leaky зависимость.
 *
 * @implements Rule<CollectedDataNode>
 */
final class MessageContractDtoLocationRule implements Rule
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
        $errors = [];
        foreach ($node->get(MessageContractDtoCollector::class) as $fileItems) {
            foreach ($fileItems as $item) {
                $dto = $item['dto'];
                $lower = strtolower($dto);

                // shared модуля или use-case-scoped — корректное место для DTO контракта.
                if (
                    str_contains($lower, '\\application\\dto\\') === true
                    || str_contains($lower, '\\usecase\\') === true
                ) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'DTO %s передаётся в поле класса-сообщения и является частью внешнего'
                    . ' контракта. Такой DTO должен быть shared (Module\\{ModuleName}\\Application\\Dto)'
                    . ' либо рядом с use case (UseCase\\{Case}\\), а не лежать в service-неймспейсе.' . self::DOC_REF,
                    $dto,
                ))
                    ->file($item['commandFile'])
                    ->line($item['commandLine'])
                    ->identifier('prikotov.dtoLocation.messageContractShared')
                    ->build();
            }
        }

        return $errors;
    }
}
