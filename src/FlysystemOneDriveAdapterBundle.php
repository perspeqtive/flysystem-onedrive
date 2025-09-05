<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive;

use PERSPEQTIVE\FlysystemOneDrive\DependencyInjection\RegisterOneDriveAdaptersPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FlysystemOneDriveAdapterBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterOneDriveAdaptersPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__.'/../config/services.xml');

        $container->parameters()->set('perspeqtive_flysystem.drives', $this->buildConfig($config));
        $container->parameters()->set('perspeqtive.flysystem_one_drive.credentials.tenantId',  $config['credentials']['tenant_id'] ?? '');
        $container->parameters()->set('perspeqtive.flysystem_one_drive.credentials.clientId',  $config['credentials']['client_id'] ?? '');
        $container->parameters()->set('perspeqtive.flysystem_one_drive.credentials.clientSecret',  $config['credentials']['client_secret'] ?? '');
    }

    private function buildConfig(array $config): array
    {
        $baseOptions = [
            'tenant_id' => $config['credentials']['tenant_id'] ?? null,
            'client_id' => $config['credentials']['client_id'] ?? null,
            'client_secret' => $config['credentials']['client_secret'] ?? null,
        ];

        $finalConfigs = [];

        foreach ($config['drives'] as $name => $onedriveConfig) {
            $mergedOptions = array_merge(
                $baseOptions,
                $onedriveConfig['options'] ?? []
            );

            $finalConfigs[$name] = [
                'drive' => $onedriveConfig['drive'],
                'options' => $mergedOptions,
            ];
        }
        return $finalConfigs;
    }

    public function configure(DefinitionConfigurator $definition): void {
        $definition->rootNode()
            ->children()
                ->arrayNode('credentials')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('tenant_id')->defaultNull()->end()
                        ->scalarNode('client_id')->defaultNull()->end()
                        ->scalarNode('client_secret')->defaultNull()->end()
                    ->end()
                ->end()

                ->arrayNode('drives')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('drive')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('options')
                                ->prototype('variable')->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}