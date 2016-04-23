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
		echo "Not allowed";
		exit;
	}

	$corprow = $res->fetch_array();


	if (isset($_REQUEST['solarSystemName']))
		$solarSystemName = $db->real_escape_string($_REQUEST['solarSystemName']);
	else
		$solarSystemName = "";


	// echo "<a href=\"api.php?action=assets&action2=ship_management&action3=add&corp_id=$corp_id&itemID=$itemID\">Register ship as corp ship</a>";
	// process ship management add actions
	if (isset($_REQUEST['action3']))
	{
		if ($_REQUEST['action3'] == 'add' && $_REQUEST['action2'] == 'ship_management')
		{
			$itemID = intval($_REQUEST['itemID']);
			$typeID = intval($_REQUEST['typeID']);
			if ($itemID > 0)
			{
				$sql3 = "INSERT INTO corp_ships (typeID, itemID, comment, registered, solarSystemName) VALUES ('$typeID', '$itemID', '', NOW(), '$solarSystemName' ) ON DUPLICATE KEY UPDATE registered=NOW() ";
				$db->query($sql3);
			}
		}
	}


	if ($solarSystemName != '')
	{			
		// get solar system id
		$id = getSolarSystemID($solarSystemName);

		$action2 = "";
		if (isset($_REQUEST['action2']))
			$action2 = $_REQUEST['action2'];
		
		if ($id != -1)
		{
			base_page_header('',$corprow['corp_name'] . " Corp Assets for $solarSystemName",$corprow['corp_name'] . " Corp Assets for $solarSystemName");
			$cnt = 0;
			$where = "1 = 1";
			$where_sub = "1 = 1";
			if ($action2 == 'ship_management')
			{
				// select offices in main query
				//$where = "t.groupID = ";
				// select ships in subquery
				$where_sub = "t.groupID IN (SELECT groupID FROM ship_size)";
			}

			
			$res = $db->query("
	select m.solarSystemName, o.*, t.typeName, t.volume, t.capacity, t.published FROM

	(
	SELECT 

	CASE 
	WHEN a.locationID BETWEEN 0 AND 60000000 THEN (
	SELECT s.solarSystemID
	FROM eve_staticdata.mapSolarSystems AS s
	WHERE s.solarSystemID = a.locationID
	)
	WHEN a.locationID BETWEEN 66000000 AND 66015004 THEN (
	SELECT s.solarSystemID
	FROM eve_staticdata.staStations AS s
	WHERE s.stationID = a.locationID -6000001
	)
	WHEN a.locationID BETWEEN 66015005 AND 67999999 THEN (

	SELECT c.solarSystemID
	FROM " . DB_NAME . ".conqStations AS c
	WHERE c.stationID = a.locationID -6000000
	)
	WHEN a.locationID BETWEEN 60014861 AND 60014928 THEN (
	SELECT c.solarSystemID
	FROM " . DB_NAME . ".conqStations AS c
	WHERE c.stationID = a.locationID
	)
	WHEN a.locationID
	BETWEEN 60000000 AND 61000000 
	THEN (

	SELECT s.solarSystemID
	FROM eve_staticdata.staStations AS s
	WHERE s.stationID = a.locationID
	)
	END as solarID


	, a.locationID as locID, a.typeID, a.parentItemID, flag, singleton, rawQuantity, inSpace, quantity, realName, a.itemID
	FROM " . DB_NAME . ".corp_assets a WHERE corp_id = $corp_id
	) as o, eve_staticdata.mapSolarSystems m, eve_staticdata.invTypes t WHERE 
	o.solarID = m.solarSystemID
	AND m.solarSystemID = $id AND t.typeID = o.typeID AND $where
	ORDER BY o.inSpace ASC
	");
	
		echo "<table style=\"width: 100%\"><tr><th class=\"table_header\">Location</th><th class=\"table_header\">Item Name</th><th class=\"table_header\">Amount / Capacity</th></tr>";
		
		$lastInSpace = -1;
		
		echo "<tr><td colspan=\"3\" class=\"long_table_header\">In Station</td></tr>";

		
		$totalPrice = 0.0;
	
		while ($row = $res->fetch_array())
		{
			
			$inSpace = $row['inSpace'];
			$typeName = $row['typeName'];
			$published = $row['published'];
			$itemID = $row['itemID'];
			$flag = $row['flag'];
			$locId = $row['locID'];
			$amount = $row['quantity'];
			$typeID = $row['typeID'];
			
			$price = "N/a";
			if ($published != '0')
			{
				$price = request_price($typeID);
				$price = $price['sell'];
				
				$totalPrice  += $amount * $price;
			}
			
			
			if ($lastInSpace != $inSpace && $inSpace == '1')
			{
				echo "<tr><td colspan=\"3\" class=\"long_table_header\">In Space</td></tr>";
			}
			
			
			$location = inventoryFlagToName($flag);
			
			echo "<tr class=\"bg" . ($cnt % 2) ."\" id=\"row$itemID\"><td>$location</td><td>$typeName</td>";
			if ($published != '0')
				echo "<td>$amount ($price ISK each)</td>";		
			else
				echo "<td>$amount</td>";	
			echo "</tr>";	
			
			
			
			echo "<!-- ";
			print_r($row);
			echo "-->";
			
			// check for sub items
			$sql2 = "SELECT t.typeName, c.itemID, c.typeID, c.locationID, c.flag, c.singleton, c.rawQuantity, c.quantity, c.inSpace, c.realName FROM 
				" . DB_NAME . ".corp_assets c, eve_staticdata.invTypes t WHERE t.typeID = c.typeID AND c.parentItemID = $itemID AND $where_sub ORDER BY t.typeName ASC";
			$res2 = $db->query($sql2);
			if ($res2->num_rows != 0)
			{
				while ($row2 = $res2->fetch_array())
				{
					$subItemName = $row2['typeName'];
					$itemID = $row2['itemID'];
					$typeID = $row2['typeID'];
					$flag = $row2['flag'];
					$locId = $row2['locationID'];
					$amount = $row2['quantity'];
					$subRealName = $row2['realName'];
					
					$location = inventoryFlagToName($flag);
					
					$price = "N/a";
					if ($published != '0')
					{
						$price = request_price($typeID);
						$price = $price['sell'];
						
						$totalPrice  += $amount * $price;
					}
					echo "<tr class=\"bg" . ($cnt % 2) ."\">";
					
					if ($published != '0')
						echo "<td id=\"row$itemID\">$location</td><td>$subItemName ($subRealName)</td><td>$amount ($price ISK each)</td></tr>";		
					else
						echo "<td id=\"row$itemID\">$location</td><td>$subItemName ($subRealName)</td><td>$amount</td></tr>";	

					if ($action2 == 'ship_management')
					{
						echo "<tr class=\"bg" . ($cnt % 2) ."\"><td>&nbsp;</td><td colspan=\"3\">";
						// check if ship is a corp registered ship
						$smql = "SELECT typeID, itemID, comment, registered FROM corp_ships WHERE itemID = $itemID";
						$mres = $db->query($smql);
						if ($mres->num_rows == 0)
						{
							echo "<a 
href=\"api.php?action=assets&action2=ship_management&action3=add&solarSystemName=$solarSystemName&corp_id=$corp_id&itemID=$itemID&typeID=$typeID#row$itemID\">
								Register $subItemName as corp ship</a>";
						} else {
							$row = $mres->fetch_array();
							echo "<a href=\"api.php?action=corp_ships&corp_id=$corp_id&typeID=$typeID\">CORP SHIP</a> (ItemID: $itemID " . $row['comment'] . ")";
						}
						echo "</tr>";
					}

					$cnt++;

				}
			}
			
			$lastInSpace = $inSpace;
		}
		
		echo "</table>";
		
		$estimated_value_str = number_format($totalPrice, 2, '.', ',');
		
		
		echo "<br />Total value of items in there: $estimated_value_str<br />";
	
	

		} else {
			echo "ERROR: Solar system not found.";
			
			exit;
		}
		
	}	
	
	
	
		
	echo "<br /><br />Last updated: ";
	
	$sql = "SELECT last_executed FROM cronjobs WHERE name = 'offices' ";
	
	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	echo $row['last_executed'];
	echo "<br />";



	base_page_footer('1','');
?>
