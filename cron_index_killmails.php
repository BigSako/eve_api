#!/usr/local/bin/php
<?php
/*************************************************
* SM3LL.net API Cronjob file                     *
* This file contains the main cron job procedure *
* Implementation of the detailed procedures is   *
* in funcs/cron.php                              *
*************************************************/
require('config.php');
require('funcs/killboard.php');

define('DEBUG',7);
define('LOGFILE',LOGDIR ."cron.log");

if(!defined('STDIN') )
{
	exit;
}



$cron_file = EXTERNAL_PATH . "/cron_index_killmails.lock";
/*
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
}*/

// create cron lock file (or at least touch it to refresh the date)
touch($cron_file);



require('funcs/basics.php');
init(); // includes all the things
require("funcs/cron.php");


$globalDb = connectToDB();
index_killmails();
remove_old_killmails();


do_log("CRON Index Killmails: done!", 0);
echo "CRON Index Killmails: done\n";

unlink($cron_file);

?>
