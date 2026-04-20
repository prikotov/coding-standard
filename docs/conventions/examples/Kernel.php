<?php

declare(strict_types=1);

namespace Common;

use Common\Component\ModuleSystem\ModuleKernelTrait;
use LogicException;
use Override;
use ReflectionObject;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/** @psalm-suppress PropertyNotSetInConstructor */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    use ModuleKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $id
    ) {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function boot(): void
    {
        parent::boot();

        $timezone = $this->getContainer()->getParameter('timezone');
        if (is_string($timezone) && $timezone !== '') {
            date_default_timezone_set($timezone);
        } else {
            throw new RuntimeException('Parameter timezone is not set');
        }

        $preBootFilePath = $this->getCommonConfigDir() . '/preboot.php';

        if (file_exists($preBootFilePath)) {
            $preInitializationServices = require $preBootFilePath;
        } else {
            throw new FileNotFoundException(sprintf("File %s does not exist", $preBootFilePath));
        }
        foreach ($preInitializationServices as $service) {
            if (!$this->getContainer()->initialized($service)) {
                $this->getContainer()->get($service);
            }
        }
    }

    public function getCommonConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    public function getAppConfigDir(): string
    {
        return $this->getProjectDir() . '/apps/' . $this->id . '/config';
    }

    #[Override]
    public function getCacheDir(): string
    {
        return ($_SERVER['APP_CACHE_DIR'] ?? $this->getProjectDir(
        ) . '/var/cache') . '/' . $this->id . '/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return ($_SERVER['APP_LOG_DIR'] ?? $this->getProjectDir() . '/var/log') . '/' . $this->id;
    }

    #[Override]
    public function registerBundles(): iterable
    {
        $commonBundles = $this->getCommonBundles();
        $appBundles = $this->getAppBundles();

        foreach (array_merge($commonBundles, $appBundles) as $class => $envs) {
            if (!$this->isEnvironmentIncluded($envs)) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Class %s does not exist', $class));
            }

            /** @var BundleInterface $bundle */
            $bundle = new $class();
            yield $bundle;
        }
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function getCommonBundles(): array
    {
        $commonBundlesFilePath = $this->getCommonConfigDir() . '/bundles.php';
        if (!is_file($commonBundlesFilePath)) {
            throw new RuntimeException(sprintf('File %s does not exist.', $commonBundlesFilePath));
        }
        return require $commonBundlesFilePath;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function getAppBundles(): array
    {
        $appBundlesFilePath = $this->getAppConfigDir() . '/bundles.php';
        if (!is_file($appBundlesFilePath)) {
            throw new RuntimeException(sprintf('File %s does not exist.', $appBundlesFilePath));
        }
        return require $appBundlesFilePath;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->doConfigureContainer($container, $this->getCommonConfigDir());
        $this->doConfigureContainer($container, $this->getAppConfigDir());
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->doConfigureRoutes($routes, $this->getCommonConfigDir());
        $this->doConfigureRoutes($routes, $this->getAppConfigDir());
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $modules = $this->getModules();
        $this->registerModules($container, $modules);
    }

    /**
     * @return array<string, array<string, bool>> $modules
     */
    private function getModules(): array
    {
        $commonModules = $this->getCommonModules();
        $appModules = $this->getAppModules();

        $intersect = array_intersect_key($commonModules, $appModules);
        if (!empty($intersect)) {
            throw new LogicException(
                sprintf(
                    'There are duplicates in modules for common and application [%s]',
                    implode(', ', array_keys($intersect))
                )
            );
        }

        return array_merge($commonModules, $appModules);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function getCommonModules(): array
    {
        $commonModulesFilePath = $this->getCommonConfigDir() . '/modules.php';
        if (!is_file($commonModulesFilePath)) {
            throw new RuntimeException(sprintf('File %s does not exist.', $commonModulesFilePath));
        }
        return require $commonModulesFilePath;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function getAppModules(): array
    {
        $appModulesFilePath = $this->getAppConfigDir() . '/modules.php';
        if (!is_file($appModulesFilePath)) {
            throw new RuntimeException(sprintf('File %s does not exist.', $appModulesFilePath));
        }
        return require $appModulesFilePath;
    }

    private function doConfigureContainer(ContainerConfigurator $container, string $configDir): void
    {
        $container->import($configDir . '/{packages}/*.{php,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,yaml}');

        if (is_file($configDir . '/services.yaml')) {
            $container->import($configDir . '/services.yaml');
            $envYaml = $configDir . '/services_' . $this->environment . '.yaml';
            if (is_file($envYaml)) {
                $container->import($envYaml);
            }
        } else {
            $container->import($configDir . '/{services}.php');
        }
    }

    protected function doConfigureRoutes(RoutingConfigurator $routes, string $configDir): void
    {
        $routes->import($configDir . '/{routes}/' . $this->environment . '/*.{php,yaml}');
        $routes->import($configDir . '/{routes}/*.{php,yaml}');

        if (is_file($configDir . '/routes.yaml')) {
            $routes->import($configDir . '/routes.yaml');
        } else {
            $routes->import($configDir . '/{routes}.php');
        }

        $fileName = (new ReflectionObject($this))->getFileName();
        if (false !== $fileName) {
            $routes->import($fileName, 'attribute');
        }
    }

    /**
     * @param array<string, bool> $envs
     */
    private function isEnvironmentIncluded(array $envs): bool
    {
        return $envs[$this->environment] ?? $envs['all'] ?? false;
    }
}
