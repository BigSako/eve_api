<?php
/*************************************************
* SM3LL.net API Basic Functions                  *
*                                                *
*************************************************/

$curEveTime = gmdate("Y-m-d H:i:s");


$supers_titans_id = array(671, 3514, 3764, 11567, 22852, 23773, 23913, 23917, 23919, 42125);
$carriers_dreads_id = array(19720, 19722, 19724, 19726, 23757, 23911, 23915, 24483, 28352);

$dreads_id = array(19720, 19722, 19724, 19726);
$carriers_id = array(23757, 23911, 23915, 24483);

$force_aux_id = array(37604, 37605, 37606, 37607);

// rorqual = 28352

$all_capital_ids = array_merge($supers_titans_id, $carriers_dreads_id, $force_aux_id, [28352]);


if(array_key_exists('HTTP_EVE_TRUSTED', $_SERVER))
{
  define('EVE_IGB', true);
}
else
{
  define('EVE_IGB', false);
}

if (isset($_SERVER['HTTP_EVE_TRUSTED']) && ($_SERVER['HTTP_EVE_TRUSTED'] == true || $_SERVER['HTTP_EVE_TRUSTED'] == 'Yes'))
{
	define('EVE_TRUSTED', true);
} else {
	define('EVE_TRUSTED', false);
}


/**	init() includes all the things needed */
function init()
{
	global $SETTINGS;	
	
	require('funcs/logging.php');
	require('funcs/http_funcs.php');
	require('funcs/api_funcs.php');
	
	$SETTINGS = get_settings();
	
	
	do_log("System initiated, debug level is: ".DEBUG,0);
}


function get_settings()
{
	$sql = "SELECT name, svalue FROM settings ORDER BY name ASC";
	
	$db = connectToDB();
	$res = $db->query($sql);
	
	$setArr = array();
	
	while ($row = $res->fetch_array())
	{
		$name = $row['name'];
		$value = $row['svalue'];
		$setArr[$name] = $value;
	}
	
	return $setArr;
}


function getStagingSystem()
{
	global $SETTINGS;
	$db = connectToDB();
	$id = $SETTINGS['staging_system_id'];
	$sql = "SELECT solarSystemName FROM eve_staticdata.mapSolarSystems WHERE solarSystemID = $id";

	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	return array ($id, $row['solarSystemName']);
}



/* checks if a user is group admin by looking at the authorisers and admin group membership */
function isGroupAdmin($authorisers)
{
    if ($authorisers == 999 || $authorisers == 0)
        return false;

	return in_array($authorisers, $GLOBALS['group_membership']) 
			|| 
				in_array(2, $GLOBALS['group_membership']);
}



function sendTelegramMessage($telegram_user_id, $message)
{
	$botapi="https://api.telegram.org/bot112916763:AAE02caBx7cU_VMWE3xqb1JeusZLf_1e84s/";
	$url = $botapi . "sendMessage?chat_id=" . $telegram_user_id . "&text=" . urlencode($message);

	$ch=curl_init();
	$timeout=5;

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

	$result=curl_exec($ch);
	curl_close($ch);

}


function sendTelegramMessageByUserId($db_user_id, $message)
{
	global $SETTINGS;
	
	if ($SETTINGS['use_telegram'] == 1)
	{
		$db = connectToDB();
		$res = $db->query("SELECT telegram_user_id FROM auth_users WHERE user_id='$db_user_id' ");
		if ($res->num_rows == 1)
		{
			$row = $res->fetch_array();
			$telegram_user_id = $row['telegram_user_id'];
			
			if ($telegram_user_id != 0)
			{			
				$botapi="https://api.telegram.org/bot112916763:AAE02caBx7cU_VMWE3xqb1JeusZLf_1e84s/";
				$url = $botapi . "sendMessage?chat_id=" . $telegram_user_id . "&text=" . urlencode($message);
				file_get_contents($url);
			}
		}
	}
}


/**
 * send a telegram notification ($message) to users that are in group $db_group-id
 */
