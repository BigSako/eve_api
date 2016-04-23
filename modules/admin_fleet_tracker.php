<?php

$url = $SETTINGS['fleet_link_url']; //"http://sm3ll.net/logi_tracker/";

base_page_header('','Fleet Tracker','Fleet Tracker');

// please change this string!
$secret_str = $SETTINGS['secret_string'];

if (!isset($_REQUEST['mod']))
	$mod = "list_fleets";
else
	$mod = $_REQUEST['mod'];

function sanitize($str)
{
	// remove any html
	$str = str_replace('<', "&lt;", $str);
	$str = str_replace('>', "&gt;", $str);
	
	return $str;
}
	
	

$mysqli = connectToDB();


// display sub menu for this
echo "<ul>";
echo "<li><a target=\"_blank\" href=\"https://docs.google.com/document/d/1hYHkFKfQJ3ahJkAIajsGwCcsqxO0KbR-qHm8zJmcPMY\">How-To</a></li>";
echo "<li><a href=\"api.php?action=admin_fleet_tracker&mod=new_fleet\">Create a new fleet</a></li>";
echo "<li><a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets\">List all fleets (newest first)</a></li>";
echo "<li><a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=player\">Player Participation by month</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=player&filter=logistics\">Logis</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=player&filter=carriers\">Carriers</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=player&filter=dreads\">Dreads</a>
</li>";
echo "<li><a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=corp\">Corp Participation by month</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=corp&filter=logistics\">Logis</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=corp&filter=carriers\">Carriers</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=corp&filter=dreads\">Dreads</a>
</li>";
echo "<li><a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=alliance\">Alliance Participation by month</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=alliance&filter=logistics\">Logis</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=alliance&filter=carriers\">Carriers</a> | 
	<a href=\"api.php?action=admin_fleet_tracker&mod=participation&m=alliance&filter=dreads\">Dreads</a>
</li>";
echo "</ul>";
echo "<hr />";



