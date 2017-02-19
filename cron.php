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
define('LOGFILE',LOGDIR ."cron.log");

$member_time_diff = 61; // how often are member APIs queried (min: 60 minutes, this is what API dictates us)

/**** CONFIG END *****/



// CHECK FOR FILES AND PERMISSIONS
if (!file_exists(LOGDIR))
	exit("Missing directory " . LOGDIR . " - exiting.\n");

if (!file_exists(TMPDIR))
	exit("Missing directory ". TMPDIR . " - exiting.\n");

if (!file_exists(EXTERNAL_PATH))
	exit("Missing " . EXTERNAL_PATH . "! exiting.\n");

// check for argc - we need the private key as a parameter
if ($argc != 3)
    exit("Expecting the private key file as parameter.\n");




// If everything worked so far, we can start looking at cron.lock

$cron_file = EXTERNAL_PATH . "/cron.lock";


// error handler function: report fatal errors to BigSako via e-mail
function myErrorHandler()
{
    global $cron_file;
    $error = error_get_last();
    $errno = $error['type'];

    $content = "";
    $fatal = false;

    $errstr = $error['message'];

    switch ($errno) {
        case E_USER_ERROR:
            $content .= "<b>My ERROR</b> [$errno] $errstr<br />\n";
            $content .= "  Fatal error on line $errline in file $errfile";
            $content .= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
            $content .= "Aborting...<br />\n";
            $fatal = true; // exit(1);
            break;

        case E_USER_WARNING:
        case E_USER_NOTICE:
        default:
            // NOT AN ERROR
            break;
    }

	if ($fatal)
	{
		$header  = 'MIME-Version: 1.0' . "\r\n";
		$header .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$header .= "To: BigSako <s4ko88@gmail.com>\r\n";


        // send mail to BigSako
        mail("s4ko88@gmail.com", "Error in cron.php", $content, $header);


        // delete cron.lock
        unlink($cron_file);

        /* Don't execute PHP internal error handler */
        return true;
	}

	return false;
}

// register a shutdown handler that checks for errors
register_shutdown_function( "myErrorHandler" );



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


// just here for other reasons, don't edit this
$corp_time_diff = 4;

while ($cronRow = $cronRes->fetch_array())
{
	$jobName = $cronRow['name'];
	$id      = $cronRow['id'];

	do_log("CRON: executing job $jobName",0);
	echo "CRON: executing job $jobName\n";
	$api_status = false;


	switch ($jobName)
	{
		case "api_check": // every 5 minutes
			$api_status = parse_server_status();

			if ($api_status == false)
			{
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
				if ($char_status == true)
				{
					$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
				} else {
					$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Down' WHERE id=$id");

					// delete cron file
					unlink($cron_file);

					exit;
				}
			}
			break;
		 case "clean_shop":
		 	clean_shop();

		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		 case "bulk_update_characters":
		 	bulk_update_characters();

		 	rebuild_groups();

		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		 case "conq_stations": // every 3 hours
		 	update_conq_stations();

		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		 case "sov": // every 3 hours
		 	if (update_sov())
		 	{
		 		$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	} else {
		 		$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Error' WHERE id=$id");
		 	}
		 	break;
		 case "skilltree": // every 7 days
		 	if (update_skilltree())
		 	{
		 		$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	} else {
		 		$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Error' WHERE id=$id");
		 	}
		 	break;
		case "player_api": // do player api every 5 minutes, though there is another flag called $member_time_diff
			// make sure that the selected mains of characters are linked via API
			check_main_characters();

			// import player api characters
			import_player_api_characters();

			// connect registered corp members with corp_member table (stateing who is registered and who is missing)
			marry_corp_members();

			// rebuild groups
			rebuild_groups();

			$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
			break;
		case "sync_permissions": // sync forum permissions
            sync_forum_permission();
			tidy_removed_groups();

			$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
			break;
		// case "ts3_backup":
		// 	//sync_ts3_permission();
		// 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Not implemented' WHERE id=$id");
		// 	break;
		// case "ts3_check": // check ts 3 usernames
		// 	check_ts3_user_names();
		// 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		// 	break;
		case "sync_forum": // sync forum permissions
			$ret = sync_forum_permission2();

			if ($ret == true)
			{
				$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
			} else {
				$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Failed' WHERE id=$id");
			}
			break;
		 case "player_notifications": // player notifications are handled every 2 hours
		 	handle_player_notifications();

		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		// case "clean_temp_folder":
		// 	// TODO: Implement
		// 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Not implemented' WHERE id=$id");
		// 	break;
		// case "log_rotate":
		// 	// TODO: Implement
		// 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='Not implemented' WHERE id=$id");
		// 	break;
		 case "alliances":
		 	update_alliances();
		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		 case "market_data":
		 	// diff: 24 hours
		 	bulk_update_market();
		 	$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
		 	break;
		case "clean_faulty_characters":
			// clean characters that were sold etc...
			$res = $globalDb->query("SELECT a.character_id, a.key_id
FROM api_characters a, player_api_keys k
WHERE a.state =99
AND a.key_id = k.keyid
AND k.state =0");
			while ($row = $res->fetch_array())
			{
				$sql = "DELETE FROM api_characters WHERE character_id = " . $row['character_id'] . " and key_id = " . $row['key_id'] . ";";
				$globalDb->query($sql);
			}

			$res = $globalDb->query("SELECT character_id, key_id FROM api_characters WHERE key_id NOT IN (SELECT keyid FROM `player_api_keys` WHERE 1)");

			// clean all chars that have no valid api key
			while ($row = $res->fetch_array())
			{
				$sql = "DELETE FROM api_characters WHERE character_id = " . $row['character_id'] . " and key_id = " . $row['key_id'] . ";";
				$globalDb->query($sql);
			}

			$globalDb->query("UPDATE cronjobs SET last_executed=now(), status='OK' WHERE id=$id");
			break;
		default:
			echo "Skipping this job (probably handled by someone else...\n";
			break;
	}
}


// handle $total_failed_api_calls and $total_api_calls
$sql = "INSERT INTO api_call_stats (`total`,`failed`) VALUES ($total_api_calls, $total_failed_api_calls)";
$globalDb->query($sql);


// delete cron file
unlink($cron_file);

do_log("CRON: Done!", 0);
echo "CRON: Done\n";

?>
