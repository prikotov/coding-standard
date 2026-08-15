<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The shipped depfile must collect classes from projects whose root
 * namespace contains digits (for example, Stocks2\...).
 *
 * @see config/deptrac/depfile.yaml
 */
final class DepfileRootNamespaceRegexTest extends TestCase
{
    private const DEPFILE = __DIR__ . '/../../config/deptrac/depfile.yaml';

    /**
     * @return array<string, list<array{must: list<string>, must_not: list<string>}>>
     */
    private function layerGroups(): array
    {
        $parsed = Yaml::parseFile(self::DEPFILE);
        if (!is_array($parsed) || !is_array($parsed['deptrac'] ?? null)) {
            return [];
        }

        $layers = $parsed['deptrac']['layers'] ?? null;
        if (!is_array($layers)) {
            return [];
        }

        $groups = [];
        foreach ($layers as $layer) {
            if (!is_array($layer) || !is_string($layer['name'] ?? null) || !is_array($layer['collectors'] ?? null)) {
                continue;
            }

            $layerGroups = [];
            foreach ($layer['collectors'] as $collector) {
                if (is_array($collector)) {
                    $layerGroups[] = $this->collectorGroup($collector);
                }
            }
            $groups[$layer['name']] = $layerGroups;
        }

        return $groups;
    }

    /**
     * @param array<mixed> $collector
     *
     * @return array{must: list<string>, must_not: list<string>}
     */
    private function collectorGroup(array $collector): array
    {
        if (($collector['type'] ?? null) !== 'bool') {
            $value = $collector['value'] ?? null;

            return [
                'must' => is_string($value) ? [$value] : [],
                'must_not' => [],
            ];
        }

        return [
            'must' => $this->regexList($collector['must'] ?? null),
            'must_not' => $this->regexList($collector['must_not'] ?? null),
        ];
    }

    /**
     * @param mixed $collectors
     *
     * @return list<string>
     */
    private function regexList(mixed $collectors): array
    {
        if (!is_array($collectors)) {
            return [];
        }

        $regexes = [];
        foreach ($collectors as $collector) {
            if (is_array($collector) && is_string($collector['value'] ?? null)) {
                $regexes[] = $collector['value'];
            }
        }

        return $regexes;
    }

    private function layerMatches(string $layerName, string $className): bool
    {
        foreach ($this->layerGroups()[$layerName] ?? [] as $group) {
            if ($this->groupMatches($group, $className)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{must: list<string>, must_not: list<string>} $group
     */
    private function groupMatches(array $group, string $className): bool
    {
        foreach ($group['must'] as $regex) {
            if (1 !== preg_match('/' . $regex . '/', $className)) {
                return false;
            }
        }

        foreach ($group['must_not'] as $regex) {
            if (1 === preg_match('/' . $regex . '/', $className)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideDigitRootNamespaceClasses(): array
    {
        return [
            'Domain service' => ['Domain', 'Stocks2\Common\Module\Billing\Domain\Service\Invoice\CreateInvoiceService'],
            'Application service' => ['Application', 'Stocks2\Common\Module\Billing\Application\Service\Invoice\PrepareInvoiceService'],
            'Application command handler' => ['ApplicationCommandHandler', 'Stocks2\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommandHandler'],
            'Infrastructure' => ['Infrastructure', 'Stocks2\Common\Module\Billing\Infrastructure\Repository\Invoice\InvoiceRepository'],
            'Integration' => ['Integration', 'Stocks2\Common\Module\Billing\Integration\Connector\Rate\ExchangeRateConnector'],
            'Presentation (app namespace)' => ['Presentation', 'Stocks2\Web\Module\Blog\Controller\PostController'],
            'Command layer' => ['ApplicationCommand', 'Stocks2\Common\Module\Billing\Application\UseCase\Command\Invoice\Create\CreateCommand'],
        ];
    }

    /**
     * @dataProvider provideDigitRootNamespaceClasses
     */
    public function testLayerCollectsDigitRootNamespaceClass(string $layerName, string $className): void
    {
        self::assertTrue(
            $this->layerMatches($layerName, $className),
            sprintf(
                'Layer %s must collect %s — check the root namespace prefix regex in depfile.yaml.',
                $layerName,
                $className,
            ),
        );
    }

    public function testNoCollectorUsesDigitBlindRootPrefix(): void
    {
        foreach ($this->layerGroups() as $layerName => $groups) {
            foreach ($groups as $group) {
                foreach (array_merge($group['must'], $group['must_not']) as $regex) {
                    self::assertStringNotContainsString(
                        '[A-Za-z_]+\\\\',
                        $regex,
                        sprintf(
                            'Layer %s uses a root namespace prefix that ignores digits — use [A-Za-z_][A-Za-z0-9_]* instead.',
                            $layerName,
                        ),
                    );
                }
            }
        }
    }
}
