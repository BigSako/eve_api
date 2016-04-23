<?php

	do_log("Entered member_audit_role",5);
	
	$members = $_REQUEST['members'];
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
		case "fuelTechnician":
			$where = "(roles &pow(2,58)) = pow(2,58) ";
			$title = "All members with Fuel Technician";
			break;
		case "configEquipment":
			$where = "(roles & pow(2,41)) = pow(2,41) ";
			$title = "All members with Config Equipment";
			break;
		case "configStarbase":
			$where = "(roles & pow(2,53)) = pow(2,53) ";
			$title = "All members with Config Starbase Equipment";
			break;
		case "starbaseDefense": // starbase defense role
			$where = "(roles & pow(2,57)) = pow(2,57) ";
			$title = "All members with Starbase Defense role";
			break;
		case "starbases": // several starbase roles
			$where = "( (roles & pow(2,53)) = pow(2,53) OR (roles & pow(2,57)) = pow(2,57) OR (roles & pow(2,58)) = pow(2,58) )";
			$title = "All members with any POS role";
			break;
		case "director": 
			$where = "(roles & 1) = 1";
			$title = "All Directors";
			break;
		case "stationManager": // 2048
			$where = "(roles & pow(2,11)) = pow(2,11) ";
			$title = "All members with Station Managers role";
			break;
		case "rentOffice": // 2048
			$where = "(roles & pow(2,49)) = pow(2,49) ";
			$title = "All members with Rent Office role";
			break;
		default:
			echo "invalid option";
			exit;
	}
	
	
	
	
	base_page_header('',"Member Audit - $title","Member Audit - $title");
	$db = connectToDB();
	
	
	echo "<b>Filter:</b> <a href=\"api.php?action=member_audit_role&members=all&corp_id=$corp_id\">All</a><br />
		POS Stuff: <a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=configStarbase\">Starbase Config</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=fuelTechnician\">Fuel Technician</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=starbaseDefense\">POS Gunner</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=starbases\">Several POS Roles (Fuel, Config, Gunnery)</a><br />
		Others: 
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=configEquipment\">Config Equipment</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=stationManager\">Station Manager</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=rentOffice\">Rent Office</a> |
		<a href=\"api.php?action=member_audit_role&corp_id=$corp_id&members=director\">Director</a>
	
	<br /><br />";
	
	
	
	
	$result=$res2->fetch_array();
	$corp_name=$result['corp_name'];
	
	// get member count without dust members
	$sth2 = $db->query("select count(uid) as max from corp_members where corp_id='$corp_id' AND character_id <= 2099999999");
	$result=$sth2->fetch_array();
	$corp_max=$result['max'];
	
	
	// get member count without dust members, with $where applied
	$sth2 = $db->query("select count(uid) as max from corp_members where corp_id='$corp_id' AND $where AND character_id <= 2099999999");
	$result=$sth2->fetch_array();
	$corp_current=$result['max'];


	
	
	echo "$corp_name - $corp_current characters<br />";

	echo "<table style=\"width: 95%\">";
		
	echo "<tr>
	<th>Character Name<br />Main / Account</th>
		<th width=\"300\">Roles</th>
		<th>Location</th>
		<th>Last Login</th>
		<th>Current Ship</th>
		</tr>";
	
	$sql = "SELECT corp_id, character_id, character_name, forum_id, state, roles, grantableRoles, shipType, location, logonDateTime from corp_members 
			WHERE corp_id = $corp_id and $where ORDER BY character_name ASC";
	
	$res = $db->query($sql);
			
	if ($db->error)
	{
		echo $sql;
		echo $db->error;
		exit;
	}
	
	
	$cnt = 0;
	
	$collectNames = "";
	
	while($result=$res->fetch_array()) 
	{
		$corp_id=$result['corp_id'];
		$character_id=$result['character_id'];
		$character_name=$result['character_name'];
		$forum_id=$result['forum_id'];
		$state=$result['state'];
		$shipType = $result['shipType'];
		$roles = $result['roles'];
		$grantableRoles = $result['grantableRoles'];		

		// find out who this toon belongs to
		$sql2 = "SELECT u.user_name FROM api_characters a, auth_users u WHERE a.character_id = $character_id AND a.user_id = u.user_id";
		$res2 = $db->query($sql2);
		if ($res2->num_rows == 1)
		{
			$row2 = $res2->fetch_array();
			$primary_user_name = $row2['user_name'];
		} else {
			$primary_user_name = "!!!Unknown!!!";
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
		
		$bgclass = "bg" . ($cnt % 2);
		
		echo "<tr class=\"$bgclass\">
				<td><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a><br />$primary_user_name</td>
				<td>";
				
		$binarr = array_reverse(str_split(decbin($roles)));
		
		if ($binarr[0] == 1)
		{
			echo "Director";
		} else {

			for ($i = 0; $i < count($binarr); $i++)
			{
				if ($binarr[$i] == 1)
				{
					if ($roleDescriptionArray[$i] != "")
						echo $roleDescriptionArray[$i] . "  <br />";
				}
			}
		}
				
				
		echo "</td>
				<td>$location</td>
				<td>$logonDateTime</td>
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
	
	
	base_page_footer('',"<a href=\"api.php?action=human_resources&corp_id=$corp_id\">Back</a>");

	
?>