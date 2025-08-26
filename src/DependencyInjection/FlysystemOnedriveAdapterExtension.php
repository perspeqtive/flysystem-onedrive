<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class FlysystemOnedriveAdapterExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('perspeqtive_flysystem.onedrive.drive', $config['onedrive']['drive']);
        $container->setParameter('perspeqtive_flysystem.onedrive.options', $config['onedrive']['options']);
    }
}