<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Metrics;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Metrics\ProjectMetricsCollector;

final class ProjectMetricsCollectorTest extends TestCase
{
    public function testCollectsOnlyProductionClassesInsideConfiguredModules(): void
    {
        $directory = sys_get_temp_dir() . '/project-metrics-collector-' . uniqid();
        $this->write($directory . '/composer.json', json_encode([
            'autoload' => ['psr-4' => [
                'Task\\Common\\' => 'src/',
                'Task\\Web\\' => 'apps/web/src/',
                'Task\\Api\\' => 'apps/api/src/',
                'Task\\Package\\' => 'packages/example/src/',
            ]],
            'autoload-dev' => ['psr-4' => ['Task\\Tests\\' => 'tests/']],
        ], JSON_THROW_ON_ERROR));
        $this->php(
            $directory . '/src/Module/Billing/SharedService.php',
            'Task\\Common\\Module\\Billing',
            'SharedService',
        );
        $this->php(
            $directory . '/apps/web/src/Module/Billing/Controller.php',
            'Task\\Web\\Module\\Billing',
            'Controller',
        );
        $this->php(
            $directory . '/apps/api/src/v1/Module/Chat/Endpoint.php',
            'Task\\Api\\v1\\Module\\Chat',
            'Endpoint',
        );
        $this->php($directory . '/src/Component/Helper.php', 'Task\\Common\\Component', 'Helper');
        $this->php(
            $directory . '/packages/example/src/Module/Internal/PackageClass.php',
            'Task\\Package\\Module\\Internal',
            'PackageClass',
        );
        $this->php(
            $directory . '/tests/Module/Billing/SharedServiceTest.php',
            'Task\\Tests\\Module\\Billing',
            'SharedServiceTest',
        );

        try {
            $report = (new ProjectMetricsCollector(
                (new ParserFactory())->createForNewestSupportedVersion(),
                $directory,
                ['module_patterns' => [
                    'src/Module/*',
                    'apps/*/src/Module/*',
                    'apps/*/src/**/Module/*',
                    'packages/*/src/Module/*',
                ]],
            ))->collect();
        } finally {
            $this->removeDirectory($directory);
        }

        self::assertSame([
            'Task\\Api\\v1\\Module\\Chat\\Endpoint',
            'Task\\Common\\Module\\Billing\\SharedService',
            'Task\\Web\\Module\\Billing\\Controller',
        ], array_column($report['classes'], 'name'));
        self::assertSame([
            'Api/v1:Chat',
            'Common:Billing',
            'Web:Billing',
        ], array_column(array_column($report['classes'], 'metrics'), 'module'));
        self::assertSame([
            'apps/api/src/v1/Module/Chat/Endpoint.php',
            'src/Module/Billing/SharedService.php',
            'apps/web/src/Module/Billing/Controller.php',
        ], array_column(array_column($report['classes'], 'metrics'), 'filePath'));
        self::assertCount(3, $report['functions']);
    }

    public function testCollectsInternalAndCrossModuleTypeDependencies(): void
    {
        $directory = sys_get_temp_dir() . '/project-metrics-dependencies-' . uniqid();
        $this->write($directory . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_THROW_ON_ERROR));
        $this->write($directory . '/src/Module/Alpha/Helper.php', <<<'PHP'
<?php

namespace App\Module\Alpha;

final class Helper
{
}
PHP);
        $this->write($directory . '/src/Module/Beta/Target.php', <<<'PHP'
<?php

namespace App\Module\Beta;

final class Target
{
}
PHP);
        $this->write($directory . '/src/Module/Alpha/Service.php', <<<'PHP'
<?php

namespace App\Module\Alpha;

use App\Module\Beta\Target as BetaTarget;

final class Service
{
    public function __construct(private Helper $helper)
    {
    }

    public function target(): BetaTarget
    {
        return new BetaTarget();
    }
}
PHP);

        try {
            $report = (new ProjectMetricsCollector(
                (new ParserFactory())->createForNewestSupportedVersion(),
                $directory,
                ['module_patterns' => ['src/Module/*']],
            ))->collect();
        } finally {
            $this->removeDirectory($directory);
        }

        self::assertSame([
            ['source' => 'App\\Module\\Alpha\\Service', 'target' => 'App\\Module\\Alpha\\Helper'],
            ['source' => 'App\\Module\\Alpha\\Service', 'target' => 'App\\Module\\Beta\\Target'],
        ], $report['dependencies']);
    }

    private function php(string $path, string $namespace, string $class): void
    {
        $this->write($path, <<<PHP
<?php

namespace $namespace;

final class $class
{
    public function run(): void
    {
    }
}
PHP);
    }

    private function write(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
