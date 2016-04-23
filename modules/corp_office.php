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
			header('Location: api.php?action=corp_office&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Corp Offices - Select Corporation","Corp Offices - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=corp_office&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');			
			
			exit;
		} else if ($isAdmin == true && isset($_REQUEST['ignore_main_corp_id']))
		{
			// display corp selection page
			base_page_header('',"Corp Offices - Select Corporation","Corp Offices - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=corp_office&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
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




	if ($solarSystemName == '') // show all corp offices
	{			
		base_page_header('',$corprow['corp_name'] . " Corp Assets", $corprow['corp_name'] . " Corp Assets");

			echo '<script>
$(document).ready(function()
{
	$("#officesTable").tablesorter();
});

</script>';

		if ($isAdmin == true)
		{
			echo "<a href=\"api.php?action=corp_office&ignore_main_corp_id=true\">Show all corps</a><br /><br />";
		}


        echo "<a href=\"api.php?action=map&corp_id=$corp_id\">Show Offices on Map</a><br />";

		echo "<h3>Offices</h3>";		

		echo "<table class=\"tablesorter\" id=\"officesTable\">
		<thead>
		<tr>
			<th>Region</th><th>System</th><th>Location</th><th>Cynos</th><th>LO</th><th>Helium Iso</th><th>Oxy Iso</th><th>Minm. FB</th><th>Amarr FB</th>
		</tr>
		</thead>";


		// get all corp offices
		$sql = get_all_offices($corp_id);

		//echo "sql = '$sql'";
		$res = $db->query($sql);
		
		if (!$res)
		{
			echo "Failed to query '$sql';\n";
			echo $db->error . "\n";
		}
		
		echo "<tbody>";

		
		while ($row = $res->fetch_array())
		{
			$office_name = $row['location'];
			$location_id = $row['locID'];
			$solar_system_id = $row['solarID'];
			$solar_system_name = $row['solarSystemName'];
			$region_name = $row['regionName'];

			$office_name = str_replace($solar_system_name, "", $office_name);

			$cyno_amount = 0;
			$liquid_amount = 0;
			$helium_amount = 0;
			$oxygen_amount = 0;
			$amarr_blocks_amount = 0;
            $minmatar_blocks_amount = 0;

			$cyno_type_id = 21096;
			$liquid_type_id = 16273;
			$helium_isotopes_type_id = 16274;
			$oxygen_isotopes_type_id = 17887;
			$amarr_fuel_block_type_id = 4247;
			$minmatar_fuel_block_type_id = 4246;

			// get all divisions for that office
			$sql_office = "SELECT itemID FROM corp_assets WHERE locationID = $location_id AND corp_id=$corp_id ";

			// get all assets where parentItemID in ($sql_office) AND cyno or LO
			$sql_stuff = "SELECT itemID, quantity, parentItemID, typeID FROM corp_assets
						WHERE parentItemID IN ($sql_office) AND
						(typeID = $cyno_type_id OR typeID = $liquid_type_id OR
						typeID = $helium_isotopes_type_id OR 
						typeID = $minmatar_fuel_block_type_id or 
						typeID = $oxygen_isotopes_type_id or
						typeID = $amarr_fuel_block_type_id) ";

			$res_stuff = $db->query($sql_stuff);

			//echo "sql='$sql_stuff'";

			while ($row_stuff = $res_stuff->fetch_array())
			{
				if ($row_stuff['typeID'] == $cyno_type_id)
				{
					$cyno_amount += $row_stuff['quantity'];
				} else if ($row_stuff['typeID'] == $helium_isotopes_type_id)
				{
					$helium_amount += $row_stuff['quantity'];
				} else if ($row_stuff['typeID'] == $oxygen_isotopes_type_id)
				{
					$oxygen_amount += $row_stuff['quantity'];
				} else if ($row_stuff['typeID'] == $amarr_fuel_block_type_id)
				{
					$amarr_blocks_amount += $row_stuff['quantity'];
				} else if ($row_stuff['typeID'] == $minmatar_fuel_block_type_id)
                {
                    $minmatar_blocks_amount += $row_stuff['quantity'];
                }
                else
                {
					$liquid_amount += $row_stuff['quantity'];
				}
			}


			echo "<tr><td>";
			
			echo generate_dotlan_link_region($region_name);
			
			echo "</td><td>";
			
			echo generate_dotlan_link_system($solar_system_name);
			echo "<!-- $location_id --></td><td>
			<a href=\"api.php?action=assets&corp_id=$corp_id&action2=show&solarSystemName=$solar_system_name\">$office_name</a></td>
			<td>$cyno_amount</td><td>$liquid_amount</td><td>$helium_amount</td><td>$oxygen_amount</td><td>$minmatar_blocks_amount</td><td>$amarr_blocks_amount</td></tr>";

		}
		
		
		echo "</tbody></table>";
	} 	
	
		
	echo "<br /><br />Last updated: ";
	
	$sql = "SELECT last_executed FROM cronjobs WHERE name = 'offices' ";
	
	$res = $db->query($sql);
	$row = $res->fetch_array();
	
	echo $row['last_executed'];
	echo "<br />";



	base_page_footer('1','');
?>
