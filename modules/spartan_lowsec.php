<?php

$db = connectToDB();

// select lowsec only
$sql = "SELECT solarSystemID, solarSystemName, r.regionName,
SECURITY FROM eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r
WHERE SECURITY <= 0.50 AND SECURITY >= 0.00 
AND m.regionID = r.regionID
ORDER BY  r.regionName, m.solarSystemName ";



$res = $db->query($sql);

$max_jumps = intval($_REQUEST['max_jumps']);

if ($max_jumps <= 0)
	$max_jumps = 5;
	
$spartan_corp_id = $SETTINGS['spartan_corp_id'];

$lastRegionName = '';
$cnt = -1;
$solarSystems = "";
while ($row = $res->fetch_array())
{
	$system_name = $row['solarSystemName'];
	$regionName = $row['regionName'];
	
	
	if ($regionName != $lastRegionName)
	{
		if ($cnt != -1)
		{	
			$solarSystems = substr($solarSystems, 0, strlen($solarSystems)-1);
			$lastRegionName = str_replace(' ', '_', $lastRegionName);
			echo "$cnt systems total in $lastRegionName - dotlan: <a href=\"http://evemaps.dotlan.net/map/$lastRegionName/$solarSystems\" target=\"_blank\">Click me!!!</a><br />";
		}
		$cnt = 0;
		$solarSystems = "";
		
		echo "<br /><h3>$regionName</h3>";
		
	}
	
	$corp_offices_res = $db->query(get_offices_close_to($system_name, $spartan_corp_id, $max_jumps));
	
	if ($corp_offices_res->num_rows == 0)
	{
		echo "NO OFFICES WITHIN $max_jumps JUMPS OF $system_name!<br />";
		$cnt++;
		
		$system_name_dotlan = str_replace(' ', '_', $system_name);
		
		$solarSystems .= $system_name_dotlan . ",";
		
		// get closest Station 
		$sql2 = "select jumps, t.stationName FROM 
staStations t, eve_routing.sys_to_sys s 
where ((s.a_name = '$system_name' and s.b = t.solarSystemID) OR 
	(s.b_name = '$system_name' and s.a = t.solarSystemID)) AND jumps < $max_jumps
ORDER BY jumps, solarSystemID LIMIT 10";

		
		$res2 = $db->query($sql2);
		if ($res2->num_rows == 0)
		{
			echo "No station available within $max_jumps of $system_name...<br />";
		} else {
			while ($row2 = $res2->fetch_array())
			{
				if ($row2['jumps'] == 0)
				{
					echo "There is a station in system: $row2[stationName]!<br />";
				} else 
				{
					echo "Station $row2[stationName] is only $row2[jumps] away!<br />";
				}
			}
		}
		echo "<br />";
	} else {
		// do nothing
	}
	
	$lastRegionName = $regionName;
}


?>