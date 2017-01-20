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
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function ($value) { return ['host' => $value]; })
                            ->end()
                            ->children()
                                ->scalarNode('host')
                                    ->info('Default read-write database connection DSN, such as driver://example.com[:port]/database')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('user')->defaultNull()->end()
                                ->scalarNode('password')->defaultNull()->end()
                                ->scalarNode('charset')->defaultValue('UTF-8')->end()
                                ->booleanNode('debug')->defaultFalse()->end()
                                ->scalarNode('driver_class')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('readonly')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function ($value) { return ['host' => $value]; })
                            ->end()
                            ->children()
                                ->scalarNode('host')
                                    ->info('Readonly optional database DSN, such as driver://example.com[:port]/database')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('user')->defaultNull()->end()
                                ->scalarNode('password')->defaultNull()->end()
                                ->scalarNode('charset')->defaultValue('UTF-8')->end()
                                ->booleanNode('debug')->defaultFalse()->end()
                                ->scalarNode('driver_class')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mapping')
                    ->normalizeKeys(true)
                    ->prototype('array')
                        ->info("Define how mappers will be registered for this bundle; key must be the bundle identifier, such as 'AppBundle'")
                        ->children()
                            ->enumNode('type')
                                ->values(['annotation'])
                                ->info("How mappers will be registered, it can be 'annotation'")
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('directory')
                                ->info("Required if type is 'annotation', it is a bundle relative directory where class lies in")
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('alias')
                                ->info("Mappers namespace, if you don't want it to be the bundle name you specified as this array key")
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
