<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('flysystem_onedrive_adapter');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('onedrive')
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
        ->end();

        return $treeBuilder;
    }
}