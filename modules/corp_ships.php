<?php


	$corp_id = -1;
	
	// check for corp id
	if (!isset($_REQUEST['corp_id']))
	{
		// check if is admin
		if ($isAdmin == true && !isset($_REQUEST['ignore_main_corp_id']))
		{
			$corp_id = $SETTINGS['main_corp_id'];
		} else if (count($director_corp_ids) == 1) 
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=assets&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Corp Assets - Select Corporation","Corp Assets - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=assets&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');			
			
			exit;
		} else if ($isAdmin == true && isset($_REQUEST['ignore_main_corp_id']))
		{
			// display corp selection page
			base_page_header('',"Corp Assets - Select Corporation","Corp Assets - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=assets&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');		
			
			exit;
		}		
		else // if 0
		{
			echo "Not allowed.";
			exit;			
		}		
	}
	
	
	if (isset($_REQUEST['corp_id']))
		$corp_id = intval($_REQUEST['corp_id']);
	
	if ($corp_id < 1)
		$corp_id = intval($SETTINGS['main_corp_id']);
	
	$db = connectToDB();


	$sql = "SELECT corp_name, ceo, corp_ticker FROM corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		echo "invalid corp";
		exit;
	}
	
	if (!in_array($corp_id, $director_corp_ids) && $isAdmin == false)
	{
		// check if user has a toon in one of the corporations (does not need to be a director)
		$sql3 = "SELECT DISTINCT corp_id FROM api_characters WHERE user_id = " . $GLOBALS['userid'] . " AND state <= 10 AND corp_id = $corp_id ";
		$res3 = $db->query($sql3);
		if ($res3->num_rows == 0)
		{
			echo "Not allowed";
			exit;
		}
	}

	$corprow = $res->fetch_array();

	if (isset($_REQUEST['setState']))
	{
		$newState = intval($_REQUEST['setState']);
		$itemID = intval($_REQUEST['itemID']);

		$sql5 = "UPDATE corp_ships SET state = $newState WHERE itemID = $itemID ";
		$db->query($sql5);
	}

	if (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'comment' && $_REQUEST['comment'] != '')
	{
		$itemID = intval($_REQUEST['itemID']);
		$comment = $db->real_escape_string($_REQUEST['comment']);
		$sql6 = "UPDATE corp_ships SET `comment` = '$comment' WHERE itemID = $itemID ";
	}

	$custom_javascript=<<<EOF
	
<script>
function toggle_item(item_id)
{
	if( document.getElementById(item_id).style.display!='' ){
		document.getElementById(item_id).style.display = '';
	}else{
		document.getElementById(item_id).style.display = 'none';
	}
}
</script>