function addTelegramNotificationForGroupId($db_group_id, $message)
{
	global $SETTINGS;
	
	if ($SETTINGS['use_telegram'] == 1)
	{
		$db = connectToDB();
		// check if this user is actually using telegram or not
		$res = $db->query("SELECT a.user_id, a.telegram_user_id FROM 
		auth_users a, group_membership m 
		WHERE a.user_id = m.user_id and m.group_id = $db_group_id AND a.telegram_user_id != 0 ");		
		
		while ($row = $res->fetch_array())
		{
			$db_user_id = $row['user_id'];
			
			$sql = "INSERT INTO player_notification (user_id, message, datetime, responsible_user_id, send_ping, unread)
				VALUES ($db_user_id, '$message', now(), 0, 1, 0);";
				$db->query($sql);			
		}		
	}
}



function addFleetbotPingForGroup($groupName, $textMessage)
{
	$db = connectToDB();

	$sql = "INSERT INTO irc_ping_history (from_character, `timestamp`, message, groupname) 
		VALUES ('SM3LL Shop', " . time() . ", '$textMessage', '$groupName')";
	if (!$db->query($sql))
	{
		echo $db->error;
	}
}


/**
 * 
 */
function addTelegramNotificationForUserId($db_user_id, $message, $sender_user_id=0)
{
	global $SETTINGS;
	
	if ($SETTINGS['use_telegram'] == 1)
	{
		$db = connectToDB();
		// check if this user is actually using telegram or not
		$res = $db->query("SELECT telegram_user_id FROM auth_users WHERE user_id='$db_user_id' ");		
		
		if ($res->num_rows == 1)
		{
			$row = $res->fetch_array();
			$telegram_user_id = $row['telegram_user_id'];
			
			if ($telegram_user_id != 0)
			{
				$sql = "INSERT INTO player_notification (user_id, message, datetime, responsible_user_id, send_ping, unread)
				VALUES ($db_user_id, '$message', now(), $sender_user_id, 1, 0);";
				$db->query($sql);
			}
		}
	}
}




function marketGetWalletItems($user_id)
{
	global $db;
	// define $shop_left_menu
	// get amount of items in shopping cart
	$sql3 = "SELECT typeID,amount FROM shopping_cart WHERE user_id = " . $user_id;
	$res3 = $db->query($sql3);

	$cart_items = array();
	
	while ($row3 = $res3->fetch_array())
	{
		$cart_items[$row3['typeID']] = $row3['amount'];
	}
	
	return $cart_items;
}





function marketGetAmountWalletItems($user_id)
{
	global $db;
	// define $shop_left_menu
	// get amount of items in shopping cart
	$sql3 = "SELECT count(*) as c FROM shopping_cart WHERE user_id = " . $user_id;
	$res3 = $db->query($sql3);
	$row3 = $res3->fetch_array();
	$amount = $row3['c'];


	if ($amount == 1)
		$amount = "1 item";
	else
		$amount = $amount . " items";
		
	return $amount;

}



function marketGetTypeID($s)
{
	global $db;
	
	// strip white space of $s 
	$s = trim($s);

	$sql = "SELECT typeID FROM eve_staticdata.invTypes WHERE typeName = '$s' AND marketGroupID is not null";

	$res = $db->query($sql);
	if ($res->num_rows == 0)
		return -1;
	else
	{
		$row = $res->fetch_array();
		return $row['typeID'];
	}
}


function marketAddToCart($user_id, $typeID, $amount)
{
	global $db;

	$sql = "INSERT INTO shopping_cart (user_id, typeID, amount)
				VALUES ($user_id, $typeID, $amount)";

				
	return $db->query($sql);
	
}




function getApiAccessCounter($user_id)
{	
	$db = connectToDB();
	$res = $db->query("select count(*) as cnt FROM  (SELECT COUNT(*) FROM log WHERE user_id=$user_id GROUP BY request_url) as a");
	
	$row = $res->fetch_array();
	
	return $row['cnt'];
}


/** returns the group ID from the api_page_access table in an array
input must be escaped */
function getPageAccess($pageName)
{
	$db = connectToDB();
	
	$sql = "SELECT GROUP_CONCAT(group_id) as d FROM `api_page_access` WHERE page_name = '$pageName'";
	
	$res = $db->query($sql);
	
	$row = $res->fetch_array();
	
	$data = $row['d'];
	
	return explode(",", $data);
}


function getGroupName($group_id)
{
	$db = connectToDB();
	
	$sql = "SELECT group_name FROM groups WHERE group_id = $group_id ";
	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	return $row['group_name'];
}


function getUsername($user_id)
{
	$db = connectToDB();
	
	$sql = "SELECT user_name FROM auth_users WHERE user_id = $user_id ";
	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	return $row['user_name'];
}



function getAllAllowedPagesForUser($userid)
{
    $db = connectToDB();

    $sql = "SELECT page_name, m.group_id FROM api_page_access a, group_membership m
		WHERE (

			(  (a.group_id = m.group_id OR m.group_id = 2) AND m.user_id =  $userid AND m.state=0  )
			OR
			a.group_id = 0


			)  ";
    $res = $db->query($sql);

    $allowed_pages = array();

    while ($row = $res->fetch_array())
    {
        $allowed_pages[] = $row['page_name'];
    }

    return $allowed_pages;
}



function getPageAccessForThisUser($pageName)
{
	global $allowed_pages;

	return in_array($pageName, $allowed_pages);
}


function getPageAccessForUser($pageName, $userid)
{
	$db = connectToDB();
	
	$sql = "SELECT page_name, m.group_id FROM api_page_access a, group_membership m
		WHERE a.page_name='$pageName' AND ( 
		
			(  (a.group_id = m.group_id OR m.group_id = 2) AND m.user_id =  $userid AND m.state=0  )
			OR
			a.group_id = 0
			
			
			)  ";
	$res = $db->query($sql);

	while ($row = $res->fetch_array())
	{
		if ($row['page_name'] == $pageName)
		{
			$allowed = true;
			return true;
		}
	}

	return false;
}


function inventory_is_corp_hangar($flag)
{
	return $flag == 4 || ($flag >= 116 && $flag <= 121);
}


function inventoryFlagToName($flag)
{
	switch ($flag)
	{
		case '0':
			return "";
		case '27':
			return "fitted - High Slot";
		case 70:
		case 71:
		case 72:
		case 73:
		case 74:
		case 75:
		case 76:
		case 77:
		case 78:
		case 79:
		case 80:
		case 81:
		case 82:
		case 83:
		case 84:
		case 85:
			return "Office";
		case 62:
			return "Deliveries";
		case '4':
			return "Corp Hangar - Div 1";
		case '116':
			return "Corp Hangar - Div 2";
		case '117':
			return "Corp Hangar - Div 3";
		case '118':
			return "Corp Hangar - Div 4";
		case '119':
			return "Corp Hangar - Div 5";
		case '120':
			return "Corp Hangar - Div 6";
		case '121':
			return "Corp Hangar - Div 7";
		case '122':
			return "In use";
		default:
			return "Unknown Flag $flag";
	}
}

function getSolarSystemID($solarSystemName)
{
	$db = connectToDB();
	
	$res = $db->query("SELECT solarSystemID FROM eve_staticdata.mapSolarSystems WHERE solarSystemName = '$solarSystemName' ");
	
	if ($res->num_rows == 1)
	{
		$row = $res->fetch_array();
		
		return $row['solarSystemID'];
	}
	
	return -1;
}


function secondsToTimeString($seconds)
{
	$timeDiffStr = "";
	if ($seconds <= 0)
	{
		$timeDiffStr  = "Finished";
	} else 
	{
		if ($seconds < 60)
		{
			$timeDiffStr = $seconds . "s";
		} else if ($seconds < 60*60*24)
		{
			$timeDiffStr = gmdate("H:i:s", $seconds);
			
		} else {
			$days = floor($seconds / (60*60*24));
			$timeDiffStr = $days . "d " . gmdate("H:i:s", $seconds);
		}
	}
	
	return $timeDiffStr;
}



function get_all_offices($corp_id)
{
	$sql = "select o.location, o.solarID, o.locID, m.solarSystemName, r.regionID, r.regionName FROM

(
SELECT 

CASE 
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.stationName
FROM " . DB_NAME . ".conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.stationName
FROM " . DB_NAME . ".conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000 
THEN (

SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as location,



CASE 
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.solarSystemID
FROM " . DB_NAME . ".conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.solarSystemID
FROM " . DB_NAME . ".conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000 
THEN (

SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as solarID


, a.location_id AS locID
FROM " . DB_NAME . ".offices a
WHERE a.corp_id = $corp_id
ORDER BY location
) as o, eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r WHERE
o.solarID = m.solarSystemID AND r.regionID = m.regionID
ORDER BY r.regionName, m.solarSystemName";
	
	return $sql;
}



function get_offices_in_region($regionId, $corp_id)
{
	$sql = "select o.location, o.solarID, o.locID, m.solarSystemName FROM
(
SELECT 

CASE
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)

WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000
THEN (

SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as location,



CASE
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)

WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000
THEN (

SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as solarID


, a.location_id AS locID
FROM offices a
WHERE a.corp_id = $corp_id
ORDER BY location
) as o, eve_staticdata.mapSolarSystems m WHERE
o.solarID = m.solarSystemID AND m.regionID = $regionId
ORDER BY m.solarSystemName LIMIT 20";
	
	return $sql;
}



function get_offices_close_to($systemName, $corp_id, $jumps = 10)
{
	$sql = "select o.location, o.solarID, o.locID, m.solarSystemName, s.jumps FROM

(
SELECT 

CASE
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)

WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000
THEN (

SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as location,



CASE
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)

WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000
THEN (

SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as solarID


, a.location_id AS locID
FROM offices a
WHERE a.corp_id = $corp_id
ORDER BY location
) as o, eve_staticdata.mapSolarSystems m, eve_routing.sys_to_sys s WHERE s.jumps < $jumps AND
o.solarID = m.solarSystemID AND ( (s.a = o.solarID AND s.b_name = '$systemName') OR (s.a_name= '$systemName' AND s.b = o.solarID) )

ORDER BY s.jumps, m.solarSystemName LIMIT 3";
	
	return $sql;
}





function shortest_path($origin, $target, $jumps)
{
	global $db;


// This will hold the result of our calculation
	$jumpResult = array(
		'origin' => $origin,
		'destination' => $target,
		'jumps' => 'N/A',
		'distance' => -1
	);

// Load the jumps, by fetching the SolarSystemIDs from the Static Data Dump
// Results in an array like
// $jumps = array(
//     'SystemID' => array('ID of neighbour system 1', 'ID of neighbour system 2', '...'),
//     '...'
// );



// Start the fun
	if (isset($jumps[$origin]) && isset($jumps[$target])) {

		// Target is a neigbour system of origin
		if (in_array($target, $jumps[$origin])) {
			$jumpResult['jumps'] = $origin . ',' . $target;
			$jumpResult['distance'] = 1;
		}
		// Lets start the fun
		else {
			// Will contain the system IDs
			$resultPath = array();
			// Already visited system
			$visitedSystems = array();
			// Limit the number of iterations
			$remainingJumps = 9000;
			// Systems we can reach from here
			$withinReach = array($origin);

			while (count($withinReach) > 0 && $remainingJumps > 0 && count($resultPath) < 1) {
				$remainingJumps--;

				// Jump to the first system within reach
				$currentSystem = array_shift($withinReach);

				// Get the IDs of the systems, connected to the current
				$links = $jumps[$currentSystem];
				$linksCount = count($links);

				// Test all connected systems
				for($i = 0; $i < $linksCount; $i++) {
					$neighborSystem = $links[$i];

					// If neighbour system is the target,
					// Build an array of ordered system IDs we need to
					// visit to get from thhe origin system to the
					// target system
					if ($neighborSystem == $target) {
						$resultPath[] = $neighborSystem;
						$resultPath[] = $currentSystem;
						while ($visitedSystems[$currentSystem] != $origin) {
							$currentSystem = $visitedSystems[$currentSystem];
							$resultPath[] = $currentSystem;
						}
						$resultPath[] = $origin;
						$resultPath = array_reverse($resultPath);
						break;
					}

					// Otherwise, store the current - neighbour
					// Connection in the visited systems and add the
					// neighbour to the systems within reach
					else if (!isset($visitedSystems[$neighborSystem])) {
						$visitedSystems[$neighborSystem] = $currentSystem;
						array_push($withinReach, $neighborSystem);
					}
				}
			}

			// If the result path is filled, we have a connection
			if (count($resultPath) > 1) {
				$jumpResult['distance'] = count($resultPath) - 1;
				$jumpResult['jumps'] = $resultPath;
			}
		}
	}

	return $jumpResult;
}





function getPercentageImage($perc, $emptyBad=false)
{
	if ($emptyBad == false)
	{
		$perc_img = "silo_empty";			
	} else {
		$perc_img = "fuel_empty";	
	}
	
	if ($perc > 5 && $perc <= 10)
	{
		if ($emptyBad == false)
		{
			$perc_img = "silo_1perc";
		} else {
			$perc_img = "fuel_1perc";
		}
	} else if ($perc > 10 && $perc <= 30)
	{
		$perc_img = "silo_20perc";
	} else if ($perc > 30 && $perc <= 50)
	{
		$perc_img = "silo_40perc";
	} else if ($perc > 50 && $perc <= 70)
	{
		$perc_img = "silo_60perc";
	} else if ($perc > 70 && $perc <= 90)
	{
		if ($emptyBad == false)
		{
			$perc_img = "silo_80perc";
		} else {
			$perc_img = "fuel_80perc";
		}
	} else if ($perc > 90)
	{
		if ($emptyBad == false)
		{
			$perc_img = "silo_full";
		} else {
			$perc_img = "fuel_full";
		}
	}
	
	
	return $perc_img . ".png";
}


function findClosest2($locationID, $x, $y, $z) {
        global $db;
        $qry = "select itemName,sqrt(pow(x-$x,2)+pow(y-$y,2)+pow(z-$z,2)) as distm from `eve_staticdata`.`mapDenormalize` WHERE solarSystemID = '$locationID' ORDER BY `distm` ASC LIMIT 0,1";
        
	$res = $db->query($qry);

        if ($res->num_rows > 0)
	{
		$row = $res->fetch_array();
		if ($row['distm'] < 20000000)
                	return $row["itemName"]; 
		else
			return "close to " . $row["itemName"] . " (" . $row['distm'] . ")"; 
        }       
	return "Unknown";
}


function getTowerNameAndLocation($db, $corp_id, $locationID, $posItemID)
{
	// based on the locationID and ItemID , let's get fuel status
	$asset_sql = "SELECT itemID, realName, x, y, z FROM corp_assets
			WHERE locationID = $locationID AND itemID = $posItemID";
			
	$subRes = $db->query($asset_sql);
	
	if ($subRes->num_rows ==1)
	{
		$row = $subRes->fetch_array();
		
		return array($row['realName'], $row['x'], $row['y'], $row['z']);
	}
	
	
	return array("", 0, 0,0);
}



function getTowerFuelStatus($db, $corp_id, $locationID, $posItemID, $fuelName)
{
	// based on the locationID and ItemID , let's get fuel status
	$asset_sql = "SELECT a.itemID, a.parentItemID, i.capacity, i.typeName
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.locationID = $locationID AND a.itemID = $posItemID AND typeName LIKE '%Control Tower%'
			AND i.typeID = a.typeID ORDER BY typeName ASC";
			
	$subRes = $db->query($asset_sql);
	

	
	while ($subRow = $subRes->fetch_array())
	{
		$subTypeName = $subRow['typeName'];
		
		$capacity = $subRow['capacity'];

		$itemID = $subRow['itemID'];		
		
		// let's get all contents
		$content_sql = "SELECT a.quantity
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.parentItemID = $itemID AND typeName LIKE '%$fuelName%'
			AND i.typeID = a.typeID";
			
		$contentRes = $db->query($content_sql);
		
		while ($contentRow = $contentRes->fetch_array())
		{
			$quantity = $contentRow['quantity'];
			return array($quantity, $capacity);
		}
		
	}
	
	
	return array(-1, -1);
}



function generate_dotlan_link_region($region_name)
{
    $region_name_dotlan = str_replace(" " , "_", $region_name);
	return "<a href=\"http://evemaps.dotlan.net/map/" . $region_name_dotlan. "\" target=\"_blank\"><img src=\"images/dotlan.png\"> " . $region_name . "</a>";
}


function generate_dotlan_link_system($system_name)
{
    $system_name_dotlan = str_replace(" " , "_", $system_name);
    return "<a href=\"http://evemaps.dotlan.net/system/" . $system_name_dotlan. "\" target=\"_blank\"><img src=\"images/dotlan.png\"> " . $system_name . "</a>";
}



function log_user_ip_address($user_id, $ip_addr)
{
	// TODO
}


/** Get All Alliances array(id => {name, ticker}) , order by $order_by
 * @param string $order_by which column to order by, usually alliance_name
 * @return array with alliance_id (id) as the index, containing another array with 'name' and 'ticker' as index.
 */
function get_all_alliances($order_by='alliance_name')
{
    $db = connectToDB();

    $sql= "SELECT alliance_id, alliance_name, alliance_ticker FROM alliances ORDER BY $order_by ";

    $res = $db->query($sql);

    $data = array();

    while ($row = $res->fetch_array())
    {
        $data[$row['alliance_id']] = array( 'name' => $row['alliance_name'],
                                            'ticker' => $row['alliance_ticker'] );
    }

    return $data;
}





function print_timerboard_form($action='timerboard_create_timer',$timer_id=-1,$type='TCU',$timer_date='',
							   $solar_system_id=-1,$entity_alliance_id=0,
                               $location="",$is_friendly=0,$additional_info='')
{
	global $db;

	$mandatory = "<span style=\"color: red\">*</span>";


	echo "\n<form method=\"post\" action=\"api.php?action=$action\">";

    $is_edit = false;

    if ($timer_id != -1)
    {
        echo "<input type=\"hidden\" name=\"timerId\" value=\"$timer_id\">";
        echo "\n<table>";
        echo "\n\t<tr><th colspan=\"2\">Edit Existing Timer</th></tr>";
        $is_edit = true;
    } else {
        echo "\n<table>";
        echo "\n\t<tr><th colspan=\"2\">Create a New Timer</th></tr>";
    }



	echo "\n\t<tr><td><b>Type</b>$mandatory</td><td><select name=\"type\">
    <option>TCU</option>
    <option>IHUB</option>
    <option>POS</option>
    <option>Station</option>
    <option>Poco</option>
    </select></td></tr>";
	echo "\n\t<tr><td><b>System Name</b>$mandatory<br />Search for system name!</td><td>";

	echo <<<EOF
    <select name="solar_system_id" style="width:350px;" tabindex="3" class="chosen-select">
    <option value="-1">Click to select solar System</option>
EOF;
	$res = $db->query("SELECT solarSystemName,solarSystemID FROM eve_staticdata.mapSolarSystems ORDER BY solarSystemName ASC");
	while ($row = $res->fetch_array()) {
        if ($row['solarSystemID'] == $solar_system_id)
        {
            echo "<option value=\"" . $row['solarSystemID'] . "\" selected>" . $row['solarSystemName'] . "</option>\n";
        } else {
            echo "<option value=\"" . $row['solarSystemID'] . "\">" . $row['solarSystemName'] . "</option>\n";
        }
	}


	echo <<<EOF

     </select>
     <script type="text/javascript">
     var config = {
       '.chosen-select'           : {no_results_text: "Oops, nothing found!"},
       '.chosen-select-deselect'  : {allow_single_deselect:true},
       '.chosen-select-no-single' : {disable_search_threshold:10},
       '.chosen-select-no-results': {no_results_text:'Oops, nothing found!'},
       '.chosen-select-width'     : {width:"95%"}
     }
     for (var selector in config) {
       $(selector).chosen(config[selector]);
     }
    </script>
EOF;


	echo <<<EOF
    <tr><td><b>Owner Alliance</b>$mandatory<br/>Select who owns the structure<br />Use <i>No Alliance</i> if not applicable</td><td>
    <select name="entity_alliance_id" style="width:350px;" tabindex="4" class="chosen-select">
    <option value="0">No Alliance (Click to select alliance)</option>
EOF;


	$alliances = get_all_alliances();


	foreach ($alliances as $id => $data) {
		$alliance_ticker = $data['ticker'];
		$alliance_name = $data['name'];


        // print name and ticker as an option with the same value (ID)
        if ($id == $entity_alliance_id)
        {
            echo "<option value=\"$id\" selected>$alliance_name / $alliance_ticker</option>";
        } else {
            echo "<option value=\"$id\">$alliance_name / $alliance_ticker</option>";
        }
	}


	echo <<<EOF

     </select>
     <script type="text/javascript">
     var config = {
       '.chosen-select'           : {no_results_text: "Oops, nothing found!"},
       '.chosen-select-deselect'  : {allow_single_deselect:true},
       '.chosen-select-no-single' : {disable_search_threshold:10},
       '.chosen-select-no-results': {no_results_text:'Oops, nothing found!'},
       '.chosen-select-width'     : {width:"95%"}
     }
     for (var selector in config) {
       $(selector).chosen(config[selector]);
     }
    </script>
EOF;

	echo "</td></tr>";


	echo "\n\t<tr><td><b>Location in System</b>$mandatory<br />e.g., Planet Moon for POS, Planet for IHUB/TCU/...</td><td>
    <input placeholder=\"Planet XYZ Moon 123\" type=\"text\" name=\"position\" value=\"$location\"/></td></tr>";

    // Timer Date (relative)
	echo "\n\t<tr><td><b>Timer (relative)</b>$mandatory<br />Also see calculated timer below!</td>
        <td>";

    $days = "";
    $hours = "";
    $min = "";

    if ($is_edit)
    {
        $date = strtotime($timer_date);
        $time_remaining = $date - time();

        if ($time_remaining > 0) {
            echo "Previously: $timer_date<br />";

            $days = intval($time_remaining / 86400);

            $time_remaining -= ((int)$days * 86400);

            $hours = intval($time_remaining / 3600);

            $time_remaining -= ((int)$hours * 3600);

            $min = intval($time_remaining / 60);
        }
        else
        {
            echo "<b>Expired ($timer_date)</b>";

        }
    }

    echo "
        <input type=\"number\" id=\"day\" value=\"$days\" name=\"day\" placeholder=\"days\" style=\"width: 70px; float: left;\"/>
        <input type=\"number\" id=\"hour\" value=\"$hours\" name=\"hour\" style=\"width: 90px; float: left;\" placeholder=\"hours\" />
        <input type=\"number\" id=\"min\" value=\"$min\" name=\"min\" style=\"width: 90px; float: left;\" placeholder=\"mins\"/>";


    echo "</td>
    </tr>";
	echo "<tr><td>Automatically Calculated Timer<br /><i>Please verify that this is correct</i></td><td id=\"calculated_exit_time\">N/a</td></tr>";

    // defensive or offensive timer?
	echo "<tr><td><b>Friendly / Defensive Timer</b>$mandatory</td><td>";
    if ($is_friendly == 1)
        $checked = "checked";
    else
        $checked = "";

    echo "<input type=\"checkbox\" name=\"timer_is_friendly\" $checked/>";
    echo "Tick the checkbox if the timer is a defensive timer <br />(structure belongs to a friendly entity)</td></tr>";

    // The name of the current user
	echo "<tr><td><b>Your Name</b><br />So we can ask you if anything is unclear</td><td>" . $GLOBALS['username'] . "</td></tr>";

    // additional info
	echo "<tr><td>Additional Info<br />Be precise and provide info!</td>
    <td><textarea placeholder=\"Enter as many information as possible, e.g., what mods are on the pos, moon-goo (if known), etc...\n
    If you copied the timer from another timer board, tell which one it was.\"
    name=\"additional_info\" style=\"resize: vertical; width: 100%; height: 120px;\">$additional_info</textarea></td>";

    if ($action == "timerboard_create_timer")
        $label = "Create";
    else
        $label = "Modify";

	echo "<tr><th colspan=\"2\"><input type=\"submit\" value=\"$label\" /></th></tr>";
	echo "</table>";

	echo "$mandatory Field is mandatory<br />";



	echo "<input type=\"hidden\" name=\"create\" value=\"1\" />";
	echo "</form>";


	// create javascript thing that updates the time
	echo "<script type=\"text/javascript\">
                function addMinutes(date, minutes) {
        return new Date(date.getTime() + minutes*60000);
    }
                // render eve time
                function updateTimer() {
                    var today = new Date();

                    var byday = parseInt(document.getElementById('day').value);
                    var byhour = parseInt(document.getElementById('hour').value);
                    var byminute = parseInt(document.getElementById('min').value);

                    if (isNaN(byday))
                        byday = 0
                    if (isNaN(byhour))
                        byhour = 0
                    if (isNaN(byminute))
                        byminute = 0

                    // get current entries from day, hour and min
                    var additional_minutes = byday * 24 * 60 +
                        byhour * 60 + byminute;


                    if (additional_minutes > 0)
                    {
                        today = addMinutes(today, additional_minutes);

                        var weekday = new Array();
                        weekday[0] = 'Sunday';
                        weekday[1] = 'Monday';
                        weekday[2] = 'Tuesday';
                        weekday[3] = 'Wednesday';
                        weekday[4] = 'Thursday';
                        weekday[5] = 'Friday';
                        weekday[6] = 'Saturday';



                        var day = weekday[today.getUTCDay()];

                        var h = today.getUTCHours();
                        var m = today.getUTCMinutes();
                        var s = today.getUTCSeconds();
                        m = checkTime(m);
                        s = checkTime(s);
                        document.getElementById('calculated_exit_time').innerHTML = day + ' '  +
                            h + \":\" + m + \":\" + s + ' EvE Time';
                    } else if (additional_minutes == 0)
                    {
                        document.getElementById('calculated_exit_time').innerHTML = 'Please enter the relative timer above<br />to see the calculated time.';
                    }
                    else {
                        document.getElementById('calculated_exit_time').innerHTML = 'Error - did you input negative numbers?';
                    }
                    var t = setTimeout(updateTimer, 500);
                }
                function checkTime(i) {
                    if (i < 10) {i = \"0\" + i};  // add zero in front of numbers < 10
                    return i;
                }

                updateTimer();
                </script>";
}






/**	get_user_details() is executed everytime the api page is opened. it obtains the 
	sm3ll.net forum hash from a cookie and determines the user details */
function get_user_details()
{
	do_log("get_user_details called",5);
	global $userid,$userstate,$forum_id,$username,$email,
			$ts3_user_id,$existing_main,$existing_main_name,$corp_ids,$director_corp_ids,
			$group_membership,$SETTINGS, $isAdmin, $isSuperAdmin, $jabber_user_name, $registered_characters;

	// BigSako: initialize forum_id
	$forum_id = -1;
	
	$isAdmin = false;
	$isSuperAdmin = false;

	$as_user_flood_protect = 0;
	
	if($_COOKIE[$SETTINGS['forum_cookie_id']])
	{
		$forum_hash=sanitise($_COOKIE[$SETTINGS['forum_cookie_id']]); // BigSako: Prevent possible SQL Injection here! Very dangerous 
		
		$forum_id=lookup_forum_id($forum_hash);
		do_log("get_user_details(): forum_hash = '$forum_hash', forum_id='$forum_id'", 9);
	}
	if($forum_id<1) 
	{
		do_log("get_user_details(): no forum_id retrieved - exiting...", 9);
		return false;
	}
	
	$db = connectToDB();
	
	// asume user  (see further below)
asuser:
	
	// set corp ids to an empty array for now
	$corp_ids = array();
	$director_corp_ids = array();
		
	// query the forum and see who this is
	$sql = "select user_id,user_name,forum_id,email,state," .
			"ts3_user_id,has_regged_main,jabber_user_name from auth_users where forum_id='".$forum_id."'";
	do_log("sql='$sql'", 9);
	$sth=$db->query($sql);
	if($sth->num_rows == 1) // should be exactly one result
	{
		$result=$sth->fetch_array();
		$userid=$result['user_id'];		
		$username=$result['user_name'];
		$userstate=$result['state'];
		$email=$result['email'];

		$ts3_user_id = $result['ts3_user_id'];
		$jabber_user_name = $result['jabber_user_name'];
		$existing_main=$result['has_regged_main'];
		
		// get main character
		$sth2 = $db->query("SELECT character_name FROM api_characters WHERE character_id = $existing_main ");
		$res2 = $sth2->fetch_array();
		$existing_main_name = $res2['character_name'];
		
		
		// get groups
		$sth3 = $db->query("SELECT group_id FROM group_membership WHERE user_id = $userid AND state=0");
		$group_membership = array();
		$group_membership[0] = 0;
		
		$cnt = 1;
		
		while ($row3 = $sth3->fetch_array())
		{
			$group_membership[$cnt] = $row3['group_id'];
			if ($row3['group_id'] == ADMIN_GROUP_ID || $row3['group_id'] == SUPERADMIN_GROUP_ID)
			{
				$isAdmin = true;
			}
            if ($row3['group_id'] == SUPERADMIN_GROUP_ID)
            {
                $isSuperAdmin = true;
            }
			$cnt++;
		}

		
		// check for ASSUME_USER flag
		if ($isAdmin == true && isset($_COOKIE['API_ASUSER']) && $as_user_flood_protect == 0)
		{
			$forum_id = intval($_COOKIE['API_ASUSER']);
			do_log("Admin is asuming to be user with forum_id $forum_id", 1);
			// reset isAdmin
			$isAdmin = false;
			$as_user_flood_protect = 1;
			goto asuser;
		}
		
		// get corp ids
		$res = $db->query("SELECT a.corp_id, a.is_director, a.is_ceo, c.is_allowed_to_reg as corp_reg, d.is_allowed_to_reg as alliance_reg FROM api_characters a, corporations c, alliances d
 				WHERE c.corp_id = a.corp_id AND a.user_id = $userid AND a.state <= 10 AND c.alliance_id = d.alliance_id");
		
		while ($row = $res->fetch_array())
		{
			$corp_ids[] = $row['corp_id'];
			if (($row['corp_reg'] == 1 || $row['alliance_reg']) && ($row['is_director'] >= 1 || $row['is_ceo'] == 1))
			{
				$director_corp_ids[] = $row['corp_id'];
			}
		}

	} else if ($sth->num_rows == 0) { // user has registered at the forums, but does not have an account here
		do_log("New user?",5);
		$existing_main = -1;
		
		
		
		$result = get_forum_details($forum_id);
		// edit: dont forget to escape the username and the email 
		$username=$db->real_escape_string($result['username']);
		$email=$db->real_escape_string($result['email']);
		
		// insert user into our database in auth_users
		$sql = "insert into auth_users (user_name,forum_id,email,state) values " .
					"('$username','$forum_id','$email','0')";
		$sth=$db->query($sql);
		
		// if insert was not successful, it either is an sql error (unlikely) OR
		// the user was already registered, so we need to manually fix this.
		if ($sth == false)
		{
			do_log("Inserting user $username with forum_id $forum_id failed...", 1);
			do_log("qry='$sql'", 1);
			error_page("Error", "It appears that you are already registered with a different forum account. Please contact the IT Team.<br /> ".
						"Details: forum-username: $username, forum_id: $forum_id");
			exit;
			//return false;
		} else {
			
			
			
			// get user id etc...
			$sth=$db->query("select user_id,user_name,forum_id,email,state,ts3_user_id from auth_users where forum_id='$forum_id'");

			$result=$sth->fetch_array();
			$userid=$result['user_id'];
			$username=$result['user_name'];
			$userstate=$result['state'];
			$email=$result['email'];
			$ts3_user_id = $result['ts3_user_id'];
			
			// give user the default group 0
			$db->query("INSERT INTO group_membership (group_id, user_id, state, previous_state) VALUES (0, $userid, 0, 0)");
			
				
		}
		
		$sth3 = $db->query("SELECT group_id FROM group_membership WHERE user_id = $userid AND state=0");
		$group_membership = array();
		$group_membership[0] = 0;
		
		$cnt = 1;
		
		while ($row3 = $sth3->fetch_array())
		{
			$group_membership[$cnt] = $row3['group_id'];
			if ($row3['group_id'] == ADMIN_GROUP_ID && !isset($_COOKIE['API_ASUSER']))
			{
				$isAdmin = true;
			}
			$cnt++;
		}


	} else {
		do_log("error happened, more than 1 row returned...", 5);
		return false;
	}


    // now that we got that far: query all characters belonging to that user with an active API
    $registered_characters = array();

    $sql = "SELECT character_id, character_name FROM api_characters WHERE user_id = $userid AND state < 90 order by key_id,character_name";
    $res = $db->query($sql);

    while ($row = $res->fetch_array())
    {
        $registered_characters[$row['character_id']] = $row['character_name'];
    }

	do_log("Username has been determined to be ".$GLOBALS["username"],9);
	return true;
}


function getEvEAPIProfilePicture128($character_id, &$file_size)
{
	$url="https://image.eveonline.com/Character/" . $character_id . "_128.jpg";
	do_log("Getting $url",9);
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,10);
	$data = curl_exec($curl);
	$info = curl_getinfo($curl);
	$file_size = $info["size_download"];
	curl_close($curl);
	return $data;
}

function getEvEAPIProfilePicture64($character_id, &$file_size)
{
	$url="https://image.eveonline.com/Character/" . $character_id . "_64.jpg";
	do_log("Getting $url",9);
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,10);
	$data = curl_exec($curl);
	$info = curl_getinfo($curl);
	$file_size = $info["size_download"];
	curl_close($curl);
	return $data;
}






function print_account_info()
{

	
	/*echo "EvE Time: " . gmdate("H:i:s") . "<br />";
	// calculate Nerd Time
	$sth = db_action("SELECT SUM(logonMinutes) as nerdMinutes FROM player_api_keys WHERE state <= 10");
	$res = $sth->fetch_array();
	$nerd_minutes = $res["nerdMinutes"];
	$nerd_hours = floor($nerd_minutes / 60);
	echo "Nerd Hours: $nerd_hours h<br />"; */
}





function sanitise($var)
{
$var=preg_replace("/'/","\\'",$var);

return $var;
}

/*
returns 0 if the typeId is not a ship
returns -1 if the typeId is not allowed (e.g. carrier, super, titan)
returns the actual ship size repackaged if a ship */
function getShipSize($typeId)
{
	$db = connectToDB();
	
	$sql = "SELECT s.shipSize, s.allowed FROM  ship_size s, eve_staticdata.invTypes t WHERE t.typeID = $typeId and t.groupID = s.groupID";

	$res = $db->query($sql);
	
	if ($res->num_rows == 0)
		return 0;
	$row = $res->fetch_array();
	if ($row['allowed']  == 1)
		return $row['shipSize'];
	else
		return -1;
}



function return_order_state($orderState)
{
	switch ($orderState)
	{
		case 0:
			return "Ordered";
		case 1:
			return "Accepted";
		case 2:
			return "In delivery";
		case 3:
			return "Contracted";
		case 4:
			return "Contract accepted - Done";
		case 5:
			return "Order denied";
		case 6:
			return "Failed";
		case 9:
			return "Canceled";
	}
}


function corp_ship_state($state)
{
	switch ($state)
	{
		case 0:
			return "OK";
		case 1:
			return "<b style=\"color: yellow\">Unkonwn</b>";
		case 99:
			return "<b style=\"color: red\">Dead</b>";
		default:
			return "Not specified";
	}
}



function return_reftypeid_longtext($refTypeId)
{
	switch ($refTypeId)
	{
		case -1:
			return "BOU - Bounty<br />
					BRFE - Broker Fee<br />
					CAW - Corp Account Withdrawal<br />
					CON - Contract<br />
					CSPA - CSPA Charge<br />
					D - Donation<br />
					INS - Insurance<br />
					MB = Market Buy<br />
					MT - Market Transaction<br />
					POCO - Customs Office Tax<br />
					REP - Repair Bill<br />
					T - Trade<br />
					TST - Transaction Tax";
		case 1:
			return "Trade";
		case 2:
			return "Market Transaction";
		case 10:
			return "Player Donation";
		case 15:
			return "Repair Bill";
		case 16:
		case 17:
			return "Bounty";
		case 19:
			return "Insurance Money";
		case 35:
			return "CSPA Charge";
		case 37:
			return "Corp Account Withdrawal";
		case 42:
			return "Market Buy";
		case 46:
			return "Broker Fee";
		case 54:
			return "Transaction Tax";
		/* the following are all contracts */
		case 63:
		case 64:
		case 71:
		case 72:
		case 73:
		case 74:
		case 79:
		case 80:
		case 81:
		case 82:
			return "Contract";
		case 85:
			return "Bounty";
		case 97:
			return "POCO";
		case 99:
			return "Incursion";

		default:
			return "Unknown (" . $refTypeId . ")";
	}
}


function return_reftypeid_text($refTypeId)
{
	switch ($refTypeId)
	{
		case -1:
			return "BOU - Bounty<br />
					BRFE - Broker Fee<br />
					CAW - Corp Account Withdrawal<br />
					CON - Contract<br />
					CSPA - CSPA Charge<br />
					D - Donation<br />
					INS - Insurance<br />
					INC - Incursion Money<br />
					MB = Market Buy<br />
					MT - Market Transaction<br />
					POCO - Customs Office Tax<br />
					REP - Repair Bill<br />
					T - Trade<br />
					TST - Transaction Tax";
		case 1:
			return "T";
		case 2:
			return "MT";
		case 10:
			return "D";
		case 15:
			return "REP";
		case 16:
		case 17:
			return "BOU";
		case 19:
			return "INS";
		case 35:
			return "CSPA";
		case 37:
			return "CAW";
		case 42:
			return "MB";
		case 46:
			return "BRFE";
		case 54:
			return "TST";
		/* the following are all contracts */
		case 63:
		case 64:
		case 71:
		case 72:
		case 73:
		case 74:
		case 79:
		case 80:
		case 81:
		case 82:
			return "CON";
		case 85:
			return "BOU";
		case 97:
			return "POCO";
		case 99:
			return "INC";

		default:
			return $refTypeId;
	}
}



/** Returns the API Key state text */
function return_state_text($state)
{
	$text[0]='Valid';
	$text[1]='Currently Validating';
	$text[2]='Temporary Problem';
	$text[10]='API Timeout - Valid at last check';
	$text[98]='API Key missing or invalid';
	$text[99]='Invalid';
	return $text[$state];
}

function return_state_class($state)
{
	$class[0]='valid';
	$class[1]='validating';
	$class[2]='api_timeout';
	$class[10]='api_timeout';
	$class[98]='p_api_missing';
	$class[99]='invalid';
	return $class[$state];
}


function return_group_state_text($state)
{
	$text[0]='Active';
	$text[1]='Active'; #state actually represents 'being validated', but we dont want this visible to the user
	$text[2]='Active'; #state actually represents 'api_timeout', but we dont want this visible to the user
	$text[3]='Awaiting Confirmation';
	$text[97]='Leaving Group';
	$text[98]='Access Suspended';
	$text[99]='Character deleted';
	return $text[$state];
}

function return_group_state_class($state)
{
	$class[0]='active';
	$class[1]='active'; #state actually represents 'being validated', but we dont want this visible to the user
	$class[2]='active'; #state actually represents 'api_timeout', but we dont want this visible to the user
	$class[3]='awaiting_confirmation';
	$class[97]='leaving_group';
	$class[98]='suspended';
	$class[99]='deleted';
	return $class[$state];
}




function get_start()
{
	return(microtime(true));
}

function get_end($start)
{
	return(microtime(true) - $start);
}


function db_action($query,$debug=8)
{
	do_log("Running query: $query",$debug);
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	$sth = $mysqli->query($query);
	global $last_insert_id;
	$last_insert_id=$mysqli->insert_id;
	#do_log("Last insert id: ".$GLOBALS['last_insert_id'],9);
	return $sth;
}

$forum_db_access = NULL; // = new mysqli(FORUM_DB_HOST, FORUM_DB_USER, FORUM_DB_PASSWORD, FORUM_DB_NAME);


function db_action_forum($query,$debug=8)
{
	global $forum_db_access;
	if ($forum_db_access == NULL)
	{
		$forum_db_access = new mysqli(FORUM_DB_HOST, FORUM_DB_USER, FORUM_DB_PASSWORD, FORUM_DB_NAME);
	}
	do_log("Running forum query: $query",$debug);

	$sth = $forum_db_access->query($query);
	global $last_insert_id;
	$last_insert_id=$forum_db_access->insert_id;
	#do_log("Last insert id: ".$GLOBALS['last_insert_id'],9);
	return $sth;
}


/* CREATE TABLE IF NOT EXISTS `player_notification` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` varchar(500) NOT NULL,
  `datetime` timestamp NULL DEFAULT NULL,
  `responsible_user_id` int(11) NOT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `datetime` (`datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
*/


function add_user_notification($user_id, $message, $responsible_user_id, $send_ping=0)
{
    $db = connectToDB();

    $current_time = date('Y-m-d H:i:s',time());
    $message = $db->real_escape_string($message);

    $sql = "INSERT INTO player_notification (user_id, message, datetime, responsible_user_id, send_ping) " .
            " VALUES ($user_id, '$message', '$current_time', $responsible_user_id, $send_ping) ";

    return $db->query($sql);
}


function check_group_chain($group,$user)
{
	$qualified=99;
	$sth = db_action("select pre_req_group from groups where group_id='$group'");
	$row = $sth->fetch_array();
	$pre_req = $row['pre_req_group'];
	$sth=db_action("select state from group_membership where group_id='$pre_req' and user_id='$user'");
	while($result=$sth->fetch_array()) {
		$qualified=$result['state'];
		}
	do_log("User state for group $group is $qualified",9);
	if($qualified<=2 && $pre_req>1) {
		$state=check_group_chain($pre_req,$user);
		}
	elseif($qualified<=2 && $pre_req==1) {
		do_log("Group chain is unbroken and valid",9);
		return(0);
	} else {
		do_log("User chain is broken",9);
		return(1);
	}
}


function getAmountOfMembersOnline()
{
	$db = connectToDB();
	$sql = "select count(*) as cnt FROM  ( SELECT user_id,count(*) FROM `log` WHERE TIME_TO_SEC(TIMEDIFF(NOW(), time)) < 300 GROUP BY user_id ) as a ";
	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	return $row['cnt'];
}


function audit_log($logText)
{
	$ip = $_SERVER['REMOTE_ADDR'];
	$user_id = $GLOBALS['userid'];

	
	$db = connectToDB();
	$logText = $db->real_escape_string($logText);
	$db->query("INSERT INTO audit_log (user_id, logText, ip) VALUES ($user_id, '$logText', '$ip') ");
}


function audit_log_cron($logText)
{
	$ip = 'cron';
	$user_id = -1;

	
	$db = connectToDB();
	$logText = $db->real_escape_string($logText);
	$db->query("INSERT INTO audit_log (user_id, logText, ip) VALUES ($user_id, '$logText', '$ip') ");
}



function sync_forum_permission()
{
	do_log("in sync_forum_permission()", 9);
	$db = connectToDB();
	
	// get the main forum group id = registered members, no access
	$res = $db->query("SELECT forum_group_id FROM groups WHERE group_name='Registered Members' ");
	$row = $res->fetch_array();
	$main_forum_group_id = $row['forum_group_id'];
	
	// get all users
	$res = $db->query("SELECT user_id as user_id, forum_id, ts3_user_id, user_name, has_regged_api, state FROM auth_users WHERE state < 10");
	
	while ($row = $res->fetch_array())
	{
		$user_id = $row['user_id'];
		$forum_id = $row['forum_id'];
		$ts3_user_id = $row['ts3_user_id'];
		$user_name = $row['user_name'];
		$has_regged_api = $row['has_regged_api'];
		$state = $row['state'];
		
		// get all group memberships of this user now
		$res2 = $db->query("SELECT m.group_id as group_id, m.state as m_state, " .
					" m.previous_state as m_previous_state, g.group_name as group_name, g.forum_group_id as forum_group_id, ts3_group_id FROM ".
					" group_membership m, groups g WHERE m.group_id = g.group_id AND m.user_id = $user_id");
		
		
		// all users should always be in the forum group main_forum_group_id (this should never be different, but we execute it to be sure)
		set_forum_group($forum_id, $main_forum_group_id); // main_forum_group_id = registered user
		
		// all users must additionally be in group 0 (basic group)
		$db->query("INSERT INTO group_membership (user_id, group_id, state) VALUES ($user_id, 0, 0)");
		
		if ($res2->num_rows == 0)
		{
			// user is not in any group - make sure they are not allowed on the forums
			do_log("user $user_name (user_id: $user_id, forum_id: $forum_id) is not in any groups, Revoking ALL forum and teamspeak permissions", 7);
			audit_log_cron("user $user_name (user_id: $user_id, forum_id: $forum_id) is not in any groups, revoking ALL forum and teamspeak permissions.");
			remove_all_forum_groups($forum_id);	
			remove_all_ts_permissions($user_name);
			// update users state to 1
			$sql = "UPDATE auth_users SET state=state+1 WHERE user_id = " . $user_id;
			$db->query($sql);
		} else {
			// user should be in some forum groups
			// get the groups he is currently in
			$current_groups = get_forum_group_membership($forum_id);

			// user is in groups, go through them
			while ($row2 = $res2->fetch_array())
			{
				$group_id = $row2['group_id'];
				$m_state = $row2['m_state'];
				$m_previous_state = $row2['m_previous_state'];
				$group_name = $row2['group_name'];
				$forum_group_id = $row2['forum_group_id'];
				$ts3_group_id = $row2['ts3_group_id'];
				
				
				// do not do anything if forum_group_id is 0
				if ($forum_group_id != 0)
				{				
					// prev = 99 AND state=0 --> add user to group
					if ($m_previous_state == 99 && $m_state == 0)
					{
						if ($current_groups[$forum_group_id] != 1)
						{
							do_log("adding user $user_name (forum_id: $forum_id) to forum group $group_name (id: $forum_group_id)", 7);
							audit_log_cron("adding user $user_name (forum_id: $forum_id) to forum group $group_name (id: $forum_group_id).");
							add_forum_group_membership($forum_id, $forum_group_id);
						} else {
							echo "User $user_name $user_id is already in forum_group $forum_group_id\n";
							$current_groups[$forum_group_id] = 0; // user was already in this group, nothing to do
						}
					} // prev = 0 AND state == 99 --> user is no longer in this group, so remove him
					else if ($m_previous_state == 0 && $m_state == 97)
					{	
						do_log("removing user $user_name (forum_id: $forum_id) from forum group $group_name (id: $forum_group_id)", 7);
						audit_log_cron("removing user $user_name (forum_id: $forum_id) from forum group $group_name (id: $forum_group_id)");
						remove_forum_group_membership($forum_id, $forum_group_id);
						$current_groups[$forum_group_id] = 0; // user is no longer in this group, good!
					} else if ($m_previous_state == 0 && $m_state == 0)
					{
						// user is in this group and should be in this group, ignore it
						$current_groups[$forum_group_id] = 0;
					}
				}				
				
				
				if ($ts3_group_id != 0)
				{				
					// prev = 99 AND state=0 --> add user to group
					if ($m_previous_state == 99 && $m_state == 0)
					{
						do_log("adding user $user_name (ts3_id: $ts3_user_id) to ts3 group $ts3_group_id", 7);
						ts3_setgroups($user_name, array($ts3_group_id), false);
					} // prev = 0 AND state == 99 --> user is no longer in this group, so remove him
					else if ($m_previous_state == 0 && $m_state == 97)
					{							
						do_log("removing user $user_name (ts3_id: $ts3_user_id) from ts3 group $ts3_group_id", 7);
						ts3_removeFromGroup($user_name, $ts3_group_id);
					}		
				}
			} // END WHILE

			if (sizeof($current_groups) > 0) {
				// now check $current_groups - if there are any groups left set to 1, user must be removed from them!
				foreach ($current_groups as $forum_group_id => $value) {
					if ($value == 1 && $forum_group_id != $main_forum_group_id) {
						echo "Apparently user $user_name (id=$user_id) is still in forum group with id '" . $forum_group_id . "' '... Removing\n";
						remove_forum_group_membership($forum_id, $forum_group_id);
					}
				}
			}
		}
	}
	
	do_log("Forum permissions synced. now tidy up group_membership table", 1);
	// delete all with state 99 because they are deleted from forum database now
	$db->query("DELETE FROM group_membership WHERE state=99");
	// update previous state to 0 if state is 0 because we just handled these cases
	$db->query("UPDATE group_membership SET previous_state=0 WHERE state=0");
}






function decrypt($data, $key)
{
	if (openssl_private_decrypt(base64_decode($data), $decrypted, $key))
		$data = $decrypted;
	else
		$data = '';

	return $data;
}



function encrypt($data, $key)
{
	if (openssl_public_encrypt($data, $encrypted, $key))
		$data = base64_encode($encrypted);
	else
		throw new Exception('Unable to encrypt data. Perhaps it is bigger than the key size?');

	return $data;
}


function resize_image($image_in,$image_out,$size_x,$size_y)
{
	$thumb = new Imagick();
	$thumb->readImage($image_in);
	$thumb->scaleImage($size_x,$size_y,true); 
	#$thumb->resizeImage($size_x,$size_y,Imagick::FILTER_CATROM,1);
	$thumb->writeImage($image_out);
	$thumb->clear();
	$thumb->destroy(); 
}

function apply_default_avatar($forum_id,$character_id)
{
	get_avatar($character_id);
	$outfile2=TMPDIR.$character_id."_200.jpg";
	$outfile3=TMPDIR.$character_id."_100.jpg";
	$size=filesize($outfile2);
	db_action_forum("replace into customavatar 
		(`userid`,
		`filedata`,
		`dateline`,
		`filename`,
		`visible`,
		`filesize`,
		`width`,
		`height`,
		`filedata_thumb`,
		`width_thumb`,
		`height_thumb`,
		`extension`) 
	values 
		('$forum_id',
		'".mysql_escape_string(file_get_contents($outfile2))."',
		'1',
		'$character_id',
		'1',
		'$size',
		'200',
		'200',
		'".mysql_escape_string(file_get_contents($outfile3))."',
		'100',
		'100',
		'jpeg')	
	",9);
}







function connectToDB()
{
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	return $mysqli;
}

function connectToForumDB()
{
	$mysqli = new mysqli(FORUM_DB_HOST, FORUM_DB_USER, FORUM_DB_PASSWORD, FORUM_DB_NAME);
	return $mysqli;
}

function connectToStaticDataDB()
{
	$mysqli = new mysqli(STATIC_DB_HOST, STATIC_DB_USER, STATIC_DB_PASSWORD, STATIC_DB_NAME);
	return $mysqli;
}





function my_meta_refresh($target,$delay)
{

		print("<body><head><meta http-equiv='refresh' content='$delay; url=$target'></head><body/></html>");
	
}

function afork($data,$max,$code)
{
	do_log("afork invoked",8);
	$count=0;
	unset($pid);
	foreach($data as $item) {
		while($count>=$max) {
			pcntl_wait($status);
			$count--;
		} 
		$count++;
		$pid = pcntl_fork();
		if(!$pid) {
			call_user_func($code, $item);
			exit;
		}
	}	
	while($count>0) {
			pcntl_wait($status);
			$count--;
	} 
}




$roleDescriptionArray = array(
    'Director',
    7 => 'Personnal Manager',
    'Accountant',
    'Security Officer',
    'Factory Manager',
    'Station Manager',
    'Auditer',
    'Hanger can take division 1',
    'Hanger can take division 2',
    'Hanger can take division 3',
    'Hanger can take division 4',
    'Hanger can take division 5',
    'Hanger can take division 6',
    'Hanger can take division 7',
    'Hanger can query division 1',
    'Hanger can query division 2',
    'Hanger can query division 3',
    'Hanger can query division 4',
    'Hanger can query division 5',
    'Hanger can query division 6',
    'Hanger can query division 7',
    'Account can take division 1',
    'Account can take division 2',
    'Account can take division 3',
    'Account can take division 4',
    'Account can take division 5',
    'Account can take division 6',
    'Account can take division 7',
    'Diplomat',
    '(Old) account can query division 2',
    '(Old) account can query division 3',
    '(Old) account can query division 4',
    '(Old) account can query division 5',
    '(Old) account can query division 6',
    '(Old) account can query division 7',
    'Config equipment',
    'Container can take division 1',
    'Container can take division 2',
    'Container can take division 3',
    'Container can take division 4',
    'Container can take division 5',
    'Container can take division 6',
    'Container can take division 7',
    'Can rent Office',
    'Can rent Factory slot',
    'Can rent Research slot',
    'Junior accountant',
    'Config starbase equipment',
    'Trader',
    'Communications Officer',
    'Contract Manager',
    'Starbase Defense',
    'Starbase Fuel Technician',
    'Fitting Manager'
);


function printXYHigh($title, $x, $y) {
	global $containerID;
	$containerID++;
	for ($i = 0; $i < count($y); $i++) { $y[$i] = sprintf("%.1f", $y[$i]); }
	$data = "[" . implode(", ", $y) . "]";
	$categories = "['" . implode("', '", $x) . "']";
	echo <<<BLA
		
		<div id="container$containerID" class="chartContainer"></div>
		<script type="text/javascript">
			$(function () {
				$('#container$containerID').highcharts({
					chart: {
						spacingRight: 20,
						backgroundColor: 'rgba(255,255,255,0.1)'
					},
					title: {
						text: '$title',
						style: {
							color: '#000000'
						}
					},
					xAxis: {
						categories: $categories,
						title: {
							text: null
						},
						labels: {
							style: {
								color: '#000000',
							},
						}
					},
					yAxis: {
						labels: {
							style: {
								color: '#000000',
							},
						},
						tickColor: '#000000',
						title: {
							text: null
						}
					},
					tooltip: {
						shared: true
					},
					legend: {
						enabled: false
					},
					credits: {
						enabled: false
					},					
					plotOptions: {
						area: {
							fillColor: {
								linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1},
								stops: [
									[0, Highcharts.getOptions().colors[0]],
									[1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
								]
							},
							lineWidth: 1,
							marker: {
								enabled: false
							},
							shadow: false,
							states: {
								hover: {
									lineWidth: 1
								}
							},
							threshold: null
						}
					},
					series: [{
						type: 'area',
						name: '$title',
						data: $data
					}]
				});
			});
		</script>
BLA;
}

function printTimeHigh($title, $from, $interval, $y) {
	global $containerID;
	$containerID++;
	$y2 = array();
	foreach ($y as $tmp) { $y2[] = sprintf("%.1f", $tmp); }
	$y = $y2;
	$data = "[" . implode(", ", $y) . "]";
	echo <<<BLA
		
		<div id="container$containerID" class="chartContainer"></div>
		<script type="text/javascript">
			$(function () {
				$('#container$containerID').highcharts({
					chart: {
						zoomType: 'x',
						spacingRight: 20,
						backgroundColor: 'rgba(255,255,255,0.1)'
					},
					title: {
						text: '$title',
						style: {
							color: '#000000'
						}
					},
					xAxis: {
						type: 'datetime',
						maxZoom: 14 * 24 * 3600000, // fourteen days
						title: {
							text: null
						},
						labels: {
							style: {
								color: '#000000',
							},
						}
					},
					yAxis: {
						labels: {
							style: {
								color: '#000000',
							},
						},
						tickColor: '#000000',
						title: {
							text: null
						}
					},
					tooltip: {
						shared: true
					},
					legend: {
						enabled: false
					},
					credits: {
						enabled: false
					},					
					plotOptions: {
						area: {
							fillColor: {
								linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1},
								stops: [
									[0, Highcharts.getOptions().colors[0]],
									[1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
								]
							},
							lineWidth: 1,
							marker: {
								enabled: false
							},
							shadow: false,
							states: {
								hover: {
									lineWidth: 1
								}
							},
							threshold: null
						}
					},
					series: [{
						type: 'area',
						name: '$title',
						pointInterval: $interval,
						pointStart: $from,
						data: $data
					}]
				});
			});
		</script>
BLA;
}

// $x is expected to contain the category labels
// $y is a array with the different data sets in a specific structure
// $y["label"] is the label of the y axis
// $y["data"] is expected to be a array, with each elem having the following structure
// $y["data"][elem] = array("name", "data" => array())
// $title is the title of the plot
function printBarHigh($title, $x, $y) {
	global $containerID;
	$containerID++;
	$tmpArray = array();
	foreach ($y["data"] as $elem) {
		$tmpArray[] = "\n\t\t\t name: '" . $elem["name"] . "', \n\t\t\t data: [" . implode(", ",
		$elem["data"]) . "]";
	}
	$series = "[{" . implode("\n\t\t}, {", $tmpArray) . "\n\t\t}]";
	$categories = "['" . implode("', '", $x) . "']";
	$ylabel = $y["label"];
	echo <<<BLA
	
		<div id="container$containerID" class="chartContainer"></div>
		<script type="text/javascript">
			$(function () {
				$('#container$containerID').highcharts({
					chart: {
						type: 'bar',
						backgroundColor: 'rgba(255,255,255,0.1)'
					},
					title: {
						text: '$title',
						style: {
							color: '#000000'
						}
					},
					xAxis: {
						categories: $categories,
						style: {
							color: '#000000'
						},
						title: {
							text: null
						},
						labels: {
							style: {
								color: '#000000',
							}
						}
					},
					yAxis: {
						min: 0,
						title: {
							text: '$ylabel',
							align: 'high',
							style: {
								color: '#000000'
							}
						},
						labels: {
							style: {
								color: '#000000',
							},
							overflow: 'justify'
						}
					},
					tooltip: {
						valueSuffix: ' $ylabel'
					},
					plotOptions: {
						bar: {
							dataLabels: {
								enabled: true,
								style: {
									color: '#000000'
								}
							}
						}
					},
					legend: {
						layout: 'vertical',
						align: 'right',
						verticalAlign: 'top',
						x: -30,
						y: 250,
						floating: true,
						borderWidth: 1,
						backgroundColor: 'rgba(255,255,255,0.1)',
						shadow: true,
						itemStyle: {
							color: '#FFFFFF'
						}
					},
					credits: {
						enabled: false
					},
					series: $series
				});
			});
		</script>
BLA;
}



function highchart_print_pie($chart_title, $data, $data_title)
{
    global $containerID;
    $containerID++;

    $colors = array('#058DC7', '#50B432', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4');

    // print the high charts container
    echo "\n<div id=\"container$containerID\" style=\"min-width: 310px; height: 400px; max-width: 600px; margin: 0 auto\"></div>";

    // print javascript
    echo <<<EOF
<script type="text/javascript">

$(function () {

    // Radialize the colors
    Highcharts.getOptions().colors = Highcharts.map(Highcharts.getOptions().colors, function (color) {
        return {
            radialGradient: {
                cx: 0.5,
                cy: 0.3,
                r: 0.7
            },
            stops: [
                [0, color],
                [1, Highcharts.Color(color).brighten(-0.3).get('rgb')] // darken
            ]
        };
    });

    // Build the chart for container with id $containerID
    $('#container$containerID').highcharts({
        chart: {
            plotBackgroundColor: null,
            plotBorderWidth: null,
            plotShadow: false,
            type: 'pie',
            backgroundColor: 'rgba(255,255,255,0.0)'
        },
        title: {
            text: '$chart_title'
        },
        tooltip: {
            pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                    style: {
                        color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                    },
                    connectorColor: 'silver'
                }
            }
        },
        series: [{
            name: '$data_title',
            data: [{
EOF;
    // fill data array with actual data



    $tmp = array();
    $datNum = 0;
    foreach ($data as $k => $v) {
        $rgb = ", \n\t color: '" . $colors[$datNum] . "'";
        $datNum++;
        if ($datNum >= count($colors)) { $datNum = 0; }
        // $rgb = "";
        if (isset($v["drill"]) && (count($v["drill"]) > 0)) {
            $tmp[] = "\n\t name: '$k'," . "\n\t y: " . $v["count"] . "," . "\n\t drilldown: 'd$k' $rgb";
        } else {
            $tmp[] = "\n\t name: '$k'," . "\n\t y: " . $v["count"] . "," . "\n\t drilldown: null $rgb";
        }
    }
    echo implode("}, {", $tmp);
    echo "\n }]";
    echo "\n }],";
    echo "\n drilldown: {";
    echo "\n activeDataLabelStyle: {";
    echo "\n\t color: '#000000'";
    echo "\n},";
    echo "\n series: [";
    echo "\n {";
    $tmp = array();
    foreach ($data as $k => $v) {
        $datNum = 0;
        if (isset($v["drill"]) && count($v["drill"]) > 0) {
            $str = "\n\t id: 'd$k', \n\t data: [{";
            $tmp2 = array();
            foreach ($v["drill"] as $dk => $dv) {
                $dkz = str_replace("'", "\\'", $dk);
                $rgb = ", \n\t color: '" . $colors[$datNum] . "'";
                $datNum++;
                if ($datNum >= count($colors)) { $datNum = 0; }
                $tmp2[] = "\n\t name: '$dkz',\n\t y: $dv" . "\n\t $rgb";
            }
            $str .= implode("}, {", $tmp2) . "\n\t }]";
            $tmp[] = $str;
        }
    }
    echo implode("\n }, {", $tmp);
    echo "\n }]";
    echo "\n }";
    echo "\n })";
    echo "\n });";
    echo "</script>";





}


function printPieHigh($title, $data) {
	global $containerID;
	$containerID++;
	// gray theme
	// $colors = array("#DDDF0D", "#7798BF", "#55BF3B", "#DF5353", "#aaeeee", "#ff0066", "#eeaaee", "#55BF3B", "#DF5353", "#7798BF", "#aaeeee");
	// grid theme
	$colors = array('#058DC7', '#50B432', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4');
	echo "\n<h4>$title</h4><div id=\"container$containerID\" class=\"chartContainer\"></div>";
	echo <<<BLA
	<script type="text/javascript">
	
	Highcharts.setOptions({
		lang: {
			drillUpText: 'Back'
		}
	});


	$(function () {
    $('#container$containerID').highcharts({
		chart: {
            type: 'pie',
			backgroundColor: 'rgba(255,255,255,0.1)'
        },
        title: {
            text: '',
			style: {
				color: '#000000'
			}
        },
        xAxis: {
            type: 'category'
        },
        legend: {
            enabled: false
        },
		credits: {
			enabled: false
		},
        plotOptions: {
            series: {
                borderWidth: 0,
                dataLabels: {
                    enabled: true,
					color: '#000000',
					format: '{point.name}: {point.y:.0f}'
                }
            }
        },

        series: [{
		name: '$title',
        colorByPoint: true,
        data: [{
BLA;
		$tmp = array();
		$datNum = 0;
        foreach ($data as $k => $v) {
			$rgb = ", \n\t color: '" . $colors[$datNum] . "'";
			$datNum++;
			if ($datNum >= count($colors)) { $datNum = 0; }
			// $rgb = "";
			if (isset($v["drill"]) && (count($v["drill"]) > 0)) {
				$tmp[] = "\n\t name: '$k'," . "\n\t y: " . $v["count"] . "," . "\n\t drilldown: 'd$k' $rgb";
			} else {
				$tmp[] = "\n\t name: '$k'," . "\n\t y: " . $v["count"] . "," . "\n\t drilldown: null $rgb";
			}
		}
		echo implode("}, {", $tmp);
        echo "\n }]";
        echo "\n }],";
        echo "\n drilldown: {";
		echo "\n activeDataLabelStyle: {";
		echo "\n\t color: '#000000'";
		echo "\n},";
		echo "\n series: [";
		echo "\n {";
		$tmp = array();
		foreach ($data as $k => $v) {
			$datNum = 0;
			if (isset($v["drill"]) && count($v["drill"]) > 0) {
				$str = "\n\t id: 'd$k', \n\t data: [{";
				$tmp2 = array();
				foreach ($v["drill"] as $dk => $dv) {
					$dkz = str_replace("'", "\\'", $dk);
					$rgb = ", \n\t color: '" . $colors[$datNum] . "'";
					$datNum++;
					if ($datNum >= count($colors)) { $datNum = 0; }
					$tmp2[] = "\n\t name: '$dkz',\n\t y: $dv" . "\n\t $rgb";
				}
				$str .= implode("}, {", $tmp2) . "\n\t }]";
				$tmp[] = $str;
			}
		}
		echo implode("\n }, {", $tmp);
        echo "\n }]";
        echo "\n }";
	echo "\n })";
	echo "\n });";
	echo "</script>";
}

?>
