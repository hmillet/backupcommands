<?php
namespace Hmillet\BackupCommandsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Process\Process;

use Dropbox\Client;

/**
 * Command that dumps
 */
class DatabaseRestoreCommand extends ContainerAwareCommand
{
    protected $directory;
    protected $filename;
    protected $link;
    protected $toFile;

    /**
     * This method set name and description
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('db:restore')
            ->setDescription('This task restore the database from a dump file');
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dropboxBackup = false;
        try {
            $dropbox_access_token = $this->getContainer()->getParameter('hmillet_backup_commands.dropbox.access_token');
            $dbx_client           = $this->dropboxConnect($output, $dropbox_access_token);
            $dropboxBackup = true;
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException $e) {
        }

        $this->directory = $this->getContainer()->get('kernel')->getRootDir() . "/tmp/dump";

        if ($dropboxBackup) {
            // First of all, look for a dump file in dropbox
            $this->dropboxSelectFile($output, $dbx_client);
        } else {
            throw new \Exception("Restore from local files not yet implemented", 1);
            
        }
    }

    /**
     * Create folder for dump
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    protected function prepareEnviroment(OutputInterface $output)
    {
        if (!is_dir($this->directory)) {
            $mkdir = new Process(sprintf('mkdir -p %s', $this->directory));
            $mkdir->run();

            if ($mkdir->isSuccessful()) {
                $output->writeln(sprintf('<info>Directory %s succesfully  created</info>', $this->directory));
                return true;
            }
            $this->failingProcess = $mkdir;
            return false;
        }
        return true;
    }

    /**
     * Dropbox methods
     */

    protected function dropboxConnect($output, $dropbox_access_token)
    {
        try {
            $dbx_client           = new Client($dropbox_access_token, "HmilletBackupCommand/1.0", 'fr');
            $dbx_account_info     = $dbx_client->getAccountInfo();
            $output->writeln('<info>Connected to dropbox account "' . $dbx_account_info['display_name'] . '"</info>');
        } catch (\Dropbox\Exception_InvalidAccessToken $e) {
            $response = $e->getMessage();
            $lines    = explode("\n", $response);
            $message  = json_decode($lines[1], true);
            $output->writeln('<error>Dropbox connection failed : "' . $lines[0] . " - " . $message['error'] . '"</error>');

            return false;
        }

        return $dbx_client;
    }

    protected function dropboxSelectFile($output, $client)
    {
        $output->writeln('<question>Please choose the file to restore</question>');

        $path = "/";
        $entry = $client->getMetadataWithChildren($path);

        while ($entry["is_dir"]) {
            $children = array();
            $folders  = array();
            $files    = array();
            foreach($entry["contents"] as $child) {
                if (($child["is_dir"])) {
                    $folders[] = $child['path'] . "/";
                }
                else {
                    $files[]   = $child['path'];
                }
            }
            $children = array_merge($folders, $files);
            if ($path !== "/") {
                array_unshift($children, $path . "/..");
            }
            $dialog = $this->getHelper('dialog');
            $choice = $dialog->select($output, 'Please select an entry :', $children, 0);

            $path   = rtrim($children[$choice],"/");

            $entry  = $client->getMetadataWithChildren($path);
            $path   = $entry['path'];
            //\Doctrine\Common\Util\Debug::dump($entry,1);
            //break;
        }

        $output->writeln('<info>You choose : "' . $path . '"</info>');

        return $path;

    }

    protected function dropboxDownload($output, $client, $destPath)
    {
        $pathError = \Dropbox\Path::findErrorNonRoot($dropboxPath);
        if ($pathError !== null) {
            $output->writeln('<error>Dropbox upload failed - Invalid <dropbox-path> : "' . $pathError . '"</error>');

            return false;
        }

        $size = null;
        if (\stream_is_local($sourcePath)) {
            $size = \filesize($sourcePath);
        } else {
            $output->writeln('<error>Dropbox upload failed - Invalid <source-path> : "' . $sourcePath . '"</error>');

            return false;
        }

        $fp = fopen($sourcePath, "rb");
        $metadata = $client->uploadFile($dropboxPath, \Dropbox\WriteMode::add(), $fp, $size);
        fclose($fp);

        $output->writeln('<info>File uploaded to dropbox : "' . $metadata['path'] . '"</info>');

        return true;
    }
}
