<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PrikotovCodingStandard\PhpStan\DtoLocationCollector;
use PrikotovCodingStandard\PhpStan\DtoReuseRule;
use PrikotovCodingStandard\PhpStan\HandlerReturnCollector;

/**
 * @extends RuleTestCase<DtoReuseRule>
 */
final class DtoReuseRuleTest extends RuleTestCase
{
    private const MIN_USES = 2;

    protected function getRule(): Rule
    {
        return new DtoReuseRule(self::MIN_USES);
    }

    protected function getCollectors(): array
    {
        return [
            new HandlerReturnCollector(),
            new DtoLocationCollector(),
        ];
    }

    public function testUnderusedDtoIsFlagged(): void
    {
        // DTO в общем пуле, возвращается одним handler'ом → <2 → ошибка.
        $this->analyse([__DIR__ . '/data/underused/UnderusedResultDto.php', __DIR__ . '/data/underused/FooHandler.php'], [
            [
                'DTO Test\DtoReuse\Underused\Module\Billing\Application\Dto\UnderusedResultDto в общем пуле'
                . ' Application\Dto используется 1 use case\'ом(ами), порог 2. Перенесите рядом с владельцем'
                . ' в UseCase\{Case}\. See: docs/conventions/core-patterns/dto.md',
                3,
            ],
        ]);
    }

    public function testReusableDtoIsNotFlagged(): void
    {
        // DTO возвращается двумя handler'ами → ≥ порога → без ошибок.
        $this->analyse([__DIR__ . '/data/reusable/ReusableSummaryDto.php', __DIR__ . '/data/reusable/BarHandler.php', __DIR__ . '/data/reusable/BazHandler.php'], []);
    }

    public function testRootSharedPoolIsNotChecked(): void
    {
        // Корневой общий пул Common\Application\Dto — не проверяется.
        $this->analyse([__DIR__ . '/data/common/PaginationResultDto.php'], []);
    }
}
