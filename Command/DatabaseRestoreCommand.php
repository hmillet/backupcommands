<?php
namespace Hmillet\BackupCommandsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

use Symfony\Component\Process\Process;

use Dropbox\Client;

/**
 * Command that dumps
 */
class DatabaseRestoreCommand extends ContainerAwareCommand
{
    protected $dumpFolder;
    protected $dumpFile;
    protected $failingProcess;

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
        $toContinue    = true;

        try {
            $dropbox_access_token = $this->getContainer()->getParameter('hmillet_backup_commands.dropbox.access_token');
            $dbx_client           = $this->dropboxConnect($output, $dropbox_access_token);
            $dropboxBackup = true;
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException $e) {
        }

        $this->dumpFolder = $this->getContainer()->get('kernel')->getRootDir() . "/tmp/dump";

        $this->dumpFile   = $this->dumpFolder . '/' . "current.sql.bz2";

        if ($toContinue) {
            $toContinue = $this->prepareEnviroment($output);
        }

        if ($toContinue) {
            if ($dropboxBackup) {
                // First of all, look for a dump file in dropbox
                $srcPath    = $this->dropboxSelectFile($output, $dbx_client);
                // Then download it and put it in dumpfolder as current dump
                $toContinue = $this->dropboxDownloadFile($output, $dbx_client, $srcPath, $this->dumpFile);
            } else {
                $fs         = new Filesystem();
                $toContinue = $fs->exists($this->dumpFile);
            }
        }

        if ($toContinue) {
            $toContinue = $this->mysqlRestore($output);
        }

        if ($toContinue) {
            $output->writeln('<info>MISSION ACCOMPLISHED</info>');
        } else {
            $output->writeln('<error>An error happened</error>');
            if ($this->failingProcess instanceOf Process) {
                $output->writeln(sprintf('<error>%s</error>', $this->failingProcess->getErrorOutput()));
            }
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
        $fs = new Filesystem();
        if (!$fs->exists($this->dumpFolder)) {
            try {
                $fs->mkdir($this->dumpFolder);
                $output->writeln(sprintf('<info>dumpFolder %s succesfully  created</info>', $this->dumpFolder));
                return true;
            } catch (IOException $e) {
                $output->writeln(sprintf('<error>Failed to create dumpFolder %s</error>', $this->dumpFolder));
            }
        }

        return true;
    }

    /**
     * Run Mysql command
     *
     * @param OutputInterface $output
     *
     * @return boolean
     */
    protected function mysqlRestore(OutputInterface $output)
    {
        $dbName     = $this->getContainer()->getParameter('database_name');
        $dbUser     = $this->getContainer()->getParameter('database_user');
        $dbPwd      = $this->getContainer()->getParameter('database_password');
        $dbHost     = $this->getContainer()->getParameter('database_host');

        $fs         = new Filesystem();
        $sqlFile    = substr($this->dumpFile,0,-4);
        $fs->remove($sqlFile);

        $command    = sprintf('bzip2 -dk %s ', $this->dumpFile);
        $decompress = new Process($command);
        $decompress->run();
        if (!$decompress->isSuccessful()) {
            $output->writeln(sprintf('<error>Decompression failed - command : %s </error>', $command));
            $output->writeln(sprintf('<error>Check if bzip2 is installed (sudo apt-get install bzip2)</error>'));
            $this->failingProcess = $decompress;
            return false;
        }

        $output->writeln(sprintf('<info>Sql file decompressed into %s</info>', $sqlFile));

        $mysql      = new Process(sprintf('mysql -e "source %s" -u %s --password=%s -h %s %s ',$sqlFile , $dbUser, $dbPwd, $dbHost, $dbName));
        $mysql->run();
        if (!$mysql->isSuccessful()) {
            $output->writeln(sprintf('<error>Data load failed : %s</error>', $sqlFile));
            $this->failingProcess = $mysql;
            return false;
        }

        $fs->remove($sqlFile);

        $output->writeln(sprintf('<info>Data loaded - Database %s restored succesfully</info>', $dbName));

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
        }

        $output->writeln('<info>You choose : "' . $path . '"</info>');

        return $path;

    }

    protected function dropboxDownloadFile($output, $client, $srcPath, $dstPath)
    {
        $pathError = \Dropbox\Path::findErrorNonRoot($srcPath);
        if ($pathError !== null) {
            $output->writeln('<error>Dropbox download failed - Invalid <dropbox-path> : "' . $pathError . '"</error>');

            return false;
        }

        $metadata = $client->getFile($srcPath, fopen($dstPath, "wb"));
        if ($metadata === null) {
            fwrite(STDERR, "File not found on Dropbox.\n");
            return false;
        }

        $output->writeln('<info>File downloaded from dropbox "' . $srcPath . '" to "' . $dstPath . '"</info>');

        return true;
    }
}
