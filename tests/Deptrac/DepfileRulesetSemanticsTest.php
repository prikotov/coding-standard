<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Deptrac;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\ProcessRunner;

/**
 * Runs the shipped depfile through the real deptrac on generated fixtures.
 *
 * Unlike the regex tests it verifies ruleset *semantics*: `+Layer` is a transitive
 * closure in Deptrac 4, so `Application: [+ApplicationCommandHandler]` used to grant
 * the whole Application layer every dependency of the handlers (Domain,
 * DomainEmbeddable, DomainEnum, Presentation). Regex checks cannot catch that.
 *
 * @see config/deptrac/depfile.yaml
 * @see docs/conventions/layers/domain/entity.md (Embeddable VO are a persistence part of entities)
 */
final class DepfileRulesetSemanticsTest extends TestCase
{
    private const DEPFILE = __DIR__ . '/../../config/deptrac/depfile.yaml';

    private const MODULE = 'Repro\Common\Module\Billing';

    /** @var non-empty-string */
    private string $fixturesDir;

    /** @var non-empty-string */
    private string $probeDepfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Outside tests/ — the shipped depfile excludes `#.*tests.*#` from analysis.
        $this->fixturesDir = sys_get_temp_dir() . '/cs-deptrac-' . uniqid();
        mkdir($this->fixturesDir . '/src', 0777, true);

        // The shipped depfile with paths pointed at generated fixtures: tests its layers
        // and ruleset as-is, without the `imports` path semantics.
        $depfile = (string) preg_replace(
            '/(\n  paths:\n)(\s+- [^\n]+\n)+/',
            "$1    - " . $this->fixturesDir . "/src\n",
            (string) file_get_contents(self::DEPFILE),
            1,
        );
        // Fresh AST per run: the default cache_file lives next to the config and would
        // otherwise serve a stale AST between tests that share class names.
        $depfile = (string) preg_replace(
            '/\ndeptrac:\n/',
            "\ndeptrac:\n  cache_file: " . $this->fixturesDir . "/deptrac.cache\n",
            $depfile,
            1,
        );
        self::assertStringContainsString($this->fixturesDir . '/src', $depfile, 'paths section was not replaced');
        self::assertStringContainsString('cache_file:', $depfile, 'cache_file was not injected');

