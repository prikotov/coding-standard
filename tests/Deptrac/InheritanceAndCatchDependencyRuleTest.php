<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use Deptrac\Deptrac\Contract\Analyser\AnalysisResult;
use Deptrac\Deptrac\Contract\Analyser\PostProcessEvent;
use Deptrac\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Deptrac\Deptrac\Contract\Ast\AstMap\ClassLikeToken;
use Deptrac\Deptrac\Contract\Ast\AstMap\DependencyContext;
use Deptrac\Deptrac\Contract\Ast\AstMap\DependencyType;
use Deptrac\Deptrac\Contract\Ast\AstMap\FileOccurrence;
use Deptrac\Deptrac\Contract\Result\RuleInterface;
use Deptrac\Deptrac\Contract\Result\Violation;
use Deptrac\Deptrac\DefaultBehavior\Dependency\Helpers\Dependency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\InheritanceAndCatchDependencyRule;

final class InheritanceAndCatchDependencyRuleTest extends TestCase
{
    private const DEPENDER = 'TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo';
    private const CONTRACT = 'TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface';
    private const COMMAND = 'TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand';
    private const SERVICE = 'TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategyService';

    #[DataProvider('provideRemoved')]
    public function testRemovesPolymorphismAndDataDependencies(DependencyType $type, string $depender, string $dependent, string $dependentLayer): void
    {
        $kept = $this->runRule([$this->violation($type, $depender, $dependent, $dependentLayer)]);

        self::assertSame([], $kept);
    }

    #[DataProvider('provideKept')]
    public function testKeepsRealCoupling(DependencyType $type, string $depender, string $dependent, string $dependentLayer): void
    {
        $violation = $this->violation($type, $depender, $dependent, $dependentLayer);

        self::assertSame([$violation], $this->runRule([$violation]));
    }

    public function testKeepsCommandAsReturnValueAndNew(): void
    {
        $asReturn = $this->violation(DependencyType::RETURN_TYPE, self::SERVICE, self::COMMAND, 'ApplicationCommand');
        $asNew = $this->violation(DependencyType::NEW, self::SERVICE, self::COMMAND, 'ApplicationCommand');

        self::assertSame([$asReturn, $asNew], $this->runRule([$asReturn, $asNew]));
    }

    /** @return iterable<string, array{DependencyType, string, string, string}> */
    public static function provideRemoved(): iterable
    {
        yield 'VO implements a domain interface' => [DependencyType::INHERIT, self::DEPENDER, self::CONTRACT, 'DomainContract'];
        yield 'service catches a domain exception' => [DependencyType::CATCH, self::SERVICE, 'Task\Common\Module\X\Domain\Exception\YException', 'DomainException'];
        yield 'strategy receives the command as a parameter' => [DependencyType::PARAMETER, self::SERVICE, self::COMMAND, 'ApplicationCommand'];
        yield 'strategy receives the query as a parameter' => [DependencyType::PARAMETER, self::SERVICE, self::COMMAND . 'Query', 'ApplicationQuery'];
    }

    /** @return iterable<string, array{DependencyType, string, string, string}> */
    public static function provideKept(): iterable
    {
        yield 'VO calls a domain service (parameter)' => [DependencyType::PARAMETER, self::DEPENDER, 'Task\Common\Module\X\Domain\Service\YService', 'DomainContract'];
        yield 'service throws a domain exception' => [DependencyType::THROW, self::SERVICE, 'Task\Common\Module\X\Domain\Exception\YException', 'DomainException'];
        yield 'service instantiates a domain contract' => [DependencyType::NEW, self::SERVICE, self::CONTRACT, 'DomainContract'];
        yield 'service instantiates a command (dispatch)' => [DependencyType::NEW, self::SERVICE, self::COMMAND, 'ApplicationCommand'];
        yield 'command passed as parameter to a non-data layer' => [DependencyType::PARAMETER, self::SERVICE, self::COMMAND, 'Domain'];
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<RuleInterface>
     */
    private function runRule(array $violations): array
    {
        $result = new AnalysisResult();
        foreach ($violations as $violation) {
            $result->addRule($violation);
        }

        (new InheritanceAndCatchDependencyRule())->onPostProcessEvent(new PostProcessEvent($result));

        $kept = [];
        foreach ($result->rules() as $violations) {
            foreach ($violations as $violation) {
                $kept[] = $violation;
            }
        }

        return $kept;
    }

    private function violation(DependencyType $type, string $depender, string $dependent, string $dependentLayer): Violation
    {
        return new Violation(
            new Dependency(
                ClassLikeToken::fromFQCN($depender),
                ClassLikeToken::fromFQCN($dependent),
                new DependencyContext(new FileOccurrence('/tmp/test.php', 10), $type),
            ),
            'SourceLayer',
            $dependentLayer,
            $this->createStub(ViolationCreatingInterface::class),
        );
    }
}
