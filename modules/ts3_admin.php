<?php

base_page_header('','TS3 Accounts','TS3 Accounts');
include("funcs/TeamSpeak3/TeamSpeak3.php");
include("funcs/ts3.php");



$queryAdmin = $SETTINGS['ts3_serveradmin'];
$queryPassword = $SETTINGS['ts3_queryPassword'];
$local = $SETTINGS['ts3_local'];
$qport = $SETTINGS['ts3_query_port'];


echo "<table><tr><th class=\"table_header\">TS3 Nickname</th><th class=\"table_header\">TS3 ID</th><th class=\"table_header\">Corp</th><th class=\"table_header\">User ID</th>
<th class=\"table_header\">User Name</th><th class=\"table_header\">Info</th></tr>";
			
try {
	$ts3_VirtualServer = TeamSpeak3::factory("serverquery://$queryAdmin:$queryPassword@$local:$qport/?server_port=$SETTINGS[ts3_port]");


$arr_ClientList = $ts3_VirtualServer->clientList();

	foreach($arr_ClientList as $ts3_Client)
	{
		// skip query clients
		if($ts3_Client["client_type"]) continue;
		
		echo "<tr><td>";
		echo $ts3_Client;
		echo "</td><td>";
		$ts_client_groups = $ts3_Client["client_servergroups"];
		
		// explode into array
		$ts_client_groups = explode(',', $ts_client_groups);
		
		$nickname = $ts3_Client["client_nickname"];
		
		// see if nickname is registered and the UID is right too
		$tsDatabaseID = $ts3_Client->client_database_id;
		$tsUniqueID = $ts3_Client->client_unique_identifier;
		
		echo "$tsUniqueID - $tsDatabaseID</td>";
		
		$sql = "SELECT has_regged_api, has_regged_main, user_name, ts3_user_id, ts3_user_name, user_id, state FROM auth_users
				WHERE ts3_user_id = $tsDatabaseID";
		
		$res = $db->query($sql);
		if ($res->num_rows == 0)
		{
			// user not found, needs to be unregistered immediately (= all groups removed)
			echo "<td>&nbsp;</td><td>Not registered</td><td>n/a</td><td>Not registered</td>";
			
		} else {
			$row = $res->fetch_array();
			$existing_main = $row['has_regged_main'];
			$user_id = $row['user_id'];
			$real_user_name = $row['user_name'];

			
			echo "<td>$user_id</td>";
			
			// get expected nickname
			$sql = "SELECT c.corp_name, c.corp_ticker, a.character_name 
				FROM api_characters a, corporations c, alliances d
						WHERE a.character_id = $existing_main AND a.corp_id = c.corp_id AND d.alliance_id = c.alliance_id
						and (c.is_allowed_to_reg = 1 or d.is_allowed_to_reg = 1 or c.is_allied = 1 or d.is_allied = 1)";
						
			$res = $db->query($sql);
			if ($res->num_rows == 0)
			{
				// user not allowed to be registered, needs to be unregistered immediately (= all groups removed)
				echo "<td>Not allowed on teamspeak</td>";
				
			} else 
			{
				$row = $res->fetch_array();

				$corp_name = $row['corp_name'];

				echo "<td>$corp_name</td><td><a href=\"api.php?action=show_member&user_id=$user_id\">$real_user_name</a></td>";
				
				$ts3_user_name = substr($row['character_name'], 0, 30);
				
				if ($nickname != $ts3_user_name)
				{
					//  user has wrong username
					echo "<td>wrong nickname, expected $ts3_user_name</td>";
				
				} else {
					echo "<td>Nickname is proper";
					// proper nickname, let's check if he needs roles 
					// only give roles if he is a guest
					if (ts3_is_guest($ts3_VirtualServer, $ts3_Client))
					{
						echo " - Roles not assigned yet...";
						
						/*
						$groups = array();
			
						// now collect all groups
						$res = $db->query("select ts3_group_id FROM groups g, group_membership m WHERE m.group_id = g.group_id AND 
											m.user_id = " . $user_id . " AND ts3_group_id <> 0 AND m.state = 0");
											
						while ($row = $res->fetch_array())
						{
							$groupId = $row['ts3_group_id'];
							$groups[] = $groupId;
						}
						
						print_r($groups);
						//$ts3_unique_id = ts3_setgroups($ts3_user_name, $groups);
						
						//echo " result - $ts3_unique_id";
						
						
						*/
						
					} else {
						echo " - account is ok!";
					}
					
					echo "</td>";
				}
			}
		}

		echo " <tr />";
	}

} catch (TeamSpeak3_Exception $e) {
	print("TS3 Error: ".$e->getMessage()." [F".__LINE__."]");
}



echo "</table>";








base_page_footer('1','');

?>