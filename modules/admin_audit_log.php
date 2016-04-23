<?php
	base_page_header('','Audit Log','Audit Log');

	
	if (!isset($_REQUEST['logtype']))
	{
		$logtype = "cron";
	} else if ($_REQUEST['logtype'] == "cron") {
		$logtype = "cron";
	} else {
		$logtype = "normal";
	}
	
	
	echo "Please select: ";
	if ($logtype == "cron")
	{
		echo "<b><a href=\"api.php?action=admin_audit_log&logtype=cron\">Cron-Log</a></b>";
		echo "<a href=\"api.php?action=admin_audit_log&logtype=normal\">User-Log</a>";
		
		$where = " l.ip = 'cron' ";
		$sql = "SELECT l.user_id, 'cron' as user_name, l.logText, l.ip, l.logTimestamp 
		FROM audit_log l
		WHERE
		 $where ORDER BY l.logTimestamp DESC ";
	} else {
		echo "<a href=\"api.php?action=admin_audit_log&logtype=cron\">Cron-Log</a>";
		echo "<b><a href=\"api.php?action=admin_audit_log&logtype=normal\">User-Log</a></b>";
		
		$where = " l.ip <> 'cron' ";
		$sql = "SELECT l.user_id, u.user_name, l.logText, l.ip, l.logTimestamp 
		FROM audit_log l, auth_users u
		WHERE
		u.user_id = l.user_id AND $where ORDER BY l.logTimestamp DESC ";
	}
	
	
	$db = connectToDB();
	
	$res = $db->query($sql);
	
	
	print("<table style=\"width:100%\">");
		print("<tr><td class=\"long_table_header\" colspan=\"5\">Log Entries</td></tr>\n");
		print("<tr><td width=\"150\" class='your_characters_header'>User</td>" .
					"<td width=\"150\" class='your_characters_header'>Timestamp</td>" .
			"<td class='your_characters_header'>Text</td>" .
			"<td class='your_characters_header'>IP</td></tr>");
	
	
	while ($row = $res->fetch_array())
	{
		$user = $row['user_id'];
		if ($user <= 0)
			$user = "Cron";
		else
			$user = "<a href=\"api.php?action=show_member&user_id=$user\">" . $row['user_name'] . "</a>";
			
		echo "<tr><td>$user</td><td>" . $row['logTimestamp'] . "</td>";
		
		echo "<td>" . $row['logText'] . "</td>";
		
		if ($row['ip'] == 'cron')
		{
			echo "<td>&nbsp;</td>";
		} 
		else
			echo "<td>" . $row['ip'] . "</td>";
		
		
		echo "</tr>";
	}
	
	
	echo "</table>";
	
	
	
	
	base_page_footer('1','');
	
		
?>