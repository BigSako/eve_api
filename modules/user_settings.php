<?php
	base_page_header('',"Settings","Settings");
	
	$db = connectToDB();
	
	
	if (isset($_REQUEST['mod']) && $_REQUEST['mod'] == 'save')
	{
		$notifications = array(0, 0, 0, 0, 0);
	
		foreach ($_REQUEST['notifications'] as $idx => $val)
		{
			$notifications[$idx] = intval($val);
		}
		
		$sum = 0;
		
		for ($i = 0; $i < count($notifications); $i++)
		{
			$sum += $notifications[$i];
		}
		
		$sum = intval($sum);


				
		$sql = "UPDATE auth_users SET wants_email_notifications='$sum' WHERE user_id = " . $GLOBALS['userid'];
		
		$db->query($sql);
		
		echo "Your settings have been saved.<br />";
	}
	
	
	
	$sql = "SELECT user_id, forum_id, user_name, email, wants_email_notifications, last_notification, has_regged_main, ts3_user_id, pull_assets
	FROM auth_users WHERE user_id = " . $GLOBALS['userid'];
	
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		echo "Unknown Error, more than 1 row returned...";
		base_page_footer('1','');
		exit;
	}
	
	$row = $res->fetch_array();
	
	
	if ($row['has_regged_main'] != 0)
	{
		$sql = "SELECT character_name FROM api_characters WHERE character_id = " . $row['has_regged_main'];
		$res2 = $db->query($sql);
		$row2 = $res2->fetch_array();
		
		$main_character = "<a href=\"api.php?action=select_main\">$row2[character_name]</a>";
	} else {
		$main_character = "<a href=\"api.php?action=select_main\">Click here to set</a>";
	}
	
	
	if ($row['ts3_user_id'] <= 0)
	{
		$ts3 = "Not registered - <a href=\"api.php?action=service_accounts\">Register now!</a>";
	} else {
		$ts3 = "Assigned (ID: $row[ts3_user_id])";
	}
	
	
	$notifications = $row['wants_email_notifications'];
	$checked = array();
	$checked[0] = ($notifications & 1?"checked=\"checked\"":"");
	$checked[1] = ($notifications & 2?"checked=\"checked\"":"");
	$checked[2] = ($notifications & 4?"checked=\"checked\"":"");

	
	echo "<table style=\"width: 100%\"><tr><th style=\"width: 50%\" class=\"your_characters_header\">Name</th><th class=\"your_characters_header\">Option</th></tr>";
	
	
	echo "<form method=\"post\" action=\"api.php?action=user_settings\" />";
	echo "<input type=\"hidden\" name=\"mod\" value=\"save\" />";
	
	echo "<tr><td><b>Username</b></td><td>$row[user_name]</td></tr>";
	echo "<tr><td><b>Teamspeak 3</b></td><td>$ts3</td></tr>";
	echo "<tr><td><b>E-Mail</b></td><td>$row[email]</td></tr>";
	echo "<tr><td><b>Main Character</b></td><td>$main_character</td></tr>";
	echo "<tr><td><b>Enable Skill-Queue Notification</b><br />
		Sends a notification mail if skill queue on one or more characters is below 13 hours</td>
		<td><input type=\"checkbox\" name=\"notifications[0]\" value=\"1\" $checked[0]/></td></tr>";
	echo "<tr><td><b>Enable Gametime Notification</b><br />Sends a notification mail one or more accounts are running out of gametime (below 3 days)</td>
		<td><input type=\"checkbox\" name=\"notifications[1]\" value=\"2\" $checked[1]/></td></tr>";
		/* update RHEA 2014-dez-09 - not needed any more */
		/*
	echo "<tr><td><b>Enable Clone Skillpoints Notification</b><br />Sends a notification mail if one of your medical clones needs to be updated</td>
		<td><input type=\"checkbox\" name=\"notifications[2]\" value=\"4\" $checked[2]/></td></tr>";
	*/
	
	echo "<tr><td>&nbsp;</td><td><input type=\"submit\" value=\"Save\" /></td></tr>";
	echo "</table>";
	
	echo "</form>";

	base_page_footer('1','');
?>
