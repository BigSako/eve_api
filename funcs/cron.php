<?php
/*************************************************
* SM3LL.net API Cronjob helper functions         *
* This file contains cron job helper funcs       *
*************************************************/

/** rebuild_groups() is rebuilding the groups for the api database
	forum permissions are handled in the function sync_forum_permission() */
function rebuild_groups()
{
	do_log("rebuilding auto-assigned groups",1);
	
	rebuild_members();
	rebuild_directors();
	rebuild_ceo();
	rebuild_titans();
	rebuild_supercarriers();
}



function tidy_removed_groups()
{
	global $globalDb;
	
	do_log("Tidying up groups that users have voluntarily left",1);
	$sth=$globalDb->query("select group_id,user_id from group_membership where state=97");
	while($result=$sth->fetch_array()) {
		$group_id=$result['group_id'];
		$user_id=$result['user_id'];
		$globalDb->query("delete from group_membership where group_id='$group_id' and user_id='$user_id'");
	}
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}








function process_telegram_notifications()
{
	global $globalDb, $SETTINGS;
	// get all messages that are pending to be sent
	$sql = "SELECT a.telegram_user_id, n.notification_id, n.message, n.responsible_user_id,
	a.telegram_start_hour, a.telegram_stop_hour
	FROM player_notification n, auth_users a WHERE n.user_id = a.user_id AND a.telegram_user_id <> 0 AND n.send_ping = 1";

	$res = $globalDb->query($sql);
	
	$cur_hour = gmdate('H');
	
	while ($row = $res->fetch_array())
	{
		$telegram_start_hour = $row['telegram_start_hour'];
		$telegram_stop_hour = $row['telegram_stop_hour'];
		$do_send = false;
		if ($telegram_start_hour == $telegram_stop_hour)
		{
			$do_send = true;
		}
		else
		{	
			// e.g., 08:00 - 17:00
			if ($telegram_start_hour < $telegram_stop_hour)
			{
				if ($telegram_start_hour <= $cur_hour && $cur_hour < $telegram_stop_hour)
				{
					$do_send = true;
				}
			} else {
				// e.g., 17:00 - 03:00
				if ($telegram_start_hour >= $cur_hour) // it's later than the start time
				{
					$do_send = true;
				} else if ($cur_hour < $telegram_stop_hour)
				{
					$do_send = true;
				}
			}
		}
		
		if ($do_send)
		{
			$telegram_user_id = $row['telegram_user_id'];
			$notification_id = $row['notification_id'];
			
			$message = $row['message'];
			
			sendTelegramMessage($telegram_user_id, $message);
			
			// make sure we set this as sent
			$sql = "UPDATE player_notification SET send_ping=2 WHERE notification_id=$notification_id; ";
			$globalDb->query($sql);
		}
	}
}




// clean logon minutes table for entries older than 120 days
function clean_logon_minutes()
{
	global $globalDb;
	
	$sql = "SELECT YEAR( TIMESTAMP ) AS tyear, MONTH( TIMESTAMP ) AS tmonth, DAY( TIMESTAMP ) AS tday, keyId, MAX( logonMinutes ) AS maxMinutes, MIN( logonMinutes ) AS minMinutes, COUNT( * ) AS cnt
FROM  `player_logonMinutes` 
WHERE DATEDIFF( NOW( ) , TIMESTAMP ) > 120
GROUP BY YEAR( TIMESTAMP ) , MONTH( TIMESTAMP ) , DAY( TIMESTAMP ) , keyId
HAVING cnt >1";

	$res = $globalDb->query($sql);
	echo "We have " . $res->num_rows . " entries to clean in logon minutes!\n";
	
	while ($row = $res->fetch_array())
	{
		$day = $row['tday'];
		$month = $row['tmonth'];
		$year = $row['tyear'];
		$keyId = $row['keyId'];
		$logonMinutes = $row['maxMinutes'];
		
		$sql2 = "DELETE FROM  `player_logonMinutes`  WHERE keyId='$keyId' AND YEAR(timestamp) = '$year' AND MONTH(TIMESTAMP) = '$month' AND DAY(TIMESTAMP) = '$day' ";
		$res2 = $globalDb->query($sql2);
		if (!$res2)
		{
			echo "ERROR executing '$sql2'\n";
		}
		
		$sql2 = "INSERT INTO `player_logonMinutes` (timestamp, keyId, logonMinutes) VALUES ('$year-$month-$day', '$keyId', '$logonMinutes') ";
		$res3 = $globalDb->query($sql2);
		if (!$res3)
		{
			echo "ERror executing '$sql2'\n";
		}
	}
	
	
}


