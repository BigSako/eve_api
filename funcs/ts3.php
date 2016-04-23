<?php


$global_ts3_virtualserver = false;

function connectToTS3Query()
{
    global $global_ts3_virtualserver,$SETTINGS;

    if (!$global_ts3_virtualserver)
    {
        $queryAdmin = $SETTINGS['ts3_serveradmin'];
        $queryPassword = $SETTINGS['ts3_queryPassword'];
        $local = $SETTINGS['ts3_local'];
        $qport = $SETTINGS['ts3_query_port'];

        $global_ts3_virtualserver = TeamSpeak3::factory("serverquery://$queryAdmin:$queryPassword@$local:$qport/?server_port=$SETTINGS[ts3_port]");
    }

    return $global_ts3_virtualserver;
}



function remove_all_ts_permissions($nickname)
{
	global $SETTINGS;
	

	$queryAdmin = $SETTINGS['ts3_serveradmin'];
	$queryPassword = $SETTINGS['ts3_queryPassword'];
	$local = $SETTINGS['ts3_local'];
	$qport = $SETTINGS['ts3_query_port'];
	
	$group_super_admin_id = -1;
	
	if (isset($SETTINGS['ts3_super_admin_group_id']))
	{
		$group_super_admin_id = intval($SETTINGS['ts3_super_admin_group_id']);
	}
	
	
	try {
		$ts3_VirtualServer = connectToTS3Query();
	} catch (TeamSpeak3_Exception $e) {
		return -1;
		//die("An error occured: ".$e->getMessage()." [F".__LINE__."]");
	}
	try {
		$tsClient = $ts3_VirtualServer->clientGetByName($nickname); // $tsClient reduces strain on the server
		$tsDatabaseID = $tsClient->client_database_id;
		$tsUniqueID = $tsClient->client_unique_identifier;
		//echo "<strong>Debug:</strong> Database ID: ".$tsDatabaseID."<br /><strong>Debug:</strong> Unique ID: ".$tsUniqueID."<br />";
		
	} catch (TeamSpeak3_Exception $e) {
		//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
		return -2;
	}
	
	
	try
	{
		$curGroups = explode(',', $tsClient->getProperty("client_servergroups"));
		
		// try setting user group
		for ($i = 0; $i < count($curGroups); $i++)
		{				
			$groupId = $curGroups[$i];
			if ($groupId != 8 && $groupId != $group_super_admin_id)
			{
				$tsClient->remServerGroup($groupId);
			}
		}
	
	} catch (TeamSpeak3_Exception $e) {
		//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
		return -3;
	}		
}




function ts3_setgroups($nickname, $newGroups, $clearGroups, $ts3_VirtualServer=0, $msg="")
{
	global $SETTINGS;

    // fix: make sure groups is unique
    $newGroups = array_unique($newGroups);

	//$queryAdmin = "serveradmin";
	//$queryPassword = "H4NxoIgV";
	$queryAdmin = $SETTINGS['ts3_serveradmin'];
	$queryPassword = $SETTINGS['ts3_queryPassword'];
	$local = $SETTINGS['ts3_local'];
	$qport = $SETTINGS['ts3_query_port'];
	
	$group_super_admin_id = -1;
	
	if (isset($SETTINGS['ts3_super_admin_group_id']))
	{
		$group_super_admin_id = intval($SETTINGS['ts3_super_admin_group_id']);
	}
	
	

    //echo "setting groups for nickname='$nickname'\n";
    //print_r($newGroups);
		
		
		try {
            if ($ts3_VirtualServer == 0) {
                $ts3_VirtualServer = connectToTS3Query();
            }
		} catch (TeamSpeak3_Exception $e) {
			return -1;
			//die("An error occured: ".$e->getMessage()." [F".__LINE__."]");
		}
		try {
			$tsClient = $ts3_VirtualServer->clientGetByName($nickname); // $tsClient reduces strain on the server
			$tsDatabaseID = $tsClient->client_database_id;
			$tsUniqueID = $tsClient->client_unique_identifier;
			//echo "<strong>Debug:</strong> Database ID: ".$tsDatabaseID."<br /><strong>Debug:</strong> Unique ID: ".$tsUniqueID."<br />";
			
		} catch (TeamSpeak3_Exception $e) {
			//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
			return -2;
		}

        $curGroups = explode(',', $tsClient->getProperty("client_servergroups"));
		
		if ($clearGroups == true) {
			try
			{
				//echo "groups = " . $tsClient->getProperty("client_servergroups");

				// try setting user group
				for ($i = 0; $i < count($curGroups); $i++)
				{				
					$groupId = $curGroups[$i];
					if ($groupId != $SETTINGS['ts3_guest_id'] && $groupId != $group_super_admin_id)
					{
						$tsClient->remServerGroup($groupId);
					}
				}
			
			} catch (TeamSpeak3_Exception $e) {
				//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
				//echo $e->getMessage();
				return -3;
			}
		}
		
	
		
		// try setting user group
		for ($i = 0; $i < count($newGroups); $i++)
		{
			$groupId = $newGroups[$i];
            // only apply group if it's not already applied
            if (!in_array($groupId, $curGroups) && $groupId != $group_super_admin_id)
			    $tsClient->addServerGroup($groupId);
		}

        if ($msg != "")
        {
            $tsClient->message("Hi! From now on I know you as $nickname! Please do not rename yourself on this Teamspeak server, or you will be kicked.");
        }
		
		return $tsDatabaseID;
}


