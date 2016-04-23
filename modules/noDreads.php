<?php

	do_log("Entered human_resources",5);
	
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
			// display corp selection page
			base_page_header('',"Human Resources - Select Corporation","Human Resources - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
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
	
	$db = connectToDB();
		
	$sql = "SELECT corp_name, ceo, corp_ticker FROM corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		echo "invalid corp";
		exit;
	}
	
	if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
	{
		echo "Not allowed";
		exit;
	}
	
	
	$corprow = $res->fetch_array();
	
	
	base_page_header('',"$corprow[corp_name] Human Resources","$corprow[corp_name] Human Resources");
	


$qry = "SELECT `auth_users`.`user_id`, `auth_users`.`user_name`
		FROM `auth_users`
		JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
		JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
		WHERE `corp_members`.`corp_id` = $corp_id
		GROUP BY `auth_users`.`user_id`";
$res = $db->query($qry);
$memberList = array();
if ($res && ($res->num_rows > 0)) {
	while (($row = $res->fetch_array()) != NULL) {
		$memberList[] = $row["user_name"];
	}
}

$qry = "SELECT `auth_users`.`user_id`, `auth_users`.`user_name`
		FROM `auth_users`
		JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
		JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
		JOIN `player_assets` ON `api_characters`.`character_id` = `player_assets`.`character_id`
		JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_assets`.`typeID`
		WHERE `eve_staticdata`.`invTypes`.`groupID` = 485
		AND `corp_members`.`corp_id` = $corp_id
		GROUP BY `auth_users`.`user_id`";
$res = $db->query($qry);
$dreadList = array();
if ($res && ($res->num_rows > 0)) {
	while (($row = $res->fetch_array()) != NULL) {
		$dreadList[] = $row["user_name"];
	}
}
echo "<p> found " . count($dreadList) . " out of " . count($memberList) . " Members have dreads </p>";
echo "<table><tr><th>Baddies</th></tr>";
foreach(array_diff($memberList, $dreadList) as $baddie) {
	echo "<tr><td>$baddie</td></tr>";
}
echo "</table>";


	base_page_footer('','');


?>