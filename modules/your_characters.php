<?php

	if (isset($_REQUEST['filter']))
		$filter = $_REQUEST['filter'];
	else
		$filter = '';
		
	$where = "1=1";
	$title = "Your Characters";

	do_log("Entered user_api_keys",9);
	$db = connectToDB();


	$menu_top = "Show <a href=\"api.php?action=your_characters\">All Characters</a> |
	<a href=\"api.php?action=your_characters&filter=cynos\">Cynos only</a>
";

	if ($filter == 'cynos')
	{
		$where = "cyno_skill > 0";
	}
	
	base_page_header('',$title,$title, "");
	
	$sql = "select character_id,key_id,walletBalance,character_name,corp_name,character_location,character_last_ship," . 
		"cyno_skill,state,last_update,skill_to_level,training_active,skill_endtime,skill_id_training,jumpFatigue,jumpActivation " .
		" from api_characters where user_id='".$GLOBALS["userid"]."' AND $where AND state <> 99 order by key_id,character_name";

	$sth = $db->query($sql);
	
	echo "<!--- $sql --->";

	echo $menu_top;
		
	// print header
	print("<table style=\"width: 95%\"><tr>
			<th>&nbsp;</th>
			<th>Name</th>
			<th>Corp</th>
			<th>ISK</th>
			<th>Location</th>
			<th>Ship</th>");
	if ($filter == 'cynos')
	{
		print("<th width=\"350\">Cyno Skill</th></tr>");
	}
	else
	{
		print("<th width=\"350\">Training</th></tr>");
	}
	$last_key_id = -1;
	$totalBalance = 0.0;
	while($result=$sth->fetch_array()) {
		$character_name=$result['character_name'];
		$character_id = $result['character_id'];
		$corp_name=$result['corp_name'];
		$jumpFatigue = $result['jumpFatigue'];
		$jumpActivation = $result['jumpActivation'];
		$jumpActivationTime = strtotime($jumpActivation)-strtotime($curEveTime);
		$jumpFatigueTime = strtotime($jumpFatigue)-strtotime($curEveTime);
		$jumpActivationString = secondsToTimeString($jumpActivationTime);
		$jumpFatigueString = secondsToTimeString($jumpFatigueTime);

		
		$character_location=$result['character_location'];
		$character_last_ship=$result['character_last_ship'];
		$cyno_skill=$result['cyno_skill'];
		$state = $result['state'];
		$key_id = $result['key_id'];
		$balance = $result['walletBalance'];
		$endTime = $result['skill_endtime'];
		$timeDiff = strtotime($endTime)-strtotime($curEveTime);
		// give warning class if the skill is below 24 hours (means you can add a new one)
		$skillqueue_class = "";
		$timeDiffStr = secondsToTimeString($timeDiff);
		$totalBalance += $balance;
		$balanceStr = number_format($balance, 0, '.', ',');
		$state_text=return_group_state_text($state);
		$state_class=return_group_state_class($state);
		$extra_text = "";
		if ($state != 0)
		{
			if ($state_text != "")
				$state_text = "($state_text)"; // $state_text = "($state_text - " . '<a href="api.php?action=delete_toon_from_api&character_id='. $character_id . '">Delete</a>' . ')';
			$extra_text = "<br />" . $state_text;
		}
		
		if ($jumpFatigueTime > 0)
		{
			$extra_text .= "<br /><img src=\"images/jump_fatigue_timer.png\" width=\"32\" height=\"32\" title=\"Fatigue\"/> $jumpFatigueString";
		}
		
		if ($jumpActivationTime > 0)
		{
			$extra_text .= "<br /><img src=\"images/jump_activation_timer.png\" width=\"32\" height=\"32\" title=\"Next Jump Activation: \"/> $jumpActivationString";
		}
		
		if ($filter == 'cynos')
		{
			$current_skill_training = "Level " . $cyno_skill;
		} else {	
			if ($result['training_active'] == 1 && $result['skill_id_training'] != 0) {
				$skill_type_id = $result['skill_id_training'];
				$to_level = $result['skill_to_level'];
				$res3 = $db->query("SELECT typeName FROM  eve_staticdata.`invTypes` WHERE typeID = $skill_type_id ");
				$row3 = $res3->fetch_array();
				if ($timeDiff != 0 && $timeDiff < 60*60*24) {
					$skillqueue_class = "warning";
					$state_class = "warning";
				}
				$current_skill_training = "<a class=\"$skillqueue_class\" href=\"api.php?action=skillsheet&character_id=$character_id\">" . $row3['typeName'] . " Level $to_level</a>, Q: $timeDiffStr";
				
			} else {
				$current_skill_training = "<i>No Skill Training</i>";
			}
		}
		$last_update = $result['last_update'];
		
		if ($last_key_id != $key_id && $last_key_id != -1)
		{
			// print empty row
			print("<tr><td colspan=\"8\">&nbsp;</td></tr>");
		}

		print("<tr><td><a href=\"api.php?action=char_sheet&character_id=$character_id\">
			<img src=\"http://imageserver.eveonline.com/Character/" . $character_id . "_64.jpg\" width=\"48\" height=\"48\" />
			</a>
			</td><td>
				<a href=\"api.php?action=char_sheet&character_id=$character_id\">
					$character_name $extra_text
				</a>
			</td>
		<td>$corp_name</td>
		<td style=\"text-align: right\"><a href=\"api.php?action=wallet_history&character_id=$character_id\">$balanceStr ISK</a></td>
		<td style=\"text-align: right\">$character_location</td>
		<td style=\"text-align: right\">$character_last_ship</td>
		<td style=\"text-align: center\">$current_skill_training</td></tr>");
		$last_key_id = $key_id;
	}
	
	$balanceStr = number_format($totalBalance, 2, '.', ',');

	print("</table>");
	
	echo "<br />";
	echo "<b>Total Balance</b>: $balanceStr ISK<br />";


	// Determine Killmail Stats for ever
	$sql = "SELECT SUM(number_kills) as kills, SUM(number_losses) as losses FROM kills_stats_per_char k, api_characters c 
	WHERE k.character_id = c.character_id AND c.user_id = " . $GLOBALS['userid'];


    $res = $db->query($sql);

    if (!$res)
    {
        echo $sql;
        echo $db->error;
    }

    $row = $res->fetch_array();

    if ($row["kills"] == NULL)
    	$row["kills"] = 0;

    
    echo "<b>Total Kills (Alltime):</b> " . $row["kills"] . "<br />";



    $sql = "SELECT SUM(number_kills) as kills, SUM(number_losses) as losses FROM kills_stats_per_char k, api_characters c 
	WHERE k.character_id = c.character_id AND k.`date`> DATE_SUB(CURDATE(),INTERVAL 36 MONTH) AND
	 c.user_id = " . $GLOBALS['userid'];

    $res = $db->query($sql);

    if (!$res)
    {
        echo $sql;
        echo $db->error;
    }

    $row = $res->fetch_array();

    if ($row["kills"] == NULL)
    	$row["kills"] = 0;

    echo "<b>Total Kills (36 Months):</b> " . $row["kills"] . "<br />";




    $sql = "SELECT SUM(number_kills) as kills, SUM(number_losses) as losses FROM kills_stats_per_char k, api_characters c 
	WHERE k.character_id = c.character_id AND k.`date`> DATE_SUB(CURDATE(),INTERVAL 12 MONTH) AND
	 c.user_id = " . $GLOBALS['userid'];

    $res = $db->query($sql);

    if (!$res)
    {
        echo $sql;
        echo $db->error;
    }

    $row = $res->fetch_array();

    if ($row["kills"] == NULL)
    	$row["kills"] = 0;


    echo "<b>Total Kills (12 Months):</b> " . $row["kills"] . "<br />";

    $sql = "SELECT SUM(number_kills) as kills, SUM(number_losses) as losses FROM kills_stats_per_char k, api_characters c 
	WHERE k.character_id = c.character_id AND k.`date`> DATE_SUB(CURDATE(),INTERVAL 1 MONTH) AND
	 c.user_id = " . $GLOBALS['userid'];

    $res = $db->query($sql);

    if (!$res)
    {
        echo $sql;
        echo $db->error;
    }

    $row = $res->fetch_array();

    if ($row["kills"] == NULL)
    	$row["kills"] = 0;


    echo "<b>Total Kills (1 Month):</b> " . $row["kills"] . "<br />";


	
	base_page_footer('1','');

?>
