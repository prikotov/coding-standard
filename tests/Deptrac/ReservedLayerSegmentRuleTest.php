<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Deptrac\ReservedLayerSegmentRule;
use Qossmic\Deptrac\Contract\Analyser\AnalysisResult;
use Qossmic\Deptrac\Contract\Analyser\PostProcessEvent;
use Qossmic\Deptrac\Core\Ast\AstMap\AstMap;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeReference;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeToken;
use Qossmic\Deptrac\Core\Ast\AstMapExtractor;

/**
 * @see ReservedLayerSegmentRule
 */
final class ReservedLayerSegmentRuleTest extends TestCase
{
    // --- Forbidden: reserved layer name nested inside a layer ---

    public function testFailsOnDomainServiceIntegrationInterface(): void
    {
        self::assertSame(
            'Integration',
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Common\Module\Source\Domain\Service\Integration\SourcePublicationSnapshotResolverInterface',
            ),
        );
    }

    public function testFailsOnDomainServiceInfrastructureGroup(): void
    {
        self::assertSame(
            'Infrastructure',
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Common\Module\Source\Domain\Service\Infrastructure\StorageAdapter',
            ),
        );
    }

    public function testFailsOnApplicationServiceIntegrationGroup(): void
    {
        self::assertSame(
            'Integration',
            ReservedLayerSegmentRule::findNestedLayerName(
                'App\Common\Module\Billing\Application\Service\Integration\PaymentBridge',
            ),
        );
    }

    public function testFailsOnInfrastructureComponentDomainGroup(): void
    {
        self::assertSame(
            'Domain',
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Common\Module\Source\Infrastructure\Component\Domain\LegacyAdapter',
            ),
        );
    }

    // --- Allowed: no nested reserved layer names ---

    public function testAllowsDomainServiceGroupNamedByDomainConcept(): void
    {
        self::assertNull(
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Common\Module\Source\Domain\Service\SourcePublication\SourcePublicationSnapshotResolverInterface',
            ),
        );
    }

    public function testAllowsIntegrationLayerItself(): void
    {
        self::assertNull(
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Common\Module\Source\Integration\Listener\SourceRefreshedListener',
            ),
        );
    }

    public function testAllowsClassOutsideModuleStructure(): void
    {
        self::assertNull(
            ReservedLayerSegmentRule::findNestedLayerName(
                'PrikotovCodingStandard\Deptrac\ReservedLayerSegmentRule',
            ),
        );
    }

    public function testAllowsPresentationAppGroupNamespace(): void
    {
        self::assertNull(
            ReservedLayerSegmentRule::findNestedLayerName(
                'Task\Web\Module\Landing\Controller\PostController',
            ),
        );
    }

    // --- Result wiring: existence of the class must add an error ---

    public function testAddsErrorToResultForForbiddenClass(): void
    {
        $result = new AnalysisResult();
        $event = new PostProcessEvent($result);

        $this->createRule([
            'Task\Common\Module\Source\Domain\Service\Integration\SourcePublicationSnapshotResolverInterface',
            'Task\Common\Module\Source\Domain\Service\SourcePublication\ValidInterface',
        ])->onPostProcessEvent($event);

        $errors = $result->errors();

        self::assertCount(1, $errors);
        self::assertStringContainsString(
            'Task\Common\Module\Source\Domain\Service\Integration\SourcePublicationSnapshotResolverInterface',
            (string) $errors[0],
        );
        self::assertStringContainsString('docs/conventions/layers/layers.md', (string) $errors[0]);
    }

    public function testAddsNoErrorForValidClasses(): void
    {
        $result = new AnalysisResult();
        $event = new PostProcessEvent($result);

        $this->createRule([
            'Task\Common\Module\Source\Domain\Service\SourcePublication\ValidInterface',
            'Task\Common\Module\Source\Integration\Service\ExchangeRateConnector',
        ])->onPostProcessEvent($event);

        self::assertSame([], $result->errors());
    }

    /**
     * @param list<string> $classNames
     */
    private function createRule(array $classNames): ReservedLayerSegmentRule
    {
        $references = [];
        foreach ($classNames as $className) {
            $references[] = new ClassLikeReference(ClassLikeToken::fromFQCN($className));
        }

        $astMap = $this->createMock(AstMap::class);
        $astMap->method('getClassLikeReferences')->willReturn($references);

        $extractor = $this->createMock(AstMapExtractor::class);
        $extractor->method('extract')->willReturn($astMap);

        return new ReservedLayerSegmentRule($extractor);
    }
}
