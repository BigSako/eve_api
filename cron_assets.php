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

// CHECK IF WE ARE ON A CONSOLE
if(!defined('STDIN') )
	exit("This program can only be launched from console - exiting.\n");

// CHECK FOR SOME NECESSARY DIRECTORIES
if (!file_exists(LOGDIR))
	exit("Missing directory " . LOGDIR . " - exiting.\n");

if (!file_exists(TMPDIR))
	exit("Missing directory ". TMPDIR . " - exiting.\n");

if (!file_exists(EXTERNAL_PATH))
	exit("Missing " . EXTERNAL_PATH . "! exiting.\n");

if ($argc != 3)
	exit("Expecting the private key file as parameter - exiting.\n");


// get private key
$keyfile = $argv[2];
global $privateKey;
$privateKey = file_get_contents($keyfile);


require('funcs/basics.php');
init(); // includes all the things
require("funcs/cron.php");

echo("Logging to: ".LOGFILE."\n");
do_log("CRON: Initialising",0);


$globalDb = connectToDB();
import_player_asset_data();


do_log("CRON Assets: Done!", 0);
echo "CRON: Done\n";

?>