EOF;

	base_page_header($custom_javascript ,$corprow[corp_name] . " Corp Ships", $corprow[corp_name] . "Corp Ships");

	// print corp asset link just for them!
	if (in_array($corp_id, $director_corp_ids) || $isAdmin == true)
	{
		echo "<a href=\"api.php?action=assets&corp_id=$corp_id\">Show all assets</a><br />";
	}

	$sql = "SELECT s.typeID, t.typeName, s.itemID, s.uid, s.comment, s.registered, s.solarSystemName, s.state 
	FROM corp_ships s, eve_staticdata.invTypes t WHERE t.typeID = s.typeID
	ORDER BY t.typeName ASC ";

	$res = $db->query($sql);

	$filter = 0;
	if (isset($_REQUEST['filter']))
		$filter = intval($_REQUEST['filter']);
		
	echo "Filter: <a href=\"api.php?action=corp_ships&filter=0&corp_id=$corp_id\">Show All</a> | <a href=\"api.php?action=corp_ships&filter=1&corp_id=$corp_id\">Show Missing</a><br /><br />";

	$table_header =  "<table style=\"width: 100%\"><tr>
	<th class=\"table_header\">item ID</th>
	<th class=\"table_header\">Item Name</th>
	<th class=\"table_header\">Last seen</th>
	<th class=\"table_header\">Corp Assets</th>
	<th class=\"table_header\">State</th>
	</tr>";


	$last_ship_type = -1;

	$expandTypeID = intval($_REQUEST['typeID']);

	
	while ($row = $res->fetch_array())
	{
		$okay = true;
		$row_str =  "<tr>";
		$itemID = $row['itemID'];
		$typeID = $row['typeID'];
		$typeName = $row['typeName'];
		$uid = $row['uid'];
		$comment = $row['comment'];
		$registeredTime = $row['registered'];
		$state = corp_ship_state($row['state']);
		$solarSystemName = $row['solarSystemName'];

		if ($last_ship_type != $typeID)
		{
			if ($last_ship_type != -1)
			{
				echo "</table></div><br />";
			}
			// print header div
			$count = 0;
			$sql6 = "SELECT COUNT(*) as c FROM corp_ships WHERE typeID = $typeID";
			$res6 = $db->query($sql6);
			$row6 = $res6->fetch_array();
			$count = $row6['c'];

			echo "<div id=\"corp_name_header\" onClick='toggle_item($typeID)'><b><u>$typeName - $count</u></b></div>";
			echo "<div id=\"$typeID\" ";
			
			if ($expandTypeID != $typeID)
				echo "style=\"display: none;\">";
			else
				echo " >";

			echo $table_header;
		}
		
		$row_str .=  "<td>$itemID</td><td>$typeName</td><td>$registeredTime <br />in<br /> $solarSystemName</td>";

		// check if it's still in corp assets		
		$sql4 = "SELECT itemID, corp_id, typeID, locationID, flag, singleton, quantity, inSpace, realName, x, y, z FROM corp_assets WHERE itemID = $itemID ";
		$res4 = $db->query($sql4);
		
		if ($res4->num_rows == 0)
		{
			$okay = false;
			// if not in corp assets, look in player assets

			$sql5 = "SELECT a.character_id, a.locationID, a.realName, c.character_name, u.user_name, u.user_id FROM player_assets a, api_characters c, auth_users u
			WHERE a.character_id = c.character_id AND c.user_id = u.user_id AND a.itemID = $itemID
			";
			$res5 = $db->query($sql5);
			if ($res5->num_rows != 0)
			{			
				$row5 = $res5->fetch_array();
				$row_str .=  "<td><b>On " . $row5['character_name'] . " ( " . $row5['user_name'] . " ) </b></td>";
				
			} else {
				$row_str .=  "<td>Location unknown, maybe dead?</td>";
				
			}
		} else {
			$row_str .=  "<td>in corp assets</td>";
		}
		
		// if not there, vOv

		// give options to change state
		$row_str .=  "<td>Cur: $state<br />
		Set state to: <a href=\"api.php?action=corp_ships&itemID=$itemID&setState=0&filter=$filter&typeID=$typeID&corp_id=$corp_id\">OK</a> | 
		<a href=\"api.php?action=corp_ships&itemID=$itemID&setState=1&filter=$filter&typeID=$typeID&corp_id=$corp_id\">Unknown</a> | 
		<a href=\"api.php?action=corp_ships&itemID=$itemID&setState=99&filter=$filter&typeID=$typeID&corp_id=$corp_id\">Dead</a>";

		if ($row['state'] == 99) // dead
		{
			$row_str .= "<br />Comment: <form method=\"post\" action=\"api.php?action=corp_ships&itemID=$itemID&setState=1&filter=$filter&typeID=$typeID&action2=comment&corp_id=$corp_id\">
			<input type=\"text\" name=\"comment\" value=\"" . $row['comment'] . "\" /> <input type=\"submit\" value=\"Save\" /></form>";
		}


		$row_str .= "</td>";

		$row_str .=  "</tr>";

		if ($filter == 1 && $okay == false)
			echo $row_str;
		else if ($filter == 0)
			echo $row_str;

		$last_ship_type  = $typeID;
	}
	
	echo "</table>";

	base_page_footer('', '');



?>
