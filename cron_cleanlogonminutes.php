#!/usr/local/bin/php
<?php
/*************************************************
* SM3LL.net API Cronjob file                     *
* This file contains the main cron job procedure *
* Implementation of the detailed procedures is   *
* in funcs/cron.php                              *
*************************************************/
require('config.php');

define('DEBUG',7);
define('LOGFILE',LOGDIR ."cron.log");

if(!defined('STDIN') )
{
	exit;
}

if ($argc != 3)
{
	exit("Expecting the private key file as parameter.");
}


$cron_file = EXTERNAL_PATH . "/cron_cleanlogonminutes.lock";

// check if cron lock file exists
if (file_exists($cron_file))
{
	$age = time() - filemtime($cron_file);
	if ($age < 60 * 5) // if cron file is less than 5 minutes old, we wait
	{
		ECHO "ERROR: $cron_file exists, cronjob probably running at the moment.\n";
		exit;
	}
	else { // file is older than 30 minutes -> probably a crash, recovering now
		echo "cron $cron_file file exists, but ignoring because it's too old.\n";
	}
}

// create cron lock file (or at least touch it to refresh the date)
touch($cron_file);





// get private key
$keyfile = $argv[2];
global $privateKey;
$privateKey = file_get_contents($keyfile);


require('funcs/basics.php');
init(); // includes all the things
require("funcs/cron.php");


$globalDb = connectToDB();
clean_logon_minutes();


do_log("CRON Clean logon minutes: done!", 0);
echo "CRON Clean logon minutes: done\n";

unlink($cron_file);

?>
