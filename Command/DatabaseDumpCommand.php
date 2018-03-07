<?php

namespace Hmillet\BackupCommandsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Hmillet\BackupCommandsBundle\DropboxConnect;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;

/**
 * Command that dumps.
 */
class DatabaseDumpCommand extends ContainerAwareCommand
{
    protected $dumpFolder;
    protected $filename;
    protected $link;
    protected $toFile;
    protected $failingProcess;

    /**
     * This method set name and description.
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('db:dump')
            ->setDescription('This task dump the database in a file');
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dropboxBackup = false;
        try {
            $dropbox_access_token = $this->getContainer()->getParameter('hmillet_backup_commands.dropbox.access_token');

            $connection = new DropboxConnect($dropbox_access_token);
            $dbx_client = $connection->connect($output);
            $dropboxBackup = true;
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException $e) {
        }

        $this->dumpFolder = $this->getContainer()->get('kernel')->getRootDir().'/tmp/dump';
        $this->link = $this->dumpFolder.'/'.'current.sql.bz2';

        $dbName = $this->getContainer()->getParameter('database_name');
        $this->filename = $dbName.'-'.date('YmdHis').'.sql.bz2';
        $this->toFile = $this->dumpFolder.'/'.$this->filename;

        $time = new \DateTime();

        if ($this->prepareEnviroment($output)
            && $this->mysqldump($output)
            && $this->createLink($output)
        ) {
            $output->writeln("<info>Dumped in $this->toFile in ".$time->diff($time = new \DateTime())->format('%s seconds').'</info>');
            if ($dropboxBackup) {
                if ($this->dropboxUpload($output, $dbx_client, $this->toFile, '/'.$dbName.'/'.$this->filename)) {
                    unlink($this->toFile);
                    $output->writeln(sprintf('<info>Deleted file %s succesfully</info>', $this->toFile));
                }
            }
            $output->writeln('<info>MISSION ACCOMPLISHED</info>');
        } else {
            $output->writeln('<error>An error happened</error>');
            if ($this->failingProcess instanceof Process) {
                $output->writeln(sprintf('<error>%s</error>', $this->failingProcess->getErrorOutput()));
            }
        }
    }

    /**
     * Create folder for dump.
     *
     * @param OutputInterface $output
     *
     * @return bool
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
     * Run MysqlDump.
     *
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function mysqldump(OutputInterface $output)
    {
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPwd = $this->getContainer()->getParameter('database_password');
        $dbHost = $this->getContainer()->getParameter('database_host');
        $mysqldump = new Process(sprintf('mysqldump -u %s --password=%s -h %s %s | bzip2 -c > %s', $dbUser, $dbPwd, $dbHost, $dbName, $this->toFile));
        $mysqldump->setTimeout(3600);
        $mysqldump->run();
        if ($mysqldump->isSuccessful()) {
            $output->writeln(sprintf('<info>Database %s dumped succesfully</info>', $dbName));

            return true;
        }
        $this->failingProcess = $mysqldump;

        return false;
    }

    /**
     * Create link to last dump.
     *
     * @param type $output
     *
     * @return bool
     */
    protected function createLink($output)
    {
        $link = new Process(sprintf('ln -f %s %s', $this->toFile, $this->link));
        $link->run();
        if ($link->isSuccessful()) {
            $output->writeln(sprintf('<info>Link %s created succesfully</info>', $this->link));

            return true;
        }
        $this->failingProcess = $link;

        return false;
    }

    protected function dropboxUpload($output, $dropbox, $sourcePath, $dropboxPath)
    {
        if (\stream_is_local($sourcePath)) {
            $dropboxFile = new DropboxFile($sourcePath);
            $file = $dropbox->upload($dropboxFile, $dropboxPath, ['autorename' => true]);

            $output->writeln('<info>File uploaded to dropbox : "'.$file->getPathDisplay().'"</info>');

            return true;
        } else {
            $output->writeln('<error>Dropbox upload failed - Invalid <source-path> : "'.$sourcePath.'"</error>');

            return false;
        }
    }
}
