<?php

namespace Hmillet\BackupCommandsBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class HmilletBackupCommandsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        if (isset($config['dropbox'])) {
            $container->setParameter('hmillet_backup_commands.dropbox.access_token', $config['dropbox']['access_token']);
        }

    }

    public function getNamespace()
    {
        return 'hmillet_backup_commands';
    }
}