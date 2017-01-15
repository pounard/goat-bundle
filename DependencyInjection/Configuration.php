<?php

namespace Goat\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('goat');

        $rootNode
            ->children()
                ->booleanNode('debug')->defaultFalse()->end()
                ->arrayNode('connection')
                    ->children()
                        ->arrayNode('readwrite')
                            ->isRequired()
                            ->children()
                                ->scalarNode('host')->isRequired()->end()
                                ->scalarNode('user')->defaultNull()->end()
                                ->scalarNode('password')->defaultNull()->end()
                                ->scalarNode('charset')->defaultValue('UTF-8')->end()
                                ->booleanNode('debug')->defaultFalse()->end()
                                ->scalarNode('driver_class')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('readonly')
                            ->children()
                                ->scalarNode('host')->isRequired()->end()
                                ->scalarNode('user')->defaultNull()->end()
                                ->scalarNode('password')->defaultNull()->end()
                                ->scalarNode('charset')->defaultValue('UTF-8')->end()
                                ->booleanNode('debug')->defaultFalse()->end()
                                ->scalarNode('driver_class')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
