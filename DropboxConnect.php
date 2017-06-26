<?php
namespace Hmillet\BackupCommandsBundle;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;

class DropboxConnect
{
   
    private $dropbox_access_token;
    
    function __construct($dropbox_access_token) {
        $this->dropbox_access_token = $dropbox_access_token;
    }
    
    public function connect($output)
    {
        try {
            $app = new DropboxApp("client_id", "client_secret", $this->dropbox_access_token);
            $dropbox = new Dropbox($app);
            
            
            $account = $dropbox->getCurrentAccount();
            
            $output->writeln('<info>Connected to dropbox account "' . $account->getDisplayName() . '"</info>');
        } catch (\Kunnu\Dropbox\Exceptions\DropboxClientException $e) {
            
            $message = $e->getMessage();
            
            $output->writeln('<error>Dropbox connection failed : "' . $message . '"</error>');

            return false;
        }

        return $dropbox;
    }

}
