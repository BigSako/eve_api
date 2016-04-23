<?php

	base_page_header('','Sovereignty','Sovereignty');
	
	$alliance_id = 1727758877;
	
	$sth=db_action("select m.solarSystemName, m.solarSystemID, m.regionID, r.regionName 
	FROM sovereignty s, 
	eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r 
	WHERE r.regionID = m.regionID AND s.solarSystemID = m.solarSystemID and s.allianceID = $alliance_id
order by regionName ASC");
	$index=0;
	
	echo "<table style=\"width: 100%\"><tr><td class=\"table_header\">Region Name</td><td class=\"table_header\">System Name</td><td class=\"table_header\">Actions</td></tr>";
	
	while($result=$sth->fetch_array()) {
		echo "<tr><td>" . $result['regionName'] . "</td><td>" . $result['solarSystemName'] . "</td><td>&nbsp;</td></tr>";
	}	
	
	echo "</table>";


	
	base_page_footer('1','');

?>