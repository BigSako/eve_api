<?php
	base_page_header('',"Jabber Ping","Jabber Ping");
	
	$db = connectToDB();
	
	if (!isset($_REQUEST['sendPing']))
	{
	
?>
<form method="post" action="api.php?action=jabber_send_ping">
<input type="hidden" name="sendPing" value="1" />
Target Group: <select name="group_id">
<?php
	$sql = "SELECT group_name, group_description, group_id as group_id FROM groups WHERE jabber_ping=1 ORDER BY group_name";
	$res = $db->query($sql);
	while ($row = $res->fetch_array())
	{
		$group_id = $row['group_id'];
		$group_name = $row['group_name'];
		if ($group_id == 1) // Validated Members
		{
			echo "<option value=\"$group_id\" selected>ALL ($group_name)</option>";
		} else {
			echo "<option value=\"$group_id\">$group_name</option>";
		}
	}
?>
</select>
<br />
<textarea name="pingMessage" rows="10" cols="30">
</textarea>
<br />
<input type="submit" value="Send Ping" />
</form>


<?
	} else {
		$group_id = intval($_REQUEST['group_id']);
	
		// actually send ping
		// collect targets
		$sql = " SELECT DISTINCT u.jabber_user_name
FROM auth_users u, groups g, group_membership m
WHERE u.jabber_user_name !=  ''
AND m.group_id = $group_id
AND m.user_id = u.user_id ";
		$res = $db->query($sql);
		
		
		$message = "\nBroadcast via FleetBot:\n\n";
		
		$in_msg = $_REQUEST['pingMessage']; // str_replace("\n", "<br />\n", $_REQUEST['pingMessage']);
		
		$message .= $in_msg;
		
		$group_target = getGroupName($group_id);
		
		$j_username = $GLOBALS['existing_main_name'];
		if ($GLOBALS['jabber_user_name'] != '')
		{
			$j_username .= " ( " . $GLOBALS['jabber_user_name'] . " @ " . $SETTINGS['jabber_host'] . " )";
		}
		
		
		$message .= "\n \nSent by " . $j_username . " to $group_target at " . date(DATE_RFC822) . " - replies are not monitored.\n";
		
		
		$targets = [];
		
		while ($row = $res->fetch_array())
		{
			$targets[] = $row['jabber_user_name'];
		}
		
		include("funcs/xmpphp-0.1rc2-r77/XMPPHP/XMPP.php");
		$conn = new XMPPHP_XMPP('jabber.northern-army.com', 5222, 'FleetBot', '34jKg60S9d3', 'FleetBot', 'jabber.northern-army.com', $printlog=False, XMPPHP_Log::LEVEL_INFO);

		try {
			$conn->connect();
			$conn->processUntil('session_start');
			$conn->presence();
			foreach ($targets as $user)
			{
				$conn->message($user . "@" . $SETTINGS['jabber_host'], $message);
			}
			$conn->disconnect();
		} catch(XMPPHP_Exception $e) {
			die($e->getMessage());
		}
		
		echo "<b>Ping sent!</b>";
		
	
	}
	
	base_page_footer('1','');
?>