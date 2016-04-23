<?php
if (isset($_REQUEST['members']))
	$members = $_REQUEST['members'];
else
    $members = "";
		
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
    $filter = false;
	
	switch ($members)
	{
		case "carrier":
			$where = "s.typeID IN (" . implode(",", $carriers_id) . ")";
			$title = "with carriers";
			break;
		case "dread":
            $where = "s.typeID IN (" . implode(",", $dreads_id) . ")";
			$title = "with dreads";
			break;
		case "nodread":
            $where = "s.typeID IN (" . implode(",", $dreads_id) . ")";
			$title = "without a dread";
            $filter = true;
			break;
		case "nocarrier":
            $where = "s.typeID IN (" . implode(",", $carriers_id) . ")";
			$title = "without a carrier";
            $filter = true;
			break;
		default:
			echo "invalid option";
			exit;
	}


	$result=$res2->fetch_array();
	$corp_name=$result['corp_name'];



	base_page_header('',"Member Audit $corp_name - List all members $title","Member Audit $corp_name - List all members  $title");

	
	echo "<b>Filter members:</b><ul>
	<li><a href=\"api.php?action=member_audit_byship&members=carrier&corp_id=$corp_id\">Has Carrier</a></li>
	<li><a href=\"api.php?action=member_audit_byship&members=dread&corp_id=$corp_id\">Has Dread</a></li>
	<li><a href=\"api.php?action=member_audit_byship&members=nocarrier&corp_id=$corp_id\">Does not have a Carrier</a></li>
	<li><a href=\"api.php?action=member_audit_byship&members=nodread&corp_id=$corp_id\">Does not have a Dread</a></li>
	</ul><br /><br />";
			


	echo "<table style=\"width: 95%\" id=\"memberList\">";

	// print table header
	echo "<thead><tr><th>Character Name</th>
		<th>Ship</th>
		<th>Location</th>
		</tr>
	</thead>";

    if (!$filter) {
        $sql = "select u.user_name, u.user_id, c.corp_id, c.character_id, c.character_name,
u.forum_id, c.state, c.shipType, c.location, c.logonDateTime, s.locationID, s.typeID, i.typeName
			from corp_members c, api_characters a, auth_users u, player_supercarriers s, eve_staticdata.invTypes i
			WHERE c.corp_id = $corp_id and $where AND a.character_id = c.character_id
			AND a.user_id = u.user_id AND s.character_id = c.character_id AND i.typeID = s.typeID AND
			$where
			ORDER BY u.user_name, c.character_name ASC";
    } else {
        $sql = "select u.user_name, u.user_id, c.corp_id, c.character_id, c.character_name,
u.forum_id, c.state, c.shipType, c.location, c.logonDateTime
			from corp_members c, api_characters a, auth_users u
			WHERE c.corp_id = $corp_id AND a.character_id = c.character_id
			AND a.user_id = u.user_id AND u.user_id NOT IN
			(select u.user_id FROM api_characters a, auth_users u, player_supercarriers s
            WHERE a.character_id = s.character_id AND $where
            AND u.user_id = a.user_id AND a.corp_id = $corp_id
            GROUP BY u.user_id)
			ORDER BY u.user_name, c.character_name ASC";
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

        $user_name = $result['user_name'];
		$user_id = $result['user_id'];

        if (!$filter) {
            $location_id = $result['locationID'];
            $shipType = $result['typeName'];
        }
        else {
            $location_id = -1;
            $shipType = $result['shipType'];
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
		

		if ($user_name != $last_user_name)
		{
			echo "<tr><th>
				<b><a style=\"color: #ffffff\" href=\"api.php?action=show_member&user_id=$user_id\">$user_name</a></b></th><th colspan=\"3\">&nbsp;</th></tr>";
			$last_user_name = $user_name;
		}
		
		echo "<tr>
				<td><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a></td>
				<td>$shipType</td>
				<td>$location</td>
			</tr>";
			
			
		$cnt++;
		

	}
	
	echo "</table>";
	
	echo "<br />";
	
	echo "<h3>List of names for copy paste</h3>";
	echo "<textarea rows=\"10\" cols=\"30\">$collectNames</textarea><br /><br />";

	
	
	base_page_footer('',"<a href=\"api.php?action=human_resources&corp_id=$corp_id\">Back</a>");

	
?>