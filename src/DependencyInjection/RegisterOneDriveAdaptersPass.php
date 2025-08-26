<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterOneDriveAdaptersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('perspeqtive.flysystem_one_drive.flysystem.one_drive_flysystem_factory')) {
            return;
        }

        $drives = $container->getParameter('perspeqtive_flysystem.drives');
        foreach (array_keys($drives) as $name) {
            $serviceId = sprintf('perspeqtive_flysystem.onedriveadapter.%s', $name);

            $adapterDef = new Definition(\PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveAdapter::class);
            $adapterDef
                ->setFactory([new Reference('perspeqtive.flysystem_one_drive.flysystem.one_drive_flysystem_factory'), 'get'])
                ->setArguments([$name])
                ->setPublic(true);

            $container->setDefinition($serviceId, $adapterDef);
        }
    }
}
