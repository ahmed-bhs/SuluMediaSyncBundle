<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sulu_media_sync');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Default value until the setting is changed in the admin.')
                ->end()
                ->scalarNode('source_locale')
                    ->defaultValue('fr')
                    ->info('Reference locale whose media are copied over.')
                ->end()
                ->arrayNode('template_directories')
                    ->info('Page template directories, scanned to find media properties.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['%kernel.project_dir%/config/templates/pages'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
