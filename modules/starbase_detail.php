<?php
	include("funcs/starbases.php");
	
	$corp_id = $SETTINGS['main_corp_id'];
	
	if ($_REQUEST['corp_id'])
	{
		$corp_id = intval($_REQUEST['corp_id']);
	}
	
	if ($corp_id == 0)
	{
		exit;
	}
	
	if (!in_array($corp_id, $corp_ids) && !$isAdmin)
	{
		echo "Not allowed";
		exit;
	}
	

	
	$locationID = intval($_REQUEST['locationID']);
	$moonID     = intval($_REQUEST['moonID']);
	$itemID     = intval($_REQUEST['itemID']);
	
	if ($moonID == 0 || $locationID == 0)
	{
		exit;
	}		

	
	$db = connectToDB();
	
	$sovereignty = false;
	
	// get alliance ID and check if this alliance holds sov in that system, which leads to different fuel/stront timers
	$sql = "SELECT alliance_id from corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);
	if ($res->num_rows == 1)
	{
		$row = $res->fetch_array();
		$allianceID = $row['alliance_id'];		
		
		// check if the system has the same sov as the corp is in --> pos fuel bonus
		$sql = "SELECT COUNT(*) as cnt FROM sovereignty WHERE allianceID = $allianceID AND solarSystemID = $locationID";
		
		$res = $db->query($sql);
		$row = $res->fetch_array();
		if ($row['cnt'] > 0)
		{
			$sovereignty = true;
		} 
		
	} else {
		$allianceID = 0;
	}
	
	
	$sql = "SELECT last_asset_update FROM corp_api_keys WHERE corp_id = $corp_id ";
	
	$res = $db->query($sql);
	$row = $res->fetch_array();
	$last_executed = $row['last_asset_update'];

	
	
	
	$can_edit_starbase_details = false;
	$can_show_starbase_fuel_log = false;
	if (getPageAccessForThisUser('starbase_save') == true) {
		$can_edit_starbase_details = true;
	}
	if (getPageAccessForThisUser('starbase_fuel_log') == true)
	{
		$can_show_starbase_fuel_log = true;
	}

	
	
	
	
	$res = $db->query("SELECT sbComment, r.regionName, m.solarSystemName, m.solarSystemID, i.typeName, locationID, moonID, pos_state, stateTimestamp, d.itemName as moonName,
							onlineTimestamp, standingOwnerID, i.typeID as towerTypeID,  s.itemID as posItemID
				 FROM starbases s, eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r, eve_staticdata.invTypes i, eve_staticdata.mapDenormalize d
WHERE s.corp_id = $corp_id AND s.locationID = $locationID AND moonID = $moonID AND s.itemID = $itemID AND m.solarSystemID = s.locationID AND m.regionID = r.regionID AND i.typeID = s.typeID AND d.itemID = moonID ORDER BY r.regionName, m.solarSystemName, d.itemName");


	if ($res->num_rows != 1) // something is wrong... exiting
		exit;

		
	
	
	$row = $res->fetch_array();
	
	$regionName = $row['regionName'];
	$solarSystemname = $row['solarSystemName'];
	$solarSystemID = $row['solarSystemID'];
	$posItemID = $row['posItemID'];
	$typeName = $row['typeName'];
	$pos_state = $row['pos_state'];
	$stateTimestamp = $row['stateTimestamp'];
	$onlineTimestamp = $row['onlineTimestamp'];
	$standingOwnerID = $row['standingOwnerID'];
	$moonName = $row['moonName'];
	$locationID = $row['locationID'];
	$moonID = $row['moonID'];
	$towerTypeID = $row['towerTypeID'];
	$sbComment   = $row['sbComment'];
	
	
	$names = getTowerNameAndLocation($db, $corp_id, $locationID, $posItemID);
	

	$x = $names[1];
	$y = $names[2];
	$z = $names[3];
	
		
	$silo_base_size = 20000.0;
	
	$tower_silo_bonus = 1.0;
	$tower_fuel_bonus = 1.0; // affected by sov afaik
	
	if (preg_match("/Gallente Control Tower/", $typeName) || preg_match("/Serpentis Control Tower/", $typeName) || preg_match("/Shadow Control Tower/", $typeName))
	{
		$tower_silo_bonus = 2;
	} else if (preg_match("/Amarr Control Tower/", $typeName) || preg_match("/Blood Control Tower/", $typeName) || preg_match("/Sansha Control Tower/", $typeName))
	{
		$tower_silo_bonus = 1.5;
	}
		
	$silo_size = $silo_base_size * $tower_silo_bonus;
	
	
	// based on the locationID and the actual location stored in names[1:3], let's get all silos
	$asset_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName, i.capacity, x, y, z
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.locationID = $locationID AND typeName LIKE '%Silo%' 
			AND i.typeID = a.typeID ORDER BY typeName ASC";
			
	$subRes = $db->query($asset_sql);
	

	$silo_type_id = 0;


    $silo_history = array();
	$silos = "<table width=\"100%\">";
	
	while ($subRow = $subRes->fetch_array())
	{
		$subTypeName = $subRow['typeName'];
		$itemID = $subRow['itemID'];

        $silo_history[$itemID] = array();

		$capacity = $subRow['capacity'];
		$silo_x = $subRow['x'];
		$silo_y = $subRow['y'];
		$silo_z = $subRow['z'];
		$silo_type_id = $subRow['typeID'];
		
		//echo "Diff:" . abs($silo_x - $x) . ", " .  abs($silo_y - $y)  . ", " .  abs($silo_z - $z) . "\n";
		// chekc how far away the structure is from the tower - if it is too far, then it's on a different tower and we need to skip it here
		
		if (max(abs($silo_x - $x),abs($silo_y - $y),abs($silo_z - $z)) > 100000)
		{
			continue;
		}
		
		$silo_size = $capacity * $tower_silo_bonus;
		
		// let's get all contents
		$content_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.parentItemID = $itemID 
			AND i.typeID = a.typeID ORDER BY typeName ASC";
			
		$contentRes = $db->query($content_sql);
		
		$silo_cnt = 0;



		
		while ($contentRow = $contentRes->fetch_array())
		{
			$quantity = $contentRow['quantity'];
			$contentTypeID = $contentRow['typeID'];
			$price = request_price($contentTypeID);
			
			$monthly_price = 24*100*30 * $price['buy'];
			
			$estimated_monthly_str = number_format($monthly_price, 2, '.', ',');
			
			$estimated_value = $price['sell'] * $quantity;
			$estimated_value_str = number_format($estimated_value, 2, '.', ',');
				
			
			// each item (moongoo) in silo has 1 m3 
			// so calculate percentage this way:
			$perc = round($quantity / $silo_size * 100);
			
			$perc_img = getPercentageImage($perc);
			
			$contentTypeName = $contentRow['typeName'];
			$silos .= "<tr><td>$subTypeName</td><td>$contentTypeName</td><td align=\"right\">$quantity / $silo_size - $estimated_value_str ISK</td><td><img alt=\"$perc %\" src=\"images/$perc_img\" /></td></tr>";
			$silos .= "<tr><td colspan=\"2\">Estimated monthly income</td><td align=\"right\">$estimated_monthly_str ISK</td><td>&nbsp;</td></tr>";

			// get the history of this silo for the last 60 days
            $history_sql = "SELECT silo_fill_state,`time` 
            FROM corp_tower_silo_history 
            WHERE silo_item_id = $itemID and 
            moonId = $moonID AND TIMESTAMPDIFF(DAY,`time`,now()) < 60 
            ORDER BY `time` ASC";

            $history_res = $db->query($history_sql);
            $last_fill_state = -1;
			$last_fill_date = 0;
            $silos .= "<tr><td colspan=\"4\"><b>Siphons:</b>";
            $siphons_found = false;
            $history_data = "";
            $history_array = array();
			
			$siphon_dates = array();



            while ($history_row = $history_res->fetch_array())
            {
                $cur_fill_state = $history_row['silo_fill_state'];
				$cur_fill_date = strtotime($history_row['time']);
				// did the fill state change?
                if ($cur_fill_state != $last_fill_state)
                {
                	$date = $history_row['time'];
					
					
					if ($last_fill_date != 0)
					{
						$time_diff = floor( ($cur_fill_date - $last_fill_date) / 3600.0); // in hours
						$calculate_difference = $cur_fill_state - $last_fill_state;
						
						$history_array[$date] = -$cur_fill_state + $last_fill_state;
						
						if ($calculate_difference < 0)
						{
							// someone took something out, so that's okay
						}
						else if ($time_diff > 0.1)
						{						
							// calculate items per hour (should be 100)
							$items_per_hour = $calculate_difference / $time_diff;
							
							if ($items_per_hour < 95)
							{
								$siphons_found = true;
								$siphon_dates[] = $date;
							}						
							
							$siphons_found = true;				
						
						}
					}	
					

                    //$silos .= $cur_fill_state . ", ";
                    $last_fill_state = $cur_fill_state;
					$last_fill_date  = $cur_fill_date;
                }


            }

            $silo_history[$itemID][$contentTypeID] = $history_array;

            if ($siphons_found == false)
            {
                $silos .= "No Siphons found. Check the POS yourself is not sure...";
            } else {
            	$silos .= "Siphons Dates: "; 
            					
            	sort($siphon_dates);				
				
				for ($i = sizeof($siphon_dates)-1; $i >= 0; $i--)
				{
					$siphon_date = $siphon_dates[$i];
					$silos .= $siphon_date . ", ";
				}
			}
            $silos .= "</td></tr>";


			$silo_cnt++;
		}

		
		
		
	}
	
	$silos .= "</table>";

	
	
	
	// based on the locationID and the actual location, let's get all pos structures
	$asset_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName, i.capacity, x, y, z, a.realName, i.groupID
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.locationID = $locationID 
			AND i.typeID = a.typeID ORDER BY typeName ASC";
			
	$subRes = $db->query($asset_sql);
	


	// sort structures by the following group id:
	// 1) storage - silo: 404, moon harvesting: 416, corp hangar: 471, sma: 363)
	// 2) defensive (hardeners: 444)
	// 3) ewar (warp disrupt: 443, stasis web: 441, sensor damp: 440, ecm: 439)
	// 4) movement (cyno generator: 838, jump bridge: 707, cyno jammer: 839)
	// 5) rest
	
	$structureArray = array();
	
	$special_group_ids = array(404, 416, 471, 363, 444, 443, 441, 440, 439, 838, 707, 839);
	
	$special_items = array();
	$rest_items = array();
	
	$subitems = array();
	
	$silo_content = array();
	
	$subitems_divlist = "";
	
	foreach ($special_group_ids as $groupID)
	{	
		$special_items[$groupID] = [];
	}
			
	while ($subRow = $subRes->fetch_array())
	{
		$group_id = $subRow['groupID'];
		
		if ($group_id == 365) // control tower
			continue;
				
		$silo_x = $subRow['x'];
		$silo_y = $subRow['y'];
		$silo_z = $subRow['z'];
		// check if this is in/close to the pos bubble
		if (max(abs($silo_x - $x),abs($silo_y - $y),abs($silo_z - $z)) > 100000) {
			continue;
		}
		
		$typeName2 = $subRow['typeName'];
		
		$structure_type_id = $subRow['typeID'];
		$structure_item_id = $subRow['itemID'];
		
		// query sub items
		$sub_asset_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName, i.capacity, x, y, z, a.realName
		FROM corp_assets a, eve_staticdata.invTypes i
		WHERE a.parentItemID = $structure_item_id
		AND i.typeID = a.typeID ORDER BY typeName ASC";
		$sub_asset_res = $db->query($sub_asset_sql);
		if (!$sub_asset_res)
		{
			echo "ERROR: Failed to execute query '$sql'\n";
			echo $db->error;
		}
			
			
		
		// append either to special groups or "normal" ones
		if (in_array($group_id, $special_group_ids))
		{
			$special_items[$group_id][$structure_item_id] = "<img src=\"//imageserver.eveonline.com/Render/" . $structure_type_id . "_32.png\">" . $typeName2;
			
			
			if ($group_id != 404) // not a silo
			{	
				// special items can contain many things (e.g., sma will contain many ships, etc...)
				if ($sub_asset_res->num_rows > 0)
				{
					$sub_items = "";
					
								
					while ($subAssetRow = $sub_asset_res->fetch_array())
					{
						$sub_items .= $subAssetRow['typeName'] . "," . $subAssetRow['quantity'] . "<br />";
					}
					$subitems_divlist .= '<div class="reveal" id="modalSubItem' . $structure_item_id . '" data-reveal><h3>' . $typeName2 . '</h3>' . $sub_items . "</div>";
				
				} else {
					$subitems_divlist .= '<div class="reveal" id="modalSubItem' . $structure_item_id . '" data-reveal><h3>' . $typeName2 . '</h3> There is nothing in it</div>';
				}
			} else {
				// special case for silo
				$sub_items = "";
				$silo_history_str = "";
				while ($subAssetRow = $sub_asset_res->fetch_array())
				{
					$sub_items .= $subAssetRow['typeName'] . "," . $subAssetRow['quantity'] . " ";
					
					$silo_history_str .= "<b>" . $subAssetRow['typeName'] . "</b><br />";
					
					$last_timestamp = 0;
	                foreach ($silo_history[$structure_item_id][$subAssetRow['typeID']] as $timestamp => $silovalue) {
						
						// add a + if the silovalue is positive
	                    if ($silovalue > 0)
	                    {
	                        $sstr = "+" . $silovalue;
	                    } else {
	                        $sstr = $silovalue;
	                    }
						
						if ($last_timestamp != 0)
						{
							$diff = strtotime($timestamp) - strtotime($last_timestamp);
							$diff = floor($diff / 3600.0);
							$silo_history_str .= $timestamp . ": " . $sstr . " ($diff hours)<br />";
						} else {				
							$silo_history_str .= $timestamp . ": " . $sstr . "<br />";
						}
						
						$last_timestamp = $timestamp;
	                }
						
				}
				
				if ($sub_items != "")
					$sub_items = " (" . $sub_items . ")";

				$silo_content[$structure_item_id] = $sub_items;
				
				// add silo history as a subitem_divlist
				
				
				
				$subitems_divlist .= '<div class="reveal" id="modalSubItem' . $structure_item_id . '" data-reveal><h3>' . $typeName2 . ' History</h3>' . $silo_history_str . '</div>';
			}
			
			
			
		} else {
			$rest_items[$structure_item_id] = "<img src=\"//imageserver.eveonline.com/Render/" . $structure_type_id . "_32.png\">" . $typeName2;
			
			// this is usually an item that does not have much in it (e.g., 1-3 piece of ammo) -> we just add it as a string
			$sub_items = "";
		
			while ($subAssetRow = $sub_asset_res->fetch_array())
			{
				$sub_items .= $subAssetRow['typeName'] . "," . $subAssetRow['quantity'] . " / ";
			}
			
			if ($sub_items != "")
				$sub_items = " (" . $sub_items . ")";
			
			$subitems[$structure_item_id] = $sub_items;
		}

		
		
		
		// check for sub items
		/*$sub_asset_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName, i.capacity, x, y, z, a.realName
			FROM corp_assets a, eve_staticdata.invTypes i
			WHERE a.parentItemID = $structure_item_id
			AND i.typeID = a.typeID ORDER BY typeName ASC";
			
		echo "<!--- SQL: $sub_asset_sql -->\n";
			
		$sub_asset_res = $db->query($sub_asset_sql);
		$sub_items = "";
		
		while ($subAssetRow = $sub_asset_res->fetch_array())
		{
			$sub_items .= $subAssetRow['typeName'] . "," . $subAssetRow['quantity'] . "<br />";

            if (strstr($typeName2, 'Silo'))
            {
                $sub_items .= "History: <br />";
				$last_timestamp = 0;
                foreach ($silo_history[$structure_item_id][$subAssetRow['typeID']] as $timestamp => $silovalue) {
					
					// add a + if the silovalue is positive
                    if ($silovalue > 0)
                    {
                        $sstr = "+" . $silovalue;
                    } else {
                        $sstr = $silovalue;
                    }
					
					if ($last_timestamp != 0)
					{
						$diff = strtotime($last_timestamp) - strtotime($timestamp);
						$diff = $diff / 60.0 / 60.0;
						$sub_items .= $timestamp . ": " . $sstr . " ($diff hours)<br />";
					} else {				
						$sub_items .= $timestamp . ": " . $sstr . "<br />";
					}
					
					$last_timestamp = $timestamp;
                }
            }
		}
		
		$strucs .= "$sub_items</td></tr>"; */
	}
	


	$strucs = $subitems_divlist . ' <ul class="vertical menu" data-accordion-menu>
		<li>
			<a href="#">Silos (' . sizeof($special_items[404]) . '), Moon Harvesting (' . sizeof($special_items[416]) . '), Hangars (' . sizeof($special_items[471]) . ') and SMAs (' . sizeof($special_items[363]) . ')</a>
			<ul class="menu vertical nested">';
			
	// list storage items (silo, corp hangar, sma) here	
	foreach ($special_items[404] as $item_id => $item)
	{		// silo
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item" . $silo_content[$item_id] . "</a></li>";		
	}
	foreach ($special_items[416] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	foreach ($special_items[471] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	foreach ($special_items[363] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	
			
	$strucs .= '</ul></li>
		<li>
			<a href="#">Hardeners (' . sizeof($special_items[444]) . ')</a>
			<ul class="menu vertical nested">';
	
	// list hardeners here	
	foreach ($special_items[444] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}

	
	$strucs .= '</ul></li>
		<li>
			<a href="#">EWAR (' . (sizeof($special_items[443]) + sizeof($special_items[441]) + sizeof($special_items[440]) + sizeof($special_items[439])) . ')</a>
			<ul class="menu vertical nested">';
				
	// list ewar items here	 ewar (warp disrupt: 443, stasis web: 441, sensor damp: 440, ecm: 439)
	foreach ($special_items[443] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	foreach ($special_items[441] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	foreach ($special_items[440] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	foreach ($special_items[439] as $item_id => $item)
	{
		$strucs .= "<li><a data-open=\"modalSubItem" . $item_id . "\">$item</a></li>";		
	}
	
	$strucs .= '</ul></li>
		<li>
			<a href="#">Movement - CynoGen (' . sizeof($special_items[838]) . '), Jammer (' . sizeof($special_items[707]) . '), Jump Bridge (' . sizeof($special_items[839]) . ')</a>
			<ul class="menu vertical nested">';
			
	// movement items here movement (cyno generator: 838, jump bridge: 707, cyno jammer: 839)
	foreach ($special_items[838] as $item_id => $item)
	{
		$strucs .= "<li><a href=\"#\" data=\"modalSubItem" . $item_id . "\">$item</a></li>";
	}
	foreach ($special_items[707] as $item_id => $item)
	{
		$strucs .= "<li><a href=\"#\" data=\"modalSubItem" . $item_id . "\">$item</a></li>";
	}
	foreach ($special_items[839] as $item_id => $item)
	{
		$strucs .= "<li><a href=\"#\" data=\"modalSubItem" . $item_id . "\">$item</a></li>";
	}


	$strucs .= '</ul></li>
		<li>
			<a href="#">Rest (' . sizeof($rest_items) . ')</a>
			<ul class="menu vertical nested">';
			
	// list remaining items here
	foreach ($rest_items as $item_id => $item)
	{
		$strucs .= "<li><a>$item" .  $subitems[$item_id]  . "</a></li>";
	}
	
	$strucs .= '</ul></li>';
	
	$strucs .= "</ul>";
	
	

	
	
	
	$pos_state_text = "Online";
	$timer = "";
	$pos_state_class = "";
	
	switch ($pos_state)
	{
		case 0:
			$pos_state_text = "Unanchored";
			$timer = "TEST: $stateTimestamp $onlineTimestamp";
			$pos_state_class = "invalid";
			break;
		case 1:
			$pos_state_text = "Anchored/Offline";
			$timer = "since $stateTimestamp";
			$pos_state_class = "warning";
			break;
		case 2:
			$pos_state_text = "Onlining";
			$timer = "until $onlineTimestamp";
			$pos_state_class = "";
			break;
		case 3:
			$pos_state_text = "Reinforced";
			$timer = "until $stateTimestamp";
			$pos_state_class = "warning";
			break;
		case 4:
			$pos_state_text = "Online";
			$timer = "";
			$pos_state_class = "active";
			break;
	}
	
	
	// collect a list of starbases of this corp
	$reslist = $db->query("SELECT r.regionName, m.solarSystemName, i.typeName, locationID, moonID, pos_state, stateTimestamp, d.itemName as moonName,
                        onlineTimestamp, standingOwnerID, s.itemID as posItemID
             FROM starbases s, eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r, eve_staticdata.invTypes i, eve_staticdata.mapDenormalize d
WHERE s.corp_id = $corp_id AND m.solarSystemID = s.locationID AND m.regionID = r.regionID AND i.typeID = s.typeID 
AND d.itemID = moonID AND s.state <= 10 ORDER BY r.regionName, m.solarSystemName, d.itemName");

	$list_of_starbases = '<li><a class="skip_hover_color" href="#">List Starbases</a>
         <ul class="menu" style="width: 300px;">';

	$list_of_starbases .= "<li><a href=\"api.php?action=starbases&corp_id=$corp_id\">Show Starbase List</a></li>";
	

	while ($rowlist = $reslist->fetch_array())
	{
		$list_moonName = $rowlist['moonName']; // action=starbase_detail&itemID=1019889326525&locationID=30004277&moonID=40270870&corp_id=1070320653
		$list_itemid = $rowlist['posItemID'];
		$list_locid = $rowlist['locationID'];
		$list_moonID = $rowlist['moonID'];
		$regionName = $rowlist['regionName'];
		$list_of_starbases .= "<li><a href=\"api.php?action=starbase_detail&itemID=$list_itemid&locationID=$list_locid&moonID=$list_moonID&corp_id=$corp_id\">$regionName / $list_moonName</a></li>";
	}
	
	
	$list_of_starbases .= '</ul></li>';


	
	// print basic info about this pos
	base_page_header('',"POS Details $moonName","POS Details $moonName", $list_of_starbases);
	
	
	
	echo "<table style=\"width: 95%\"> <tr><td width=\"256\"><img src=\"//imageserver.eveonline.com/Render/" . $towerTypeID . "_256.png\" /></td><td>";
	echo "<table style=\"width: 100%\">";
	echo "<tr><th>Name</th><td>" . $names[0] . "</td></tr>";
	echo "<tr><th>Location</th><td>" . generate_dotlan_link_region($regionName) . " / " . generate_dotlan_link_system($solarSystemname) . " - $moonName</td></tr>";
	echo "<tr><th>Type</th><td>$typeName</td></tr>";
	echo "<tr><th>State</th><td><span class=\"$pos_state_class\">$pos_state_text $timer</span></td></tr>";	
	echo "<tr><th>Last API Pull</th><td>$last_executed</td></tr>";
		
	// based on the locationID and ItemID , let's get fuel status
	$block_status = getTowerFuelStatus($db, $corp_id, $locationID, $posItemID, 'Fuel Block');
	$stront_status = getTowerFuelStatus($db, $corp_id, $locationID, $posItemID, 'Stront');
	
	if ($can_show_starbase_fuel_log)
	{
		echo "<tr><th>Fuel Blocks / Time 
		(<a href=\"api.php?action=starbase_fuel_log&itemID=$posItemID&locationID=$locationID&moonID=$moonID&corp_id=$corp_id\">Log</a>)
		</th><td>";
	} else {
		echo "<tr><th>Fuel Blocks / Time</th><td>";
	}
	
	$perc = $block_status[0] / ($block_status[1]/5) * 100;	
	$perc_img = getPercentageImage($perc, true);	

	echo "" . $block_status[0] . " / " . ($block_status[1]/5) . " <img src=\"images/$perc_img\" /> ";	
	
	if ($sovereignty == false)
	{
		$hours = $block_status[0] / $controlTowerFuelPerHour[$typeName];
	} else {
		$hours = $block_status[0] / ($controlTowerFuelPerHour[$typeName]*0.75);
	}
	
	$hours = floor($hours);
	if ($pos_state == 4) {
		$offline_in = strtotime($last_executed) + $hours * 60 * 60;
		
		echo "Runs out of fuel at";
		if ($sovereignty == true)
		{
			echo " (Sovereignity Bonus applied)";
		}
		
		echo ": " .  date("Y-m-d H:i", $offline_in)  . "<br />";
	} else {
		echo "Fuel for $hours h<br />";
	}
	echo "</td></tr><th>Stront</th><td>";
	
	echo $stront_status[0] . " (";
	
	
	if ($sovereignty == false)
	{
		$hours = $stront_status[0] / $controlTowerStrontPerHour[$typeName];
	} else {
		$hours = $stront_status[0] / ($controlTowerStrontPerHour[$typeName]*0.75);
	}
	
	$hours = floor($hours);
	
	echo "Timer";
	
	$hours_str = "";
	if ($hours < 24)
	{
		$hours_str = $hours . " h";
	} else {
		$days = floor($hours/24);
		$hours = $hours - 24*$days;
		$hours_str = "$days d, $hours h";
	}
	
	echo ": $hours_str)</td></tr>";
	
	echo "</table></td></tr>";	
	if ($silo_type_id != 0)
	{
		echo "<tr><td width=\"256\"><img src=\"//imageserver.eveonline.com/Render/" . $silo_type_id . "_256.png\" alt=\"Silos\"/></td><td>";
		echo $silos;
		echo "</td></tr>";
	}
	echo "<tr><td width=\"256\"><b>Comment/Notes</b><br />If there is anything special with this POS, please write it down here!</td><td>";
	
	
	if ($can_edit_starbase_details == true)
	{	
		echo "<form method=\"post\" action=\"api.php?action=starbase_save\"><input type=\"hidden\" name=\"action2\" value=\"save_comment\" />";
		echo "<input type=\"hidden\" name=\"itemID\" value=\"$posItemID\"><input type=\"hidden\" name=\"locationID\" value=\"$locationID\">
		<input type=\"hidden\" name=\"moonID\" value=\"$moonID\"><input type=\"hidden\" name=\"corp_id\" value=\"$corp_id\">";
		echo "<textarea name=\"comment\" rows=\"5\" cols=\"50\">";
		echo $sbComment;
		echo "</textarea><input type=\"submit\" value=\"Save\" />";
	
	} else
	{
		$sbComment = str_replace("\n", "<br />", $sbComment);
		echo $sbComment;
	}
	
	echo "</td></tr>";
	echo "</table>";
	
	// done printing silos and general info
	
	// see if there was any siphon killmails in this system in the last 30 days?
	$siphon_ids = array(33477, 33478, 33479,  33581, 33583);
	$sql = "SELECT k.internal_kill_ID, k.external_kill_ID, k.kill_time, 
k.victim_ship_type_id, k.victim_character_id, k.victim_character_name, k.victim_corp_id, k.victim_corp_name,
k.position_x, k.position_y, k.position_z, i.typeName

FROM `kills_killmails` k, eve_staticdata.invTypes i
WHERE victim_ship_type_id in (33477, 33478, 33479, 33581, 33583)  AND TIMESTAMPDIFF(month,kill_time,now()) <= 2 AND k.solar_system_id = $solarSystemID
AND i.typeID = k.victim_ship_type_Id
ORDER BY kill_time DESC";
	
	$siphon_res = $db->query($sql);
		
	echo "<h3>Siphon Killmails</h3>";
	
	if ($siphon_res->num_rows == 0)
	{
		echo "<b>There has been no siphons killed in this solar system for the last 60 days</b><br />";
	} else {
		echo "<table style=\"width: 100%\">
		<tr><th>Location</th><th>Type</th><th>Character</th><th>Corp</th><th>Timestamp</th><th>Killmail</th></tr>";	
		while ($siphon_row = $siphon_res->fetch_array())
		{
			$siphon_type = $siphon_row['typeName'];
			$victim_char_name = $siphon_row['victim_character_name'];
			$victim_corp_name = $siphon_row['victim_corp_name'];
			$timestamp = $siphon_row['kill_time'];
			$external_kill_id = $siphon_row['external_kill_ID'];
			$zkill_url = "https://zkillboard.com/kill/" . $external_kill_id . "/";
			
			$kill_x = $siphon_row['position_x'];
			$kill_y = $siphon_row['position_y'];
			$kill_z = $siphon_row['position_z'];
			
			
			if (max(abs($kill_x - $x),abs($kill_y - $y),abs($kill_z - $z)) < 500000)
			{
				echo "<tr><td><b>POS</b></td><td><b>$siphon_type</b></td><td>$victim_char_name</td><td>$victim_corp_name</td><td>$timestamp</td><td>[<a href=\"$zkill_url\">ZKillboard.com</a>]</td></tr>";
			} else {
				echo "<tr><td>Somewhere in System</td><td>$siphon_type</td><td>$victim_char_name</td><td>$victim_corp_name</td><td>$timestamp</td><td>[<a href=\"$zkill_url\">ZKillboard.com</a>]</td></tr>";
			}
		}
		
		echo "</table>";
	}
	
	// next
	


	echo "<div style=\"overflow: hidden;\">";
	echo "<div style=\"width: 600px; float: left;\">";
	echo "<h3>All structures on this POS</h3><br />";
	echo $strucs;
	echo "</div>";
	echo "<div style=\"width: 400px; float: left;\">";
	
	
	$qrySys = "SELECT *
			FROM `corp_members`
			JOIN `api_characters` ON `corp_members`.`character_id` = `api_characters`.`character_id`
			JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
			WHERE `corp_members`.`corp_id` = $corp_id
			AND `api_characters`.`character_location` = '$solarSystemname'";
	$resSys = $db->query($qrySys);
	if ($resSys && $resSys->num_rows > 0) {
			
		echo "<h3>Chars in System</h3>";
		
		echo "<table><tr><th style=\"width: 200px;\">Character</th><th style=\"width: 200px;\">Main (Account)</th></tr>";
		while (($rowSys = $resSys->fetch_array()) != NULL) {
			$char_id = $rowSys['character_id'];
			$user_id = $rowSys['user_id'];
			echo "<tr>
				<td><a href=\"api.php?action=show_character&character_id=$char_id\">" . $rowSys["character_name"] . "</a></td>
				<td><a href=\"api.php?action=show_member&user_id=$user_id\">" . $rowSys["user_name"] . "</a></td></tr>";
		}
		echo "</table>";
	} else {
		echo "<h3>There are no characters in this system</h3>";
	}
	echo "</div>";
	



	base_page_footer('1','');
?>
