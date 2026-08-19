<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The shipped depfile must collect versioned app namespaces
 * (for example, Task\Api\v1\Module\...) into the Presentation layer.
 *
 * Regression for a silent regex bug: the double-escaped group (?:\\v\\d+)?
 * matched the literal string "\v\d" and never matched real namespaces,
 * so the whole API application stayed outside architectural control.
 *
 * @see config/deptrac/depfile.yaml
 */
final class DepfileVersionedNamespaceRegexTest extends TestCase
{
    private const DEPFILE = __DIR__ . '/../../config/deptrac/depfile.yaml';

    private const PRESENTATION_REGEX_EXPECTATION = 'Layer Presentation must collect %s — check the version group (?:\\\\v\d+)? in the depfile Presentation collector.';

    private function presentationRegex(): string
    {
        $parsed = Yaml::parseFile(self::DEPFILE);
        $deptrac = is_array($parsed) ? ($parsed['deptrac'] ?? null) : null;
        $layers = is_array($deptrac) ? ($deptrac['layers'] ?? null) : null;

        foreach (is_array($layers) ? $layers : [] as $layer) {
            if (!is_array($layer) || ($layer['name'] ?? null) !== 'Presentation') {
                continue;
            }

            $collectors = $layer['collectors'] ?? null;
            $collector = is_array($collectors) ? ($collectors[0] ?? null) : null;
            if (is_array($collector) && is_string($collector['value'] ?? null)) {
                return $collector['value'];
            }

            self::fail('Presentation collector in depfile.yaml has no string value.');
        }

        self::fail('depfile.yaml has no Presentation layer.');
    }

    private function presentationMatches(string $className): bool
    {
        return 1 === preg_match('/' . $this->presentationRegex() . '/', $className);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideVersionedAppNamespaceClasses(): array
    {
        return [
            'Api v1 with root namespace' => ['Task\Api\v1\Module\Source\Controller\ListController'],
            'Api v2 with digit root namespace' => ['Stocks2\Api\v2\Module\Billing\Controller\InvoiceController'],
            'Api multi-digit version' => ['Task\Api\v10\Module\Source\Controller\ListController'],
            'Api v1 without root namespace' => ['Api\v1\Module\Source\Controller\ListController'],
            'Api without version segment' => ['Task\Api\Module\Source\Controller\ListController'],
            'Web without version' => ['Task\Web\Module\Blog\Controller\PostController'],
            'Console without root namespace' => ['Console\Module\Source\Command\ImportCommand'],
            'Blog with root namespace' => ['Task\Blog\Module\Docs\Controller\ArticleController'],
        ];
    }

    /**
     * @dataProvider provideVersionedAppNamespaceClasses
     */
    public function testPresentationCollectsAppNamespaceClass(string $className): void
    {
        self::assertTrue(
            $this->presentationMatches($className),
            sprintf(self::PRESENTATION_REGEX_EXPECTATION, $className),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideNonAppNamespaceClasses(): array
    {
        return [
            'Common module code' => ['Task\Common\Module\Source\Domain\Entity\Source'],
            'API with named (non-digit) version' => ['Task\Api\beta\Module\Source\Controller\ListController'],
            'API version outside app segment' => ['Task\Api\v1\Common\Module\Source\Controller\ListController'],
            'Prefixed app name' => ['Task\Weba\Module\Blog\Controller\PostController'],
        ];
    }

    /**
     * @dataProvider provideNonAppNamespaceClasses
     */
    public function testPresentationIgnoresNonAppNamespaceClass(string $className): void
    {
        self::assertFalse(
            $this->presentationMatches($className),
            sprintf('Layer Presentation must not collect %s — the Presentation collector regex is too broad.', $className),
        );
    }

    public function testVersionGroupIsNotDoubleEscaped(): void
    {
        self::assertStringNotContainsString(
            '\\\\v\\\\d',
            $this->presentationRegex(),
            'The version group must be (?:\\\\v\d+)? — double escaping (\\v\d as a literal) silently matches nothing.',
        );
    }
}
