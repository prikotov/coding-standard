<?php

declare(strict_types=1);

namespace Common\Component\ModuleSystem;

use Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass;
use Common\Component\ModuleSystem\DependencyInjection\TwigCompilerPass;
use Common\Component\ModuleSystem\Extension\DoctrineInterface;
use Common\Component\ModuleSystem\Extension\TranslationInterface;
use Common\Component\ModuleSystem\Extension\TwigInterface;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @method bool isEnvironmentIncluded(array $envs)
 */
trait ModuleKernelTrait
{
    /**
     * @param array<string, array<string, bool>> $modules
     */
    private function registerModules(ContainerBuilder $container, array $modules): void
    {
        foreach ($modules as $class => $envs) {
            if (!$this->isEnvironmentIncluded($envs)) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException(
                    sprintf('Class %s does not exist', $class)
                );
            }

            $module = new $class();
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(
                    sprintf('Module must implement %s interface', ModuleInterface::class)
                );
            }

            $container->addCompilerPass(
                new ModuleCompilerPass($module->getModuleConfigPath(), $this->environment),
                PassConfig::TYPE_BEFORE_OPTIMIZATION,
                10000
            );

            if ($module instanceof TwigInterface) {
                $additionalPaths = $module->getAdditionalTemplatesPaths();

                $container->addCompilerPass(
                    new TwigCompilerPass(
                        defaultTemplatePath: $module->getBaseTemplatesPath(),
                        defaultTwigNamespace: $module->getBaseTwigNamespace(),
                        additionalPathMap: $additionalPaths,
                    ),
                );
            }

            if ($module instanceof TranslationInterface) {
                $container->prependExtensionConfig('framework', [
                    'translator' => ['paths' => [$module->getBaseTranslationsPath()]],
                ]);

                foreach ($module->getAdditionalTranslationsPaths() as $path) {
                    $container->prependExtensionConfig('framework', [
                        'translator' => ['paths' => [$path]],
                    ]);
                }
            }

            if (
                $module instanceof DoctrineInterface
                && is_dir($module->getMappingPath())
            ) {
                $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                    [$module->getEntityNamespace()],
                    [$module->getMappingPath()],
                ));
            }
        }

        $this->registerCommonDoctrineComponents($container);
    }

    private function registerCommonDoctrineComponents(ContainerBuilder $container): void
    {
        $componentDoctrinePath = $this->getProjectDir() . '/src/Component/Doctrine';

        if (!is_dir($componentDoctrinePath)) {
            return;
        }

        $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
            ['Common\Component\Doctrine'],
            [$componentDoctrinePath],
        ));
    }
}
