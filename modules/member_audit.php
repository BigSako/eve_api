<?php
if (isset($_REQUEST['members']))
    $members = $_REQUEST['members'];
else
    $members = "";
	
	$filter = false;
	if (isset($_REQUEST['filter']))
		$filter = true;
		
	$corp_id = intval($_REQUEST['corp_id']);
	
	if ($corp_id < 1)
		exit;
		
	if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
	{
		echo "Not allowed";
		exit;
	}
		
		
	// get corp name
	$res2 = $db->query("select corp_name from corporations where corp_id='$corp_id'");
	
	if ($res2->num_rows != 1)
	{
		echo "corp not found";
		exit;
	}
	
	
	
	$where = "";
	$title = "";
	
	switch ($members)
	{
		case "all":
			$where = "1=1";
			$title = "All members";
			break;
		case "registered":
			$where = "c.state=0";
			$title = "Only registered members";
			break;
		case "missing":
			$where = "c.state <> 0 AND c.character_id <= 2099999999";
			$title = "Only members without API registered (DUST members are filtered automatically)";
			break;
		case "dust":
			$where = "c.character_id > 2099999999";
			$title = "Only Dust 514 characters";
			break;
		case "supersandtitans":
			$where = "c.shipType in ('Nyx', 'Aeon', 'Hel', 'Wyvern', 'Revenant', 'Vendetta', 'Vanquisher', 'Avatar', 'Erebus', 'Ragnarok', 'Leviathan') ";
			$title = "Supers and Titans only";
			break;
        case "supers":
            $where = "c.shipType in ('Nyx', 'Aeon', 'Hel', 'Wyvern', 'Revenant', 'Vendetta') ";
            $title = "Supers only";
            break;
        case "titans":
            $where = "c.shipType in ('Avatar', 'Erebus', 'Ragnarok', 'Leviathan', 'Vanquisher') ";
            $title = "Titans only";
            break;
		default:
			echo "invalid option";
			exit;
	}


	$result=$res2->fetch_array();
	$corp_name=$result['corp_name'];

	// get member count without dust members
	$sth2 = $db->query("select count(uid) as max from corp_members where corp_id='$corp_id' AND character_id <= 2099999999");
	$result=$sth2->fetch_array();
	$corp_max=$result['max'];

	// get registered member count
	$sth2 = $db->query("select count(uid) as current from corp_members where corp_id='$corp_id' and state>1");
	$result=$sth2->fetch_array();
	$corp_current=$result['current'];

	$percent=round(100-(($corp_current / $corp_max)*100),2);
	
	base_page_header('',"Member Audit $corp_name - $title","Member Audit $corp_name - $title");

	
	echo "<b>Filter:</b> <a href=\"api.php?action=member_audit&members=all&corp_id=$corp_id\">All</a> | 
	<a href=\"api.php?action=member_audit&members=registered&corp_id=$corp_id\">Registered</a> | 
			<a href=\"api.php?action=member_audit&members=missing&corp_id=$corp_id\">Missing</a> |
			<a href=\"api.php?action=member_audit&members=supersandtitans&corp_id=$corp_id\">Supers and Titans</a> |
			<a href=\"api.php?action=member_audit&members=supers&corp_id=$corp_id\">Just Supers</a> |
			<a href=\"api.php?action=member_audit&members=titans&corp_id=$corp_id\">Just Titans</a> |
			<a href=\"api.php?action=member_audit&members=dust&corp_id=$corp_id\">Dust 514</a><br />";
			
	if (!$filter)
		echo "<a href=\"api.php?action=member_audit&members=$members&corp_id=$corp_id&filter=true\">Group by Main Members</a>";
	else
		echo "Grouped by Main Members - <a href=\"api.php?action=member_audit&members=$members&corp_id=$corp_id\">Undo</a>";
		
	echo "<br /><br />";
	
	
	
	


	echo "Total Members: $corp_max<br />";
	echo "Validated Members: " . ($corp_max - $corp_current) . " ($percent%)<br />";
	echo "Missing: " . ($corp_current) . "<br />";

	if (!$filter)
	{
		echo "<br />This table is interactive, you can sort it by clicking on the column titles!<br />";
	}
	echo "<br />";


	echo "<table style=\"width: 95%\"";

	if(!$filter) // disable tablesorter only if filter is active
		echo "class=\"tablesorter\"";

	echo "id=\"memberList\">";

	// print table header
	echo "<thead><tr><th>Character Name</th>
		<th>State</th>
		<th>Last EvE Logon</th>
		<th>Location</th>
		<th>Ship</th>
		</tr>
	</thead>";
		
		
	if (!$filter)
	{
		$sql = "select c.corp_id, c.character_id, c.character_name, forum_id, c.state, c.shipType, c.location, c.logonDateTime 
				from corp_members c
				WHERE c.corp_id = $corp_id and $where ORDER BY c.character_name ASC";
	} else {
		$sql = "select u.user_name, u.user_id, c.corp_id, c.character_id, c.character_name, u.forum_id, c.state, c.shipType, c.location, c.logonDateTime 
				from corp_members c, api_characters a, auth_users u
				WHERE c.corp_id = $corp_id and $where AND a.character_id = c.character_id AND a.user_id = u.user_id ORDER BY u.user_name, c.character_name ASC";
	}

	echo "<!-- SQL = '$sql' --> \n";
	
	$res = $db->query($sql);
			
	if ($db->error)
	{
		echo $sql;
		echo $db->error;
		exit;
	}
	
	
	$cnt = 0;
	
	$collectNames = "";
	
	$last_user_name = "";
	
	while($result=$res->fetch_array()) 
	{
		$corp_id=$result['corp_id'];
		$character_id=$result['character_id'];
		$character_name=$result['character_name'];
		$forum_id=$result['forum_id'];
		$state=$result['state'];
		$shipType = $result['shipType'];
		if ($filter)
		{
			$user_name = $result['user_name'];
			$user_id = $result['user_id'];
		}
		
		if ($shipType == 'Unknown Type')
		{
			$shipType = 'docked';
		}
		
		
		$collectNames.= $character_name . "\n";
		
		$location = $result['location'];
		$logonDateTime = $result['logonDateTime'];


		$state_text=return_state_text($state);
		$state_class=return_state_class($state);
		
		if ($character_id > 2099999999)
		{
			$state_text .= " (DUST 514)";
		}
		

		if ($filter && $user_name != $last_user_name)
		{
			echo "<tr><th>
				<b><a style=\"color: #ffffff\" href=\"api.php?action=show_member&user_id=$user_id\">$user_name</a></b></th><th colspan=\"4\">&nbsp;</th></tr>";
			$last_user_name = $user_name;
		}
		
		echo "<tr>
				<td><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a></td>
				<td class=\"$state_class\">$state_text</td>
				<td>$logonDateTime</td>
				<td>$location</td>
				<td>$shipType</td>
			</tr>";
			
			
		$cnt++;
		

	}
	
	echo "</table>";
	
	echo "<br />";
	
	echo "<h3>List of names for copy paste</h3>";
	echo "<textarea rows=\"10\" cols=\"30\">$collectNames</textarea><br /><br />";
	
	// get last executed
	$sql = "SELECT last_executed FROM cronjobs WHERE name='corp_members'";
	$res = $db->query($sql);
	
	$row = $res->fetch_array();
	echo "Last updated: $row[last_executed]<br /><br />";

if(!$filter) { // disable tablesorter if filter is active
	echo '<script>
$(document).ready(function()
{
    $("#memberList").tablesorter();
});

</script>';
}
	
	
	base_page_footer('',"<a href=\"api.php?action=human_resources&corp_id=$corp_id\">Back</a>");

	
?>