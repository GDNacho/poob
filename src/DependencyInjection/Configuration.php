<?php

namespace Gdnacho\Poob\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('poob');

        $rootNode = $treeBuilder->getRootNode();
        assert($rootNode instanceof ArrayNodeDefinition);

        $rootNode
            ->children()

                ->arrayNode('docs')
                    ->addDefaultsIfNotSet()
                    ->children()

                        ->scalarNode('title')
                            ->defaultValue('Poob API')
                        ->end()

                        ->scalarNode('version')
                            ->defaultValue('1.0.0')
                        ->end()

                        ->scalarNode('description')
                            ->defaultNull()
                        ->end()

                        // SERVERS
                        ->arrayNode('servers')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('url')->isRequired()->end()
                                    ->scalarNode('description')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()

                        // DEFAULT RESPONSES
                        ->arrayNode('default_responses')
                            ->useAttributeAsKey('status')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('description')->isRequired()->end()
                                ->end()
                            ->end()
                            ->defaultValue([
                                '200' => ['description' => 'OK'],
                            ])
                        ->end()

                        // PATH PREFIX
                        ->scalarNode('path_prefix')
                            ->defaultNull()
                        ->end()

                        // OUTPUT FILE
                        ->scalarNode('output')
                            ->defaultValue('%kernel.project_dir%/openapi.yaml')
                        ->end()

                    ->end()
                ->end()

            ->end();

        return $treeBuilder;
    }
}
