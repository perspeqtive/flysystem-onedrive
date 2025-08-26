<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\DependencyInjection;

use PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class FlysystemOnedriveAdapterExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('perspeqtive_flysystem.drives', $this->buildConfig($config));
        $this->buildAdapters($config, $container);

    }

    private function buildAdapters(array $configs, ContainerBuilder $container): void
    {

        $drives = $container->getParameter('perspeqtive_flysystem.drives');
        foreach (array_keys($drives) as $name) {
            $serviceId = sprintf('perspeqtive_flysystem.onedriveadapter.%s', $name);

            $adapterDef = new Definition(OneDriveAdapter::class);
            $adapterDef
                ->setFactory([new Reference('perspeqtive_flysystem.onedrive.factory'), 'get'])
                ->setArguments([$name])
                ->setPublic(true);

            $container->setDefinition($serviceId, $adapterDef);
        }
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
}