function process_telegram_updates()
{
	global $SETTINGS, $globalDb, $curEveTime;
	$botapi="https://api.telegram.org/bot112916763:AAE02caBx7cU_VMWE3xqb1JeusZLf_1e84s/";

	$last_update_id = $SETTINGS['last_telegram_update_id']+1;

	$url = $botapi . "getUpdates?offset=" . $last_update_id . "&timeout=25"; // 25 seconds timeout
    $content = [];
    try {
        $content = file_get_contents($url);
        $json = json_decode($content, true);
    } catch (Exception $ignore)
    {
        echo "Error receiving telegram updates... Exiting...";
        return false;
    }

    $new_offset_id = 0;

	if ($json['ok'] == true)
	{
		$new_offset_id = 0;
		echo "true";
		$result = $json['result']; // array
		foreach ($result as $msg)
		{
			$this_update_id = $msg['update_id'];
			$message = $msg['message'];
			$msgid = $globalDb->real_escape_string($message['chat']['id']);
			$msgtext = $message['text'];
			echo "update=" . $this_update_id . ", msgid=" . $msgid . ", text=$msgtext\n";
			if ($this_update_id > $new_offset_id)
			{
				$new_offset_id = $this_update_id;
			}
			$msgtext = strtolower($msgtext);

			if (startsWith($msgtext, "subscribe"))
			{
				// check for token
				$pos = strpos($msgtext, " ");
				if ($pos > 0)
				{
					// subscribe to chat $msgid
					$token = substr($msgtext, $pos+1);
					echo "msgid=$msgid, Token = '$token'\n";
					// find token
					$sql = "UPDATE auth_users SET telegram_active=1, telegram_user_id=$msgid WHERE telegram_active=0 AND telegram_key='" . $globalDb->real_escape_string($token) . "' ";
					echo "sql=$sql\n";
					$res = $globalDb->query($sql);
					sendTelegramMessage($msgid, "Okay, subscribed! Please check whoami.");
				} else {
					sendTelegramMessage($msgid, "Error: Please use the following format: subscribe xyz1234567");
				}
				
			} else if ($msgtext == "stop")
			{
				// unsubscribe that $msgid
				$sql = "UPDATE auth_users SET telegram_active=0 WHERE telegram_user_id='" . $msgid . "' ";
				$globalDb->query($sql);
				sendTelegramMessage($msgid, "Okay, unscubscribed!");
			} else if ($msgtext == "evetime")
			{
				sendTelegramMessage($msgid, "Current EvE Time: " . $curEveTime);
			} else if ($msgtext == "whoami")
			{
				// user must be registered to receive that request
				$sql = "SELECT user_id,user_name,has_regged_main FROM auth_users WHERE telegram_user_id='" . $msgid . "' ";
				$res = $globalDb->query($sql);
				
				if ($res->num_rows == 0)
				{
					sendTelegramMessage($msgid, "I am sorry, I do not know who you are. Did you subscribe yet?");
				} else {
					$row = $res->fetch_array();
					$user_name = $row['user_name'];
					sendTelegramMessage($msgid, "You are currently logged in as " . $user_name . "\n");
				}
				
			} else if ($msgtext == "continue" || $msgtext == "start")
			{
				// user must be registered to receive that request
				$sql = "SELECT user_id,user_name,has_regged_main FROM auth_users WHERE telegram_user_id='" . $msgid . "' ";
				$res = $globalDb->query($sql);

				if ($res->num_rows == 0)
				{
					sendTelegramMessage($msgid, "I am sorry, I do not know who you are. Did you subscribe yet?");
				} else {
					$row = $res->fetch_array();
					$user_name = $row['user_name'];
					sendTelegramMessage($msgid, "Subscribed! You will receive pings again!");
					$sql = "UPDATE auth_users SET telegram_active=1 WHERE telegram_user_id='" . $msgid . "' ";
					$globalDb->query($sql);
				}
			} else if (startsWith($msgtext, "settime"))
			{
				// specify start and end time
				// check for space
				$pos = strpos($msgtext, " ");
				if ($pos > 0)
				{
					$starttime = substr($msgtext, $pos+1);
					$pos2 = strpos($starttime, " ");
					
					if ($pos2 > 0) 
					{
						$stoptime = intval(substr($starttime, $pos2+1));
						$starttime = intval(substr($starttime, 0, $pos2));
						
						echo "stoptime='$stoptime', starttime='$starttime'\n";
						
						$sql = "SELECT user_id,user_name,has_regged_main FROM auth_users WHERE telegram_user_id='" . $msgid . "' ";
						$res = $globalDb->query($sql);
						
						if ($res->num_rows == 0)
						{
							sendTelegramMessage($msgid, "I am sorry, I do not know who you are. Did you subscribe yet?");
						} else
						{
							// update 
							$sql = "UPDATE auth_users SET telegram_start_hour='$starttime', telegram_stop_hour='$stoptime' WHERE telegram_user_id='" . $msgid . "' ";
							$globalDb->query($sql);
							sendTelegramMessage($msgid, "You will now receive pings only between $starttime:00 and $stoptime:00 EVE TIME (GMT)! If you want to reset, just write settime 0 0.");
						}
					}
				}
				
			} else if ($msgtext == "shop")
			{
				// user must be registered to receive that request
				$sql = "SELECT user_id,user_name,has_regged_main FROM auth_users WHERE telegram_user_id='" . $msgid . "' ";
				$res = $globalDb->query($sql);
				
				if ($res->num_rows == 0)
				{
					sendTelegramMessage($msgid, "I am sorry, I do not know who you are. Did you subscribe yet?");
				} else
				{
					$row = $res->fetch_array();
					$user_name = $row['user_name'];
					$user_id = $row['user_id'];
				}
			} else {
				sendTelegramMessage($msgid, "Wrong command! The following commands are supported:\n
				subscribe 123xyzabc\nstop\ncontinue\nstart\nevetime\nwhoami\nsettime 5 23 - set timeframe for receiving messages to 05:00 - 23:00 EVE TIME (GMT).\nIf you want me to stop sending you messages, just say stop.");
			}
		}

	}
	
	if ($new_offset_id != 0)
	{
		// update last_telegram_update_id to new_offset_id
		$globalDb->query("UPDATE settings SET svalue='" . $new_offset_id . "' WHERE name='last_telegram_update_id' ");
	}
}




function sync_forum_permission2()
{
	global $globalDb, $SETTINGS;
	
	do_log("in sync_forum_permission2():", 1);


	$res = $globalDb->query("SELECT forum_group_id FROM groups WHERE group_name='Registered Members' ");
	$row = $res->fetch_array();
	$main_forum_group_id = $row['forum_group_id'];
	
	// get all users
	$sql = "SELECT user_id, user_name, has_regged_api, forum_id FROM auth_users ";
	$sth=$globalDb->query($sql);

	
	while ($row = $sth->fetch_array())
	{
		$user_id = $row['user_id'];
		$user_name = $row['user_name'];
		$forum_id = $row['forum_id'];
		
		$res = $globalDb->query("select g.forum_group_id, g.group_name, g.display_forum_title FROM groups g, group_membership m WHERE m.group_id = g.group_id AND 
							m.user_id = " . $user_id . "  AND m.state=0");

		$forum_group_names = array();
			
		if ($res->num_rows == 0) // this means the user has no groups with state 0 --> no groups assigned --> remove all 
		{
			// what? No groups? let's make sure he doesnt have roles on forum
			do_log("Removing all forum groups (except Registered) from $user_name (forum_id: $forum_id)!", 1);
			remove_all_forum_groups($forum_id);

			// set him to registered user again:
			//set_forum_group($forum_id, $main_forum_group_id);
			add_forum_group_membership($forum_id, $main_forum_group_id);
			
		} else 
		{
			// sync groups
			while ($row2 = $res->fetch_array())
			{
				$group_name = $row2['group_name'];
				$forum_group_id = $row2['forum_group_id'];
				$forum_title = $row2['display_forum_title'];
				$forum_group_id = $row2['forum_group_id'];

				if ($forum_title != '')
				{
					$forum_group_names[] = $forum_title;
				}
				
				if ($forum_group_id != 0 && !is_member_of_group($forum_id, $forum_group_id))
				{
					// add him
					do_log("Adding user $user_name (forum_id: $forum_id) to forum group $forum_group_id !", 1);
					add_forum_group_membership($forum_id, $forum_group_id);
				}
				
			}
		}

		// build forum title string
		$forum_title = implode(', ', $forum_group_names);
		update_user_title($forum_id, $forum_title);
	}

	return true;
}



function decrypt_vcode($vcode)
{
	global $privateKey;
	if ($privateKey == '')
	{
		echo "Private key is invaild...";
	}
	return decrypt($vcode,$privateKey);
}
	
	
function parse_server_status()
{
	$api_up = false;
	
	$res = get_server_status();
	
	if ($res["filename"] == "error")
	{
		$api_up = false;
	} else 
	{	
		$xml = simplexml_load_file($res['filename']);
		
		$serverOpen = $xml->result->serverOpen;
		if ($serverOpen == 'True')
		{
			$api_up = true;
		}
	
	}
	
	return $api_up;
}


function clean_shop()
{
	global $globalDb;

	// delete all canceled orders that are older than a day
	$globalDb->query("DELETE FROM shopping_order WHERE state=9 AND TIMESTAMPDIFF(MINUTE,last_updated,NOW()) > 1440 ");
}


function update_alliances()
{
	global $globalDb;
	
	$res = api_get_alliance_list();	
	do_log("Updating the list of alliances and member corporations",0);
	
	if ($res['status'] != 'OK')
		return false;
	
	$alliancexml = simplexml_load_string($res['data']);
	
	// update alliances, set state to 1 = updateing 
	$globalDb->query("UPDATE alliances SET state=1 WHERE state <> 99");
	$list=$alliancexml->result->rowset[0];

	echo "UPdateing alliances...\n";

	foreach ($list as $allRow)
	{
		$memberCount = intval($allRow['memberCount']);
		$alliance_id = intval($allRow['allianceID']);
		$alliance_name = $globalDb->real_escape_string($allRow['name']);
		$alliance_ticker = $globalDb->real_escape_string($allRow['shortName']);


		if ($memberCount > 10) // ignore alliances with less than 10 members
		{
			do_log("Processing : $alliance_name ($alliance_id)", 8);
			$sql = "INSERT INTO alliances (alliance_id, alliance_name, alliance_ticker, member_count, state)
			VALUES ($alliance_id, '$alliance_name', '$alliance_ticker', $memberCount, 0) ON DUPLICATE KEY UPDATE
				alliance_name='$alliance_name', alliance_ticker='$alliance_ticker', member_count = $memberCount, state=0 ";

			$res = $globalDb->query($sql);
			if (!$res) {
				echo "ERROR: $sql";
				echo "\n" . $globalDb->error . "\n";
				do_log("Error: $sql failed (" . $globalDb->error . ")", 1);
			}

			// update all corporations belonging to this alliance
			$globalDb->query("UPDATE corporations SET state=1 WHERE state <> 99 AND alliance_id = $alliance_id");

			// go through rowset corp
			foreach ($allRow->rowset->row as $corp) {
				$corp_id = intval($corp['corporationID']);
				$corp_name = "";

				do_log("Member corporation found: $corp_id ", 9);

				$sql = "INSERT INTO corporations
				(alliance_id, corp_id, corp_name, corp_ticker, ceo, state) VALUES 
				($alliance_id, $corp_id, '', '', '', 0) ON DUPLICATE KEY UPDATE
				state=0, alliance_id=$alliance_id"; // do not update corp_name here

				$res = $globalDb->query($sql);
				if (!$res) {
					echo "ERROR: $sql\n" . $globalDb->error . "\n";
					do_log("SQL error on inserting corporation with corp_id $corp_id, sql='$sql', error = " . $globalDb->error, 1);
				}

			}

			// remove/clean up remaining corportations with state state=1 from this alliance
			$sql = "DELETE FROM corporation WHERE alliance_id = $alliance_id AND state = 1";
			$globalDb->query($sql);

		}
	}

	// delete all alliances with state = 1
	$sql = "DELETE FROM alliances WHERE state = 1";
	$globalDb->query($sql);

	// get all alliances that are allowed to register and make sure there exists a group for them
	$res = $globalDb->query("SELECT a.alliance_id, a.alliance_name, a.is_allowed_to_reg, a.is_allied FROM alliances a
				WHERE a.alliance_name != '' and (a.is_allowed_to_reg = 1 or a.is_allied = 1)");

	while ($row = $res->fetch_array())
	{
		$all_id = $row['alliance_id'];
		$group_name = "";
		$group_desc = "";
		if ($row['is_allowed_to_reg'] == 1)
		{
			$group_name = "Alliance " . $row['alliance_name'];
			$group_desc = "Auto generated group for " . $row['alliance_name']; 
		} else if ($row['is_allied'] == 1)
		{
			$group_name = "Alliance " . $row['alliance_name'];
			$group_desc = "Auto generated group for " . $row['alliance_name'];
		}
		// check if group exists
		$sql = "SELECT group_id, group_name FROM groups WHERE group_name = '$group_name'";
		$res2 = $globalDb->query($sql);

		if ($res2->num_rows == 0)
		{ // group does not exist yet
			$group_name = $globalDb->real_escape_string($group_name);
			$group_desc = $globalDb->real_escape_string($group_desc);

			$sql = "INSERT INTO groups (group_name, group_description, auto_join, pre_req_group, authorisers, forum_group_id, ts3_group_id, jabber_group_name, jabber_ping, hidden, autoGenerated)
                                VALUES
                                ('$group_name', '$group_desc', 0, 1, 999, 0, 0, '', 0, 0, 1)";
                        if (!$globalDb->query($sql))
                        {
                            echo "SQL Error: $sql\n";
                            do_log("SQL Error: $sql", 1);
                        }

		}
		

	}


	// get all corporations that are allowed to register and check for their CEO
	$res = $globalDb->query("select c.alliance_id, a.alliance_name, c.corp_name, c.corp_id, c.ceo, c.state,
				c.is_allowed_to_reg as corp_allowed_to_reg, a.is_allowed_to_reg as alliance_allowed_to_reg
 				FROM corporations c, alliances a
				WHERE (c.alliance_id = a.alliance_id) AND 
						(c.is_allowed_to_reg=1 OR a.is_allowed_to_reg=1 OR a.is_allied = 1 or c.is_allied = 1) AND
						1=1 
						ORDER BY a.alliance_name, c.corp_name ");

    echo "Downloading corp_sheet for all corporations\n";
						
	while ($row = $res->fetch_array())
	{
		$corp_id = $row['corp_id'];
		$xml = api_get_corporation_sheet($corp_id);
		
		$corp_xml = simplexml_load_string($xml['data']);
		
		$ceo_id = intval($corp_xml->result->ceoID);
		$ticker = $globalDb->real_escape_string($corp_xml->result->ticker);
		
		$corp_name = $globalDb->real_escape_string($corp_xml->result->corporationName);
		$alliance_id = intval($corp_xml->result->allianceID);

		// set default alliance to 1 (if it does not exist)
		if ($alliance_id == 0)
			$alliance_id = 1;
		
		if ($ceo_id != 0 && $ticker != "")
		{
			$sql = "UPDATE corporations SET ceo = $ceo_id, corp_ticker='$ticker', 
				alliance_id = $alliance_id, corp_name='$corp_name' WHERE corp_id = $corp_id ";
			if (!$globalDb->query($sql))
			{
				echo "SQL Error: \"$sql\" \n";
				do_log("SQL Error: \"$sql\" \n", 1);
			}

			if ($row['corp_allowed_to_reg'] == 1 || $row['alliance_allowed_to_reg'] == 1) {
				// TODO: check if a group for them exists
				$sql = "SELECT group_id, group_name FROM groups WHERE group_name = '$corp_name'";
				$res2 = $globalDb->query($sql);

				if ($res2->num_rows == 0) {
					$sql = "INSERT INTO groups (group_name, group_description, auto_join, pre_req_group, authorisers, forum_group_id, ts3_group_id, jabber_group_name, jabber_ping, hidden, autoGenerated)
				VALUES
				('$corp_name', 'Auto generated group for corporation', 0, 1, 999, 0, 0, '', 0, 0, 1)";

					if (!$globalDb->query($sql)) {
						echo "SQL Error: $sql\n";
						do_log("SQL Error: $sql", 1);
					}
				}
			}
			
		}
		
		
	}
}


	



function update_conq_stations()
{
	global $globalDb;
	
	do_log("In update_conq_stations", 7);
	$result = api_get_conq_stations();
	$xml = simplexml_load_string($result['data']);
	
	
	$globalDb->query("UPDATE conqStations SET state=1 WHERE state=0");
	
	foreach ($xml->result->rowset->row as $row) 
	{
		$stationID = $row['stationID'];
		$stationName = $globalDb->real_escape_string($row['stationName']);
		$stationTypeID = $row['stationTypeID'];
		$solarSystemID = $row['solarSystemID'];
		$corpID = $row['corporationID'];
		$corpName = $globalDb->real_escape_string($row['corporationName']);
		
		$res2 = $globalDb->query("INSERT INTO conqStations (stationID, stationName, stationTypeID, solarSystemID, corporationID, state, corpName) VALUES " .
				" ($stationID, '$stationName', $stationTypeID, $solarSystemID, $corpID, 0, '$corpName') ON DUPLICATE KEY UPDATE " .
				" stationName='$stationName', state=0, corporationID=$corpID, corpName='$corpName' ");
				
		if (!$res2) {
		echo "INSERT INTO conqStations (stationID, stationName, stationTypeID, solarSystemID, corporationID, state, corpName) VALUES " .
				" ($stationID, '$stationName', $stationTypeID, $solarSystemID, $corpID, 0, '$corpName') ON DUPLICATE KEY UPDATE " .
				" stationName='$stationName', state=0, corporationID=$corpID, corpName='$corpName' ";
		}
	}
	
	
	$globalDb->query("UPDATE conqStations SET state=99 WHERE state=1");
	
}


/** Update sov status
    get https://api.eveonline.com/map/Sovereignty.xml.aspx, prase it and put it into the sovereignty table */
function update_sov()
{
	global $globalDb;
	
	do_log("in update_sov", 1);
	$res = api_get_sovereignty();
	
	if ($res['status'] == 'OK')
	{
		$xml = simplexml_load_string($res['data']);
		if ($xml)
		{
			foreach($xml->result->rowset->row as $row) 
			{
				$solarSystemID = $row['solarSystemID'];
				$allianceID    = $row['allianceID'];
				$factionID     = $row['factionID'];
				$solarSystemName = $row['solarSystemName'];
				$corporationID   = $row['corporationID'];
				
				$sql = "INSERT INTO sovereignty (solarSystemID, allianceID, factionID, corporationID, state) VALUES
						($solarSystemID, $allianceID, $factionID, $corporationID, 0) ON DUPLICATE KEY UPDATE
							allianceID=$allianceID, factionID=$factionID, corporationID=$corporationID, state=0 ";
							
				//echo "Update for sov: $sql\n";
				
				$globalDb->query($sql);
			}
			
			return true;
		}		
	}
	
	
	return false;
}


/**
 * update a sub-item of corp assets
 */
function update_corp_asset_subitem($corp_id, $parent_id, $rows, $stmt)
{
	global $globalDb, $SETTINGS;

	foreach ($rows->row as $row)
	{
		$itemID = "" . $row['itemID'];

		$locationID = $row['locationID'];
		$typeID = $row['typeID'];

		$quantity = $row['quantity'];
		$flag = $row['flag'];
		$rawQuantity = $row['rawQuantity'];
		$singleton = $row['singleton'];

		if (!$rawQuantity)
			$rawQuantity = 0;

        $is_sub_item = false;

		// insert this into database
		$stmt->bind_param("iiiiiiiiii", $itemID, $corp_id, $parent_id, $typeID, $locationID, $flag, $singleton, $rawQuantity, $quantity, $is_sub_item);
		$stmt->execute();

		if ($stmt->affected_rows != 1)
		{
			do_log("error, secondary insert into corp_assets probably failed...", 1);
			echo "error, secondary insert into corp_assets probably failed...\n";
			echo "  Child row: corp_id=$corp_id, parent_id=$parent_id, itemID=$itemID, locationID=$locationID, typeID=$typeID, rawQ=$rawQuantity, q=$quantity, flag=$flag\n";
			echo $globalDb->error . "\n";
			break;
		}		


		// does this have children?
		if ($row->rowset[0])
		{
			update_corp_asset_subitem($corp_id, $itemID, $row->rowset[0], $stmt);
		}

	}
}


/**
 * update a sub-item of player assets
 */
function update_player_asset_subitem($character_id, $parent_id, $rows, $stmt)
{
	global $globalDb, $SETTINGS;

	foreach ($rows->row as $row)
	{
		$itemID = "" . $row['itemID'];

		$locationID = $row['locationID'];
		$typeID = $row['typeID'];

		$quantity = $row['quantity'];
		$flag = $row['flag'];
		$rawQuantity = $row['rawQuantity'];

		if (!$rawQuantity)
			$rawQuantity = 0;

        $is_sub_item = false;

		// insert this into database
		$stmt->bind_param("iiiiiiiii", $itemID, $character_id, $parent_id, $typeID, $locationID, $flag, $rawQuantity, $quantity, $is_sub_item);
		$stmt->execute();

		if ($stmt->affected_rows != 1)
		{
			do_log("error, secondary insert into player_supercarriers probably failed...", 1);
			echo "error, secondary insert into player_supercarriers probably failed...\n";
			echo "  Child row: character_id=$character_id, parent_id=$parent_id, itemID=$itemID, locationID=$locationID, typeID=$typeID, rawQ=$rawQuantity, q=$quantity, flag=$flag\n";
			echo $globalDb->error . "\n";
			break;
		}		


		// does this have children?
		if ($row->rowset[0])
		{
			update_player_asset_subitem($character_id, $itemID, $row->rowset[0], $stmt);
		}

	}
}



/**
 * update player assets
 * step one: pull the xml data to a permanent location (TODO)
 * step two: parse the xml data with an efficient parser, look for supercarriers and titans only and store those in a database
 */
function update_player_assets($character_id, $key_id, $vCode)
{
	global $globalDb, $SETTINGS, $all_capital_ids;

	$res = api_get_player_asset_list($character_id, $key_id, $vCode);
	if ($res['status'] != 'OK')
	{
		echo "error - could not load asset list for character $character_id (key_id: $key_id)\n";
		do_log("error - could not load asset list for character $character_id (key_id: $key_id)", 1);
		return -1;
	}

	$xml = simplexml_load_string($res['data']);
	if ($xml && $xml->result)
	{
		// start by updating the characters capital ships
		$sql = "DELETE FROM player_supercarriers WHERE character_id = $character_id";
		$globalDb->query($sql);

		// prepare a statement
		$sql = "INSERT INTO player_supercarriers (itemID, character_id, parentItemID, typeID, locationID, flag, rawQuantity, quantity, inSpace) VALUES 
						(?, ?, ?, ?, ?, ?, ?, ?, ?)";
		if (! ($stmt = $globalDb->prepare($sql)))
		{
			do_log("Error preparing statement $sql", 0);
            do_log($globalDb->error, 0);
			echo "Error preparing statement $sql; \n" . $globalDb->error . "\n";
            return;
		}


		// go through all rows in the asset list and find a capital ship
		foreach($xml->result->rowset->row as $row) 
		{				
			$itemID = "" . $row['itemID'];
			$locationID = $row['locationID'];
			$typeID = $row['typeID'];

			if (!in_array($typeID, $all_capital_ids))
			{
				// not a super carrier
				continue;
			}
						
			$singleton = $row['singleton']; // whether or not this item is packaged
			
			$quantity = $row['quantity'];
			$flag = $row['flag'];				
			$parentItemID = -1;
			
						
			if ($singleton == 0) // packaged
			{
				$rawQuantity = 0;
			} else {
				
				$rawQuantity = $row['rawQuantity'];
			}
			
			// check if in space, if yes - collect the item ID - we will call locations api later
			$inSpace = false;
			if ($locationID < 60000000)
			{
				$inSpace = true;
			}
			
			
			// insert this into database
			$stmt->bind_param("iiiiiiiii", $itemID, $character_id, $parentItemID, $typeID, $locationID, $flag, $rawQuantity, $quantity, $inSpace);
			$stmt->execute();
			
			
			if ($stmt->affected_rows != 1)
			{
				do_log("error, primary insert into player_supercarriers probably failed...", 1);
				echo "error, primary insert into player_supercarriers probably failed...\n";
				echo " itemId= $itemID, charId= $character_id, parent=$parentItemID, type=$typeID, location=$locationID, flag=$flag, raw=$rawQuantity, quant=$quantity, inSpace=$inSpace \n";
				echo $globalDb->error . "\n";				
				break;
			}		

			// get name and location in space of this supercarrier
			$loc_result = api_get_player_asset_locations($character_id, $key_id, $vCode, $itemID);
			$loc_xml = simplexml_load_string($loc_result['data']);

			if ($loc_xml && $loc_xml->result->rowset)
			{
				foreach($loc_xml->result->rowset->row as $locRow)
				{
					$itemName = $globalDb->real_escape_string($locRow['itemName']);
					$itemName = str_replace("\n", " ", $itemName);
					$x    = $locRow['x'];
					$y    = $locRow['y'];
					$z    = $locRow['z'];


					$sql5 = "UPDATE player_supercarriers SET realName = '$itemName', x=$x, y=$y, z=$z WHERE itemID = $itemID and character_id = $character_id ";

					if (!$globalDb->query($sql5))
					{
						echo "Failed updating player_supercarriers, sql='$sql'\n";
						echo $globalDb->error;
					}
				}
			}		
			
			
			// does it have children? (unless it's a completely fresh super, it should)
			if ($row->rowset[0])
			{
				//echo "this row has children!\n";
				$parentItemID = "$itemID";
				$parentInSpace = $inSpace;
				
				// check children
				update_player_asset_subitem($character_id, $parentItemID, $row->rowset[0], $stmt);
			}
		} // END foreach

		

	} else {
		echo "error - could not load asset list for character $character_id (key_id: $key_id)\n";
		do_log("error - could not load asset list for character $character_id (key_id: $key_id)", 1);
		return -1;
	}

	return 0;
}



function check_main_characters()
{
	global $globalDb;

    // get all mains that are no longer linked properly
    $res = $globalDb->query("SELECT u.user_id FROM auth_users u, api_characters c WHERE u.has_regged_main = c.character_id AND c.user_id <> u.user_id ");

    while ($row = $res->fetch_array())
    {
        $sql = "UPDATE auth_users SET has_regged_main = 0 WHERE user_id = " . $row['user_id'];
        $globalDb->query($sql);
    }
}



/**
 * Update corp assets and offices
 * first: update office list
 * second: update all items and sub-items
 */
function update_corp_assets()
{
	global $corp_time_diff, $globalDb, $SETTINGS;
	do_log("in update_corp_assets()", 1);		
	
	$sth = $globalDb->query("select a.corp_id as corp_id,a.keyid as keyid,a.vcode as vcode, " . 
				"b.alliance_id as alliance_id from corp_api_keys a, corporations b WHERE " .
				"a.state = 0 AND a.corp_id=b.corp_id AND (last_asset_update is NULL OR timestampdiff(hour,last_asset_update,now()) >= 6) ");
				
	while($result=$sth->fetch_array()) 
	{
		$alliance_id=intval($result['alliance_id']);
		$corp_id=intval($result['corp_id']);
		$key_id=$result['keyid'];
		$vcode=decrypt_vcode($result['vcode']);	
		
		$office_result = api_get_corp_asset_list($corp_id, $key_id, $vcode);
		
		echo "Updating assets for corp $corp_id\n";
		
		if ($office_result['status'] != 'OK')
		{
			echo "error - could not load corp asset list from key $key_id; Result = " . $office_result['status']. " \n";
			do_log("ERROR: could not load corp asset list from key $key_id; Result = " . $office_result['status']. "", 1);
			continue;
		}
			
		$corp_assets_xml = simplexml_load_string($office_result['data']);
		
		
		if ($corp_assets_xml)
		{
			// FIRST: update current list of offices
			$globalDb->query("UPDATE offices SET state=1 WHERE state=0 AND corp_id=$corp_id");
			
			$rows=$corp_assets_xml->result->rowset[0]->xpath("//row[@typeID='27']"); // only get offices
			$cnt = 0;
			foreach($rows as $row) {
				$attr = $row[0]->attributes();
				$location_id = $attr['locationID'];
				
				if (!$globalDb->query("INSERT INTO offices (corp_id, location_id, state) VALUES ($corp_id, $location_id, 0) ON DUPLICATE KEY UPDATE state=0"))
				{
					echo "Error: Could not add office for corp $corp_id\n";
				}
				
				$cnt++;
			}			
			
			echo "Found $cnt offices for corp $corp_id\n";			
			
			// remove the remaining offices
			if (!$globalDb->query("DELETE FROM offices WHERE state=1 AND corp_id=$corp_id;"))
			{
				echo "Error: Could not delete offices for corp $corp_id\n";
				echo $globalDb->error . "\n";
			}		
			
			
			// SECOND: as we have the asset list already, let's iterate over all corp assets
			
			// clean all existing entries for now, we will add all of them again
			if (!$globalDb->query("DELETE FROM corp_assets WHERE corp_id = $corp_id"))
			{
				echo "Error: Could not delete corp_assets entries for corp $corp_id \n";
				echo $globalDb->error . "\n";
			}
			
			// use a prepared statement for performance reasons
			$sql = "INSERT INTO corp_assets (itemID, corp_id, parentItemID, typeID, locationID, flag, singleton, rawQuantity, quantity, inSpace) VALUES 
							(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			if (! ($stmt = $globalDb->prepare($sql)))
			{
				do_log("Error preparing statement '$sql' ", 0);
				echo "Error preparing statement '$sql' for corp $corp_id \n";
				echo $globalDb->error;
				continue;
			}
			
			
			$inSpaceItemIDs = array();
			
			// go through all rows in the asset list
			foreach($corp_assets_xml->result->rowset->row as $row) 
			{
				$itemID = $row['itemID'];
				$locationID = $row['locationID'];
				$typeID = $row['typeID'];
				$quantity = $row['quantity'];
				$flag = $row['flag'];
				$singleton = $row['singleton'];
				$rawQuantity = $row['rawQuantity'];
				$parentItemID = -1;
				
				// check if in space, if yes - collect the item ID - we will call Corp/locations later
				$inSpace = false;
				if ($locationID < 60000000)
				{
					$inSpace = true;
					$inSpaceItemIDs[] = $itemID;
				}
				
				//echo "asset row: itemID=$itemID, locationID=$locationID, typeID=$typeID, rawQ=$rawQuantity, q=$quantity, flag=$flag, sing=$singleton\n";
				
				// insert this into database
				$stmt->bind_param("iiiiiiiiii", $itemID, $corp_id, $parentItemID, $typeID, $locationID, $flag, $singleton, $rawQuantity, $quantity, $inSpace);
				if (!$stmt->execute())
				{
					echo "Failed to execute prepared statement for insert into corp_assets\n";
					echo $globalDb->error . "\n";
					echo "asset row: itemID=$itemID, locationID=$locationID, typeID=$typeID, rawQ=$rawQuantity, q=$quantity, flag=$flag, sing=$singleton\n";
				}
				
				
				if ($stmt->affected_rows != 1)
				{
					do_log("error, insert into corp_assets probably failed...", 1);
					echo "Failed inserting this row!\n";
					echo $globalDb->error . "\n";
					// if this fails, means the itemID already exists, need to flip the corp id !
					$sql = "DELETE FROM corp_assets WHERE itemID=$itemID";
					$globalDb->query($sql);
					//exit; // wtf ... do not exit EVER
					// insert this into database
					$stmt->bind_param("iiiiiiiiii", $itemID, $corp_id, $parentItemID, $typeID, $locationID, $flag, $singleton, $rawQuantity, $quantity, $inSpace);
					$stmt->execute();
				}
						
				
				
				// does it have children? - shouldn't, as it is a flat list...
				if ($row->rowset[0])
				{
					//echo "this row has children!\n";
					$parentItemID = "$itemID";
					$parentInSpace = $inSpace;
					
					// check children
					update_corp_asset_subitem($corp_id, $parentItemID, $row->rowset[0], $stmt);
				}
			} // END foreach
			
			
			
			
			// okay, now go through $inSpaceItemIDs and update the database information with it
			if (count($inSpaceItemIDs) > 1)
			{
				$chunks = array_chunk($inSpaceItemIDs,50); // chunking this with 50 per peice
				
				for ($i = 0; $i < count($chunks); $i++)
				{
					$itemIDs = implode(",",$chunks[$i]);	
					
					$loc_result = api_get_corp_locations($corp_id, $key_id, $vcode, $itemIDs);
					
					$loc_xml = simplexml_load_string($loc_result['data']);
				
					if ($loc_xml && $loc_xml->result->rowset)
					{
						foreach($loc_xml->result->rowset->row as $locRow)
						{
							$itemID = $locRow['itemID'];
							$itemName = $globalDb->real_escape_string($locRow['itemName']);
							$x    = $locRow['x'];
							$y    = $locRow['y'];
							$z    = $locRow['z'];
														
							if (!$globalDb->query("UPDATE corp_assets SET x=$x, y=$y, z=$z, realName='$itemName' WHERE itemID = $itemID"))
							{
								echo "Failed updating corp-assets location...\n";
								echo $globalDb->error . "\n";
								echo "itemIDs=$itemIDs\n";
							}
						}
					}
					else
					{
						do_log("Error, couldn't load corp locations xml for corp_id $corp_id, items: $itemIDs", 1);
					}
				}			
			}
			
			$globalDb->query("UPDATE corp_api_keys SET last_asset_update=now() WHERE corp_id=$corp_id;");
		}
		else
		{
			do_log("Error, couldn't load/parse asset xml for corp_id $corp_id", 1);
		}
	}
	
	// delete all offices that are gone
	$sql = "DELETE FROM offices WHERE state=99";
	$globalDb->query($sql);


	$updated_silo_cnt = 0;
	// save tower silo states
	$mainRes = $globalDb->query("SELECT locationID, corp_id, itemID, moonID FROM `starbases` WHERE state=0");
	while ($row = $mainRes->fetch_array())
	{
		$locationID = $row['locationID'];
		$corp_id = $row['corp_id'];
		$posItemID = $row['itemID'];
		$moonID = $row['moonID'];


		$names = getTowerNameAndLocation($globalDb, $corp_id, $locationID, $posItemID);

		$x = $names[1];
		$y = $names[2];
		$z = $names[3];

		// get all silos
		$sql = "SELECT a.itemID, a.quantity, x, y, z
						FROM corp_assets a, eve_staticdata.invTypes i
						WHERE a.locationID = $locationID AND typeName LIKE '%Silo%'
						AND i.typeID = a.typeID AND a.corp_id = $corp_id
						 ORDER BY typeName ASC";
		$res = $globalDb->query($sql);
		
		
						
		while ($subRow = $res->fetch_array())
		{
			$silo_x = $subRow['x'];
			$silo_y = $subRow['y'];
			$silo_z = $subRow['z'];

			$silo_id = $subRow['itemID'];

			// check how far away the structure is from the tower - if it is too far, then it's on a different tower and we need to skip it here
			if (max(abs($silo_x - $x),abs($silo_y - $y),abs($silo_z - $z)) > 50000)
			{
				continue;
			}

			// let's get all contents
			$content_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName
				FROM corp_assets a, eve_staticdata.invTypes i
				WHERE a.parentItemID = $silo_id 
				AND i.typeID = a.typeID ORDER BY typeName ASC";
				
			$contentRes = $globalDb->query($content_sql);

			// add silo content row to the database 
			while ($contentRow = $contentRes->fetch_array())
			{
				$quantity = $contentRow['quantity'];
				$sql = "INSERT INTO corp_tower_silo_history (corp_id, moonID, silo_item_id, silo_fill_state) VALUES ($corp_id, $moonID, $silo_id, $quantity)";
				if (!$globalDb->query($sql))
				{
					echo "Failed to query '$sql'\n";
					echo $globalDb->error . "\n";
				}			
				
				$updated_silo_cnt++;
			}			

		}
	}

	echo "Done updating corp assets, also udpated $updated_silo_cnt silos!\n";
}


function handle_player_notifications()
{
	global $globalDb, $SETTINGS;
	do_log("in handle_player_notifications()", 1);
	// get all players that want notifications
	$sql = "SELECT user_id as user_id, forum_id, user_name, email, wants_email_notifications FROM " .
		" auth_users WHERE wants_email_notifications > 0 AND (
		              last_notification is null OR TIMESTAMPDIFF(MINUTE , last_notification, NOW( ) ) >= 360
					  ) "; // and didnt receive a notification in the last 6 hours
	
	$res = $globalDb->query($sql);
	
	while ($row = $res->fetch_array())
	{
		$user_id = intval($row['user_id']);
		echo "checking user with user_id $user_id\n";
		$forum_id = $row['forum_id'];
		$notType = $row['wants_email_notifications'];
		$mail = $row['email'];
		
		// flag whether or not we found an issue
		$notification_found = false;
		// build notification message
		$notifications = "Hello!\n\nThis is an automated mail from the $SETTINGS[base_url] API system. You are receiving this mail because you wanted to be notified about:\n";
		
		if ($notType & 1)
		{
			// check skills
			$notifications .= " - Your skill queue running low\n";
		}
		
		if ($notType & 2)
		{
			// check account payment
			$notifications .= " - Your accounts running out of gametime\n";
		}
		
		
		$notifications .= "\n--------------------\nNotifications:\n";
		
		
		// alright, check his api keys now, right?
		$sql2 = "SELECT keyid, `comment` , char_training, paidUntil, last_checked, -timestampdiff ( minute, paidUntil, now( )) as diff
	FROM player_api_keys 
	WHERE user_id = $user_id AND state <= 10";

		$res2 = $globalDb->query($sql2);
		if ($res2->num_rows == 0)
		{
			do_log("Error... couldn't get any api keys for user_id $user_id", 1);
			$notifications = "There are no API keys active for you. Please go to $SETTINGS[api_url] and add or reactivate your API keys.\n\n";
			$notification_found = true;
		} else {
			// cycle through all active api keys
			while ($row2 = $res2->fetch_array())
			{
				$keyid = $row2['keyid'];
				// get characters for this keyid
				$sql3 = "SELECT character_name FROM api_characters WHERE key_id = $keyid";
				$res3 = $globalDb->query($sql3);
				$characters = "";
				while ($row3 = $res3->fetch_array())
				{
					$characters .= $row3['character_name'] . ", ";
				}
				
				$characters = substr($characters, 0, strlen($characters)-2);
				
				$comment = $row2['comment'];
				$paidUntil = $row2['paidUntil'];
				$paidDiff  = $row2['diff'];
				$training  = $row2['char_training'];
				
				// check gametime
				if ($notType & 2)
				{
					if ($paidDiff < 0) // already negative
					{
						$notifications .= "Account with API key id $keyid ($characters - $comment) has already expired at $paidUntil.\n";
						$notification_found = true;
					}
					else if ($paidDiff < 60*13) // less than 13 hours
					{
						$notifications .= "Account with API key id $keyid ($characters - $comment) will expire at $paidUntil.\n";
						$notification_found = true;
					}					
				}
				
				// check skills
				if ($notType & 1)
				{
					if ($training == 0)
					{
						$notifications .= "Account with API key id $keyid ($characters - $comment) is currently not training.\n";
						$notification_found = true;
					} else {
						// it is training, but let's check skill queues, shall we?
						$timeDiff = 780; // = 13 hours
						$sql3 = "SELECT character_name, skill_endtime, -timestampdiff ( minute, skill_endtime, now( )) as diff 
								FROM api_characters WHERE skill_id_training <> 0 
								AND user_id = $user_id AND key_id = $keyid AND -timestampdiff ( minute, skill_endtime, now( )) < $timeDiff";
						
						$res3 = $globalDb->query($sql3);
						if ($res3->num_rows == 0)
						{
							do_log("Error... couldn't get any characters for key_id $keyid on user_id $user_id", 1);
						} else 
						{
							while ($row3 = $res3->fetch_array())
							{
								$char_name = $row3['character_name'];
								$skill_endtime = $row3['skill_endtime'];
								$skill_diff = $row3['diff'];
								$skill_diff_hours = ceil($skill_diff/60);
								
								$notifications .= "Account with API key id $keyid ($characters - $comment), character $char_name skill-queue is finished in less than $skill_diff_hours hours at $skill_endtime.\n";
								$notification_found = true;
							}
						}
					}
				}
			}
		
		}

		
		
		if ($notification_found == true)
		{
			$notifications .= "\n\nYours sincerly,\nThe $SETTINGS[site_name] API Services.\n$SETTINGS[api_url]\n";
			$notifications .= "You can unsubscribe by going to $SETTINGS[api_url], logging into the forums and going to the API site.";
			
			$notifications = str_replace("\n", "\r\n", $notifications);
			
			$globalDb->query("UPDATE auth_users SET last_notification=now() WHERE user_id=$user_id");
			// send mail
			mail($mail, "$SETTINGS[site_name] API Notification", $notifications, "From: $SETTINGS[site_name] API Services <$SETTINGS[from_email]>\r\n");
			sleep(2); // sleep 2 seconds, because of some SMTP limitation...
		}
		
		
	} // END WHILE
}



/** get_all_members_from_apixml($xmldata) extracts an array with 
	character_ids as index and character_name as value from the
	xml file supplied via corp api and returns it*/
function get_all_members_from_apixml($xmldata, $alliance_id, $corp_id)
{
	global $globalDb;
	
	$data = array();
	$cnt = 0;
	foreach ($xmldata->result->rowset->row as $result) 
	{		
		$character_id=intval($result['characterID']);
		$character_name=$globalDb->real_escape_string($result['name']); 
		$startDateTime = $result['startDateTime'];
		$title = $globalDb->real_escape_string($result['title']);
		$logonDateTime = $result['logonDateTime'];
		$logoffDateTime = $result['logoffDateTime'];
		$locationID = intval($result['locationID']);
		$location = $globalDb->real_escape_string($result['location']);
		$shipTypeID = intval($result['shipTypeID']);
		$shipType = $globalDb->real_escape_string($result['shipType']);
		$roles = intval($result['roles']);
		$grantableRoles = intval($result['grantableRoles']);
		
		
		// because the character MIGHT already be in database, use on duplicate key update
		$sql = "INSERT INTO corp_members 
			(alliance_id, corp_id, character_id, character_name,state, shipTypeID, shipType, 
					location, title, logonDateTime, logoffDateTime, roles, grantableRoles, startDateTime )
			 VALUES ($alliance_id, $corp_id, $character_id, '$character_name', 0, $shipTypeID, '$shipType', 
					'$location', '$title', '$logonDateTime', '$logoffDateTime', $roles, $grantableRoles, '$startDateTime') 
			 ON duplicate key 
			 UPDATE alliance_id=$alliance_id, corp_id=$corp_id, character_name='$character_name',
			shipTypeID=$shipTypeID, shipType='$shipType', location='$location', title='$title',
			logonDateTime='$logonDateTime', logoffDateTime='$logoffDateTime', roles=$roles, grantableRoles=$grantableRoles, startDateTime='$startDateTime'
			 ";
		$res = $globalDb->query($sql);
		if (!$res)
			do_log("ERROR - query failed (qry1): $sql", 1);
			
		$sql = "INSERT INTO session_tracking
			(corp_id, character_id, logonDateTime, logoffDateTime) VALUES 
				($corp_id, $character_id, '$logonDateTime', '$logoffDateTime') ON DUPLICATE KEY UPDATE logoffDateTime='$logoffDateTime'";
		$res = $globalDb->query($sql);
		if (!$res)
			do_log("ERROR - query failed (qry2): $sql", 1);
		
		
		$data[$character_id] = $character_name;
		$cnt++;
	}

	do_log("get_all_members_from_apixml(alliance_id=$alliance_id, corp_id=$corp_id): found $cnt members", 1);
	
	return $data;
}	




/** get_all_members_from_db($corp_id) extracts an array with 
character_ids as index and character_name as value from the 
database based on the corp_id and returns it*/
function get_all_members_from_db($corp_id)
{
	global $globalDb;
	
	$data = array();
	
	$sth = $globalDb->query("SELECT character_id, character_name FROM corp_members WHERE corp_id=$corp_id ");
	
	while ($result = $sth->fetch_array() ) 
	{		
		$character_id=$result['character_id']; 
		// run real_escape_string here to be able to compare characters
		$character_name=$globalDb->real_escape_string($result['character_name']); 
		
		$data[$character_id] = $character_name;
	}	
	
	return $data;
}	



/** Updates the skill tree from eve api (https://api.eveonline.com/eve/SkillTree.xml.aspx)
 * @return bool
 */
function update_skilltree()
{
	global $globalDb;
	do_log("Entered update_skilltree", 1);
	
	$res = api_get_skilltree();
	if ($res['status'] == 'OK')
	{
		$skilltree = simplexml_load_string($res['data']);
	
		if ($skilltree)
		{
			echo "skilltree ok";
			$error = false;
			foreach ($skilltree->result->rowset->row as $group) 
			{
				$groupName = $group['groupName'];
				$groupID   = $group['groupID'];
				
				//echo "Group = $groupName\n";
				
				foreach ($group->rowset->row as $skill)
				{
					$skillName = $skill['typeName'];
					$skillTypeID = $skill['typeID'];
					$published   = $skill['published'];
					$rank = 0;
					
					//echo "SkillName = $skillName\n";
					
					$sql = "INSERT INTO invSkills (typeID, groupName, typeName, groupID, published, rank) VALUES 
						($skillTypeID, '$groupName', '$skillName', $groupID, $published, $rank) ON DUPLICATE KEY UPDATE
							groupName = '$groupName', typeName = '$skillName', groupID = '$groupID', rank=$rank, published=$published";
							
					//echo $sql;
							
					$dbres = $globalDb->query($sql);
					
					if ($dbres != 1)
					{
						do_log("Error in update_skilltree(): " . $globalDb->error . " - executing '$sql' ", 1);
						$error = true;
						break;
					}
				}
			}
			
			return !$error;
		}
		else
		{
			do_log("failed parsing skilltree " . $res['filename'], 1);
		}
	} else {
		do_log("failed downloading skilltree " . $res['filename'], 1);
	}
	
	return false;
}




/**
 * Import player assets IF they have a "normal" (non allied) api key
 */
function import_player_asset_data()
{
	global $member_time_diff, $globalDb, $SETTINGS;
	do_log("Entered import_player_asset_data",9);

	// run all of them at a time
	$sth = $globalDb->query("select p.user_id, p.keyid, p.vcode, p.access_mask 
		FROM player_api_keys p, auth_users u 
		WHERE
				p.state <=10 AND p.user_id = u.user_id AND u.pull_assets = 1 AND p.is_allied = 0
		ORDER BY p.state, p.user_id");
	
	if ($sth->num_rows > 0)
    {
		while($result=$sth->fetch_array()) 
		{
			// get all character IDs associated to the current user_id
			$sql2 = "SELECT character_id FROM api_characters WHERE key_id = " . $result['keyid'] . " AND user_id = " . $result['user_id'] . " AND state <= 10";
			$res2 = $globalDb->query($sql2);
			while ($row2 = $res2->fetch_array()) {
				update_player_assets($row2['character_id'], $result['keyid'], decrypt_vcode($result['vcode']));
			}			
		}
	} 
}




function import_player_api_characters()
{
	global $member_time_diff, $globalDb;
	do_log("Entered import_player_api_characters",9);

	// only do 50 at a time!, state > 0 has precedence
	$sth = $globalDb->query("select user_id, keyid, vcode from player_api_keys WHERE " .
				"state <=10 AND (last_checked = '0000-00-00 00:00:00' OR timestampdiff(minute,last_checked,now()) >= $member_time_diff OR state > 0) " .
				"order by last_checked, state, user_id LIMIT 50");
	
	if ($sth->num_rows > 0) {
		do_log("We have " . $sth->num_rows . " player api keys to be updated...", 1);
		echo "CRON: We have " . $sth->num_rows . " player api keys to be updated...\n";


		$sql = " INSERT INTO `wallet_journal` (
			`refID` ,`character_id` ,`date` ,`refTypeId` ,`ownerName1` ,`ownerID1` ,`ownerName2` ,`ownerID2` ,
			`argName1` ,`argID1` ,`amount` ,`reason` ,`taxReceiverID` ,`taxAmount` ,`owner1TypeID` ,`owner2TypeID`)
			VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dupli = dupli + 1;";

		// prepare statement for wallet journal
		if (! ($prepareStatementWalletJournal = $globalDb->prepare($sql)))
		{
			do_log("Error preparing statement $sql", 0);
			echo "Error preparing statement $sql\n";
			echo $globalDb->error;
			return;
		}

		$i = 0;
		while($result=$sth->fetch_array()) 
		{
			do_log("import_player_api_characters(): Key $i/" . $sth->num_rows, 9);
			import_individual_api_key($result['user_id'], $result['keyid'], decrypt_vcode($result['vcode']), $prepareStatementWalletJournal);
			$i++;
		}
	} else {
		do_log("no player api keys to update...", 5);
		echo "CRON: No player api keys to update.\n";
	}
	
	// check for API keys that got deleted but still have characters in our database
	// note: this should not happen because it prevents user from re-registering their characters
	$sql = "select uid, character_id, character_name, user_id, key_id FROM api_characters a WHERE a.key_id not in (SELECT keyid from player_api_keys)";
	
	// idealy this should never happen, but there is a chance for it to happen,
	// as Faidin from Infamy profed. Therefore, this little script is going to
	// be dedicated to him by calling it Faidin-Check.
	$res1 = $globalDb->query($sql);
	while ($row = $res1->fetch_array())
	{
		$uid = $row['uid'];
		$key_id = $row['key_id'];
		$user_id = $row['user_id'];
		$character_name = $row['character_name'];
		do_log("Faidin-Check: Deleting old char $character_name (user_id: $user_id, key_id: $key_id) because key $key_id does no longer exist", 1);
		$sql2 = "DELETE FROM api_characters WHERE uid = $uid";
		$globalDb->query($sql2);
	}
	
}



function check_public_sheet($char_id, $expected_name)
{
	$result = api_get_public_character_info($char_id);
	
	// check general status
	if ($result['status'] != 'OK')
		return false;
		
	
	// parse content
	$char_status = simplexml_load_string($result['data']);
	
	if ($char_status && $char_status->result->characterName == $expected_name)
		return true;
	
	
	return false;
}




// update a player api key
function import_individual_api_key($user_id, $key_id, $vcode, $wallet_journal_prepstmt)
{
	global $globalDb,$SETTINGS;
	do_log("Entered import_individual_api_key",9);
	// BigSako: don't use db_action that often, you are opening and closign the database connection like 1000 times, this costs a lot of time
	// open it once, close it once - done
	$mysqlidb = $globalDb;
	
	$time_start = microtime(true);
	
	
	if ($vcode == "")
	{
		echo "Decrypting vcode for key_id $key_id failed... skipping.\n";
		do_log("Error decrypting vcode for key_id $key_id - skipping", 1);
		return false;
	}
	
	
	// check key permissions and validity
	$key_details=api_get_key_permissions($key_id,$vcode);


    $member_api_key_accessmasks = explode(",",$SETTINGS["member_api_key_accessmask"]);
    $allied_access_mask = $SETTINGS["allied_api_key_accessmask"];

    $valid_access_masks = array_merge(array($allied_access_mask), $member_api_key_accessmasks);

	
	// check access mask
	if(! // if any of the things is not true (proper access mask, account context (not character)
        (
			in_array($key_details['mask'], $valid_access_masks) &&
            $key_details['context']=='Account' &&
            $key_details['unauthorized'] == false
        )
    )
    {
		// there was a problem getting key permission, either the access mask was wrong, the context is wrong or the key expired
		if ($key_details['status'] == 'Expired' || $key_details['status'] == 'Forbidden'
            || $key_details['status'] == 'Unauthorized' || $key_details['status'] == 'Authentication Failure'
            || $key_details['context'] == 'Character')
		{
			$msg = "Disabling API Key (key_id: $key_id, user_id: $user_id, status: " . $key_details['status']. ", mask: " .
                $key_details['mask'] . ", context: " . $key_details['context'] . ")";
			do_log($msg, 1);
			
			// disable this api key for now (state = 99)
			$mysqlidb->query("update player_api_keys set state=99, last_checked=now(), access_mask='" . $key_details['mask'] . "', last_status='" . $key_details['status'] . "' where keyid='$key_id'");
			$mysqlidb->query("update api_characters set state=99 where key_id='$key_id'");


			add_user_notification($user_id, "Your API Key with ID " . $key_id . " has been disabled (Reason: " .  $key_details['status'] . ")", 0, 1);


		} else {
			// temporary / unknown problem
			$msg = "There was a (temporary) problem contacting the API services (key_id: $key_id, user_id: $user_id, status: " .
                $key_details['status']. ", mask: " . $key_details['mask'] . ", context: " . $key_details['context'] . ")";
			
			do_log($msg, 1);
			echo $msg . "\n";
			
			// set them to state 2 = temporary problem
			$mysqlidb->query("update player_api_keys set state=2, last_checked=now(), access_mask='" . $key_details['mask'] . "', last_status='unknown' where keyid='$key_id'");
			$mysqlidb->query("update api_characters set state=2 where key_id='$key_id'");
		}

		return false;
	}

    $is_allied = 0;
    if ($key_details['mask'] == $allied_access_mask)
    {
        $is_allied = 1;
    }
	
	// set characters to state 1 = validating
    $sql = "update player_api_keys set state=1, is_allied=$is_allied, last_status='', access_mask='" . $key_details['mask'] . "' where keyid='$key_id'";
	if (!$mysqlidb->query($sql))
    {
        echo "MySQL Error; SQL = '$sql'\n Error: " . $mysqlidb->error . "\n";
    }
	$mysqlidb->query("update api_characters set state=1 where key_id='$key_id'");
	
	if($is_allied == 0) { // only get account status for non allied members (meaning: only for full members)
        // get account status and paid until
        $results = api_get_account_status($key_id, $vcode);

        if ($results['unauthorized'] == true) {
            do_log("CRON: Error accessing account status for key id $key_id: Permission denied.", 1);

            return false;
        }
        if ($results['status'] != 'OK') {
            do_log("CRON: Error accessing account status for key id $key_id: " . $results['status'], 1);

            return false;
        }

        $account_status = simplexml_load_string($results['data']);


        $paidUntil = $account_status->result->paidUntil;
        $logonMinutes = $account_status->result->logonMinutes;

        // set paidUntil and the access mask
        $mysqlidb->query("update player_api_keys set paidUntil='$paidUntil', logonMinutes=$logonMinutes where keyid='$key_id'");

        // archive logonMinutes for activity tracking
        $mysqlidb->query("INSERT INTO  player_logonMinutes (`keyID`, `logonMinutes`) VALUES ($key_id, $logonMinutes)");
    }
	
	// counter for the amount of characters that are training
	$char_training = 0;
	
	$apikeyxml = $key_details['xml'];
	
	
	foreach($apikeyxml->result->key->rowset->row as $row)
    {
		$character_id=intval($row['characterID']);
		$character_name=sanitise($row['characterName']);
		$corp_id=intval($row['corporationID']);
		$corp_name=sanitise($row['corporationName']);
		do_log("import_individual_api_key(): Processing $character_name ($character_id)",7);
		
		// insert into character history
		$sql = "insert into character_history (userid, character_id) VALUES ($user_id, $character_id) ON DUPLICATE KEY UPDATE last_seen=now()";
		$inres_res = $mysqlidb->query($sql);
		
		// insert just in case it's not there already
		$sql = "insert into api_characters " .
			"(`character_id`,`character_name`,`corp_id`,`corp_name`,`user_id`,`key_id`,`state`) values " .
			" ('$character_id','$character_name','$corp_id','$corp_name','$user_id','$key_id','0') 
			ON DUPLICATE KEY UPDATE state=0, character_name='$character_name', user_id=$user_id, key_id=$key_id "; // do not update corp_id here
			
			
		$ins_res = $mysqlidb->query($sql);
		
		if ($ins_res != 1)
		{
			echo "Error executing $sql\n";
			do_log("Error executing $sql\n", 1);
			continue;
		}

		// insert into character_system_log

        if ($is_allied == 0) { // only update full members beyond this point
            // get character sheet
            $results = api_get_character_sheet($key_id, $vcode, $character_id);

            if ($results['unauthorized'] == true) {
                echo "CRON: Error accessing character sheet for key id $key_id: Permission denied.\n";
                do_log("CRON: Error accessing character sheet for key id $key_id: Permission denied.", 1);
                continue;
            }

            if ($results['status'] != 'OK') {
                echo "CRON: Error loading character sheet for key id $key_id: API Problems - result was " . $results['status'] . ".\n";
                do_log("CRON: Error loading character sheet for key id $key_id: API Problems - result was " . $results['status'] . ".", 1);
                continue;
            }


            $charsheetxml = simplexml_load_string($results['data']);


            if (!$charsheetxml) {
                echo "Error parsing XML " . $results['filename'] . "... \n";
                do_log("Error parsing XML " . $results['filename'] . "... ", 1);
            } else {
                // get balance
                $balance = $charsheetxml->result->balance;

                // insert into balance log immediately
                // BigSako: disabling this, as we are not really using this anymore. Instead, we track all money movements
                // $mysqlidb->query("INSERT INTO walletBalance_log (character_id, walletBalance) VALUES ($character_id, $balance)");

                $cyno_skill = '0';

                // update skillsheet database
                //$mysqlidb->query("DELETE FROM char_skillsheet WHERE character_id = $character_id");

                if ($stmt = $mysqlidb->prepare("INSERT INTO char_skillsheet (typeID, character_id, level, skillpoints) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE level=?, skillpoints=?")) {

                    // parse skillsheet
                    $skill_rows = $charsheetxml->xpath('/eveapi/result/rowset[@name="skills"]')[0];
                    //$skill_rows=$charsheetxml->result->rowset[0];
                    foreach ($skill_rows as $skill) {
                        $typeID = intval($skill['typeID']);
                        if ($typeID == 164) // clone grade alpha
                            continue;

                        $level = $skill['level'];
                        $sp = $skill['skillpoints'];

                        // check for cyno skill
                        if ($typeID == 21603) {
                            $cyno_skill = $skill['level'];
                        }

                        $stmt->bind_param("iiiiii", $typeID, $character_id, $level, $sp, $level, $sp);
                        $stmt->execute();

                        if ($stmt->affected_rows != 1) {
                            if ($mysqlidb->error != "") {
                                echo "Error: insert into char_skillsheet probably failed: " . $mysqlidb->error . "\n";
                                do_log("Error: insert into char_skillsheet probably failed (character_id=$character_id, typeID = $typeID, level=$level, sp=$sp): " . $mysqlidb->error, 1);
                            }
                        }

                    }

                } else {
                    do_log("error: couldn't prepare statement for char_skillsheet", 1);
                    echo "error: couldn't prepare statement for char_skillsheet\n";
                }


                // get skill in training
                $skil_results = api_get_skill_in_training($key_id, $vcode, $character_id);
                $skill_xml = simplexml_load_string($skil_results['data']);


                $skill_rows = $skill_xml->result;

                // clear skill queue
                $mysqlidb->query("DELETE FROM skill_queue WHERE character_id = $character_id");

                if ($skill_rows->skillInTraining == '1') {
                    do_log("Character $character_name is currently training", 8);

                    $char_training++;

                    // okay, we know there is a skill training, so query the skill queue and see how many skills are training and when it will finish
                    $queue_result = api_get_skill_queue($key_id, $vcode, $character_id);
                    if ($queue_result['status'] == 'OK') {
                        $queue_xml = simplexml_load_string($queue_result['data']);

                        if (!$queue_xml) {
                            do_log("There was an XML error trying to load " . $queue_result['filename'] . ", skipping skill things for this character.", 1);
                        } else {
                            $qrows = $queue_xml->result->rowset[0];

                            if (!$queue_xml->result->rowset[0]) {
                                do_log("possibly problem: skill queue rowset does not exist!", 1);
                            }

                            do_log("Skill-Queue:", 8);

                            $last_skill_endtime = "";

                            foreach ($qrows as $qrow) {
                                $typeID = $qrow['typeID'];
                                $level = $qrow['level'];
                                $endTime = $qrow['endTime'];
                                $position = $qrow['queuePosition'];

                                do_log("typeID=$typeID, level=$level, endTime=$endTime", 8);

                                // YYYY-MM-DD HH:MM:SS
                                $last_skill_endtime = $endTime;
                                $mysqlidb->query("INSERT INTO skill_queue(character_id,position,typeID, level, endTime) VALUES ($character_id,$position,$typeID, $level, '$endTime')");
                            }

                            $mysqlidb->query("update api_characters SET training_active=1,skill_endtime='" . $last_skill_endtime . "', " .
                                "skill_to_level=" . $skill_rows->trainingToLevel . ", skill_id_training=" . $skill_rows->trainingTypeID . " WHERE character_id='$character_id' ");

                        }
                    } else {
                        do_log("There was an XML error trying to load " . $queue_result['filename'] . ", skipping skill things for this character.", 1);
                    }

                } else {
                    $mysqlidb->query("update api_characters SET training_active=0,skill_endtime=0,skill_to_level=0,skill_id_training=0 WHERE character_id='$character_id' ");
                }


                // check if this CHARACTER is a director
                $director_count = 0;
                $roles_rowset = $charsheetxml->xpath('/eveapi/result/rowset[@name="corporationRoles"]')[0];
                foreach ($roles_rowset as $roles_row) {
                    if ($roles_row['roleID'] == '1') {
                        $director_count = 1;
                        break; // no need to go any further
                    }
                }

                // update jump fatigue, cyno skills, director, etc...
                $jumpActivation = $mysqlidb->real_escape_string($charsheetxml->result->jumpActivation);
                $jumpFatigue = $mysqlidb->real_escape_string($charsheetxml->result->jumpFatigue);
                $jumpLastUpdate = $mysqlidb->real_escape_string($charsheetxml->result->jumpLastUpdate);
				
				$cloneJumpDate = $mysqlidb->real_escape_string($charsheetxml->result->cloneJumpDate);
				$homeStationID = intval($charsheetxml->result->homeStationID);
				$freeSkillPoints = intval($charsheetxml->result->freeSkillPoints);
				
				
				$tmpsql = "update api_characters set 
                	jumpActivation='$jumpActivation', jumpFatigue='$jumpFatigue', jumpLastUpdate='$jumpLastUpdate',
                	cyno_skill='$cyno_skill', walletBalance='$balance', is_director='$director_count', homeStationID = $homeStationID, freeSP = $freeSkillPoints
                    WHERE character_id='$character_id'";

                $tmpres = $mysqlidb->query($tmpsql);
				if (!$tmpres)
				{
					echo "Error executing query $sqltmp\n" . $mysqlidb->error . "\n";
				}


                $mysqlidb->query("update api_titles set prev_state = state WHERE user_id = $character_id");
                $mysqlidb->query("update api_titles set state = '1' WHERE user_id = $character_id");

                // get corp titles
                $titles_rows = $charsheetxml->xpath('/eveapi/result/rowset[@name="corporationTitles"]')[0];
                foreach ($titles_rows as $title_row) {
                    $row = $title_row->attributes();
                    $titleID = intval($row['titleID']);
                    $titleName = sanitise($row['titleName']);

                    do_log("title thing: $titleID , $titleName", 9);
                    $mysqlidb->query("insert into `api_titles` (`user_id`,`title_id`, `state`, `prev_state`) VALUES " .
                        "($character_id, $titleID, 0, 0) on duplicate key update state='0'");
                }

                // delete remaining titles
                $mysqlidb->query("delete from api_titles WHERE state='1' ");


                // try to get api character info
                $results = api_get_character_info($key_id, $vcode, $character_id);
                if ($results['status'] == 'OK') {
                    $charinfoxml = simplexml_load_string($results['data']);

                    $skillpoints = $charinfoxml->result->skillPoints;

                    $ship_type = $mysqlidb->real_escape_string($charinfoxml->result->shipTypeName); // BigSako - sanitise this just in case
                    $location = $mysqlidb->real_escape_string($charinfoxml->result->lastKnownLocation); // BigSako - sanitise this just in case...
                    $securityStatus = doubleval($charinfoxml->result->securityStatus);
                    
                    $mysqlidb->query("update api_characters set 
                    	skillpoints = $skillpoints, character_location='$location', 
                    	character_last_ship='$ship_type',
                    	sec_status=$securityStatus
                    	where character_id='$character_id'");


                    // get the last location of this character
                    $sql3 = "SELECT first_seen, location FROM character_system_log WHERE character_Id=$character_id ORDER BY last_seen DESC LIMIT 1";
                    $res3 = $mysqlidb->query($sql3);
                    $last_known_location = "";
                    $first_seen = 0;
                    if ($res3->num_rows == 1)
                    {
                    	$locrow = $res3->fetch_array();
                    	$last_known_location = $locrow['location'];
                    	$first_seen = $locrow['first_seen'];
                    }

                    // if location has changed, insert it into system_log
                    if ($last_known_location != $location)
                    {
						$sql3 = "INSERT INTO character_system_log (character_id, first_seen, location, ship, last_seen) 
						VALUES
						($character_id, now(), '$location', '$ship_type', now())
						";
						$res3 = $mysqlidb->query($sql3);
					} else {
						$sql3 = "UPDATE character_system_log SET last_seen=now() WHERE character_id=$character_id AND first_seen='$first_seen'";
						$res3 = $mysqlidb->query($sql3);
					}

					if (!$res3)
					{
						echo "Error doing '$sql3': " . $mysqlidb->error . "\n";
					}

                } // else - not a big deal, didnt get last ship type name and last known location, so let's just set them to unknown.
                else {
                    $mysqlidb->query("update api_characters set character_location='API Error', character_last_ship='API Error' where character_id='$character_id'");
                }

                // update wallet data
                // first: check if there is any wallet data, and if there is, select the max refID
                $sql = "SELECT COUNT( * ) as c, max( refID ) as b FROM `wallet_journal` WHERE character_Id = $character_id";
                $resw = $mysqlidb->query($sql);
                $roww = $resw->fetch_array();

                $max_ref_id = -1;

                if ($roww['c'] != 0) {
                    $max_ref_id = $roww['b'];
                    //echo "max_ref_id = $max_ref_id ";
                }

                // store last ref id
                $last_refID = -1;
                $stopped = false;

                do_log("Going to retrieve wallet data for character $character_id", 2);

                while ($stopped == false) {
                    $results = api_get_walletjournal($key_id, $vcode, $character_id, $last_refID);
                    if ($results['status'] == 'OK') {
                        $walletjournal = simplexml_load_string($results['data']);


                        // parse wallet journal
                        $wallet_rows = $walletjournal->result->rowset[0];
                        //echo "Array Size: " . count($wallet_rows);
                        // check if anything is in the rowset, if not, we are done
                        if (count($wallet_rows) == 0) {
                            $stopped = true;
                            break;
                        }

                        foreach ($wallet_rows as $wallet_row) {
                            $refID = $wallet_row['refID'];

                            if ($refID == $max_ref_id) {
                                // already in database, we can stop now
                                $stopped = true;
                                break;
                            }


                            // else: not yet in database, let's continue
                            $date = $wallet_row['date'];
                            $refTypeID = intval($wallet_row['refTypeID']);
                            $ownerName1 = sanitise($wallet_row['ownerName1']);
                            $ownerID1 = intval($wallet_row['ownerID1']);
                            $ownerName2 = sanitise($wallet_row['ownerName2']);
                            $ownerID2 = intval($wallet_row['ownerID2']);
                            $argName1 = sanitise($wallet_row['argName1']);
                            $argID1 = intval($wallet_row['argID1']);
                            $amount = sanitise($wallet_row['amount']);
                            $balance = sanitise($wallet_row['balance']);
                            $reason = sanitise($wallet_row['reason']);
                            $taxReceiverID = sanitise($wallet_row['taxReceiverID']);
                            $taxAmount = sanitise($wallet_row['taxAmount']);
                            $owner1TYpeID = sanitise($wallet_row['owner1TypeID']);
                            $owner2TYpeID = sanitise($wallet_row['owner2TypeID']);


                            // insert this into database
                            $wallet_journal_prepstmt->bind_param("ssssssssssssssss", $refID, $character_id, $date,
                                $refTypeID, $ownerName1, $ownerID1, $ownerName2, $ownerID2, $argName1, $argID1, $amount,
                                $reason, $taxReceiverID, $taxAmount, $owner1TYpeID, $owner2TYpeID
                            );

                            if (!$wallet_journal_prepstmt->execute()) {
                                echo "Failed to execute prepared statement for wallet journal for character $character_id \n";
                                echo "MySQL Error: " . $mysqlidb->error . "\n";
                                echo "Prepared Statement Error: " . $wallet_journal_prepstmt->error . "\n";
                                echo "Prepared Statement Parameters: refId=$refID, charId=$character_id, date=$date,
                                refTypeId=$refTypeID, ownerName1=$ownerName1, ownerID1=$ownerID1, ownerName2=$ownerName2, ownerID2=$ownerID2, argName1=$argName1, argID1=$argID1, amount=$amount,
                                reason=$reason, taxReceiverID=$taxReceiverID, taxAmount=$taxAmount, owner1TypeID=$owner1TYpeID, owner2TypeID=$owner2TYpeID \n";
                                return;
                            }

                            $last_refID = $refID;
                        }
                    } else {
                        do_log("Error getting wallet journal data from character $character_id (keyid: $key_id)", 1);
                    }
                }

                $mysqlidb->query("update api_characters set state=0, last_update=now() where character_id='$character_id'");
            }
        }
	}
	$mysqlidb->query("update api_characters set state=99, last_update=now() where state=1 and key_id='$key_id'");
	$mysqlidb->query("update player_api_keys set state=0, char_training=$char_training, last_checked=now() where keyid='$key_id'");
	
	
	$time_end = microtime(true);
	$time = $time_end - $time_start;

	//echo "Processed key in $time seconds\n";

}


/** marry_corp_members() is called as a cronjob
and connects the corp_members table with the api_characters table.
basically every character that is in api_characters and in the corp in corp_members will be flagged with 0,
everybody that is missing with a 1 */
function marry_corp_members()
{
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	$sth = $mysqli->query("update corp_members set state=11 WHERE state=0");
	$sth = $mysqli->query("update corp_members a, api_characters b set a.state=0 where a.character_id=b.character_id and b.state<=10");
	$sth = $mysqli->query("update corp_members set state=98 where state=11");
}




function get_all_group_names()
{
	global $globalDb;
	
	$res = $globalDb->query("SELECT group_id, group_name FROM groups ORDER BY group_id");
	
	$arr = array();
	
	while ($row = $res->fetch_array())
	{
		$arr[ $globalDb->real_escape_string($row['group_name']) ] = $row['group_id'];
	}
	
	return $arr;
}


function get_all_auto_group_names()
{
	global $globalDb;
	
	$res = $globalDb->query("SELECT group_id, group_name FROM groups WHERE autoGenerated = 1 ORDER BY group_id");
	
	$arr = array();
	
	while ($row = $res->fetch_array())
	{
		$arr[ $globalDb->real_escape_string($row['group_name']) ] = $row['group_id'];
	}
	
	return $arr;
}



function bulk_update_market()
{
	global $globalDb;
	$timeDiff = 60*24; // once per day
	$res = $globalDb->query("SELECT type_id, region_id, last_update FROM prices WHERE last_update is NULL OR TIMESTAMPDIFF(MINUTE, last_update, NOW()) > $timeDiff");

	$typeIds = array();
	$regionId = 0;
	while ($row = $res->fetch_array())
	{
		$typeIds[] = $row['type_id'];
		$regionId = $row['region_id'];		
	}

	$csvChunks = array_chunk($typeIds, 100);
	foreach ($csvChunks as $chunk)
	{
		$csvIds = implode($chunk, ",");
		bulk_request_price_from_api($csvIds, $regionId);
	}
}



function bulk_update_characters()
{
	global $globalDb;
	
	// use character affiliation api call, e.g. https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=349190414,94936849,94105123
	// therefore we need to select about 200 IDs and provide them with a post request to the eve api
	// we do this 200 by 200, so first, query the IDs
	
	$sql = "SELECT character_id FROM api_characters";
	$res = $globalDb->query($sql);
	
	$character_ids = array();
	
	$cnt = 0;
	
	while ($row = $res->fetch_array())
	{
		$character_ids[] = $row['character_id'];		
		$cnt++;
	}
	
	// prepare a statement
	$sql = "UPDATE api_characters SET character_name=?, corp_id=?, corp_name=? WHERE character_id=?";
	if (! ($stmt = $globalDb->prepare($sql)))
	{
		do_log("Error preparing statement $sql", 0);
        do_log($globalDb->error, 0);
		echo "Error preparing statement $sql\n";
		echo $globalDb->error;
        return;
	}
		
		
	
	$csvChunks = array_chunk($character_ids, 200);
	// collect chunks and query api
	foreach ($csvChunks as $chunk)
	{
		$csvIds = implode($chunk, ",");
		$res = api_get_character_affiliation($csvIds);
		$xml_res = simplexml_load_file($res['filename']);
		$list=$xml_res->result->rowset[0];
	
		foreach ($list as $characterRow)
		{
			$character_id = intval($characterRow['characterID']);
			$character_name = $characterRow['characterName'];
			$alliance_id = intval($characterRow['allianceID']);
			$corporation_id = intval($characterRow['corporationID']);
			$corporation_name = $characterRow['corporationName'];
			
			
			// insert this into database
			$stmt->bind_param("sisi", $character_name, $corporation_id, $corporation_name, $character_id);
			if (!$stmt->execute())
			{
				echo "Failed to execute prepared statement for data: $character_id,$character_name,$alliance_id,$corporation_id,$corporation_name \n";
				echo $globalDb->error . "\n";
				echo $stmt->error . "\n";
				return;
			}
		}
		
	}
}




// rebuild validated members membership
function rebuild_members()
{
	global $globalDb;
	
	do_log("Rebuilding 'validated members' group membership",1);
	
	$validated_members_groupid=1; // validated member group
	
	$groups = get_all_auto_group_names();
	// add group 1 to its
	$groups[] = $validated_members_groupid;
	
	// compile auto_generated group IDs as a string
	$auto_group_ids = implode(",", $groups);
	
	do_log("Auto_group_ids=$auto_group_ids", 1);

	// set group membership previous state to state, and then state to 1, indicating we are working in this groups right now
	$globalDb->query("update group_membership set previous_state=state WHERE
		group_id IN ($auto_group_ids)");
	$globalDb->query("update group_membership set state=1 where group_id IN ($auto_group_ids) ");
	
	// update group membership for all auto generated groups


	// query all characters with api state = 0, only these must be reactivated
	$sth = $globalDb->query("SELECT a.user_id, a.user_name, b.corp_id, c.corp_name, d.alliance_name,
c.is_allowed_to_reg as corp_allowed_reg, c.is_allied as corp_allied,
d.is_allowed_to_reg as alliance_allowed_reg,  d.is_allied as alliance_allied,
p.is_allied as key_allied
FROM auth_users a, api_characters b, corporations c, alliances d, player_api_keys p
WHERE b.state <=2
AND a.user_id = b.user_id AND p.user_id = a.user_id
AND (d.alliance_id = c.alliance_id)
AND c.corp_id = b.corp_id
AND (c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1 or c.is_allied = 1 or d.is_allied=1)
");
    echo "We have " . $sth->num_rows . " rows to process for membership...\n";

	while($result=$sth->fetch_array())
    {
		$user_id=$result['user_id'];
        $key_allied = $result['key_allied']; // is this a key of an ally?
        $corp_allied = $result['corp_allied']; // is this corp allied?
        $alliance_allied = $result['alliance_allied']; // is this alliance allied

        $sql = "insert into group_membership (`group_id`,`user_id`,`state`) values " .
            "('$validated_members_groupid','$user_id',0) on duplicate key update state='0'";
		
		// valid member, so insert into group_membership or update and set state to 0
		if (!$globalDb->query($sql))
        {
            echo "SQL='$sql' failed ...\n" . $globalDb->error . "\n";
        }
				
		// check if there is a group with the corp_name
		$corp_name = $globalDb->real_escape_string($result['corp_name']);

        // if it is an allied key, it must match corp_allied
        if ($corp_allied == 1 || $key_allied == $corp_allied)
        {
            if (array_key_exists($corp_name, $groups)) {
                $corp_group_id = $groups[$corp_name];
                $globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`) values " .
                    "('$corp_group_id','$user_id',0) on duplicate key update state='0'");
            } else {
				// there is no auto-generated corp, this is probably a corp of an allied alliance, so we dont bother
                //echo "Warning: Corp_allied == key_allied, but could not find corp_name '$corp_name' in groups\n";
                //print_r($groups);
            }
        }


        if ($alliance_allied == 1 || $key_allied == $alliance_allied)
        {
            // check if there is a group with the alliance_name
            $alliance_name = "Alliance " . $globalDb->real_escape_string($result['alliance_name']);

            if (array_key_exists($alliance_name, $groups)) {
                $alliance_group_id = $groups[$alliance_name];
                $globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`) values " .
                    "('$alliance_group_id','$user_id',0) on duplicate key update state='0'");
            }
        }
		
		
	}
	
	// get all users with validated member group not working anymore, they need to be removed from any group
	$sql = "SELECT user_id FROM group_membership WHERE state=1 and group_id='$validated_members_groupid' ";
	$res = $globalDb->query($sql);
	
	while ($row = $res->fetch_array())
	{
		// set state to 99
		$userid = $row['user_id'];
		echo "Removing user $userid from all groups, as no longer a valid member...\n";
		do_log("Removing user $userid from all groups because he is no longer a valid member ",1);
		
		$globalDb->query("update group_membership set state=99 where user_id = $userid");
	}
	
	// get all users that we set to state=1, they need to be removed from that group
	$sql = "SELECT user_id, group_id FROM group_membership WHERE state=1 ";
	$res = $globalDb->query($sql);
	
	while ($row = $res->fetch_array())
	{
		// set state to 99 
		$userid = $row['user_id'];
		$group_id = $row['group_id'];
		do_log("Removing user $userid from group $group_id because he is no longer valid.",1);

		add_user_notification($userid, "You have been automatically removed from group $group_id.", 0);

		
		$globalDb->query("update group_membership set state=99 where 
					group_id = $group_id AND user_id = $userid");
	}
}





// rebuild director group membership
function rebuild_directors()
{
	global $globalDb;
	
	do_log("Rebuilding 'directors' group membership",1);
	$group=6;

        // set group membership previous state to state, and then state to 1, indicating we are working in this groups right now
	$globalDb->query("update group_membership set previous_state=state where group_id='$group'");
	$globalDb->query("update group_membership set state=1 where group_id='$group'");


	// now check all active apis and directors
	// ONLY select characters from corporations that are allowed to reg (NOT ALLIED)
	// else we will have foreign directors being able to register and getting director roles automatically *facepalm*
	$sth=$globalDb->query("SELECT a.user_id
FROM auth_users a, api_characters b, corporations c, alliances d
WHERE b.state <=2
AND a.user_id = b.user_id AND b.is_director >= 1 
AND (d.alliance_id = c.alliance_id)
AND c.corp_id = b.corp_id
AND (c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1)
GROUP BY a.user_id
");
		

	while($result=$sth->fetch_array()) {
		$user_id=$result['user_id'];
		do_log("Starting group chain investigation for user id $user_id against group $group",9);
		// check group chain
		$chain=check_group_chain($group,$user_id);
		if($chain==0) {
			$globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`, `previous_state`) values " .
				"('$group','$user_id',3, 99) on duplicate key update state=previous_state");
			
		}
		else
		{
			do_log("Error: Group chain for $user_id against group $group said no...", 9);
		}
	}
	
	// if they were not confirmed, they need to set to state=99
	$globalDb->query("update group_membership set state=99 where state='1' and group_id='$group'");
}





// rebuild director group membership
function rebuild_ceo()
{
	global $globalDb;
	
	do_log("Rebuilding 'ceo' group membership",1);		
	$group=50;
	
	// first of all, reset all is_ceo fields
	$globalDb->query("update api_characters set is_ceo='0' ");
	// now query corporations table for all ceos
	$globalDb->query(" UPDATE api_characters a, corporations c set a.is_ceo='1' WHERE a.character_id = c.ceo AND a.corp_id =  c.corp_id ");

    // set group membership previous state to state, and then state to 1, indicating we are working in this groups right now
	$globalDb->query("update group_membership set previous_state=state where group_id='$group'");
	$globalDb->query("update group_membership set state=1 where group_id='$group'");


	// now check all active apis and directors
	// ONLY select characters from corporations that are allowed to reg
	// else we will have foreign directors being able to register and getting director roles automatically *facepalm*
	$sth=$globalDb->query("SELECT a.user_id, b.corp_id, b.corp_name, d.alliance_name
FROM auth_users a, api_characters b, corporations c, alliances d
WHERE b.state <=2 AND b.is_ceo = 1
AND a.user_id = b.user_id 
AND (d.alliance_id = c.alliance_id)
AND c.corp_id = b.corp_id
AND (c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1)
");
		

	while($result=$sth->fetch_array()) {
		$user_id=$result['user_id'];
		do_log("Starting group chain investigation for user id $user_id against group $group",9);
		// check group chain
		$chain=check_group_chain($group,$user_id);
		if($chain==0) {
			$globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`) values " .
				"('$group','$user_id',0) on duplicate key update state='0'");
				
			// check if toon is CEO
			
		}
	}
	
	// if they were not confirmed, they need to set to state=99
	$globalDb->query("update group_membership set state=99 where state='1' and group_id='$group'");
}





// rebuild titan group membership
function rebuild_titans()
{
	global $globalDb;
	
	do_log("Rebuilding 'titan' group membership",1);
	$group=3;
	$globalDb->query("update group_membership set previous_state=state where group_id='$group'");
	$globalDb->query("update group_membership set state=1 where group_id='$group'");
	
	
	$sth=$globalDb->query("SELECT a.user_id, b.corp_id, b.corp_name, d.alliance_name
FROM auth_users a, api_characters b, corporations c, alliances d
WHERE b.state <=2 AND b.character_last_ship in ('Avatar','Erebus','Leviathan','Ragnarok')
AND a.user_id = b.user_id 
AND (d.alliance_id = c.alliance_id)
AND c.corp_id = b.corp_id
AND (c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1)
");
	
	
	while($result=$sth->fetch_array()) {
		$user_id=$result['user_id'];
		do_log("Starting group chain investigation for user id $user_id against group $group",9);
		$chain=check_group_chain($group,$user_id);
		if($chain==0) {
			$globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`) values ('$group','$user_id',0) on duplicate key update state='0'");
			}
	}
	$globalDb->query("update group_membership set state=99 where state='1' and group_id='$group'");
}

// rebuild supercarrier group member ship
function rebuild_supercarriers()
{
	global $globalDb;
	
	do_log("Rebuilding 'supercarriers' group membership",1);
	$group=4;
	$globalDb->query("update group_membership set previous_state=state where group_id='$group'");
	$globalDb->query("update group_membership set state=1 where group_id='$group'");
	
	$sth=$globalDb->query("SELECT a.user_id, b.corp_id, b.corp_name, d.alliance_name
FROM auth_users a, api_characters b, corporations c, alliances d
WHERE b.state <=2 AND b.character_last_ship in ('Aeon','Hel','Nyx','Wyvern','Revenant')
AND a.user_id = b.user_id 
AND (d.alliance_id = c.alliance_id)
AND c.corp_id = b.corp_id
AND (c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1)
");
	
	
	while($result=$sth->fetch_array()) {
		$user_id=$result['user_id'];
		do_log("Starting group chain investigation for user id $user_id against group $group",9);
		$chain=check_group_chain($group,$user_id);
		if($chain==0) {
			$globalDb->query("insert into group_membership (`group_id`,`user_id`,`state`) values " .
				"('$group','$user_id',0) on duplicate key update state='0'");
		}
	}
	$globalDb->query("update group_membership set state=99 where state=1 and group_id='$group'");	
}





?>