        $this->probeDepfile = $this->fixturesDir . '/depfile.yaml';
        file_put_contents($this->probeDepfile, $depfile);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixturesDir)) {
            $this->removeDir($this->fixturesDir);
        }

        parent::tearDown();
    }

    public function testApplicationServiceMustNotDependOnDomainEmbeddable(): void
    {
        $this->writeClass('Domain/Entity/ValueObject/DeviceVo.php', 'final readonly class DeviceVo');
        $this->writeClass(
            'Application/Service/LeakyService.php',
            'final class LeakyService',
            'public function __construct(private readonly DeviceVo $device) {}',
            ['DeviceVo' => 'Domain\Entity\ValueObject\DeviceVo'],
        );

        self::assertSame(
            ['Application\Service\LeakyService on DomainEmbeddable'],
            $this->edges($this->analyse(), 'LeakyService'),
            'Application must not reach Embeddable VO: they are a persistence part of entities.',
        );
    }

    public function testApplicationMayUseDomainEntities(): void
    {
        // docs/conventions/layers/application.md: Application orchestrates services,
        // repositories and entities; core-patterns/mapper.md: mappers take Entity as input.
        $this->writeClass('Domain/Entity/PaymentModel.php', 'final class PaymentModel');
        $this->writeClass(
            'Application/Mapper/PaymentDtoMapper.php',
            'final readonly class PaymentDtoMapper',
            'public function map(PaymentModel $model): string { return $model->name; }',
            ['PaymentModel' => 'Domain\Entity\PaymentModel'],
        );

        self::assertSame([], $this->edges($this->analyse(), 'PaymentDtoMapper'));
    }

    public function testApplicationServiceMayBuildRepositoryFindCriteria(): void
    {
        $this->writeClass('Domain/Repository/Payment/Criteria/PaymentFindCriteria.php', 'final class PaymentFindCriteria');
        $this->writeClass(
            'Application/Service/LeakyService.php',
            'final class LeakyService',
            'public function find(): PaymentFindCriteria { return new PaymentFindCriteria(); }',
            ['PaymentFindCriteria' => 'Domain\Repository\Payment\Criteria\PaymentFindCriteria'],
        );

        self::assertSame([], $this->edges($this->analyse(), 'LeakyService'));
    }

    public function testCommandHandlerMayUseEntitiesButNotEmbeddable(): void
    {
        $this->writeClass('Domain/Entity/PaymentModel.php', 'final class PaymentModel');
        $this->writeClass('Domain/Entity/ValueObject/ClientCostVo.php', 'final readonly class ClientCostVo');
        $this->writeClass(
            'Application/UseCase/Command/Pay/DoPayCommandHandler.php',
            'final class DoPayCommandHandler',
            'public function __construct(private readonly PaymentModel $model, private readonly ClientCostVo $cost) {}',
            [
                'PaymentModel' => 'Domain\Entity\PaymentModel',
                'ClientCostVo' => 'Domain\Entity\ValueObject\ClientCostVo',
            ],
        );

        self::assertSame(
            ['Application\UseCase\Command\Pay\DoPayCommandHandler on DomainEmbeddable'],
            $this->edges($this->analyse(), 'DoPayCommandHandler'),
            'Handlers work with entities; Embeddable stay behind the entity.',
        );
    }

    public function testApplicationServiceMayCallDomainContracts(): void
    {
        $this->writeClass('Domain/Service/PaymentServiceInterface.php', 'interface PaymentServiceInterface');
        $this->writeClass(
            'Application/Service/LeakyService.php',
            'final class LeakyService',
            'public function __construct(private readonly PaymentServiceInterface $payments) {}',
            ['PaymentServiceInterface' => 'Domain\Service\PaymentServiceInterface'],
        );

        self::assertSame([], $this->edges($this->analyse(), 'LeakyService'));
    }

    public function testDomainValueTypesMustNotOwnEmbeddable(): void
    {
        $this->writeClass('Domain/Entity/ValueObject/DeviceVo.php', 'final readonly class DeviceVo');
        $this->writeClass(
            'Domain/Dto/SessionDto.php',
            'final readonly class SessionDto',
            'public function __construct(public readonly DeviceVo $device) {}',
            ['DeviceVo' => 'Domain\Entity\ValueObject\DeviceVo'],
        );
        $this->writeClass(
            'Domain/Specification/HasDeviceSpecification.php',
            'final class HasDeviceSpecification',
            'public function __construct(private readonly DeviceVo $device) {}',
            ['DeviceVo' => 'Domain\Entity\ValueObject\DeviceVo'],
        );

        $violations = $this->analyse();

        self::assertSame(['Domain\Dto\SessionDto on DomainEmbeddable'], $this->edges($violations, 'SessionDto'));
        self::assertSame(
            ['Domain\Specification\HasDeviceSpecification on DomainEmbeddable'],
            $this->edges($violations, 'HasDeviceSpecification'),
        );
    }

    /**
     * The Domain layer is "entities only"; every other domain type must land in a
     * dedicated layer. A class under Domain\ that matches no layer is never checked.
     * Deptrac reports "uncovered" only for referenced classes, so each probe class
     * is referenced by an Application service.
     */
    #[DataProvider('provideDomainTypesOutsideEntities')]
    public function testEveryDomainTypeOutsideEntitiesIsCovered(string $relativePath, string $declaration, string $shortName): void
    {
        $this->writeClass($relativePath, $declaration);
        // An Application DTO may not depend on any Domain layer: a covered probe
        // becomes a violation, an uncovered one is reported as "uncovered".
        $this->writeClass(
            'Application/Dto/Probe/LeakyDto.php',
            'final readonly class LeakyDto',
            'public function __construct(public readonly ' . $shortName . ' $dependency) {}',
            [$shortName => str_replace('/', '\\', dirname($relativePath)) . '\\' . $shortName],
        );

        $report = $this->analyseReport();

        self::assertSame(0, $report['Uncovered'], 'domain type without a layer: ' . $relativePath);
        self::assertSame(1, $report['Violations'], 'covered domain type referenced from an Application DTO must be a violation: ' . $relativePath);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function provideDomainTypesOutsideEntities(): iterable
    {
        yield 'service interface' => ['Domain/Service/Pay/PayServiceInterface.php', 'interface PayServiceInterface', 'PayServiceInterface'];
        yield 'service implementation' => ['Domain/Service/Pay/PayService.php', 'final class PayService', 'PayService'];
        yield 'find criteria' => ['Domain/Repository/Payment/Criteria/PaymentFindCriteria.php', 'final class PaymentFindCriteria', 'PaymentFindCriteria'];
        yield 'calculator' => ['Domain/Calculator/RateCalculator.php', 'final readonly class RateCalculator', 'RateCalculator'];
        yield 'normalizer in domain root' => ['Domain/FieldNormalizer.php', 'final class FieldNormalizer', 'FieldNormalizer'];
        yield 'exception' => ['Domain/Exception/InvalidAmountException.php', 'final class InvalidAmountException', 'InvalidAmountException'];
        yield 'value object interface without Vo suffix' => ['Domain/ValueObject/Parameters/ParametersInterface.php', 'interface ParametersInterface', 'ParametersInterface'];
        yield 'value object without Vo suffix' => ['Domain/ValueObject/CleanupList.php', 'final class CleanupList', 'CleanupList'];
    }

    public function testDomainValueObjectMayThrowDomainException(): void
    {
        $this->writeClass('Domain/Exception/InvalidAmountException.php', 'final class InvalidAmountException extends \\InvalidArgumentException');
        $this->writeClass(
            'Domain/ValueObject/AmountVo.php',
            'final readonly class AmountVo',
            "public function __construct(private int \$amount) { if (\$amount < 0) { throw new InvalidAmountException(); } }",
            ['InvalidAmountException' => 'Domain\Exception\InvalidAmountException'],
        );

        self::assertSame([], $this->edges($this->analyse(), 'AmountVo'));
    }

    public function testEntityAndDomainServiceMayThrowDomainException(): void
    {
        $this->writeClass('Domain/Exception/InvalidAmountException.php', 'final class InvalidAmountException extends \\InvalidArgumentException');
        $this->writeClass(
            'Domain/Entity/PaymentModel.php',
            'final class PaymentModel',
            "public function __construct(private int \$amount) { if (\$amount < 0) { throw new InvalidAmountException(); } }",
            ['InvalidAmountException' => 'Domain\Exception\InvalidAmountException'],
        );
        $this->writeClass(
            'Domain/Service/PayService.php',
            'final class PayService',
            "public function pay(PaymentModel \$m): void { if (\$m->amount < 0) { throw new InvalidAmountException(); } }",
            ['InvalidAmountException' => 'Domain\Exception\InvalidAmountException', 'PaymentModel' => 'Domain\Entity\PaymentModel'],
        );

        $violations = $this->analyse();

        self::assertSame([], $this->edges($violations, 'PaymentModel'));
        self::assertSame([], $this->edges($violations, 'PayService'));
    }

    public function testDomainValueObjectMustNotDependOnEmbeddable(): void
    {
        $this->writeClass('Domain/Entity/ValueObject/DeviceVo.php', 'final readonly class DeviceVo');
        $this->writeClass(
            'Domain/ValueObject/ResolutionVo.php',
            'final readonly class ResolutionVo',
            'public function __construct(private readonly DeviceVo $device) {}',
            ['DeviceVo' => 'Domain\Entity\ValueObject\DeviceVo'],
        );

        self::assertSame(
            ['Domain\ValueObject\ResolutionVo on DomainEmbeddable'],
            $this->edges($this->analyse(), 'ResolutionVo'),
        );
    }

    public function testEntityMayOwnItsEmbeddable(): void
    {
        $this->writeClass('Domain/Entity/ValueObject/DeviceVo.php', 'final readonly class DeviceVo');
        $this->writeClass(
            'Domain/Entity/AttributionSessionModel.php',
            'final class AttributionSessionModel',
            'public function __construct(private readonly DeviceVo $device) {}',
            ['DeviceVo' => 'Domain\Entity\ValueObject\DeviceVo'],
        );

        $violations = $this->analyse();

        self::assertSame([], $this->edges($violations, 'AttributionSessionModel'));
        self::assertSame([], $this->edges($violations, 'DeviceVo'));
    }

    /**
     * @param array<string, string> $imports short name => namespace-qualified path under the module
     */
    private function writeClass(string $relativePath, string $declaration, string $members = '', array $imports = []): void
    {
        $path      = $this->fixturesDir . '/src/' . $relativePath;
        $namespace = self::MODULE . '\\' . str_replace('/', '\\', dirname($relativePath));

        $uses = implode(
            "\n",
            array_map(
                static fn (string $fq): string => 'use ' . self::MODULE . '\\' . $fq . ';',
                $imports,
            ),
        );

        is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
        file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . $namespace . ";\n"
            . ($uses === '' ? '' : "\n" . $uses . "\n")
            . "\n" . $declaration . "\n{\n"
            . ($members === '' ? '' : "    " . $members . "\n")
            . "}\n",
        );
    }

    /**
     * @return list<array{message: string}>
     */
    private function analyse(): array
    {
        $result = (new ProcessRunner())->run(
            [
                PHP_BINARY,
                dirname(self::DEPFILE, 3) . '/vendor/bin/deptrac',
                'analyse',
                '--config-file=' . $this->probeDepfile,
                '--no-progress',
                '--no-ansi',
                '--formatter=json',
            ],
            ['pipe', 'r'],
        );

        self::assertNotNull($result, 'deptrac process could not start');
        [, $stdout, $stderr] = $result;

        // The json formatter reports to stdout; diagnostics (syntax errors etc.) go to stderr.
        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, 'deptrac json output expected; stdout: ' . $stdout . ' stderr: ' . $stderr);

        $report = $decoded['Report'] ?? null;
        self::assertIsArray($report);
        self::assertSame('', trim($stderr), 'deptrac stderr: ' . $stderr);

        $files = $decoded['files'] ?? [];
        self::assertIsArray($files);

        $messages = [];
        foreach ($files as $file) {
            self::assertIsArray($file);
            $fileMessages = $file['messages'] ?? [];
            self::assertIsArray($fileMessages);
            foreach ($fileMessages as $message) {
                self::assertIsArray($message);
                self::assertIsString($message['message'] ?? null);
                $messages[] = ['message' => $message['message']];
            }
        }

        return $messages;
    }

    /**
     * @return array{Violations: int, Uncovered: int, Errors: int}
     */
    private function analyseReport(): array
    {
        $result = (new ProcessRunner())->run(
            [
                PHP_BINARY,
                dirname(self::DEPFILE, 3) . '/vendor/bin/deptrac',
                'analyse',
                '--config-file=' . $this->probeDepfile,
                '--no-progress',
                '--no-ansi',
                '--formatter=json',
            ],
            ['pipe', 'r'],
        );

        self::assertNotNull($result, 'deptrac process could not start');
        [, $stdout, $stderr] = $result;

        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, 'deptrac json output expected; stdout: ' . $stdout . ' stderr: ' . $stderr);
        $report = $decoded['Report'] ?? null;
        self::assertIsArray($report);

        foreach (['Violations', 'Uncovered', 'Errors'] as $key) {
            self::assertIsInt($report[$key] ?? null, 'deptrac report field missing: ' . $key);
        }

        return [
            'Violations' => $report['Violations'],
            'Uncovered'  => $report['Uncovered'],
            'Errors'     => $report['Errors'],
        ];
    }

    /**
     * Violations where `$className` is the depender, as `DependerPath on TargetLayer`.
     * Deptrac message format: `X must not depend on Y (DependerLayer on TargetLayer)`.
     *
     * @param list<array{message: string}> $violations
     *
     * @return list<string>
     */
    private function edges(array $violations, string $className): array
    {
        $prefix = self::MODULE . '\\';
        $edges  = [];
        foreach ($violations as $violation) {
            $message = $violation['message'];
            if (preg_match('/^(?<from>\S+) must not depend on \S+ \([^)]+ on (?<target>[^)]+)\)$/', $message, $m) !== 1) {
                continue;
            }

            if (str_ends_with($m['from'], '\\' . $className)) {
                $edges[] = str_replace($prefix, '', $m['from']) . ' on ' . $m['target'];
            }
        }

        sort($edges);

        return $edges;
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path) && is_link($path) === false) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
