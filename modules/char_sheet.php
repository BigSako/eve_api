<?php

$character_id = intval($_REQUEST['character_id']);
$user_id = $GLOBALS['userid'];

$db = connectToDB();

$sth = $db->query("SELECT c.character_name, c.corp_name, c.user_id, c.state, skillpoints,  sec_status, freeSP,
				walletBalance, character_last_ship, character_location, jumpFatigue,jumpActivation
				FROM api_characters c 
				WHERE c.character_id = $character_id and user_id = $user_id");

if ($sth->num_rows == 1)
{
	$row = $sth->fetch_array();
	$name = $row['character_name'];
	$corp = $row['corp_name'];
	$skillpoints = $row['skillpoints'];
	$balance = $row['walletBalance'];
	$ship = $row['character_last_ship'];
	$location = $row['character_location'];		
	$balanceStr = number_format($balance, 2, '.', ',');
	$jumpFatigue = $row['jumpFatigue'];
	$jumpActivation = $row['jumpActivation'];
	$jumpActivationTime = strtotime($jumpActivation)-strtotime($curEveTime);
	$jumpFatigueTime = strtotime($jumpFatigue)-strtotime($curEveTime);
	$jumpActivationString = secondsToTimeString($jumpActivationTime);
	$jumpFatigueString = secondsToTimeString($jumpFatigueTime);
	$sec_status = $row['sec_status'];
	$freeSP = $row['freeSP'];

	base_page_header('',"Character Sheet for $name","Character Sheet for $name");
	
	
	
	echo "<table style=\"width: 95%\"><tr><td style=\"vertical-align: top\" width=\"257\">";
	echo "<img src=\"//imageserver.eveonline.com/Character/". $character_id . "_256.jpg\" />";
	echo "</td><td>";
	

	echo "<table width=\"100%\">
		<tr><td>Name:</td><td>$name</td></tr>
		<tr><td>Corporation:</td><td>$corp</td></tr>
		<tr><td>Skillpoints:</td><td>$skillpoints [<a href=\"api.php?action=skillsheet&character_id=$character_id\">Skillsheet</a>]</td></tr>";
		
	if ($freeSP > 0.0)
	{
		echo "<tr><td><b>Unapplied Skillpoints:</td><td>$freeSP</td></tr>";
	}

	echo "
		<tr><td>Current Ship:</td><td>$ship</td></tr>
		<tr><td>Location:</td><td>$location</td></tr>
		<tr><td>Wallet Balance:</td><td>$balanceStr ISK [<a href=\"api.php?action=wallet_history&character_id=$character_id\">History</a>]</td></tr>
		<tr><td>Security Status:</td><td>$sec_status</td></tr>";
		
	if ($jumpFatigueTime > 0)
	{
		echo "<tr><td><img src=\"images/jump_fatigue_timer.png\" width=\"24\" height=\"24\" title=\"Fatigue\"/> Fatigue: </td><td>$jumpFatigueString</td></tr>";
	}
		
	if ($jumpActivationTime > 0)
	{
		echo "<tr><td><img src=\"images/jump_activation_timer.png\" width=\"24\" height=\"24\" title=\"Jump Activation\"/> Next Jump Activation: </td><td>$jumpActivationString</td></tr>";
	}


	// determine number of kills that this toon has
    $sql = "SELECT SUM(number_kills) as kills FROM kills_stats_per_char k WHERE
    k.character_id = " . $character_id;
    $reskb = $db->query($sql);
    $rowkb = $reskb->fetch_array();

    if ($rowkb['kills'] == NULL)
    {
    	$rowkb['kills'] = 0;
    }

    echo "<tr><td>All Time Kills:</td><td>" . $rowkb['kills'] . " [<a target=\"_blank\" href=\"https://zkillboard.com/character/" . $character_id . "/\">ZKillboard</a>]</td></tr>";

	echo "</table>";
	
	
	
	// get skill queue
	$res = $db->query("SELECT t.typeName as name, q.position as position, q.endTime as endTime, q.level as level FROM skill_queue q, 
	eve_staticdata.invTypes t WHERE q.character_id = $character_id AND q.typeID = t.typeID ORDER BY q.position ASC");
	
	if ($res->num_rows > 0)
	{
		echo "<br /><h4>Skill queue</h4>";
		echo "<table><th>Skill Name</th><th>Finishes in...</th></tr>";
		while ($row = $res->fetch_array())
		{
			// $curEveTime = gmdate("Y-m-d H:i:s");
			$endTime = $row['endTime'];
			$timeDiff = strtotime($endTime)-strtotime($curEveTime);
			$timeDiffStr = "";
			if ($timeDiff <= 0)
			{
				$timeDiffStr  = "Finished";
			} else 
			{
				if ($timeDiff < 60)
				{
					$timeDiffStr = "$timeDiff seconds";
				} else if ($timeDiff < 60*60*24)
				{
					$timeDiffStr = gmdate("H:i:s", $timeDiff);
					
				} else {
					$days = floor($timeDiff / (60*60*24));
					$timeDiffStr = "$days days, " . gmdate("H:i:s", $timeDiff);
				}
			}
			echo "<tr><td>" . $row['name'] . " to level " . $row['level'] . "</td><td>$timeDiffStr</td></tr>";
		}
		
		echo "</table>";
	} else {
		echo "This character is currently not training any skills.";
	}
	
	echo "</td></tr></table>";
	base_page_footer('','');

}




?>
