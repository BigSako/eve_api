<?php

base_page_header('','Service Accounts','Service Accounts');


$db = connectToDB();



if($GLOBALS["forum_id"]==0) {
	// not possible anymore
	print("You do not currently have a forum account linked. This should not happen. Please contact an administrator!<br>");
/*	print("To create a forum account, please select your main character from the list below, and click 'Create Account'<br>");
	print("<br><form action=api.php method=post>
	<input type=hidden name=action value='create_forum_account'>
	<select name=main>");
	$sth=db_action("select character_name,character_id from api_characters where user_id='".$GLOBALS["userid"]."' and corp_id in ('1070320653')");
	while($result=$sth->fetch_array()) {
		$character_name=$result['character_name'];
		$character_id=$result['character_id'];
		print("<option value='$character_id'>$character_name</option>\n");
		}
	print("</select><br><br><input type=submit value='Create Forum Account'>"); */
} else {
	echo "<table style=\"width: 95%\">";
	echo "<th>Type</th><th>Details</th><th>Actions</th></tr>";
	
	

	
	
	$res = get_forum_details($GLOBALS["forum_id"]);
	

	$user_name=$res['username'];

	
	print("<tr><td style=\"vertical-align: top\">Forum</td><td>Connected to account <b>$user_name</b>.</td><td>&nbsp;</td><tr/>");
	
	
	
	
	// ts 3
	$tsexternalhost = $SETTINGS['ts3_host'];
	$port = $SETTINGS['ts3_port'];
	
	$jabberexternalhost = "";
	//$jabberexternalhost = $SETTINGS['jabber_host'];


	
	// generate reset token
	$reset_token = sha1(SECRET_PHRASE . "//reset_token//" . $GLOBALS['userid'] . "//" . date('l jS'));
	
	
	// ts3	
	echo "<tr><td style=\"vertical-align: top\">TeamSpeak 3 (<a href=\"https://www.teamspeak.com/downloads\">Download</a>)</td>";
	
	
	// check if the user has an existing main character
	if ($existing_main == 0)
	{
		echo "<td>You need to <a href=\"api.php?action=select_main\">select your main character</a> first!</td>";
	} else {	
		// select the TS3 user name
		// get corp ticker
		$sql = "SELECT c.corp_name, c.corp_ticker 
		FROM api_characters a, corporations c, alliances d
		WHERE a.character_id = $existing_main 
			AND a.corp_id = c.corp_id AND c.alliance_id = d.alliance_id AND
			(c.is_allowed_to_reg = 1 OR d.is_allowed_to_reg = 1 or c.is_allied = 1 or d.is_allied=1)";
		$res = $db->query($sql);
		if ($res->num_rows != 1)
		{
			echo "<td>Your character is not allowed to register for these services. Select <a href=\"api.php?action=select_main\">a different main character</a> please!</td><td></td>";
		} else {
			$row = $res->fetch_array();
			$ticker = $row['corp_ticker'];
			
			// limit username to 30 characters
			$ts3_user_name = substr($existing_main_name, 0, 30);
		
			echo "<td>Server: $tsexternalhost:$port<br />Username: <input size=\"32\" type=\"text\" value=\"$ts3_user_name\" readonly /> (copy paste from this text field)<br />";
			
			if ($GLOBALS['ts3_user_id'] == '' && $_REQUEST['register_ts3'] == true)
			{
				require_once("funcs/TeamSpeak3/TeamSpeak3.php");
				require_once("funcs/ts3.php");
				
				$groups = array();
				
				// now collect all groups
				$res = $db->query("select ts3_group_id FROM groups g, group_membership m WHERE m.group_id = g.group_id AND 
									m.user_id = " . $GLOBALS['userid'] . " AND ts3_group_id <> 0 AND m.state=0");
				while ($row = $res->fetch_array())
				{
					$groupId = $row['ts3_group_id'];
					$groups[] = $groupId;
				}
				
				
				$ts3_unique_id = ts3_setgroups($ts3_user_name, $groups, $msg="1");
				if ($ts3_unique_id == -1)
				{
					echo "Error: couldn't connect to teamspeak service... Please try again later.</td><td></td>";
				} else if ($ts3_unique_id == -2) {
					echo "Error: Couldn't find you on teamspeak, is your username set to '$ts3_user_name'?</td><td></td>";
				} else if ($ts3_unique_id == -3) {
					echo "Error: Couldn't assign roles on teamspeak.</td><td></td>";
				} else {
				
					$db->query("UPDATE auth_users SET ts3_user_id='$ts3_unique_id', ts3_user_name='" . $db->real_escape_string($ts3_user_name) . "'
					
					WHERE user_id=". $GLOBALS['userid'] . " ");
					echo "Registered as $ts3_user_name!</td>
							<td><a href=\"ts3server://$tsexternalhost?port=$port&nickname=$ts3_user_name\">CONNECT as $ts3_user_name</a></td>";
				}
			}
			else if ($GLOBALS['ts3_user_id'] == '')
			{
				echo "Not registered yet.</td>
						<td style=\"color: #00ff00; border-style: solid; border-color: #ff0000;\">
						<a href=\"ts3server://$tsexternalhost?port=$port&nickname=$ts3_user_name&addbookmark=$SETTINGS[site_name] Teamspeak\">Click here to connect,</a> <br />
							then click <a href=\"api.php?action=service_accounts&register_ts3=true\">Register</a> to register.</td>";
			} else {
			
				// check if user wants to reset account
				if (isset($_REQUEST['unregister_ts3']) && $_REQUEST['unregister_ts3'] === $reset_token)
				{
					echo "Teamspeak Account was reset.</td>
								<td><a href=\"api.php?action=service_accounts\">Click here to continue</a></td>";

					// reset ts3 user id
					$db->query("UPDATE auth_users SET ts3_user_id='', ts3_user_name=''
							WHERE user_id=". $GLOBALS['userid'] . " ");

					// TODO: kick the user from server and strip from roles
					

				} else {				
					echo "Registered as $ts3_user_name (teamspeak id: " . $GLOBALS['ts3_user_id'] . ")!</td>
						<td><a href=\"ts3server://$tsexternalhost?port=$port&nickname=$ts3_user_name\">CONNECT as $ts3_user_name</a> <br />
							<a href=\"api.php?action=service_accounts&unregister_ts3=$reset_token\">Reset TS3 Account</a></td>";
				}
			}
			
		}
	
	}
	

	echo "</tr>";
	
	


	if (isset($SETTINGS['local_discord']) && $SETTINGS['local_discord'] == '1')
	{
		if (isset($_REQUEST['reset_discord']))
        {
        	// Reseting discord auth means setting the auth_token to empty
            $db->query("UPDATE discord_auth SET discord_auth_token = '' WHERE user_id=" . $GLOBALS['userid']);
        }
        if (isset($_REQUEST['reset_discord_time']))
        {
        	$sql = "UPDATE discord_auth SET ping_start_hour = 0, ping_stop_hour = 0 WHERE user_id = " . $GLOBALS['userid'];
        	$db->query($sql);
        }


        $sql = "SELECT discord_auth_token, discord_member_id FROM discord_auth WHERE user_id=" . $GLOBALS['userid'];
        $res = $db->query($sql);

        $allow_reset = false;
        $is_discord_authed = false;

        if ($res->num_rows == 0)
        {
        	// no auth token known --> new user: create an auth token
        	$token = sha1(SECRET_PHRASE . "discord" . $GLOBALS['userid'] . rand() . "discord");
        	$sql = "INSERT INTO discord_auth (user_id, discord_auth_token, discord_member_id) VALUES (" . $GLOBALS['userid'] . ", '$token', '') ";        	
        	$db->query($sql);

        } else {
        	// auth token found
        	$row = $res->fetch_array();
        	$token = $row['discord_auth_token'];
        	$member_id = $row['discord_member_id'];

        	if ($token == "")  // empty token
        	{
        		if ($member_id == '') // empty member id --> reset successful
        		{
        			$token = sha1(SECRET_PHRASE . "discord" . $GLOBALS['userid'] . rand() . "discord");
        			$sql = "UPDATE discord_auth SET discord_auth_token = '$token' WHERE user_id = " . $GLOBALS['userid'] . " ";
        			$db->query($sql);
        		} else {
        			$token = "RESET IN PROGRESS! PLEASE WAIT!";
        		}
        	} else {
        		$allow_reset = true;
        		if ($member_id != '') // user is authed
        		{
        			$is_discord_authed = true;
        		}
        	}
        }


        echo "<tr><td>Discord Auth Token (<a href=\"https://discordapp.com/download\" target=\"_blank\">Download</a>)<br /><b>New and shiny!</b></td><td>";

        if (!$is_discord_authed)
        {
        	echo "<input type=\"text\" value=\"auth=$token\" />";
        }
        else
        {
        	echo "You are already authed on discord!";
        }

        $extra_str = "";

        if ($is_discord_authed == true)
        {
	        // now let's see when to ping this user
	        $sql = "SELECT ping_start_hour, ping_stop_hour FROM discord_auth WHERE user_id = " . $GLOBALS['userid'];
	        $res = $db->query($sql);

	        if ($res->num_rows == 1)
	        {
	        	$row = $res->fetch_array();
	        	if ($row['ping_start_hour'] != 0 && $row['ping_stop_hour'] != 0)
	        	{
	        		echo " You are receiving pings between " . $row['ping_start_hour'] . ":00 and " . $row['ping_stop_hour'] . ":00 UTC<br />";
	        		$extra_str = "<a href=\"api.php?action=service_accounts&reset_discord_time=1\">Reset to 24h/day</a>";
	        	} else {
	        		echo " You are receiving pings 24h/day";
	        	}
	        } 

    	}


        echo "</td><td>";



        if ($allow_reset)
        {
        	echo "<a href=\"api.php?action=service_accounts&reset_discord=1\">Reset Discord Token</a>";
        } 
        if ($extra_str != "")
        {
        	echo "<br />$extra_str ";
        }





        echo "</td></tr>";

	}
	
	if ($SETTINGS['use_telegram'] == 1)
	{
		echo "<tr><td colspan=\"3\"><hr></td></tr>";

		// telegram
		$res = $db->query("select telegram_key,telegram_active,telegram_user_id,telegram_start_hour,telegram_stop_hour FROM auth_users WHERE user_id = " . $GLOBALS['userid'] . " ");
		$row = $res->fetch_array();
		$telegram_active = $row['telegram_active'];
		$telegram_key    = $row['telegram_key'];
		$telegram_user_id = $row['telegram_user_id'];
		$telegram_start_hour = $row['telegram_start_hour'];
		$telegram_stop_hour = $row['telegram_stop_hour'];

		if (strlen($telegram_key) == 0)
		{
			// create a telegram_key
			$key = md5("random string for salt sadgfjkdlt43sfi" . $GLOBALS['userid'] . "sdafaksdlfjsld�fjsdakfkcvxveuioui");
			$telegram_key = substr($key,0,10);
			$db->query("UPDATE auth_users SET telegram_key = '" . $telegram_key . "' WHERE user_id=" . $GLOBALS['userid'] . " ");
		}

		echo "<tr><td style=\"vertical-align: top\">Telegram (<a href=\"https://telegram.org/\">Download</a>)<br />No longer supported</td>";

		if ($telegram_active == 1)
		{
			echo "<td>";
			if ($telegram_start_hour != $telegram_stop_hour)
			{
				echo "Telegram Fleetbot Forwarding is active between $telegram_start_hour:00 and $telegram_stop_hour:00 EVE TIME (GMT).<br />User Id: " . $telegram_user_id;
			} else {
				echo "Telegram Fleetbot Forwarding is active 24h/day.<br />Telegram User Id: " . $telegram_user_id;
			}

			echo "</td>";
		} else {
			echo "<td>Telegram Fleetbot Forwarding is NOT active.<br />Message Sm3llBot with <b>subscribe " . $telegram_key . "</b></td>";
		}

		echo "<td>";
		if (isset($_REQUEST['telegram_test']))
		{
			sendTelegramMessageByUserId($GLOBALS['userid'], "This is a test message from the sm3ll.net api!");
			echo "Test message sent!";
		} else {
			echo "<a href=\"api.php?action=service_accounts&telegram_test=1\">Send test message</a>";
			if ($telegram_start_hour != $telegram_stop_hour)
			{
				echo "<br /><a href=\"api.php?action=service_accounts&telegram_reset_timespan=1\">Reset timespan to 24h/day</a>";
			}
		}
		echo "</td>";

		echo "</tr>";
	}

	


	if (isset($jabberexternalhost) && $jabberexternalhost != '')
	{
	// jabber	
	echo "<tr><td style=\"vertical-align: top\">Jabber</td>";
	
	
	// check if the user has an existing main character
	if ($existing_main == 0)
	{
		echo "<td>You need to <a href=\"api.php?action=select_main\">select your main character</a> first!</td>";
	} else {	
		$new_jabber_user_name = strtolower(str_replace(' ', '_', $GLOBALS['username']));
		$new_jabber_user_name = strtolower(str_replace('-', '_', $new_jabber_user_name));
		$new_jabber_user_name = strtolower(str_replace('.', '_', $new_jabber_user_name));
		$new_jabber_user_name = strtolower(str_replace("'", '_', $new_jabber_user_name));
		$new_jabber_user_name = strtolower(str_replace('"', '_', $new_jabber_user_name));
		
		$password = substr( sha1(SECRET_PHRASE . "//temp_password//" . $GLOBALS['userid'] . "//" . date('l jS')), 0, 10);
	
		// select the Jabber Username
		// get corp ticker
		$sql = "SELECT c.corp_name, c.corp_ticker 
		FROM api_characters a, corporations c, alliances d 
		WHERE a.character_id = $existing_main 
		AND a.corp_id = c.corp_id AND c.alliance_id = d.alliance_id AND
		(c.is_allowed_to_reg = 1 or d.is_allowed_to_reg = 1) ";
		$res = $db->query($sql);
		if ($res->num_rows != 1)
		{
			echo "<td>Your character is not allowed to register for these services. Select <a href=\"api.php?action=select_main\">a different main character</a> please!</td>";
		} else {
			$row = $res->fetch_array();
			$ticker = $row['corp_ticker'];
			
			// limit username to 30 characters
			$jabber_nick_name = substr($ticker . " - " . $existing_main_name, 0, 30);
		
			echo "<td>Protocol: XMPP<br />Domain: $jabberexternalhost<br />Resource: $jabberexternalhost<br />";
			
			if ($GLOBALS['jabber_user_name'] == '' && $_REQUEST['register_jabber'] == true)
			{				
				$groups = array();
				
				// now collect all groups
				$res = $db->query("select jabber_group_name FROM groups g, group_membership m WHERE m.group_id = g.group_id AND 
									m.user_id = " . $GLOBALS['userid'] . " AND jabber_group_name <> '' AND m.state=0");
				while ($row = $res->fetch_array())
				{
					$groupName = $row['jabber_group_name'];
					$groups[] = $groupName;
				}
				
				
				
				
				// register user
				exec('sudo /usr/sbin/ejabberdctl register "'.$new_jabber_user_name.'" "'.$jabberexternalhost.'" "'.$password.'" 2>&1',$output,$status);
				
				// set nickname
				exec('sudo /usr/sbin/ejabberdctl set_nickname "'.$new_jabber_user_name.'" "'.$jabberexternalhost.'" "'.$jabber_nick_name.'" 2>&1',$output,$status);
				
				
				for ($i = 0; $i < count($groups); $i++) 
				{
					// ejabberdctl srg-user-add bigsako3 northern-army.com ncdot northern-army.com
					$group = $groups[$i];
					echo "Adding user to group $group<br />";
					exec('sudo /usr/sbin/ejabberdctl srg_user_add "'.$new_jabber_user_name.'" "'.$jabberexternalhost.'" "'.$group.'" "'.$jabberexternalhost.'" 2>&1',$output,$status);
				}
				
				
				/*
				
				$ts3_unique_id = ts3_setgroups($ts3_user_name, $groups);
				if ($ts3_unique_id == -1)
				{
					echo "Error: couldn't connect to teamspeak service... Please try again later.</td><td></td>";
				} else if ($ts3_unique_id == -2) {
					echo "Error: Couldn't find you on teamspeak, is your username set to '$ts3_user_name'?</td><td></td>";
				} else if ($ts3_unique_id == -3) {
					echo "Error: Couldn't assign roles on teamspeak.</td><td></td>";
				} else {
				*/
					$db->query("UPDATE auth_users SET jabber_user_name='" . $db->real_escape_string($new_jabber_user_name) . "' 					
					WHERE user_id=". $GLOBALS['userid'] . " ");
					
					
					echo "Username: $new_jabber_user_name<br />Password: <input type=\"text\" value=\"$password\" readonly/></td>
							<td></td>";
				//}


			}
			else if ($GLOBALS['jabber_user_name'] == '')
			{
				echo "Not registered yet.</td>
						<td style=\"color: #00ff00; border-style: solid; border-color: #ff0000;\">
					
							Click <a href=\"api.php?action=service_accounts&register_jabber=true\">here</a> to register.</td>";
			} else {
			
				// check if user wants to reset account
				if (isset($_REQUEST['unregister_jabber']) && $_REQUEST['unregister_jabber'] === $reset_token)
				{
					// kick user
					exec('sudo /usr/sbin/ejabberdctl kick_session "'.$GLOBALS['jabber_user_name'].'" "'.$jabberexternalhost.'" "'.$jabberexternalhost.'" 2>&1',$output,$status);
					// unregister
					exec('sudo /usr/sbin/ejabberdctl unregister "'.$GLOBALS['jabber_user_name'].'" "'.$jabberexternalhost.'" 2>&1',$output,$status);

					echo "Jabber Account was reset.</td>
								<td><a href=\"api.php?action=service_accounts\">Click here to continue</a></td>";
					
					// reset ts3 user id
					$db->query("UPDATE auth_users SET jabber_user_name=''
							WHERE user_id=". $GLOBALS['userid'] . " ");
							
							
					
					

				} else {				
					echo "Username: $new_jabber_user_name</td>
						<td><a href=\"api.php?action=service_accounts&unregister_jabber=$reset_token\">Reset jabber account</a></td>";
				}
			}
			
		}
	
	}
	}
	

	echo "</tr>";
	
	
	echo "</table>";

	echo "<b>Note:</b> Please do not reset anything, unless you are setting up a completely new account!<br /><br />";


}


base_page_footer('1','');




?>
