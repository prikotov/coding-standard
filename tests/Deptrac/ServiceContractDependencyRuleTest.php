<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\ServiceContractDependencyRule;
use Qossmic\Deptrac\Contract\Analyser\AnalysisResult;
use Qossmic\Deptrac\Contract\Analyser\ProcessEvent;
use Qossmic\Deptrac\Contract\Ast\DependencyContext;
use Qossmic\Deptrac\Contract\Ast\DependencyType;
use Qossmic\Deptrac\Contract\Ast\FileOccurrence;
use Qossmic\Deptrac\Contract\Result\Violation;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeReference;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeToken;
use Qossmic\Deptrac\Core\Dependency\Dependency;

/**
 * @see ServiceContractDependencyRule
 */
final class ServiceContractDependencyRuleTest extends TestCase
{
    // --- Same module, allowed references ---

    public function testDomainUsesOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\Billing\Domain\Service\Invoice\CalculateInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testApplicationUsesOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\Billing\Domain\Service\Invoice\CalculateInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testApplicationUsesOwnApplicationServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testApplicationImplementsOwnApplicationServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceService',
            'App\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testApplicationImplementsOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceService',
            'App\Common\Module\Billing\Domain\Service\Invoice\PrepareInvoiceServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testInfrastructureUsesOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Infrastructure\Service\Invoice\StoreInvoiceService',
            'App\Common\Module\Billing\Domain\Service\Invoice\BuildInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testInfrastructureUsesOwnInfrastructureServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Infrastructure\Service\Invoice\StoreInvoiceService',
            'App\Common\Module\Billing\Infrastructure\Service\Lock\InvoiceLockServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationImplementsOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\FetchExchangeRateService',
            'App\Common\Module\Billing\Domain\Service\ExchangeRate\FetchExchangeRateServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationUsesOwnDomainServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\SyncExchangeRateService',
            'App\Common\Module\Billing\Domain\Service\ExchangeRate\FetchExchangeRateServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationUsesOwnApplicationServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Invoice\PublishInvoiceService',
            'App\Common\Module\Billing\Application\Service\Invoice\PublishInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationUsesOwnApplicationServiceClassWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Invoice\PublishInvoiceService',
            'App\Common\Module\Billing\Application\Service\Invoice\PublishInvoiceService',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationImplementsOwnIntegrationServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\FetchExchangeRateService',
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\FetchExchangeRateServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationUsesOwnIntegrationServiceInterfaceWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\SyncExchangeRateService',
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\FetchExchangeRateServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    // --- Cross-module violations ---

    public function testDomainUsesOtherModuleDomainServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testApplicationUsesOtherModuleDomainServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testApplicationUsesOtherModuleApplicationServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\User\Application\Service\Account\FindAccountServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testInfrastructureUsesOtherModuleInfrastructureServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Infrastructure\Service\Invoice\StoreInvoiceService',
            'App\Common\Module\User\Infrastructure\Service\Lock\UserLockServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testInfrastructureImplementsOtherModuleDomainServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Infrastructure\Service\Invoice\StoreInvoiceService',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testIntegrationUsesOtherModuleDomainServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Chat\Integration\Service\ProjectAccess\ProjectOwnerCheckerService',
            'App\Common\Module\Project\Domain\Service\OwnerChecker\OwnerCheckerServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testIntegrationUsesOtherModuleApplicationServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Invoice\PublishInvoiceService',
            'App\Common\Module\User\Application\Service\Account\FindAccountServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testIntegrationUsesOtherModuleApplicationServiceClassWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\User\Integration\Service\RegistrationBilling\InitializeRegistrationBillingService',
            'App\Common\Module\Billing\Application\Service\UserBilling\InitializeRegistrationBillingService',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    // --- Forbidden layer combinations (same module) ---

    public function testDomainUsesOwnApplicationServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testApplicationUsesOwnIntegrationServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\FetchExchangeRateServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testIntegrationImplementsApplicationServiceInterfaceWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Invoice\PublishInvoiceService',
            'App\Common\Module\Billing\Application\Service\Invoice\PublishInvoiceServiceInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    // --- use imports ignored ---

    public function testUseImportIsIgnoredWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\User\Application\Service\Account\FindAccountServiceInterface',
            DependencyType::USE,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testUseImportOfOtherModuleServiceClassIsIgnoredWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\User\Integration\Service\RegistrationBilling\InitializeRegistrationBillingService',
            'App\Common\Module\Billing\Application\Service\UserBilling\InitializeRegistrationBillingService',
            DependencyType::USE,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    // --- Non-service classes ignored ---

    public function testNonServiceClassIsIgnoredWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Domain\Entity\Invoice',
            'App\Common\Module\User\Domain\Entity\User',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    // --- Namespace without Common\Module is ignored ---

    public function testNonModuleClassIsIgnoredWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\SomeOther\Billing\Domain\Service\InvoiceService',
            'App\SomeOther\User\Domain\Service\UserServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    // --- Works with and without root namespace prefix ---

    public function testNoRootNamespacePrefixSameModuleWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'Common\Module\Billing\Domain\Service\Invoice\CalculateInvoiceServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testNoRootNamespacePrefixCrossModuleWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    // --- Non-standard interface naming (no ServiceInterface suffix) ---

    public function testIntegrationImplementsApplicationInterfaceWithoutServiceSuffixWithViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Payment\RouterPaymentNotificationUrlGenerator',
            'App\Common\Module\Billing\Application\Service\Payment\PaymentNotificationUrlGeneratorInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event));
    }

    public function testInfrastructureImplementsDomainInterfaceWithoutServiceSuffixWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Infrastructure\Service\Payment\SomePaymentUrlGenerator',
            'App\Common\Module\Billing\Domain\Service\Payment\PaymentUrlGeneratorInterface',
            DependencyType::INHERIT,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    public function testIntegrationUsesDomainInterfaceWithoutServiceSuffixWithoutViolation(): void
    {
        $event = $this->createProcessEvent(
            'App\Common\Module\Billing\Integration\Service\Payment\SomePaymentUrlGenerator',
            'App\Common\Module\Billing\Domain\Service\Payment\PaymentUrlGeneratorInterface',
            DependencyType::PARAMETER,
        );
        $rule = new ServiceContractDependencyRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event));
    }

    // --- ruleName / ruleDescription ---

    public function testRuleName(): void
    {
        $rule = new ServiceContractDependencyRule();

        self::assertSame('ServiceContractDependencyRule', $rule->ruleName());
    }

    public function testRuleDescription(): void
    {
        $rule = new ServiceContractDependencyRule();

        self::assertSame(
            'Service dependencies must stay inside the module; service interfaces must also be referenced or implemented only by allowed layers.',
            $rule->ruleDescription(),
        );
    }

    /**
     * @return array{module: string, layer: string, path: string}|null
     */
    private function extractModuleLayer(string $className): ?string
    {
        if (
            1 !== preg_match(
                '/^(?:[A-Za-z_]+\\\\)?Common\\\\Module\\\\[A-Za-z][A-Za-z0-9]*\\\\(?P<layer>Domain|Application|Infrastructure|Integration)\\\\/',
                $className,
                $matches,
            )
        ) {
            return null;
        }

        return $matches['layer'];
    }

    private function createProcessEvent(
        string $depender,
        string $dependent,
        DependencyType $dependencyType,
    ): ProcessEvent {
        $dependerToken = ClassLikeToken::fromFQCN($depender);
        $dependentToken = ClassLikeToken::fromFQCN($dependent);
        $dependency = new Dependency(
            $dependerToken,
            $dependentToken,
            new DependencyContext(new FileOccurrence('/tmp/test.php', 10), $dependencyType),
        );

        $dependerLayer = $this->extractModuleLayer($depender) ?? 'Unknown';
        $dependentLayer = $this->extractModuleLayer($dependent) ?? 'Unknown';

        return new ProcessEvent(
            $dependency,
            new ClassLikeReference($dependerToken),
            $dependerLayer,
            new ClassLikeReference($dependentToken),
            [$dependentLayer => true],
            new AnalysisResult(),
        );
    }

    private function layerName(string $className): string
    {
        $layer = $this->extractModuleLayer($className);
        self::assertNotNull($layer, sprintf('Failed to extract layer from class "%s".', $className));

        return $layer;
    }

    /**
     * @return list<Violation>
     */
    private function violations(ProcessEvent $event): array
    {
        return array_values($event->getResult()->rules()[Violation::class] ?? []);
    }
}
