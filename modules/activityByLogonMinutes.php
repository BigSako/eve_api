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
			base_page_header('',"Activity By LogonMinutes - Select Corporation","Activity By LogonMinutes - Select Corporation");

			
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
			header('Location: api.php?action=activityByLogonMinutess&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Activity By LogonMinutes - Select Corporation","Activity By LogonMinutes - Select Corporation");

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
		
		




base_page_header('',"Activity by LogonMinutes","Activity by LogonMinutes");



$db = connectToDB();
// amount anyone logged in total (max of any accounts of a person)
$logon = array();
$logon["1 Week"] = getLogonMinutesForTimeframe("1 WEEK");
$logon["2 Weeks"] = getLogonMinutesForTimeframe("2 WEEK");
$logon["1 Month"] = getLogonMinutesForTimeframe("1 MONTH");
$logon["3 Months"] = getLogonMinutesForTimeframe("3 MONTH");
$membersMerged = array(); 
echo "<table>";
echo "<tr><th>Player</th>";
foreach ($logon as $k => $v) { 
	echo "<th>$k</th>"; 
	$membersMerged = array_merge($membersMerged, array_keys($v));
}
echo "</tr>";
$membersMerged = array_unique($membersMerged);
natcasesort($membersMerged);
foreach ($membersMerged as $k) {
	echo "<tr><td>$k</td>";
	foreach ($logon as $dat) {
		printf("<td>%s</td>", (isset($dat[$k]) ? $dat[$k] : "0"));
	}
	echo "</tr>";
}

base_page_footer('','');

function getLogonMinutesForTimeframe($timeframe) {
	global $corp_id;
	global $db;
	$qry = "SELECT `user_name`, MAX(`minutes`) as `minutes` 
			FROM (
				SELECT `user_name`, MAX(`logonMinutes`) - MIN(`logonMinutes`) as `minutes`
				FROM `player_logonMinutes` 
				JOIN `api_characters` ON `player_logonMinutes`.`keyID` = `api_characters`.`key_id`
				JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
				JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
				WHERE `api_characters`.`corp_id` = $corp_id
				AND `corp_members`.`corp_id` = $corp_id
				AND DATE(`timestamp`) > DATE_SUB(CURDATE(), INTERVAL $timeframe)
				GROUP BY `keyID`
			) as `blub`
			GROUP BY `user_name`
			ORDER BY `user_name` ASC";
	$res = $db->query($qry);
	$ret = array();
	if ($res && ($res->num_rows > 0)) {
		while (($row = $res->fetch_array()) != NULL) {
			$ret[$row["user_name"]] = $row["minutes"];
		}
	}
	return $ret;
}
?>