function ts3_is_guest($ts3_VirtualServer, $tsClient)
{
	try
	{
		$curGroups = explode(',', $tsClient->getProperty("client_servergroups"));
		
		if (in_array(8, $curGroups) && count($curGroups) == 1)
		{
			return true;
		}		
	
	} catch (TeamSpeak3_Exception $e) {
		//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
		return false;
	}
	
	return false;
}



// remove all ts3 groups from a selected tsclient except for the guest group and any temp groups
function ts3_remove_all_groups($ts3_VirtualServer, $tsClient)
{
	global $SETTINGS;

	if (isset($SETTINGS['ts3_temp_groups']))
		$temp_groups = explode(',',$SETTINGS['ts3_temp_groups']);

	try
	{
		$curGroups = explode(',', $tsClient->getProperty("client_servergroups"));
		
		// try setting user group
		for ($i = 0; $i < count($curGroups); $i++)
		{				
			$groupId = $curGroups[$i];
			// remove everything that's not the ts3_guest_id and that is not in temp_groups (e.g. allies, channel command, ...)
			if ($groupId != $SETTINGS['ts3_guest_id'] && !in_array($groupId, $temp_groups))
			{
				$tsClient->remServerGroup($groupId);
			}
		}
	
	} catch (TeamSpeak3_Exception $e) {
		return false;
	}
}



function ts3_sync_groups($ts3_VirtualServer, $tsClient, $cur_ts_groups, $target_ts_groups)
{
	global $SETTINGS;
	
	$temp_groups = array();
	
	if (isset($SETTINGS['ts3_temp_groups']))
		$temp_groups = explode(',',$SETTINGS['ts3_temp_groups']);
	
	try
	{		
		// check if all the groups the user is currently in are still valid
		for ($i = 0; $i < count($cur_ts_groups); $i++)
		{				
			$groupId = $cur_ts_groups[$i];
			
			// if not, needs to be removed
			if (!in_array($groupId, $target_ts_groups) && $groupId != $SETTINGS['ts3_guest_id'] && !in_array($groupId, $temp_groups))
			{
				$tsClient->remServerGroup($groupId);
			}
		}
		
		// check if the user has all the groups he should be in
		for ($i = 0; $i < count($target_ts_groups); $i++)
		{				
			$groupId = $target_ts_groups[$i];
			
			// if not, needs to be added
			if (!in_array($groupId, $cur_ts_groups) && $groupId != $SETTINGS['ts3_guest_id'])
			{
				$tsClient->addServerGroup($groupId);
			}
		}
	
	} catch (TeamSpeak3_Exception $e) {
		return false;
	}
}