switch ($mod)
{
	case 'verify_participation':
		$fleetchat_names = str_replace("\r", "", $_REQUEST['fleetchat_names']);
		$fleet_id = intval($_REQUEST['fleet_id']);
		if (strlen($fleetchat_names) < 5)
		{
			echo "Invalid input. Please copy paste fleetchat list to the textbox.";
		} else {						
			
			
			$data = split("\n", $fleetchat_names);
			
			// preprocess
			$error = false;
			for ($i = 0; $i < count($data); $i++)
			{
				$data[$i] = $mysqli->real_escape_string(sanitize($data[$i]));
				
				if (strlen($data[$i]) > 30)
				{
					echo "Seems like you did not post chat in here, but something else... exitting.";
					$error = true;
					break;
				}
			}
			
			if ($error == false)
			{
			
				for ($i = 0; $i < count($data); $i++)
				{
					$char_name = $data[$i];
					$sql = "UPDATE participation SET verified=1 WHERE fleet_id=$fleet_id 
						AND character_name='$char_name' ";
						
					$res = $mysqli->query($sql);
					if ($res == 1)
					{
						echo "$char_name verified!";
					} 
					
					echo "<br />";			
				}
				
				// show all unverified members:
				$sql = "SELECT character_name FROM participation WHERE verified=0 AND fleet_id=$fleet_id";
				
				$res = $mysqli->query($sql);
				
				if ($res->num_rows > 0)
				{								
					while ($row = $res->fetch_array())
					{
						echo $row['character_name'] . " is not verified/not in fleet - deleting.<br />";
					}
					// DELETE THEM
					$sql = "DELETE FROM participation WHERE verified=0 AND fleet_id=$fleet_id";
					$mysqli->query($sql);
				}
				
				
				// set fleet to verified
				$sql = "UPDATE fleet SET verified=1 WHERE fleet_id = $fleet_id";
				$mysqli->query($sql);
				
				echo "Done! ";
			}
		}
		
		
		echo "<a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id\">Go back to the fleet</a>!";
		
		
		break;
	case 'participation':
		
		$where = "1=1";
		$title = "Participation for ";						
		
		// filter by player, corp or alliance
		$filter_type = "character_name";
		$filter_type_title = "Character Name";
		
		$m = "";
		
		if (isset($_REQUEST['m']))
		{
		
			switch ($_REQUEST['m'])
			{
				case 'player':
					$filter_type = "character_name";
					$filter_type_title = "Character Name";
					$m = $_REQUEST['m'];
					break;
				case 'corp':
					$filter_type = "corp_name";
					$filter_type_title = "Corp Name";
					$m = $_REQUEST['m'];
					break;
				case 'alliance':
					$filter_type = "alliance_name";
					$filter_type_title = "Alliance Name";
					$m = $_REQUEST['m'];
					break;
			}
		
		}
		
		
		if ($_REQUEST['cur_year'])
			$cur_year = intval($_REQUEST['cur_year']);
		else
			$cur_year = date('Y');
			
		if ($_REQUEST['cur_month'])
			$cur_month = intval($_REQUEST['cur_month']);
		else
			$cur_month = intval(date('m'));
		
		
		$title .= "$cur_month - $cur_year";
		
		$filter = "";
		
		if (isset($_REQUEST['filter']))
		{
			
		
			switch ($_REQUEST['filter'])
			{
				case 'logistics':
					$where = "ship_name in ('Scimitar', 'Oneiros', 'Guardian', 'Basilisk')";
					$title .= " (Logis only)";
					$filter = $_REQUEST['filter'];
					break;
				case 'dreads':
					$where = "ship_name in ('Moros', 'Naglfar', 'Revelation', 'Phoenix')";
					$title .= " (Dreads only)";
					$filter = $_REQUEST['filter'];
					break;
				case 'carriers':
					$where = "ship_name in ('Thanatos', 'Nidhoggur', 'Chimera', 'Archon')";
					$title .= " (Carriers only)";
					$filter = $_REQUEST['filter'];
					break;
			}
			
		}
		
		echo "<h3>$title</h3>";
		
		
		$sql = "SELECT $filter_type, COUNT(*) as cnt FROM participation
		WHERE YEAR( curTimestamp ) = $cur_year AND MONTH( curTimestamp ) = $cur_month AND $where GROUP BY 
			$filter_type ORDER BY COUNT(*) DESC";
	
	
		echo "<table><tr><th>$filter_type_title</th><th>Times participated</th></tr>";
		$res = $mysqli->query($sql);
		
		while ($row = $res->fetch_array())
		{
			$char_name = $row[$filter_type];
			$cnt = $row['cnt'];
			
			echo "<tr><td>$char_name</td><td>$cnt</td></tr>";
		}
		
		echo "</table><br />";
		
		$prev_month = $cur_month-1;
		$prev_year = $cur_year;
		if ($prev_month <= 0)
		{
			$prev_month += 12;
			$prev_year = $prev_year -1;
		}
		
		$next_month = $cur_month +1;
		$next_year = $cur_year;
		if ($next_month > 12)
		{
			$next_month = $next_month - 12;
			$next_year = $next_year + 1;
		}
		

		
		echo "<a href=\"api.php?action=admin_fleet_tracker&mod=participation&cur_month=$prev_month&cur_year=$prev_year&filter=$filter&m=$m\">Previous month</a> | 
		<a href=\"api.php?action=admin_fleet_tracker&mod=participation&cur_month=$next_month&cur_year=$next_year&filter=$filter&m=$m\">Next month</a>";

		break;
	case 'show_fleet':
		$fleet_id = intval($_REQUEST['fleet_id']);
		
		$order_by = "";
		
		switch ($_REQUEST['order_by'])
		{
			case '':
				$order_by = "ORDER BY character_name ASC";
				break;
			case 'location':
				$order_by = "ORDER BY location ASC";
				break;
			case 'shipname':
				$order_by = "ORDER BY ship_name ASC";
				break;
			case 'corp':
				$order_by = "ORDER BY corp_name ASC";
				break;
			case 'alliance':
				$order_by = "ORDER BY alliance_name ASC";
				break;								
		}
		
		
		$sql = "SELECT a.user_name as character_name, fleet_tracker_id, fleet_name, openTimestamp, restrict_to_alliance,
				f.closeTimestamp, verified FROM fleet f, auth_users a 
				WHERE a.user_id = f.user_id AND fleet_id=$fleet_id";
		$res = $mysqli->query($sql);
		
		if ($res->num_rows == 1)
		{
			$row = $res->fetch_array();
			echo "Fleet Name: " . $row['fleet_name'] . "<br />";
			echo "Fleet created at: " . $row['openTimestamp'] . "<br />";
			echo "Fleet created by: " . $row['character_name'] . "<br />";
			
			$closed = $row['closeTimestamp'];
			
			
			if ($closed !== NULL)
			{
				echo "Fleet closed at: $closed. <br />";
			} else {
				echo "Fleet Tracking Link: <br />
					<textarea cols=\"60\">" . $url . "?fleet_link=" . $row['fleet_tracker_id'] . "</textarea>
					<br />";
			}
			echo "<br />";
			
			$verified = $row['verified'];
			
			if ($verified == 1 && $closed !== NULL)
			{
				echo "<i>Fleet participants have been verified.</i><br />";
			}
			
			
			if ($closed !== NULL && $verified == 0)
			{
				echo "<i><a href=\"#verify_part\">Verify participation with fleet chat</a></i><br />";
			}
			
			
			
			echo "Fleet is open to everybody.<br /><b>Members in fleet by alliance name:</b><br />";
			$sql = "SELECT alliance_name, COUNT(*) as cnt FROM participation 
				WHERE fleet_id=$fleet_id GROUP BY alliance_name ORDER BY COUNT(*) DESC";
				
			$res2 = $mysqli->query($sql);
			
			while ($row2 = $res2->fetch_array())
			{
				echo $row2['alliance_name'] . ": " . $row2['cnt'] . "<br />";
			}
			
			echo "<br /><b>Ship Types:</b><br />";
		
			$sql = "SELECT ship_name, COUNT(*) as cnt FROM participation 
				WHERE fleet_id=$fleet_id GROUP BY ship_name ORDER BY COUNT(*) DESC";
				
			$res2 = $mysqli->query($sql);
			
			while ($row2 = $res2->fetch_array())
			{
				echo $row2['ship_name'] . ": " . $row2['cnt'] . "<br />";
			}
			
			
			echo "<br /><b>Participation:</b><br />";
			$sql = "SELECT character_name, ship_name, corp_name, alliance_name, location, 
				curTimestamp FROM participation WHERE fleet_id=$fleet_id 
				$order_by";
			$res = $mysqli->query($sql);
			
			
			echo "<table style=\"width: 100%\">
				<tr class=\"td_header\">
					<th><a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id&order_by=\">Character Name</a></th>
					<th><a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id&order_by=corp\">Corporation</a></th>
					<th><a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id&order_by=alliance\">Alliance</a></th>
					<th>Date</th>
					<th><a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id&order_by=shipname\">Ship</a></th>
					<th><a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id&order_by=location\">Location</a></th>
				</tr>";
				
			$i = 0;
			
			while ($row = $res->fetch_array())
			{
				if (($i % 2) == 0)
				{
					$bgclass = "td_darkgray";
				} else {
					$bgclass = "td_lightgray";
				}
				
				$character_name = $row['character_name'];
				$ship_name = $row['ship_name'];
				$location = $row['location'];
				$curTimestamp = $row['curTimestamp'];
				$corp_name = $row['corp_name'];
				$alliance_name = $row['alliance_name'];
				
				echo "<tr class=\"$bgclass\"><td>$character_name</td><td>$corp_name</td><td>$alliance_name</td><td>$curTimestamp</td>
				<td>$ship_name</td><td>$location</td></tr>";
			}
			
			echo "</table>";
			
			
			if ($closed !== NULL && $verified == 0)
			{
				echo "<br /><br />";
				echo "<a name=\"verify_part\"><h3>Verify participation</h3></a>";
				echo "This is not necessary, but recommended: Copy and paste (CTRL A then CTRL C) the characters names from fleet chat (not dscan) into the textbox below: <br />";
				echo "What does it do? It checks every name that is on the list with all the names from above and removes characters that are not on the list (anti-cheat).<br />";
				echo "<table><tr><td><form method=\"post\" action=\"?mod=verify_participation\">
					<input type=\"hidden\" name=\"fleet_id\" value=\"$fleet_id\">
					<textarea name=\"fleetchat_names\" cols=\"50\" rows=\"20\"></textarea><br />
					<input type=\"submit\" value=\"Verify!\"></form></td><td><img src=\"help_textbox.png\"></td></tr></table>";
			} else {
				echo "<br /><br />";
				if ($closed == NULL)
				{
					echo "Fleet needs <a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets&close_fleet_id=$fleet_id\">to be closed</a> to be verified.";
				} else
				{
					echo "Fleet has already been verified. <a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets&reopen_fleet_id=$fleet_id\">Re-open fleet</a> to be able to verify it again.";
				}
			}
			
		} else {
			echo "Fleet not found. <a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets\">Go back</a>.";
		}
		break;
	case 'new_fleet':
		// generate a random unique id string for this fleet
		$r = rand();
		$link_id = substr(sha1($secret_str . sha1($r)), 0, 8);
		echo "<form method=\"post\" action=\"api.php?action=admin_fleet_tracker&mod=new_fleet2\">
		<input type=\"hidden\" name=\"fleet_link\" value=\"$link_id\" />
		<table><tr><td>Fleet Name</td><td><input type=\"text\" name=\"fleet_name\" /></td></tr>
		<tr><td>Fleet Id</td><td>$link_id</td></tr>
		<tr><td>&nbsp;</td><td><input type=\"submit\" value=\"Create Fleet\" /></td></tr>
		</table>";
		
		break;
	case 'new_fleet2':
		$fleet_link = $mysqli->real_escape_string($_REQUEST['fleet_link']);
		$fleet_name = $mysqli->real_escape_string(sanitize($_REQUEST['fleet_name']));
		
		$restrict_fleet = -1;
		
		if (strlen($fleet_name) < 5)
		{
			echo "Fleet name needs to be longer than 5! <a href=\"api.php?action=admin_fleet_tracker&mod=new_fleet\">Go back</a>.";
		} else {						
			$sql = "INSERT INTO fleet (fleet_tracker_id, fleet_name, user_id, restrict_to_alliance) VALUES
				('$fleet_link', '$fleet_name', ". $GLOBALS['userid'] . ", $restrict_fleet)";
			$res = $mysqli->query($sql);
			
			if ($res == 1)
			{
				echo "Done!<br />Copy and paste the following tracking link:<br />";
				echo "<textarea cols=\"60\">$url?fleet_link=$fleet_link</textarea>";
			} else {
				echo "Unknown Error...";
			}
		}
		break;
	case 'list_fleets':
		// check if close_fleet_id is set first

		if ($_REQUEST['close_fleet_id'] != '')
		{
			$fleet_id = intval($_REQUEST['close_fleet_id']);
			if ($fleet_id > 0)
			{
				$sql = "UPDATE fleet set closeTimestamp=now(), verified=0 WHERE fleet_id=$fleet_id";
				$mysqli->query($sql);
			
			}
		}
		
		if ($_REQUEST['reopen_fleet_id'] != '')
		{
			$fleet_id = intval($_REQUEST['reopen_fleet_id']);
			if ($fleet_id > 0)
			{
				$sql = "UPDATE fleet set closeTimestamp=NULL, verified=0 WHERE fleet_id=$fleet_id";
				$mysqli->query($sql);
			
			}
		}		
		
	
		// display all fleets
		$sql = "SELECT f.verified, a.user_name as character_name, fleet_id, fleet_tracker_id, fleet_name, 
restrict_to_alliance, openTimestamp, closeTimestamp FROM
fleet f, auth_users a WHERE a.user_id = f.user_id ORDER BY openTimestamp DESC";
		
		echo "<table style=\"width: 95%\"><tr class=\"td_header\"><th>Fleet Name</th><th>Fleet Tracker Link</th>
		<th>Opened at</th><th>Closed at</th><th>Action</th></tr>";
		
		$res = $mysqli->query($sql);
		
		$i = 0;
		
		while ($row = $res->fetch_array())
		{
			// 
			if (($i % 2) == 0)
			{
				$bg_class="td_darkgray";
			}
			else
			{
				$bg_class="td_lightgray";
			}
		
			$fleet_id = $row['fleet_id'];
			$fleet_tracker_id = $row['fleet_tracker_id'];
			$fleet_name = $row['fleet_name'];
			$openTimestamp = $row['openTimestamp'];
			$closeTimestamp = $row['closeTimestamp'];
			$restrict_to_alliance = $row['restrict_to_alliance'];
			$character_name = $row['character_name'];
			$verified = $row['verified'];
			
			$text_color = '#ffffff';
			
			if ($verified == 1)
				$text_color = 'yellow';
			
			$actions = "";
			
			if ($closeTimestamp === NULL)
			{
				$actions .= "<a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets&close_fleet_id=$fleet_id\">Close fleet</a><br />";

				$closeTimestamp = "Not closed yet";
				
				$tracker_link = "<textarea cols=\"50\">$url?fleet_link=$fleet_tracker_id</textarea>";
			} else 
			{
				$tracker_link = "Tracking closed.";
				
				$actions .= "<a href=\"api.php?action=admin_fleet_tracker&mod=list_fleets&reopen_fleet_id=$fleet_id\">Re-open fleet</a><br />";
			}			
		
			
			
			echo "<tr class=\"$bg_class\"><td style=\"color: $text_color\">$fleet_name</td><td>$tracker_link</td>
				<td>$openTimestamp<br />by $character_name</td><td>$closeTimestamp</td><td>$actions <br />
				<a href=\"api.php?action=admin_fleet_tracker&mod=show_fleet&fleet_id=$fleet_id\">Show participation</a></td></tr>";
				
			$i++;
		}
		echo "</table>";
		
		echo "<b style=\"color: $text_color\">Yellow Text</b> - Fleet has been verified<br />";
		
		break;

	
}


base_page_footer('','');



?>