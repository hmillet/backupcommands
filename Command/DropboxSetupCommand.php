<?php
namespace Hmillet\BackupCommandsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command that dumps
 */
class DropboxSetupCommand extends ContainerAwareCommand
{
    private static $description = 'This task allow you to get your dropbox access token, so you can setup your "backup_dropbox_access_token" parameter and then backup your files to your dropbox';

    /**
     * This method set name and description
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('dropbox:setup')
            ->setDescription(self::$description);
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract class is not implemented
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>' . self::$description . '</comment>');
        $output->writeln('<info>Please follow this process :</info>');
        $output->writeln('');

        $authorizeUrl   = json_decode(file_get_contents('http://api.hmillet.com/dropbox/authorization/start/hmillet-backup-symfony2-bundle'));
        $output->writeln('1. Go to: ' . $authorizeUrl);
        $output->writeln('2. Click "Allow" (you might have to log in first)');
        $output->writeln('3. Get your authorization code');

        $dialog         = $this->getHelperSet()->get('dialog');
        $authCode       = $dialog->ask($output, '4. Enter the authorization code here : ');
        $accessToken    = json_decode(file_get_contents('http://api.hmillet.com/dropbox/authorization/finish/hmillet-backup-symfony2-bundle/' . $authCode));
        $output->writeln('');

        $output->writeln('5. Put the following line "backup_dropbox_access_token: ' . $accessToken . '" in your  "app/config/parameters.yml" file');
        $output->writeln('');
    }
}
