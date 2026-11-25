<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\DomainTypeCatalogRule;

final class DomainTypeCatalogRuleTest extends TestCase
{
    #[DataProvider('provideViolations')]
    public function testFlagsClassOutsideTheCatalog(string $className): void
    {
        self::assertNotNull(
            DomainTypeCatalogRule::classify($className),
            'Class must be flagged: ' . $className,
        );
    }

    #[DataProvider('provideAllowed')]
    public function testAcceptsClassInsideTheCatalog(string $className): void
    {
        self::assertNull(
            DomainTypeCatalogRule::classify($className),
            'Class must be accepted: ' . $className,
        );
    }

    public function testViolationMessageNamesTheDirectory(): void
    {
        $message = DomainTypeCatalogRule::classify(
            'TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Chain\ExecutionStrategyInterface',
        );

        self::assertIsString($message);
        self::assertStringContainsString('Contract', $message);
        self::assertStringContainsString('closed', $message);
    }

    /** @return iterable<string, array{string}> */
    public static function provideViolations(): iterable
    {
        yield 'class directly in Domain root (stocks2 FieldNormalizer)' => ['Stocks2\Common\Module\Maintenance\Domain\TransactionFieldNormalizer'];
        yield 'class in Domain root in another module' => ['Stocks2\Common\Module\Income\Domain\DohodDividendFieldNormalizer'];
        yield 'ValueObject class without Vo suffix (TasK CleanupErrorList)' => ['Task\Common\Module\Source\Domain\ValueObject\CleanupErrorList'];
        yield 'unknown directory under Domain (task-orchestrator Contract)' => ['TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Chain\ExecutionStrategyInterface'];
        yield 'nested ValueObject class without Vo suffix' => ['Task\Common\Module\Source\Domain\ValueObject\SourceVariant\CanonicalProfileParametersInterface'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideAllowed(): iterable
    {
        yield 'interface directly in Domain root' => ['TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface'];
        yield 'entity' => ['Task\Common\Module\Billing\Domain\Entity\PaymentModel'];
        yield 'service' => ['Task\Common\Module\Billing\Domain\Service\Pay\PayService'];
        yield 'repository' => ['Task\Common\Module\Billing\Domain\Repository\Payment\PaymentRepository'];
        yield 'value object' => ['Task\Common\Module\Billing\Domain\ValueObject\MoneyVo'];
        yield 'enum' => ['Task\Common\Module\Billing\Domain\Enum\StatusEnum'];
        yield 'exception' => ['Task\Common\Module\Billing\Domain\Exception\InvalidAmountException'];
        yield 'specification' => ['Task\Common\Module\Billing\Domain\Specification\OverdueSpecification'];
        yield 'calculator' => ['Stocks2\Common\Module\Trading\Domain\Calculator\RateCalculator'];
        yield 'mapper' => ['Stocks2\Common\Module\Moex\Domain\Mapper\RateMapper'];
        yield 'strategy' => ['Task\Common\Module\Attribution\Domain\Strategy\AttributionStrategyInterface'];
        yield 'helper' => ['TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper\LoopHelper'];
        yield 'factory' => ['Task\Common\Module\Billing\Domain\Factory\PaymentCreateFactory'];
        yield 'not a domain class' => ['Task\Common\Module\Billing\Application\Service\PayService'];
    }
}
