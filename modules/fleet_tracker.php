<?php

base_page_header('','Fleet Participation','Fleet Participation');

// get all characters belonging to $GLOBALS['userid']
$db = connectToDB();

$sql = "SELECT a.character_name, a.character_id, f.fleet_name, p.curTimestamp, p.location, p.ship_name
FROM api_characters a, participation p, fleet f
WHERE p.character_id = a.character_id AND f.fleet_id = p.fleet_id AND a.user_id = " . $GLOBALS['userid'] . " ORDER BY p.curTimestamp ";

$res = $db->query($sql);

echo "<table style=\"width: 100%\">
<tr><th class=\"table_header\">Fleet Name</th><th class=\"table_header\">Date</th>
<th class=\"table_header\">Character Name</th><th class=\"table_header\">Ship Type</th></tr>";

$i = 0;

while ($row = $res->fetch_array())
{
	$fleet_name = $row['fleet_name'];
	$dateTime = $row['curTimestamp'];
	$char_name = $row['character_name'];
	$ship_type = $row['ship_name'];
	
	if (($i % 2) == 0)
	{
		$bgclass = "td_darkgray";
	} else {
		$bgclass = "td_lightgray";
	}				
	
	echo "<tr class=\"$bgclass\"><td>$fleet_name</td><td>$dateTime</td><td>$char_name</td><td>$ship_type</td></tr>";
	
	
	$i++;
}

echo "</table>";


base_page_footer('','');


?>