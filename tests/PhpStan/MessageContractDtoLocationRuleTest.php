<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PrikotovCodingStandard\PhpStan\MessageContractDtoCollector;
use PrikotovCodingStandard\PhpStan\MessageContractDtoLocationRule;

/**
 * @extends RuleTestCase<MessageContractDtoLocationRule>
 */
final class MessageContractDtoLocationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MessageContractDtoLocationRule();
    }

    protected function getCollectors(): array
    {
        return [new MessageContractDtoCollector()];
    }

    public function testServiceScopedDtoInCommandIsFlagged(): void
    {
        // DTO в service-неймспейсе, передан в поле Command → внешний контракт
        // утащил в service-неймспейс → ошибка.
        $this->analyse([
            __DIR__ . '/data/message-misplaced/SourceEventDto.php',
            __DIR__ . '/data/message-misplaced/DeliverCommand.php',
        ], [
            [
                'DTO Test\DtoReuse\MessageMisplaced\Module\Billing\Application\Service\SourceEvent\SourceEventDto'
                . ' передаётся в поле класса-сообщения и является частью внешнего контракта.'
                . ' Такой DTO должен быть shared (Module\{ModuleName}\Application\Dto) либо рядом'
                . ' с use case (UseCase\{Case}\), а не лежать в service-неймспейсе.'
                . ' See: docs/conventions/core-patterns/dto.md',
                11,
            ],
        ]);
    }

    public function testSharedDtoInCommandIsNotFlagged(): void
    {
        // DTO в Application\Dto (shared), передан в поле Command → корректно.
        $this->analyse([
            __DIR__ . '/data/message-contract/EventPayloadDto.php',
            __DIR__ . '/data/message-contract/DeliverCommand.php',
        ], []);
    }
}
