<?php

declare(strict_types=1);

namespace Justus\FlysystemOneDrive\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class FlysystemOnedriveAdapterExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('perspeqtive.flysystem.onedrive.drive', $config['flysystem']['onedrive']['drive']);
        $container->setParameter('perspeqtive.flysystem.onedrive.options', $config['flysystem']['onedrive']['options']);
    }
}