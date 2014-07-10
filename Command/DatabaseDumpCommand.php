<?php
namespace Hmillet\DatabaseCommandsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use Dropbox\Client;

/**
 * Command that dumps
 */
class DatabaseDumpCommand extends ContainerAwareCommand
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
            ->setName('db:dump')
            ->setDescription('This task dump the database in a file');
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
        $dropboxBackup = false;
        try {
            $dropbox_access_token = $this->getContainer()->getParameter('hmillet_database_commands.dropbox.access_token');
            $dbx_client           = $this->dropboxConnect($output, $dropbox_access_token);
            $dropboxBackup = true;
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException $e) {
        }
        
        $this->directory = $this->getContainer()->get('kernel')->getRootDir() . "/tmp/dump";
        $this->link      = $this->directory . '/' . "current.sql.bz2";

        $dbName          = $this->getContainer()->getParameter('database_name');
        $this->filename  = $dbName . "-" . date('YmdHis').'.sql.bz2';
        $this->toFile    = $this->directory . '/' . $this->filename;

        $time = new \DateTime();

        if ($this->prepareEnviroment($output)
            && $this->mysqldump($output)
            && $this->createLink($output)
        ) {
            $output->writeln("<info>Dumped in $this->toFile in ". $time->diff($time = new \DateTime())->format('%s seconds').'</info>');
            if ($dropboxBackup) {
               if ($this->dropboxUpload($output, $dbx_client, $this->toFile, '/' . $dbName . '/' . $this->filename)) {
                   unlink($this->toFile);
                   $output->writeln(sprintf('<info>Deleted file %s succesfully</info>', $this->toFile));
               }
            }
            $output->writeln('<info>MISSION ACCOMPLISHED</info>');
        } else {
            $output->writeln('<error>Nasty error happened :\'-(</error>');
            if ($this->failingProcess instanceOf Process) {
                $output->writeln('<error>%s</error>', $this->failingProcess->getErrorOutput());
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
        // $output->writeln(sprintf('<info>Directory %s already exists</info>', $this->directory));
        return true;
    }

    /**
     * Run MysqlDump
     * 
     * @param OutputInterface $output
     * 
     * @return boolean 
     */
    protected function mysqldump(OutputInterface $output)
    {
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPwd  = $this->getContainer()->getParameter('database_password');
        $dbHost = $this->getContainer()->getParameter('database_host');
        $mysqldump=  new Process(sprintf('mysqldump -u %s --password=%s -h %s %s | bzip2 -c > %s', $dbUser, $dbPwd, $dbHost, $dbName, $this->toFile));
        $mysqldump->run();
        if ($mysqldump->isSuccessful()) {
            $output->writeln(sprintf('<info>Database %s dumped succesfully</info>', $dbName));
            return true;
        }
        $this->failingProcess = $mysqldump;
        return false;
    }

    /**
     * Create link to last dump
     * 
     * @param type $output
     * 
     * @return boolean 
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
    
    /**
     * Dropbox methods
     */
    
    protected function dropboxConnect($output, $dropbox_access_token)
    {
        try {
            $dbx_client           = new Client($dropbox_access_token, "HmilletDatabaseCommand/1.0", 'fr');
            //var_dump($dbx_client->getAccountInfo());
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

    protected function dropboxUpload($output, $client, $sourcePath, $dropboxPath)
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

        //print_r($metadata);
        $output->writeln('<info>File uploaded to dropbox : "' . $metadata['path'] . '"</info>');
        
        return true;
    }
}
