<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\CrossModuleDomainRule;
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
 * @see CrossModuleDomainRule
 */
final class CrossModuleDomainRuleTest extends TestCase
{
    // ─── Domain → foreign Domain: violation ───────────────────────────

    public function testDomainDependsOnForeignDomainService(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
        );
    }

    public function testDomainDependsOnForeignDomainEntity(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\User\Domain\Entity\User',
        );
    }

    // ─── Application → foreign Domain: violation ──────────────────────

    public function testApplicationDependsOnForeignDomainService(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
        );
    }

    public function testApplicationDependsOnForeignApplication(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\User\Application\Service\Account\FindAccountService',
        );
    }

    // ─── Infrastructure → foreign *: violation ────────────────────────

    public function testInfrastructureDependsOnForeignDomainService(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\RunAgentService',
            'App\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface',
        );
    }

    public function testInfrastructureImplementsForeignDomainContract(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\JsonlAuditLogger',
            'App\Common\Module\ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface',
            DependencyType::INHERIT,
        );
    }

    public function testInfrastructureDependsOnForeignDomainEntity(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Domain\Entity\ChainExecution',
        );
    }

    public function testInfrastructureDependsOnForeignDomainViaAnySubdirectory(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Domain\Port\Gateway\SomeGatewayInterface',
        );
    }

    public function testInfrastructureDependsOnForeignApplication(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Application\Service\SomeService',
        );
    }

    public function testInfrastructureDependsOnForeignInfrastructure(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Infrastructure\Service\Chain\JsonlAuditLogger',
        );
    }

    public function testInfrastructureDependsOnForeignIntegration(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Integration\Service\AgentRunner\RunAgentService',
        );
    }

    // ─── Integration → foreign Domain: violation ──────────────────────

    public function testIntegrationDependsOnForeignDomainService(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\AgentRunner\RunAgentService',
            'App\Common\Module\ChainExecution\Domain\Service\Static\RunAgentServiceInterface',
        );
    }

    public function testIntegrationImplementsForeignDomainContract(): void
    {
        $this->assertViolation(
            'App\Common\Module\Chat\Integration\Service\ProjectAccess\ProjectOwnerChecker',
            'App\Common\Module\Project\Domain\Service\OwnerChecker\OwnerCheckerServiceInterface',
            DependencyType::INHERIT,
        );
    }

    // ─── Integration → foreign Infrastructure: violation ──────────────

    public function testIntegrationDependsOnForeignInfrastructure(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\SomeService',
            'App\Common\Module\ChainExecution\Infrastructure\Service\Chain\JsonlAuditLogger',
        );
    }

    // ─── Integration → foreign Integration: violation ─────────────────

    public function testIntegrationDependsOnForeignIntegration(): void
    {
        $this->assertViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\SomeService',
            'App\Common\Module\ChainExecution\Integration\Service\AgentRunner\RunAgentService',
        );
    }

    // ─── Integration → foreign Application: ALLOWED ───────────────────

    public function testIntegrationDependsOnForeignApplicationService(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\AgentRunner\RunAgentService',
            'App\Common\Module\ChainExecution\Application\Service\SomeService',
        );
    }

    public function testIntegrationDependsOnForeignCommandHandler(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\AgentRunner\RunAgentService',
            'App\Common\Module\ChainExecution\Application\UseCase\Command\RunAgent\RunAgentCommandHandler',
        );
    }

    public function testIntegrationDependsOnForeignQueryHandler(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Chat\Integration\Service\User\FindUserService',
            'App\Common\Module\User\Application\UseCase\Query\FindUser\FindUserQueryHandler',
        );
    }

    // ─── Same module: no violation ────────────────────────────────────

    public function testInfrastructureDependsOnOwnDomain(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Infrastructure\Service\Invoice\StoreInvoiceService',
            'App\Common\Module\Billing\Domain\Service\Invoice\BuildInvoiceServiceInterface',
        );
    }

    public function testIntegrationDependsOnOwnDomain(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Integration\Service\ExchangeRate\SyncService',
            'App\Common\Module\Billing\Domain\Service\ExchangeRate\FetchRateServiceInterface',
        );
    }

    public function testApplicationDependsOnOwnDomain(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler',
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceServiceInterface',
        );
    }

    // ─── Domain\Entity → foreign Domain\Entity: allowed (Doctrine ORM) ──

    public function testDomainEntityDependsOnForeignDomainEntity(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Domain\Entity\UsageModel',
            'App\Common\Module\User\Domain\Entity\UserModel',
        );
    }

    public function testDomainEntityDependsOnForeignDomainEntityWithInherit(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Domain\Entity\PaymentModel',
            'App\Common\Module\User\Domain\Entity\UserModel',
            DependencyType::INHERIT,
        );
    }

    public function testDomainEntityDependsOnForeignDomainEntityMultipleRelations(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Project\Domain\Entity\ProjectModel',
            'App\Common\Module\User\Domain\Entity\TeamMembershipModel',
        );
    }

    // ─── Domain\Entity → foreign non-Entity: still violation ──────────

    public function testDomainEntityDependsOnForeignDomainService(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Domain\Entity\PaymentModel',
            'App\Common\Module\User\Domain\Service\FindUserServiceInterface',
        );
    }

    public function testDomainServiceDependsOnForeignDomainEntity(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Domain\Service\Usage\TrackUsageService',
            'App\Common\Module\User\Domain\Entity\UserModel',
        );
    }

    // ─── Command Handler → foreign Domain\Repository: temporary allowed ──

    public function testCommandHandlerDependsOnForeignDomainRepository(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Usage\Create\CreateCommandHandler',
            'App\Common\Module\User\Domain\Repository\User\UserRepositoryInterface',
        );
    }

    public function testCommandHandlerDependsOnForeignDomainRepositoryCriteria(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Usage\Create\CreateCommandHandler',
            'App\Common\Module\User\Domain\Repository\User\Criteria\UserFindCriteria',
        );
    }

    // ─── Command Handler → foreign Domain\Entity: temporary allowed ──

    public function testCommandHandlerDependsOnForeignDomainEntity(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Application\UseCase\Command\Usage\Track\TrackCommandHandler',
            'App\Common\Module\User\Domain\Entity\UserModel',
        );
    }

    // ─── Query Handler → foreign Domain\Repository: still violation ───

    public function testQueryHandlerDependsOnForeignDomainRepository(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\UseCase\Query\Usage\Find\FindQueryHandler',
            'App\Common\Module\User\Domain\Repository\User\UserRepositoryInterface',
        );
    }

    public function testQueryHandlerDependsOnForeignDomainEntity(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\UseCase\Query\Usage\Find\FindQueryHandler',
            'App\Common\Module\User\Domain\Entity\UserModel',
        );
    }

    // ─── Application Service → foreign Domain\*: still violation ───────

    public function testApplicationServiceDependsOnForeignDomainRepository(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\Service\SomeService',
            'App\Common\Module\User\Domain\Repository\User\UserRepositoryInterface',
        );
    }

    public function testApplicationServiceDependsOnForeignDomainEntity(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\Service\SomeService',
            'App\Common\Module\User\Domain\Entity\UserModel',
        );
    }

    public function testApplicationDependsOnForeignDomainServiceStillViolation(): void
    {
        $this->assertViolation(
            'App\Common\Module\Billing\Application\Service\SomeService',
            'App\Common\Module\User\Domain\Service\Account\FindAccountServiceInterface',
        );
    }

    // ─── Shared data types: no violation ──────────────────────────────

    public function testInfrastructureUsesForeignDomainValueObject(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo',
        );
    }

    public function testInfrastructureUsesForeignDomainEnum(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum',
        );
    }

    public function testInfrastructureUsesForeignDomainDto(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\SomeService',
            'App\Common\Module\ChainExecution\Domain\Dto\ChainResultAuditDto',
        );
    }

    public function testIntegrationUsesForeignDomainValueObject(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Integration\Service\ChainDefinition\Mapper',
            'App\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo',
        );
    }

    public function testDomainUsesForeignDomainValueObject(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService',
            'App\Common\Module\User\Domain\ValueObject\UserIdVo',
        );
    }

    public function testApplicationUsesForeignDomainEnum(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\Billing\Application\Service\SomeService',
            'App\Common\Module\User\Domain\Enum\UserRoleEnum',
        );
    }

    // ─── use imports ignored ──────────────────────────────────────────

    public function testUseImportIsIgnored(): void
    {
        $this->assertNoViolation(
            'App\Common\Module\DynamicLoop\Infrastructure\Service\RunAgentService',
            'App\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface',
            DependencyType::USE,
        );
    }

    // ─── Non-module classes ignored ───────────────────────────────────

    public function testNonModuleClassIsIgnored(): void
    {
        $this->assertNoViolation(
            'App\Web\Component\UserEmail\WebUserEmailUrlGenerator',
            'App\Common\Module\User\Domain\Service\Email\UserEmailUrlGeneratorInterface',
        );
    }

    // ─── Works without root namespace prefix ──────────────────────────

    public function testNoRootNamespacePrefixCrossModuleWithViolation(): void
    {
        $this->assertViolation(
            'Common\Module\DynamicLoop\Infrastructure\Service\RunAgentService',
            'Common\Module\ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface',
        );
    }

    // ─── ruleName / ruleDescription ───────────────────────────────────

    public function testRuleName(): void
    {
        $rule = new CrossModuleDomainRule();

        self::assertSame('CrossModuleDomainRule', $rule->ruleName());
    }

    public function testRuleDescription(): void
    {
        $rule = new CrossModuleDomainRule();

        self::assertSame(
            'Cross-module dependencies are forbidden. '
            . 'The only allowed path is Integration → foreign Application (Command/Query handlers).',
            $rule->ruleDescription(),
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function assertViolation(
        string $depender,
        string $dependent,
        DependencyType $dependencyType = DependencyType::PARAMETER,
    ): void {
        $event = $this->createProcessEvent($depender, $dependent, $dependencyType);
        $rule = new CrossModuleDomainRule();

        $rule->onProcessEvent($event);

        self::assertCount(1, $this->violations($event), sprintf(
            'Expected violation for %s → %s, but got none.',
            $depender,
            $dependent,
        ));
    }

    private function assertNoViolation(
        string $depender,
        string $dependent,
        DependencyType $dependencyType = DependencyType::PARAMETER,
    ): void {
        $event = $this->createProcessEvent($depender, $dependent, $dependencyType);
        $rule = new CrossModuleDomainRule();

        $rule->onProcessEvent($event);

        self::assertSame([], $this->violations($event), sprintf(
            'Expected no violation for %s → %s, but got one.',
            $depender,
            $dependent,
        ));
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

        $dependerLayer = $this->extractLayer($depender) ?? 'Unknown';
        $dependentLayer = $this->extractLayer($dependent) ?? 'Unknown';

        return new ProcessEvent(
            $dependency,
            new ClassLikeReference($dependerToken),
            $dependerLayer,
            new ClassLikeReference($dependentToken),
            [$dependentLayer => true],
            new AnalysisResult(),
        );
    }

    private function extractLayer(string $className): ?string
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

    /**
     * @return list<Violation>
     */
    private function violations(ProcessEvent $event): array
    {
        return array_values($event->getResult()->rules()[Violation::class] ?? []);
    }
}
