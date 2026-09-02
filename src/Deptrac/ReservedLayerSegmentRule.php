<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Qossmic\Deptrac\Contract\Analyser\PostProcessEvent;
use Qossmic\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Qossmic\Deptrac\Contract\Result\Error;
use Qossmic\Deptrac\Core\Ast\AstMapExtractor;

/**
 * Custom Deptrac rule that forbids reserved layer names as nested namespace segments.
 *
 * Layer names (Domain, Application, Infrastructure, Integration) are reserved
 * path segments: the layer is the segment right after Module\{ModuleName}.
 * A namespace of one layer must not contain another layer name — for example,
 * Domain\Service\Integration\*Interface is forbidden.
 *
 * This is the deptrac-side twin of ReservedLayerSegmentSniff: it fails the
 * analysis on the mere existence of such a class, even in projects that do
 * not run PHPCS. Unlike layer rulesets, it is not limited to dependency edges.
 *
 * Register in depfile.yaml:
 *   services:
 *     - class: PrikotovCodingStandard\Deptrac\ReservedLayerSegmentRule
 *       autowire: true
 *       tags:
 *         - { name: kernel.event_subscriber }
 */
final class ReservedLayerSegmentRule implements ViolationCreatingInterface
{
    private const DOC_REF = ' See: docs/conventions/layers/layers.md';

    /**
     * Reserved layer names.
     *
     * @var list<string>
     */
    private const LAYERS = ['Domain', 'Application', 'Infrastructure', 'Integration'];

    private const MODULE_CLASS_PATTERN = '/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)?Common\\\\Module\\\\'
        . '(?P<module>[A-Za-z][A-Za-z0-9]*)\\\\'
        . '(?P<layer>Domain|Application|Infrastructure|Integration)\\\\'
        . '(?P<path>.+)$/';

    public function __construct(private readonly AstMapExtractor $astMapExtractor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostProcessEvent::class => 'onPostProcessEvent',
        ];
    }

    public function onPostProcessEvent(PostProcessEvent $event): void
    {
        $astMap = $this->astMapExtractor->extract();

        foreach ($astMap->getClassLikeReferences() as $reference) {
            $className = $reference->getToken()->toString();
            $nestedLayer = self::findNestedLayerName($className);

            if ($nestedLayer !== null) {
                $event->getResult()->addError(new Error(
                    sprintf(
                        'Class "%s" namespace contains reserved layer name "%s" inside the %s layer.'
                        . ' Layer names are reserved path segments — rename the group'
                        . ' or move the code to the %s layer.',
                        $className,
                        $nestedLayer,
                        self::resolveLayer($className),
                        $nestedLayer,
                    ) . self::DOC_REF,
                ));
            }
        }
    }

    public function ruleName(): string
    {
        return 'ReservedLayerSegmentRule';
    }

    public function ruleDescription(): string
    {
        return 'Layer names are reserved namespace segments: a class of one layer'
            . ' must not contain another layer name in its namespace;'
            . ' such namespaces are forbidden regardless of dependencies.';
    }

    /**
     * Returns the reserved layer name nested inside another layer, if any.
     */
    public static function findNestedLayerName(string $className): ?string
    {
        if (1 !== preg_match(self::MODULE_CLASS_PATTERN, $className, $matches)) {
            return null;
        }

        foreach (explode('\\', $matches['path']) as $segment) {
            if (in_array($segment, self::LAYERS, true)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Returns the layer segment — the one right after Module\{ModuleName}.
     */
    public static function resolveLayer(string $className): ?string
    {
        if (1 !== preg_match(self::MODULE_CLASS_PATTERN, $className, $matches)) {
            return null;
        }

        return $matches['layer'];
    }
}
