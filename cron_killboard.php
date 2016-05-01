#!/usr/local/bin/php
<?php
/*************************************************
* SM3LL.net API Cronjob file                     *
* This file contains the main cron job procedure *
* Implementation of the detailed procedures is   *
* in funcs/cron.php                              *
*************************************************/
error_reporting(E_ALL ^ E_NOTICE);

if(!defined('STDIN') )
{
    echo "This file must be executed from the CLI\n";
    exit;
}


$total_api_calls = 0;
$total_failed_api_calls = 0;


require('config.php');


/**** CONFIG ****/

define('DEBUG',7);
define('LOGFILE',LOGDIR ."cron_killboard.log");

$member_time_diff = 61; // how often are member APIs queried (min: 60 minutes, this is what API dictates us)

/**** CONFIG END *****/



// CHECK FOR FILES AND PERMISSIONS
if (!file_exists(LOGDIR))
{
    echo "Missing directory " . LOGDIR . " - trying to create it...\n";
    if (!mkdir(LOGDIR,770,true))
    {
        echo "Error: Can not create " . LOGDIR . "! Please create this directory with read and write permissions for www-data\n";
        exit();
    }

}

if (!file_exists(TMPDIR))
{
    echo "Missing directory ". TMPDIR . " - trying to create it...\n";
    if (!mkdir(TMPDIR,770,true))
    {
        echo "Error: Can not create " . TMPDIR . "! Please create this directory with read and write permissions for www-data\n";
        exit();
    }
}

if (!file_exists(EXTERNAL_PATH))
{
    echo "Missing " . EXTERNAL_PATH . "! Trying to create it...\n";
    if (!mkdir(EXTERNAL_PATH,770,true))
    {
        echo "Error: Can not create " . EXTERNAL_PATH . "! Please create this directory with read and write permissions for www-data\n";
        exit();
    }
}




// If everything worked so far, we can start looking at cron.lock

$cron_file = EXTERNAL_PATH . "/cron_killboard.lock";


// check if cron lock file exists
if (file_exists($cron_file))
{
	$age = time() - filemtime($cron_file);
	if ($age < 60 * 30) // if cron file is less than 30 minutes old, we wait
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




require('funcs/basics.php');
init(); // includes all the things
require("funcs/cron.php");
require("funcs/cron_killboard.php");

echo("Logging to: ".LOGFILE."\n");
do_log("CRON: Initialising",0);



$globalDb = connectToDB();

$i = 0;
while ($i < 10000) {

	$sql = "SELECT max(external_kill_id) as m, COUNT(*) as c  FROM kills_killmails;";
	$res = $globalDb->query($sql);
	$row = $res->fetch_array();
	$next_kill_id = $row['m'];
	if ($row['c'] == 0)
	{
		$next_kill_id = 0;
	}
	

	echo "Run $i, fetching kills after $next_kill_id\n";
	$cnt = parse_zkillboard_data($next_kill_id);
	echo "Added $cnt killmails.\n";
	if ($cnt == 0)
		break;

	$i++;
        // sleep21 second, dont want to hammer zkill servers too much, right?
	sleep(60);
}

// delete cron file
unlink($cron_file);

do_log("CRON: Done!", 0);
echo "CRON: Done\n";

?>
