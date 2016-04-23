<?php
// check for corp_id
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
			base_page_header('',"Activity By Kills - Select Corporation","Activity By Kills - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=activityByKills&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');		
			
			exit;
		}		
		else if (count($director_corp_ids) == 1) 
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=activityByKills&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Activity By Kills - Select Corporation","Activity By Kills - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=activityByKills&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
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
		$corp_id = $SETTINGS['main_corp_id'];
		
		




base_page_header('',"Freighter List","Freighter List");
echo '<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>';
echo '<script src="js/highcharts.js"></script>';
echo '<script src="js/drilldown.js"></script>';
$containerID = 0;



$db = connectToDB();

$groupNames = array(513 => "Freighter",	902 => "Jump Freighter");
$qry = "SELECT `stationName`, `typeName`, `character_name`, `user_name`, `groupID`
		FROM `player_assets` 
		JOIN `eve_staticdata`.`invTypes` ON `player_assets`.`typeID` = `eve_staticdata`.`invTypes`.`typeID`
		JOIN `eve_staticdata`.`staStations` ON `player_assets`.`locationID` = `eve_staticdata`.`staStations`.`stationID`
		JOIN `api_characters` ON `player_assets`.`character_id` = `api_characters`.`character_id`
		JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
		WHERE `eve_staticdata`.`invTypes`.`groupID` IN (513, 902)
		AND `lastSeen` >= CURDATE()
		ORDER BY `groupID`, `stationName`, `typeName`";
$res = $db->query($qry);
$datArray = array("Freighter" => array("count" => 0, "drill" => array()), "Jump Freighter" => array("count" => 0, "drill" => array()));
$table = "<table><tr><th>Ship</th><th>Station</th><th>Char</th><th>Owner</th></tr>";
if ($res && ($res->num_rows > 0)) {
	while (($row = $res->fetch_array()) != NULL) {
		$table .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
				$row["typeName"],
				$row["stationName"],
				$row["character_name"],
				$row["user_name"]
		);
		$datArray[$groupNames[$row["groupID"]]]["count"]++;
		if (!isset($datArray[$groupNames[$row["groupID"]]]["drill"][$row["typeName"]])) {
			$datArray[$groupNames[$row["groupID"]]]["drill"][$row["typeName"]] = 0;
		}
		$datArray[$groupNames[$row["groupID"]]]["drill"][$row["typeName"]]++;
	}
}
$table .= "</table>";
echo "<div style=\"overflow: hidden\">";
printPieHigh("Freighters by Type", $datArray);
echo "</div>";
echo $table;

base_page_footer('','');

?>