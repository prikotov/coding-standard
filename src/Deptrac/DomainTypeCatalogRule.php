<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Deptrac;

use Deptrac\Deptrac\Contract\Analyser\PostProcessEvent;
use Deptrac\Deptrac\Contract\Analyser\ViolationCreatingInterface;
use Deptrac\Deptrac\Contract\Result\Error;
use Deptrac\Deptrac\Core\Ast\AstMapExtractor;

/**
 * Domain types form a closed catalog: a class under `Module\{Module}\Domain\` must
 * live in a recognized type directory, and the Domain root namespace may contain
 * only interfaces. Anything else — for example `Domain\TransactionFieldNormalizer`
 * or `Domain\ValueObject\CleanupErrorList` (without the `Vo` suffix) — fits none of
 * the convention types and is therefore a convention violation by definition,
 * not a dependency to classify.
 *
 * This is the deptrac-side twin of MissingVoSuffixSniff (which covers only
 * ValueObject): it checks the whole Domain catalog even in projects that do not run
 * PHPCS.
 *
 * Register in depfile.yaml:
 *   services:
 *     - class: PrikotovCodingStandard\Deptrac\DomainTypeCatalogRule
 *       autowire: true
 *       tags:
 *         - { name: kernel.event_subscriber }
 */
final class DomainTypeCatalogRule implements ViolationCreatingInterface
{
    private const DOC_REF = ' See: docs/conventions/core-patterns/ and docs/conventions/layers/domain.md';

    private const MODULE_DOMAIN_PATTERN = '/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)?Common\\\\Module\\\\'
        . '(?P<module>[A-Za-z][A-Za-z0-9]*)\\\\'
        . 'Domain\\\\'
        . '(?P<path>[^\\\\]+.*)$/';

    /**
     * Directories of Domain types found in the conventions and in the consumer
     * projects (stocks2, TasK, task-orchestrator).
     *
     * @var list<string>
     */
    private const ALLOWED_TYPE_DIRECTORIES = [
        'Calculator',
        'Criteria',
        'Dto',
        'Entity',
        'Enum',
        'Exception',
        'Factory',
        'Helper',
        'List',
        'Mapper',
        'Provider',
        'Repository',
        'Service',
        'Specification',
        'Strategy',
        'ValueObject',
    ];

    public function __construct(private readonly AstMapExtractor $astMapExtractor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostProcessEvent::class => ['onPostProcessEvent'],
        ];
    }

    public function onPostProcessEvent(PostProcessEvent $event): void
    {
        foreach ($this->astMapExtractor->extract()->getClassLikeReferences() as $reference) {
            $className = $reference->getToken()->toString();
            $violation = self::classify($className);
            if ($violation !== null) {
                $event->getResult()->addError(new Error($violation . '.' . self::DOC_REF));
            }
        }
    }

    /**
     * Returns the violation message for a class outside the catalog, null if it fits.
     */
    public static function classify(string $className): ?string
    {
        if (1 !== preg_match(self::MODULE_DOMAIN_PATTERN, $className, $matches)) {
            return null;
        }

        $path = $matches['path'];

        // Only interfaces directly in the Domain namespace (e.g. ChainDefinitionInterface).
        if (str_contains($path, '\\') === false) {
            return str_ends_with($path, 'Interface') === false
                ? sprintf(
                    'Class "%s" sits directly in the Domain namespace; only interfaces are allowed there. '
                    . 'The Domain catalog is closed: %s.',
                    $className,
                    implode(', ', self::ALLOWED_TYPE_DIRECTORIES),
                )
                : null;
        }

        $directory = explode('\\', $path, 2)[0];
        if (in_array($directory, self::ALLOWED_TYPE_DIRECTORIES, true) === false) {
            return sprintf(
                'Class "%s" is in Domain\\%s, which is not a Domain type directory. '
                . 'The Domain catalog is closed: %s.',
                $className,
                $directory,
                implode(', ', self::ALLOWED_TYPE_DIRECTORIES),
            );
        }

        // Classes in ValueObject must carry the "Vo" suffix (MissingVoSuffixSniff covers PHPCS projects).
        if ($directory === 'ValueObject' && str_ends_with($path, 'Vo') === false) {
            return sprintf(
                'Class "%s" is in Domain\\ValueObject without the "Vo" suffix. '
                . 'Value Object names are suffixed with "Vo" (docs/conventions/core-patterns/value-object.md).',
                $className,
            );
        }

        return null;
    }

    public function ruleName(): string
    {
        return 'DomainTypeCatalogRule';
    }

    public function ruleDescription(): string
    {
        return 'Domain classes must live in a recognized type directory; the Domain root'
            . ' namespace contains only interfaces. A class fitting no convention type'
            . ' is a violation by definition.';
    }
}
