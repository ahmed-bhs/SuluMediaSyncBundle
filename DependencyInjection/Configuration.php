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
                    ->info('Valeur par defaut tant que le reglage n\'a pas ete change dans l\'administration.')
                ->end()
                ->scalarNode('source_locale')
                    ->defaultValue('fr')
                    ->info('Locale de reference dont les medias sont recopies.')
                ->end()
                ->arrayNode('template_directories')
                    ->info('Dossiers des gabarits de page, lus pour reperer les proprietes media.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['%kernel.project_dir%/config/templates/pages'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
