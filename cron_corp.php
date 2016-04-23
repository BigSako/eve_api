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

define('DEBUG',0);
define('LOGFILE',LOGDIR ."cron_corp.log");


// CHECK FOR FILES AND PERMISSIONS
if (!file_exists(LOGDIR))
	exit("Missing directory " . LOGDIR . " - exiting.\n");

if (!file_exists(TMPDIR))
	exit("Missing directory ". TMPDIR . " - exiting.\n");

if (!file_exists(EXTERNAL_PATH))
	exit("Missing " . EXTERNAL_PATH . "! exiting.\n");



$member_time_diff = 61; // how often are member APIs queried (min: 60 minutes, this is what API dictates us)

/**** CONFIG END *****/


// check for argc - we need the private key as a parameter
if ($argc != 3)
{
    exit("Expecting the private key file as parameter.\n");
}



// If everything worked so far, we can start looking at cron.lock

$cron_file = EXTERNAL_PATH . "/cron_corp.lock";



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



// get private key
$keyfile = $argv[2];
global $privateKey;
$privateKey = file_get_contents($keyfile);


require('funcs/basics.php');
init(); // includes all the things
require("funcs/cron.php");
require("funcs/TeamSpeak3/TeamSpeak3.php");
require("funcs/ts3.php");

echo("Logging to: ".LOGFILE."\n");
do_log("CRON: Initialising",0);


// include forum stuff
switch ($SETTINGS['forum_type'])
{
    case 'vbulletin':
        include('funcs/basics_vbulletin.php');
        break;
    case 'phpBB':
        define('IN_PHPBB', true);

        $phpEx = "php";
        include(PHPBB_ROOT_PATH . 'common.php');
        include(PHPBB_ROOT_PATH . 'includes/functions_user.php');
        include('funcs/basics_phpbb.php');
        break;

}



$globalDb = connectToDB();
// get cronjobs
$cronRes = $globalDb->query("SELECT id, name, last_executed, time_inbetween, 
STATUS 
FROM cronjobs WHERE last_executed = 0 OR time_inbetween = 0 OR TIMESTAMPDIFF( 
MINUTE , last_executed, NOW( ) )  > time_inbetween
ORDER BY  `order` ASC ");

$corp_time_diff = 4;


while ($cronRow = $cronRes->fetch_array())
{
    $jobName = $cronRow['name'];
    $id = $cronRow['id'];

    switch ($jobName)
    {
        case "api_check": // every time before we do anything, we need to check whether or not the API endpoint is available
            $api_status = parse_server_status();

            if ($api_status == false) {
                do_log("API down. Exiting.", 0);
				echo "API Down, exiting.\n";
                $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Down' WHERE id=$id");

                // delete cron file
                unlink($cron_file);

                exit;
            } else {
                // 2ndary check: get information from a character (we can use the character BigSako to do this)
                // https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID=1352400035

                $char_status = check_public_sheet(1352400035, 'BigSako');
                if ($char_status == true) {
                    $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
                } else {
                    $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Down' WHERE id=$id");

                    // delete cron file
                    unlink($cron_file);

                    exit;
                }
            }
            break;
        case "check_corp_api_key": // calls account/APIKeyInfo.xml for each corp api key
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            check_corp_api_keys();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        case "corp_sheet": // calls corp/CorporationSheet.xml for each corp api key and updates information
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            update_corp_sheet();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        case "corp_members": // calls corp/MemberTracking.xml for each corp api key and updates the corp member list
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            import_corp_members();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        case "corp_starbases": // calls corp/StarbaseList.xml for each corp api key and updates the starbase list
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            update_corp_starbases();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        case "corp_assets": // calls corp/AssetList.xml
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            update_corp_assets();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        case "corp_wallet_data": // calls corp/AccountBalance.xml
            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Running' WHERE id=$id");

            update_corp_wallet();

            $globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
            break;
        default: // cron job is not handled in this file
            break;
    }
}


// handle $total_failed_api_calls and $total_api_calls
$sql = "INSERT INTO api_call_stats (`total`,`failed`) VALUES ($total_api_calls, $total_failed_api_calls)";
$globalDb->query($sql);


// delete cron file
unlink($cron_file);

do_log("CRON CORP: Done!", 0);
echo "CRON CORP: Done\n";

?>
