<?php

	do_log("Entered corp_movement",5);
	
	if (!isset($_REQUEST['corp_id']))
		exit;
	
	$corp_id = intval($_REQUEST['corp_id']);
	$state = intval($_REQUEST['state']);
	
	if ($corp_id < 1)
		exit;
		
	if (!in_array($corp_id, $director_corp_ids) && !$isAdmin)
	{
		echo "Not allowed";
		exit;
	}
	
	$where = "";
	$title = "";
	
	switch ($state)
	{
		case 0:
			$where = "m.action in (1,2)";
			$title = "Arrivals and Departures from corp";
		case 1:
			$where = "m.action=1";
			$title = "Arrivals to corp";
			break;
		case 2:
			$where = "m.action=2";
			$title = "Departures from corp";
			break;
		default:
			exit;
	}

	$left_menu = "<ul>
	<li><a href=\"api.php?action=human_resources&$corp_id\">Back to HR</a></li>
	<li><a href=\"api.php?action=corp_movement&corp_id=$corp_id&state=1\">Arrivals</a></li>
	<li><a href=\"api.php?action=corp_movement&corp_id=$corp_id&state=2\">Departures</a></li>
</ul>
";
	
	base_page_header('',$title,$title, $left_menu);
		
	$db = connectToDB();
	
	$sql = "SELECT m.character_id as char_id, m.action as action, m.current_corp as cur_corp_id, 
			m.timestamp as timestamp, c.character_name as char_name 
			FROM corp_movement m, corp_members c WHERE 
			c.character_id=m.character_id and m.current_corp = $corp_id AND $where ORDER BY m.timestamp DESC";
	
	$res = $db->query($sql);
	do_log("query: $sql", 5);
	
	if ($res->num_rows > 0) {
		echo "<table style=\"width: 100%\"> <tr>
	<th  class=\"table_header\">Character Name</th><th class=\"table_header\">Action</th><th class=\"table_header\"></th><th class=\"table_header\">Timestamp</th></tr>";

		while ($row = $res->fetch_array())
		{
			$character_id = $row['char_id'];
			$character_name = $row['char_name'];
			$current_corp = $row['cur_corp_id'];

			$action = $row['action'];
			if ($action == '0')
			{
				$action = "Unknown";
			} else if ($action == '1') {
				$action = "Join";
			} else if ($action == '2') {
				$action = "Leave";
			}
			$timestamp = $row['timestamp'];
			echo "<tr><td><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a> 
			($character_id <a href=\"http://evewho.com/pilot/" . $character_name . "\">EvE WHO</a>)</td>" .
				"<td>$action</td><td>&nbsp;</td><td>$timestamp</td></tr>";
		}

		echo "</table>";
	
	} else {
		echo "No data available.";
	}
	
	echo "<br />";
	
	
	
	base_page_footer('','<a href="api.php?action=human_resources">Back</a>');
?>
