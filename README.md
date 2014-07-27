# Hmillet Backup Commands Bundle #

This bundle provides symfony console commands that allow you to backup your database and your files in your dropbox

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/06c239a1-6497-4210-b96a-0cd9d8e05e79/big.png)](https://insight.sensiolabs.com/projects/06c239a1-6497-4210-b96a-0cd9d8e05e79)

## Installation ##

### Step 1

#### Using Composer

Add the following code to your composer.json :

    "require": {
        ...
        "hmillet/backup-commands-bundle": "dev-master",
        "dropbox/dropbox-sdk": "1.1.*"
        ...
    },

Run a Composer update

    $ php composer.phar update


### Step 2

Register the bundle in the `AppKernel.php` file :

    public function registerBundles()
    {
        $bundles = array(
            ...
            new Hmillet\BackupCommandsBundle\HmilletBackupCommandsBundle(),
            ...
        );

        return $bundles;
    }

### Step 3 (optionnal)

Add parameters and configuration for the bundle, so it can read and write to your dropbox.

First of all, you have to get an access token. In order to get it, just run :

    php app/console dropbox:setup

in app/config/parameters.yml-dist (otherwise, "composer.phar update" will remove your parameter from parameters.yml), add the line :

    backup_dropbox_access_token: ~

in app/config/parameters.yml, add the line given by the above command :

    backup_dropbox_access_token: your token

in app/config/config.yml, add the lines :

    hmillet_backup_commands:
        dropbox:
            access_token: %backup_dropbox_access_token%

## Requirements ##

This bundle needs (in local and remote server)

  * mysql (commandline)
  * mysqldump (commandline)
  * bunzip2 (commandline)

The library dropbox-sdk-php needs :

  * PHP 5.3+, [with 64-bit integers](http://stackoverflow.com/questions/864058/how-to-have-64-bit-integer-on-php).
  * PHP [cURL extension](http://php.net/manual/en/curl.installation.php) with SSL enabled (it's usually built-in).
  * Must not be using [`mbstring.func_overload`](http://www.php.net/manual/en/mbstring.overload.php) to overload PHP's standard string functions.


## Command line ##

Now from your console you can run

    ./app/console db:dump

and see that a new file has been saved in folder /app/tmp/dump with an hard link to the newest one.


