<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Di;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\DirectoryRemover;

/**
 * Runs bin/coding-standard-di-check against an isolated consumer project
 * built from scratch in a temporary directory.
 */
final class CodingStandardDiCheckCommandTest extends TestCase
{
    private const CONVENTIONAL_EXCLUDE = [
        '%module.billing.module_dir%/Resource/',
        '%module.billing.module_dir%/Domain/Entity/',
        '%module.billing.module_dir%/**/*Dto.php',
        '%module.billing.module_dir%/**/*Event.php',
        '%module.billing.module_dir%/**/*Exception.php',
        '%module.billing.module_dir%/**/*Enum.php',
        '%module.billing.module_dir%/**/*Vo.php',
        '%module.billing.module_dir%/Application/UseCase/Command/**/*Command.php',
        '%module.billing.module_dir%/Application/UseCase/Query/**/*Query.php',
        '%module.billing.module_dir%/BillingModule.php',
    ];

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/coding-standard-di-check-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        (new DirectoryRemover())->remove($this->directory);
    }

    public function testPassesWhenExcludeCoversAllNonServiceTypes(): void
    {
        $this->createModule();

        $result = $this->execute();

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('exclude covers non-service types of module Task\Common\Module\Billing', $result['output']);
        self::assertStringContainsString('OK: non-service classes are excluded from Symfony DI.', $result['output']);
    }

    public function testFailsWhenOnlyIndividualDtoFilesAreExcluded(): void
    {
        $this->createModule([
            'exclude' => $this->replaceSuffixMasks('Dto', [
                '%module.billing.module_dir%/Application/Dto/OrderDto.php',
                '%module.billing.module_dir%/Application/Dto/AmountDto.php',
                '%module.billing.module_dir%/Application/UseCase/Command/CreateOrder/CreateOrderDto.php',
            ]),
        ]);

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('exclude does not fully cover *Dto.php', $result['output']);
        self::assertStringContainsString("Add '%module.billing.module_dir%/**/*Dto.php' to exclude.", $result['output']);
    }

    public function testFailsWhenOnlyDtoDirectoryIsExcluded(): void
    {
        $this->createModule([
            'exclude' => $this->replaceSuffixMasks('Dto', [
                '%module.billing.module_dir%/Application/Dto/',
            ]),
        ]);

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('exclude does not fully cover *Dto.php', $result['output']);
        self::assertStringContainsString("Add '%module.billing.module_dir%/**/*Dto.php' to exclude.", $result['output']);
    }

    public function testFailsWhenExcludeIsMissingEntirely(): void
    {
        $this->createModule(['exclude' => []]);

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('exclude does not fully cover *Dto.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Event.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Exception.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Enum.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Vo.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Command.php', $result['output']);
        self::assertStringContainsString('exclude does not fully cover *Query.php', $result['output']);
    }

    public function testFailsWhenOnlyOneSuffixMaskIsMissing(): void
    {
        $this->createModule([
            'exclude' => $this->replaceSuffixMasks('Vo', []),
        ]);

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertSame(1, substr_count($result['output'], '[FAIL]'));
        self::assertStringContainsString('exclude does not fully cover *Vo.php', $result['output']);
    }

    public function testFailsWhenUseCaseCommandMaskIsNotRecursive(): void
    {
        $this->createModule([
            'exclude' => $this->replaceSuffixMasks('Command', [
                '%module.billing.module_dir%/Application/UseCase/Command/*Command.php',
            ]),
        ]);

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('exclude does not fully cover *Command.php', $result['output']);
        self::assertStringContainsString(
            "Add '%module.billing.module_dir%/Application/UseCase/Command/**/*Command.php' to exclude.",
            $result['output'],
        );
    }

    public function testFailsWhenServiceInjectsDto(): void
    {
        $this->createModule();
        $this->writeClass(
            'Task\Common\Module\Billing\Infrastructure\Adapter',
            'BillingAdapter',
            "final class BillingAdapter\n{\n    public function __construct(\n        private readonly AmountDto \$amount,\n    ) {\n    }\n}\n",
            ['Task\Common\Module\Billing\Application\Dto\AmountDto'],
        );

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString(
            'Infrastructure/Adapter/BillingAdapter.php:11: constructor of Task\Common\Module\Billing\Infrastructure\Adapter\BillingAdapter'
            . ' injects non-service Task\Common\Module\Billing\Application\Dto\AmountDto (DTO)',
            $result['output'],
        );
        self::assertStringContainsString('pass them as method arguments', $result['output']);
    }

    public function testFailsWhenServiceInjectsApplicationCommand(): void
    {
        $this->createModule();
        $this->writeClass(
            'Task\Common\Module\Billing\Application\UseCase\Command\CreateOrder',
            'CreateOrderCommandHandler',
            "final class CreateOrderCommandHandler\n{\n    public function __construct(\n        private readonly CreateOrderCommand \$command,\n    ) {\n    }\n\n    public function __invoke(): void\n    {\n    }\n}\n",
        );

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertStringContainsString(
            'injects non-service Task\Common\Module\Billing\Application\UseCase\Command\CreateOrder\CreateOrderCommand (application command)',
            $result['output'],
        );
    }

    public function testAcceptsSymfonyConsoleCommandInjection(): void
    {
        $this->createModule();
        $this->writeClass(
            'Task\Common\Module\Billing\Infrastructure\Adapter',
            'BillingAdapter',
            "final class BillingAdapter\n{\n    public function __construct(\n        private readonly SyncBillingCommand \$sync,\n    ) {\n    }\n}\n",
            ['Task\Common\Module\Billing\Presentation\Cli\SyncBillingCommand'],
        );

        $result = $this->execute();

        self::assertSame(0, $result['code'], $result['output']);
    }

    public function testDoesNotRequireCommandMaskWhenModuleHasNoUseCases(): void
    {
        $this->createModule([
            'exclude' => $this->replaceSuffixMasks(
                'Query',
                $this->replaceSuffixMasks('Command', []),
            ),
            'withoutUseCases' => true,
        ]);

        $result = $this->execute();

        self::assertSame(0, $result['code'], $result['output']);
    }

    public function testIgnoresConstructorOfClassesExcludedFromContainer(): void
    {
        $this->createModule();
        $this->writeClass(
            'Task\Common\Module\Billing\Domain\Entity',
            'PaymentModel',
            "final class PaymentModel\n{\n    public function __construct(\n        private readonly MoneyVo \$money,\n    ) {\n    }\n}\n",
            ['Task\Common\Module\Billing\Domain\ValueObject\MoneyVo'],
        );

        $result = $this->execute();

        self::assertSame(0, $result['code'], $result['output']);
    }

    public function testReportsEachClassOnceWhenModuleIsImportedBySeveralConfigs(): void
    {
        $this->createModule();
        $this->writeClass(
            'Task\Common\Module\Billing\Infrastructure\Adapter',
            'BillingAdapter',
            "final class BillingAdapter\n{\n    public function __construct(\n        private readonly AmountDto \$amount,\n    ) {\n    }\n}\n",
            ['Task\Common\Module\Billing\Application\Dto\AmountDto'],
        );
        $this->writeFile(
            'config/services.yaml',
            str_replace(
                '%module.billing.module_dir%',
                '../src/Module/Billing',
                (string) file_get_contents($this->directory . '/src/Module/Billing/Resource/config/services.yaml'),
            ),
        );

        $result = $this->execute();

        self::assertSame(1, $result['code']);
        self::assertSame(
            1,
            substr_count($result['output'], 'injects non-service Task\Common\Module\Billing\Application\Dto\AmountDto (DTO)'),
            $result['output'],
        );
    }

    public function testWarnsWhenNoModuleConfigExists(): void
    {
        $result = $this->execute();

        self::assertSame(0, $result['code'], $result['output']);
        self::assertStringContainsString('no module services.yaml with a resource import found', $result['output']);
    }

    /**
     * @param array{exclude?: list<string>, withoutUseCases?: bool} $options
     */
    private function createModule(array $options = []): void
    {
        $exclude = $options['exclude'] ?? self::CONVENTIONAL_EXCLUDE;
        $excludeLines = $exclude === []
            ? ''
            : "    exclude:\n" . implode('', array_map(
                static fn (string $entry): string => "      - '$entry'\n",
                $exclude,
            ));

        $this->writeFile('src/Module/Billing/Resource/config/services.yaml', <<<YAML
            parameters:
              module.billing.module_dir: '%kernel.project_dir%/src/Module/Billing'

            services:
              _defaults:
                autowire: true
                autoconfigure: true

              Task\\Common\\Module\\Billing\\:
                resource: '%module.billing.module_dir%/'
            $excludeLines
            YAML);

        $this->writeClass('Task\Common\Module\Billing', 'BillingModule', "final class BillingModule\n{\n}\n");

        if (!($options['withoutUseCases'] ?? false)) {
            $this->writeClass(
                'Task\Common\Module\Billing\Application\UseCase\Command\CreateOrder',
                'CreateOrderCommand',
                "final readonly class CreateOrderCommand\n{\n    public function __construct(\n        public string \$orderId,\n    ) {\n    }\n}\n",
            );
            $this->writeClass(
                'Task\Common\Module\Billing\Application\UseCase\Command\CreateOrder',
                'CreateOrderCommandHandler',
                "final readonly class CreateOrderCommandHandler\n{\n    public function __invoke(CreateOrderCommand \$command): void\n    {\n    }\n}\n",
            );
            $this->writeClass(
                'Task\Common\Module\Billing\Application\UseCase\Command\CreateOrder',
                'CreateOrderDto',
                "final readonly class CreateOrderDto\n{\n    public function __construct(\n        public string \$status,\n    ) {\n    }\n}\n",
            );
            $this->writeClass(
                'Task\Common\Module\Billing\Application\UseCase\Query\FindOrder',
                'FindOrderQuery',
                "final readonly class FindOrderQuery\n{\n    public function __construct(\n        public string \$orderId,\n    ) {\n    }\n}\n",
            );
        }

        $this->writeClass(
            'Task\Common\Module\Billing\Application\Dto',
            'AmountDto',
            "final readonly class AmountDto\n{\n    public function __construct(\n        public int \$value,\n    ) {\n    }\n}\n",
        );
        $this->writeClass(
            'Task\Common\Module\Billing\Application\Dto',
            'OrderDto',
            "final readonly class OrderDto\n{\n    public function __construct(\n        public string \$id,\n        public AmountDto \$amount,\n    ) {\n    }\n}\n",
        );
        $this->writeClass(
            'Task\Common\Module\Billing\Domain\ValueObject',
            'MoneyVo',
            "final readonly class MoneyVo\n{\n    public function __construct(\n        public int \$amount,\n    ) {\n    }\n}\n",
        );
        $this->writeClass(
            'Task\Common\Module\Billing\Presentation\Cli',
            'SyncBillingCommand',
            "#[AsCommand(name: 'billing:sync')]\nfinal class SyncBillingCommand extends Command\n{\n}\n",
            ['Symfony\\Component\\Console\\Attribute\\AsCommand', 'Symfony\\Component\\Console\\Command\\Command'],
        );
    }

    /**
     * Replaces the module-wide mask of the given suffix with the provided
     * selective patterns (empty list removes the coverage).
     *
     * @param list<string> $replacement
     *
     * @return list<string>
     */
    private function replaceSuffixMasks(string $suffix, array $replacement): array
    {
        $kept = array_values(array_filter(
            self::CONVENTIONAL_EXCLUDE,
            static fn (string $entry): bool => !str_contains($entry, '*' . $suffix . '.php'),
        ));

        return [...$kept, ...$replacement];
    }

    /** @param list<string> $uses */
    private function writeClass(string $namespace, string $className, string $body, array $uses = []): void
    {
        $modulePrefix = 'Task\\Common\\Module\\Billing';
        $insideModule = str_starts_with($namespace, $modulePrefix)
            ? substr($namespace, strlen($modulePrefix))
            : '\\' . $namespace;
        $relative = trim(str_replace('\\', '/', $insideModule), '/') . '/' . $className . '.php';
        $useLines = $uses === []
            ? ''
            : implode('', array_map(static fn (string $use): string => "use $use;\n", $uses));
        $this->writeFile(
            'src/Module/Billing/' . $relative,
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n$useLines$body",
        );
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $path = $this->directory . '/' . $relativePath;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    }

    /** @return array{code: int, output: string} */
    private function execute(): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/coding-standard-di-check', $this->directory];
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output . $error];
    }
}
