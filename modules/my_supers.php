<?php
	base_page_header('','Your Caps', 'Your Caps');


	$all_capital_ids_str = '(' . implode($all_capital_ids, ',') . ')';


	$user_id = $GLOBALS['userid'];
	$sql = "
SELECT a.locationID, t.typeID, t.typeName, a.itemId, a.character_id, a.inSpace, a.realName, a.x, a.y, a.z, c.character_name, c.corp_name, c.character_id, c.jumpFatigue, c.jumpActivation
FROM player_supercarriers a, eve_staticdata.invTypes t, api_characters c
WHERE a.typeID = t.typeID and t.typeId in $all_capital_ids_str
AND a.character_id = c.character_id
and c.user_id = $user_id
ORDER BY t.typeName ASC
";


	$res = $db->query($sql);

	echo "This page is <b>Work in Progress</b>. Features will be added...<br />";

	echo "<table style=\"width: 100%\"><tr>
		<th width=\"65\">&nbsp;</th>
		<th>Character Name</th>
		<th>Ship</th>
		<th>Ship Name</th>
		<th>System Location</th>
		<th>Fuel Bay</th>
		<th>Extras</th>
	</tr>";

	$itemDivision = "";
	$droneBayDivision = "";
	$fittingDivision = "";
	
	while ($row = $res->fetch_array())
	{
		$realName = $row['realName'];
		$itemID = $row['itemId'];
		$character_id = $row['character_id'];
		$character_name = $row['character_name'];
		$inSpace = $row['inSpace'];
		
		$jumpFatigue = $row['jumpFatigue'];
		$jumpActivation = $row['jumpActivation'];
		$jumpActivationTime = strtotime($jumpActivation)-strtotime($curEveTime);
		$jumpFatigueTime = strtotime($jumpFatigue)-strtotime($curEveTime);
		$jumpActivationString = secondsToTimeString($jumpActivationTime);
		$jumpFatigueString = secondsToTimeString($jumpFatigueTime);

		if ($realName == "")
		{
			$realName = $row['typeName'];
		}
		echo "<tr><td><img alt=\"" . $row['typeName'] . "\" src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\"/></td>";
		echo "<td>" .  $character_name . "</td><td>" . $row['typeName'] . "</td><td>" . $realName . "<br />";
		
		if ($jumpFatigueTime > 0)
		{
			echo "<img src=\"images/jump_fatigue_timer.png\" width=\"24\" height=\"24\" title=\"Fatigue\"/> $jumpFatigueString<br />";
		}
			
		if ($jumpActivationTime > 0)
		{
			echo "<img src=\"images/jump_activation_timer.png\" width=\"24\" height=\"24\" title=\"Jump Activation\"/> $jumpActivationString";
		}
		
		echo "</td>";

		$x = $row['x'];
		$y = $row['y'];
		$z = $row['z'];
		$locationID = $row['locationID'];

		if ($inSpace == 1)
		{
			if ($x != 0 && $y != 0 && $z != 0)		
				$celestial = findClosest2($locationID, $x, $y, $z);
			else
				$celestial = "Unknown ($x/$y/$z)";
		} else {
			// get character location from api
			
			$celestial = "In Station";
		}

		// System Location
		echo "<td>$celestial</td>";

		// FUEL BAY
		$sql5 = "SELECT t.typeID, t.typeName, a.quantity FROM player_supercarriers a, eve_staticdata.invTypes t WHERE a.typeID = t.typeID and a.flag = 133 AND a.parentItemID = $itemID and a.character_id = $character_id";
		$res5 = $db->query($sql5);
		$fuel = "";
		while ($row5 = $res5->fetch_array())
		{
			$fuel .= $row5['quantity'] . "x " . $row5['typeName'] . "<br />";
		}
		echo "<td>$fuel</td>";


		// get items in fleet hangar
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t 
		WHERE a.typeID = t.typeID and a.flag = 155 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}

		$itemDivision .= "<div class=\"reveal\" id=\"fleethangar_" . $itemID . "\" data-reveal><b>Fleet Hangar of $realName ($character_name)</b><br />" . $items . "</div>";
		
		
		// get items in ship maintenance bay
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t 
		WHERE a.typeID = t.typeID and a.flag = 90 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}

		$itemDivision .= "<div class=\"reveal\" id=\"shiphangar_" . $itemID . "\" data-reveal><b>Ship Hangar of $realName ($character_name)</b><br />" . $items . "</div>";

		
		

		// get items in dronebay
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t 
		WHERE a.typeID = t.typeID and a.flag = 87 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}

		$droneBayDivision .= "<div class=\"reveal\" id=\"dronebay_" . $itemID . "\" data-reveal><b>Drone Bay of $realName ($character_name)</b><br />" . $items . "</div>";

		// get fittings for low slot
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t 
		WHERE a.typeID = t.typeID and 
		(a.flag >= 11 and a.flag <= 18)
		 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}
		$low_slots = "<b>Low Slots:</b>$items";

		// get fittings for medium slot
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t 
		WHERE a.typeID = t.typeID and 
		(a.flag >= 19 and a.flag <= 26)
		 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}
		$medium_slots = "<b>Medium Slots:</b>$items";

		// get fittings for high slot
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t WHERE a.typeID = t.typeID and 
		(a.flag >= 27 and a.flag <= 34)
		 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}
		$high_slots = "<b>High Slots:</b>$items";

		// get fittings for rigs
		$sql5 = "SELECT t.typeID, t.typeName, SUM(a.quantity) quantity FROM player_supercarriers a, eve_staticdata.invTypes t WHERE a.typeID = t.typeID and 
		(a.flag >= 92 and a.flag <= 99)
		 AND a.parentItemID = $itemID and a.character_id = $character_id GROUP BY t.typeID, t.typeName";
		$res5 = $db->query($sql5);
		$items = "";
		while ($row5 = $res5->fetch_array())
		{
			$items .= "<br />" . $row5['quantity'] . "x " . $row5['typeName'];
		}
		$rig_slots = "<b>Rigs:</b>$items";

		$fittingDivision .= "<div class=\"reveal\" id=\"fitting_" . $itemID . "\" data-reveal><b>Fitting of $realName ($character_name)</b><br />$high_slots <br />$medium_slots<br />$low_slots<br />$rig_slots</div>";

		echo "<td>
<a data-open=\"shiphangar_$itemID\">Ship Hangar</a>
<a data-open=\"fleethangar_$itemID\">Fleet Hangar</a><br />
<a data-open=\"dronebay_$itemID\">Drone Bay</a><br />
<a data-open=\"fitting_$itemID\">Fitting</a>
</td>";

		echo "</tr>";
	}

	echo "</table><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />";

	echo $itemDivision;
	echo $droneBayDivision;
	echo $fittingDivision;


	base_page_footer('', '');

?>