function check_ts3_user_names()
{
	global $SETTINGS;

    $main_url = $SETTINGS['base_url'];
	
	$db = connectToDB();
	
	//$queryAdmin = "serveradmin";
	//$queryPassword = "H4NxoIgV";
	$queryAdmin = $SETTINGS['ts3_serveradmin'];
	$queryPassword = $SETTINGS['ts3_queryPassword'];
	$local = $SETTINGS['ts3_local'];
	$qport = $SETTINGS['ts3_query_port'];
				
	try {
		$ts3_VirtualServer = connectToTS3Query();
	} catch (TeamSpeak3_Exception $e) {
        echo "An error occured with TEamspeak: ".$e->getMessage();
        return -1;
	}
	
	$arr_ClientList = $ts3_VirtualServer->clientList();
	
	try 
	{
	
		foreach($arr_ClientList as $ts3_Client)
		{
			// skip query clients
			if($ts3_Client["client_type"]) continue;
			
			echo $ts3_Client;
			$ts_client_groups = $ts3_Client["client_servergroups"];
			
			// explode into array
			$ts_client_groups = explode(',', $ts_client_groups);
			
			$nickname = $ts3_Client["client_nickname"];
			
			// see if nickname is registered and the UID is right too
			$tsDatabaseID = $ts3_Client->client_database_id;
			$tsUniqueID = $ts3_Client->client_unique_identifier;




            $ts_user_ip_addr = "";
			
			$sql = "SELECT has_regged_api, has_regged_main, user_name, ts3_user_id, ts3_user_name, user_id, state FROM auth_users
					WHERE ts3_user_id = $tsDatabaseID";
			
			$res = $db->query($sql);
			if ($res->num_rows == 0)
			{
				// user not found, needs to be unregistered immediately (= all groups removed)
				echo "Removing all groups from teamspeak user $nickname because he is not registered with our database.\n";
				ts3_remove_all_groups($ts3_VirtualServer, $ts3_Client);
				$ts3_Client->message("Sorry, I could not find you in my database. Please register on http://" . $main_url . " !");
				
			} else {
				$row = $res->fetch_array();
				$existing_main = $row['has_regged_main'];
				$user_id = $row['user_id'];
				// get expected nickname
				$sql = "SELECT c.corp_name, c.corp_ticker, a.character_name 
					FROM api_characters a, corporations c, alliances d
							WHERE a.character_id = $existing_main AND a.corp_id = c.corp_id AND c.alliance_id = d.alliance_id AND
							(c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1 or c.is_allied = 1 or d.is_allied = 1) ";


                log_user_ip_address($user_id, $ts_user_ip_addr);
							
				$res = $db->query($sql);
				if ($res->num_rows == 0)
				{
					// user not allowed to be registered, needs to be unregistered immediately (= all groups removed)
					echo "Removing all groups from teamspeak user $nickname because he is not allowed to be registered with our teamspeak server.\n";
                    $ts3_Client->poke("All roles have been removed.");
					ts3_remove_all_groups($ts3_VirtualServer, $ts3_Client);
					
				} else 
				{
					$row = $res->fetch_array();
					
					$ts3_user_name = substr($row['character_name'], 0, 30);
					
					if ($nickname != $ts3_user_name) {
                        //  user has wrong username
                        echo "TS user with id $tsDatabaseID has the wrong nickname. Nickname is = $nickname, expected $ts3_user_name\n";
                        $msg = "Hello $nickname! Just a friendly reminder, you are using the wrong nickname. I was expecting your name to be '$ts3_user_name', (without quotes) please change it or you will be kicked.";

                        $ts3_Client->message($msg);
                    }

                    // if the name still "kind of" matches, e.g., BigSako1 (because of reconnect), then apply roles
                    if ($ts3_user_name == $nickname || $ts3_user_name . '1' == $nickname || strtoupper($ts3_user_name) == strtoupper($nickname))
                    {
						// proper nickname, let's check if he needs roles 
						
						// get all the groups he should be in
						$groups = array();				
						
						$res = $db->query("select ts3_group_id FROM groups g, group_membership m WHERE m.group_id = g.group_id AND 
											m.user_id = " . $user_id . " AND ts3_group_id <> 0 AND m.state = 0");
						// collect them in the groups array	
						while ($row = $res->fetch_array())
						{
							$groupId = $row['ts3_group_id'];
							$groups[] = $groupId;
						}

						// FIX: groups in this array are not necessary unique (that's bad, but we need to live with it)
						$groups = array_unique($groups);
						
						
						if (ts3_is_guest($ts3_VirtualServer, $ts3_Client))
						{	// only give roles if he is a guest					
							$ts3_unique_id = ts3_setgroups($ts3_user_name, $groups, true, $ts3_VirtualServer);
						} else { // not a guest, check his roles anyway
							// $ts_client_groups holds all the necessary groups	
							$ret_value = ts3_sync_groups($ts3_VirtualServer, $ts3_Client, $ts_client_groups, $groups);
						}
					}
                    else { // name does not match at all. remove all roles and kick client.
                        ts3_remove_all_groups($ts3_VirtualServer, $ts3_Client);
                        $ts3_Client->kick(TeamSpeak3::KICK_SERVER, "Wrong nickname! Expected '$ts3_user_name', but was '$nickname'.");
                    }
				}
			}		
		}
	
	} catch (TeamSpeak3_Exception $e) {
		die("An error occured: ".$e->getMessage()." [F".__LINE__."]");
	}
	
	
}





function ts3_removeFromGroup($nickname, $groupId,$ts3_VirtualServer=0)
{
	global $SETTINGS;
	
	//$queryAdmin = "serveradmin";
	//$queryPassword = "H4NxoIgV";
	$queryAdmin = $SETTINGS['ts3_serveradmin'];
	$queryPassword = $SETTINGS['ts3_queryPassword'];
	$local = $SETTINGS['ts3_local'];
	$qport = $SETTINGS['ts3_query_port'];
		
		
		try {
			if ($ts3_VirtualServer == 0) {
				$ts3_VirtualServer = connectToTS3Query();
			}
		} catch (TeamSpeak3_Exception $e) {
			return -1;
			//die("An error occured: ".$e->getMessage()." [F".__LINE__."]");
		}
		try {
			$tsClient = $ts3_VirtualServer->clientGetByName($nickname); // $tsClient reduces strain on the server
			$tsDatabaseID = $tsClient->client_database_id;
			$tsUniqueID = $tsClient->client_unique_identifier;
			//echo "<strong>Debug:</strong> Database ID: ".$tsDatabaseID."<br /><strong>Debug:</strong> Unique ID: ".$tsUniqueID."<br />";
			
		} catch (TeamSpeak3_Exception $e) {
			//die("Error: Could not find you on the server, your nickname should be exactly \"$nickname\" (Error: ".$e->getMessage()." [F".__LINE__."])");
			return -2;
		}
		
		
		
		try {
			$tsClient->remServerGroup($groupId);
		} catch (Teamspeak3_Exception $e) {
			return -3;
		}
					
		
		
		return $tsDatabaseID;
}


?>
