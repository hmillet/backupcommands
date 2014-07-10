<?php

namespace Hmillet\DatabaseCommandsBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class HmilletDatabaseCommandsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);
        
        if ($config['dropbox']) {
            $container->setParameter('hmillet_database_commands.dropbox.access_token', $config['dropbox']['access_token']);
        }

    }

    public function getNamespace()
    {
        return 'hmillet_database_commands';
    }
}