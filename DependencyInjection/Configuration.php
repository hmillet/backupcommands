<?php

namespace Hmillet\BackupCommandsBundle\DependencyInjection;;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 */
class Configuration implements ConfigurationInterface
{

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('hmillet_backup_commands');

        $rootNode
                ->children()
                    ->arrayNode('dropbox')
                        ->children()
                            ->scalarNode('access_token')->end()
                        ->end()
                    ->end()
                ->end();

        return $treeBuilder;
    }
}
