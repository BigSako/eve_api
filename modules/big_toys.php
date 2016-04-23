<?php
	$corp_id = -1;

	// check for corp id
	if (!isset($_REQUEST['corp_id']))
	{
		// check if is admin
		if ($isAdmin == true && !isset($_REQUEST['ignore_main_corp_id']))
		{
			$corp_id = $SETTINGS['main_corp_id'];
		}
		else if ($isAdmin == true && isset($_REQUEST['ignore_main_corp_id']))
		{
			// display corp selection page
			base_page_header('',"Human Resources - Select Corporation","Human Resources - Select Corporation");


			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);

			while ($row = $res->fetch_array())
			{
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=human_resources&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}

			echo "</ul>";



			base_page_footer('','');

			exit;
		}
		else if (count($director_corp_ids) == 1)
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=human_resources&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);

            // char is director in several corps, but only one of them is also available on services
			if ($res->num_rows == 1)
			{
				$row = $res->fetch_array();
                $corp_id = $row['corp_id'];
                header("Location: api.php?action=human_resources&corp_id=$corp_id");
			} else {
                // list all corps where this toon is director

                // display corp selection page
                base_page_header('',"Human Resources - Select Corporation","Human Resources - Select Corporation");

                echo "<ul>";

                while ($row = $res->fetch_array()) {
                    $corp_id = $row['corp_id'];
                    echo "<li><a href=\"api.php?action=human_resources&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
                }

                echo "</ul>";

                base_page_footer('','');
            }

			exit;
		}
	}

	if (isset($_REQUEST['corp_id']))
		$corp_id = intval($_REQUEST['corp_id']);

	if ($corp_id < 1)
		$corp_id = $SETTINGS['main_corp_id'];
	
	$db = connectToDB();
	
	$sql = "SELECT corp_name, ceo, corp_ticker FROM corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);

	if ($res->num_rows != 1)
	{
		echo "invalid corp";
		exit;
	}

	
	base_page_header('','Supers and Titans','Supers and Titans');
	
	echo "<b>Note:</b> Information on this list is delayed by up to 6 hours to update by CCP.<br />";

	$titans = 0;
	$supers = 0;


	
	
	$sth=$db->query("
			SELECT c.character_id, c.character_name, c.location, c.shipType, r.regionName 
			FROM `corp_members` c, eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r
			WHERE c.corp_id= $corp_id AND  c.shipType in ('Aeon', 'Hel', 'Nyx', 'Wyvern', 'Revenant', 'Avatar', 'Erebus', 'Ragnarok', 'Leviathan') 
			and c.location = m.solarSystemName COLLATE utf8_unicode_ci
			and m.regionID = r.regionID
			ORDER BY r.regionName, c.location ASC, c.character_name ASC
	");
	$index=0;

	$tmp_string = "";

    echo "<table class=\"tablesorter\" id=\"shinyTable\">
		<thead>
		<tr>
			<th>Region</th><th>System</th><th>Ship</th><th>Character</th><th>Exact Location</th><th>Last Logon</th><th>Fatigue?</th>
		</tr>
		</thead>";

	while($result=$sth->fetch_array()) {
		$character_id=$result['character_id'];
		$character_name=$result['character_name'];
        $systemName=$result['location'];

        // get last logon time of that character
        $sql = "SELECT max(logonDAteTime) as logonTime FROM `session_tracking` WHERE character_id=$character_id";
        $res2 = $db->query($sql);
        $row = $res2->fetch_array();
        $last_logon_time = $row['logonTime'];
		

		$character_last_ship=$result['shipType'];
		
		if (in_array($character_last_ship, array( 'Aeon', 'Hel', 'Nyx', 'Wyvern', 'Revenant')))
		{
			$supers++;
		}
		
		if (in_array($character_last_ship, array( 'Avatar', 'Erebus', 'Ragnarok', 'Leviathan')))
		{
			$titans++;
		}
		
		$regionName = $result['regionName'];

		// check actual moon location
		$sql3 = "
		SELECT a.locationID, a.realName, a.x, a.y, a.z, c.jumpFatigue, c.jumpActivation
		FROM player_supercarriers a, eve_staticdata.invTypes t, api_characters c
		WHERE a.typeID = t.typeID AND t.typeName = '$character_last_ship' AND a.character_id = $character_id AND a.character_id = c.character_id";
		
		$res1 = $db->query($sql3);
		$row1 = $res1->fetch_array();
		
		$x = $row1['x'];
		$y = $row1['y'];
		$z = $row1['z'];
		$locationID = $row1['locationID'];

		$realName = $row1['realName'];
		
		$jumpFatigue = $row1['jumpFatigue'];
		$jumpActivation = $row1['jumpActivation'];
		
		$jumpActivationTime = strtotime($jumpActivation)-strtotime($curEveTime);
		$jumpFatigueTime = strtotime($jumpFatigue)-strtotime($curEveTime);
		
		if ($jumpFatigueTime > 0)
		{
			$jumpActivationString = secondsToTimeString($jumpActivationTime);
			$jumpFatigueString = secondsToTimeString($jumpFatigueTime);
			if ($jumpActivationTime > 0)
				$jumpFatigueString .= " (Next Jump: $jumpActivationString)";
			else
				$jumpFatigueString .= " (can jump)";
		} else {
			$jumpFatigueString = "";			
		}

		if ($x != 0 && $y != 0 && $z != 0)		
			$celestial = findClosest2($locationID, $x, $y, $z);
		else
			$celestial = "Exact position unknown";
			
		$index++;

        $regionName = generate_dotlan_link_region($regionName);
        $systemName = generate_dotlan_link_system($systemName);

		echo "<tr><td>$regionName</td><td>$systemName</td><td title=\"Ships real name: $realName\">$character_last_ship</td>
        <td><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a></td>
        <td>$celestial</td><td>$last_logon_time</td><td>$jumpFatigueString</td></tr>";


	}

    echo "</table>";



			

echo '<script>
$(document).ready(function()
{
	$("#shinyTable").tablesorter();
});

</script>';


	base_page_footer('',"<a href=\"api.php?action=human_resources&corp_id=$corp_id\">Back</a>");

?>
