<?php	

if (!isset($_REQUEST['user_id'])) {
	
	if (isset($_REQUEST['character_id']))
	{
		// this was probably meant to go to show_character.php
		$char_id = intval($_REQUEST['character_id']);
		header('Location: api.php?action=show_character&character_id='. $char_id);
		exit;
	}
	
	base_page_header('', 'Error', 'Error');
	echo "No user_id set!";
	base_page_footer();
	exit();
}

$user_id = intval($_REQUEST['user_id']);

$my_user_id = $GLOBALS['userid'];

if ($user_id == 0) {
	base_page_header('', 'Error', 'Error');
	echo "No user_id set!";
	base_page_footer();
	exit();
}

$db = connectToDB();

// first things first: see if this user exists
$sql = "SELECT user_id, forum_id, user_name, has_regged_main, has_regged_api, state FROM auth_users WHERE user_id = $user_id";

$res = $db->query($sql);

if ($res->num_rows == 0)
{
	base_page_header('Error - User not found', 'Error - User not found');
	echo "User not found!<br />If you think you should see something here, please notify an admin.<br />";
	base_page_footer();
	exit();
}

// so we found it, pull data
$row = $res->fetch_array();

$account_user_name = $row['user_name'];
$account_forum_id = $row['forum_id'];
$account_main_id  = $row['has_regged_main'];
$account_state    = $row['state'];
	
// print header
base_page_header("","Showing Information for $account_user_name", "Showing Information for $account_user_name");
$containerID = 0;
echo '
<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
<script src="js/highcharts.js"></script>
<script src="js/drilldown.js"></script>';


if ($account_main_id > 0)
{
	$main_char_img = "<img src=\"//imageserver.eveonline.com/Character/" . $account_main_id . "_200.jpg\"/>";
	$sql = "SELECT character_name FROM api_characters WHERE character_id = $account_main_id";
	$res = $db->query($sql);
	$row = $res->fetch_array();
	$main_char_name = $row['character_name'];
} else {
	$main_char_img = "<img src=\"images/aura.png\" alt=\"No Main Character Selected\"/>";
	$main_char_name = "None set!";
}


echo '<h3>Account Information</h3>';
echo "<table><tr><td rowspan=\"3\">$main_char_img</td><th>Account Name</th><td>$account_user_name</td></tr>";
echo "<tr><th>Forum Account ID:</th><td>$account_forum_id (<a href=\"" . create_forum_profile_link($account_forum_id) . "\">Open Profile</a>)</td></tr>";
echo "<tr><th>Main Character:</th><td>$main_char_name</td></tr>";
echo '</table>';


echo "<h3><a id=\"groups\" href=\"#groups\">Groups</a></h3> " ;
// get the groups that the user is in
$res3 = $db->query("SELECT g.group_name as title, g.group_id as group_id, m.state as state, g.forum_group_id as forum_group_id
					FROM groups g, group_membership m 
					WHERE g.group_id = m.group_id and m.user_id = $user_id");

		
echo "<table style=\"width: 100%\">";
echo "<tr><th>Group Name</th><th>State</th><th>Granted by</th></tr>";
while ($row3 = $res3->fetch_array())
{
	$state = $row3['state'];
	$group_id = $row3['group_id'];
	$group_name = $row3['title'];
	$state_text=return_group_state_text($state);
	$state_class=return_group_state_class($state);
	$forum_group_id = $row3['forum_group_id'];
	
	$granted_by = ""; // either a user or the API
	$granted_at = ""; // when was this role granted?
	
	/*
	if ($forum_group_id == 0)
	{
		//$forum_text = "Group is not affiliated to a forum-group";
		$forum_text = "";
	} else 
	{
		$forum_text = get_group_name_test($forum_group_id);
		// check if it is assigned
		if (is_member_of_group($account_forum_id, $forum_group_id))
		{
			$forum_text .= " - assigned!";
		} else {
			$forum_text .= " - pending...";
		}
	} */
	
	echo '<tr><td>';
	
	if ($isAdmin)
	{
		echo "<a href=\"api.php?action=group_admin&action2=show&group_id=$group_id\">$group_name</a>";
	} else {
		echo $group_name;
	}
	
	echo "</td>
		<td class=\"$state_class\">$state_text</td>
		<td>$granted_by ($granted_at)</td>
	</tr>";
}

echo "</table><br />";


// print information about services (ts3, discord, etc...)
echo "<h3>Services</h3>";


$is_allowed_to_view_affiliation = false;
$canViewCharacterHistory = getPageAccessForThisUser('show_character_history');
// allowed (= director, ceo of corp, or alliance leadership, or admin)


// check if this account is allowed to view character affiliation
if (!($isSuperAdmin || $isAdmin))
{
	// check if the user that we are trying to access is in a corp that we are in
	$sql = "SELECT c.user_id FROM api_characters c 
		WHERE c.user_id = $user_id AND c.corp_id in (SELECT a.corp_id FROM api_characters a WHERE a.user_id = $my_user_id and a.is_director = 1)";
		
	$res = $db->query($sql);
	if ($res->num_rows > 0)
	{
		$is_allowed_to_view_affiliation = true;
	}	
} else {
	$is_allowed_to_view_affiliation = true;
}


if ($is_allowed_to_view_affiliation)
{
	$is_allowed_to_view_character_details = getPageAccessForThisUser('show_character');
	echo "<h3>Characters / Affiliation</h3>";
	$sql = "SELECT c.character_name, character_id, c.corp_name, c.user_id, c.state, skillpoints,
				walletBalance, character_last_ship, character_location, jumpFatigue,jumpActivation
				FROM api_characters c WHERE c.user_id = $user_id ORDER BY c.character_name;";
	$res = $db->query($sql);
	
	if ($res->num_rows > 0)
	{
		echo "<table style=\"width: 100%\">
		<tr>
			<th>Character Name</th>
			<th>Corporation</th>
			<th>Alliance</th>
			<th>Location</th>
			<th>Skillpoints</th>
			<th>Money</th>
		</tr>";
		while ($row = $res->fetch_array())
		{
			$character_id = $row['character_id'];
			$this_char_name = $name = $row['character_name'];
			$corp_name = $row['corp_name'];
			$skillpoints = $row['skillpoints'];
			$ship = $row['character_last_ship'];
			$balance = $row['walletBalance'];
			$location = $row['character_location'];
			$balanceStr = number_format($balance, 2, '.', ',');
			
			echo "<tr><td>";
			if ($is_allowed_to_view_character_details)
			{
				echo "<a href=\"api.php?action=show_character&character_id=$character_id\">$this_char_name</a>";	
			} else {
				echo "$this_char_name";
			}
			
			
			echo "</td>
			<td>$corp_name</td>
			<td>TODO</td>
			<td>$location</td>
			<td style=\"text-align: right;\">$skillpoints</td>
			<td style=\"text-align: right\">$balanceStr ISK</td></tr>";			
			
			echo '</tr>';
		}
		echo "</table>";
	} else {
		echo "There are no characters affiliated to this account.";
	}

	if ($canViewCharacterHistory)
	{
		echo "This account has been associated to the following characters (including former characters):";
		$sql = "SELECT h.character_id, h.last_seen, a.character_name FROM character_history h 
		
		LEFT JOIN api_characters a 
		ON  a.character_id = h.character_id WHERE h.userid = $user_id ORDER BY h.last_seen DESC";
		$res = $db->query($sql);

		if (!$res)
		{
			echo $sql . " failed! Error: " . $db->error;
		}

		echo "<table>";
		echo "<tr><th>User Name</th><th>Time</th></tr>";
		while ($row = $res->fetch_array())
		{		
			echo "<tr><td>";
			if ($row['character_name'])
			{
				echo "<a href=\"api.php?action=show_character&character_id=" . $row['character_id'] . "\">" . $row['character_name'] . "</a>";
			} else {
				echo "<a href=\"https://zkillboard.com/character/" . $row['character_id'] . "\" target=\"_blank\">Unknown, check zkillboard</a>";
			}

			echo "</td><td>" . $row['last_seen'] . "</td></tr>";
		
		}

		echo "</table>";
	}
	
	echo "<br />";
	
	
	echo "The following API keys are associated with this account:<br />";
		
	// get account status and api key id
	$res4 = $db->query("SELECT keyid, state, last_checked, paidUntil, access_mask, `comment`
			FROM player_api_keys WHERE user_id = $user_id ORDER BY keyid ASC");
				
		
	// list api keys and whether or not they are valid
	echo "<table style=\"width: 100%\">";
	echo "<tr><th>API Key ID</th><th>API Key State</th><th>Account active?</th></tr>";
		
		
	while ($result = $res4->fetch_array())
	{
		$paid_until = $result['paidUntil'];
		$timeDiff = strtotime($paid_until)-strtotime($curEveTime);
		$timeDiffStr = secondsToTimeString($timeDiff);
		$state = $result['state'];
		$key_id = $result['keyid'];
		$access_mask = $result['access_mask'];
		$last_checked = $result['last_checked'];		
		
		if ($timeDiff < 0)
		{
			$timeDiffStr = "Account inactive";
		}		
		
		$state_text=return_state_text($state);
		
		$state_text .= " ($last_checked)";
		
		if ($access_mask != '' && $state != 0)
		{
			$state_text .= "<br/>Access Mask: $access_mask";
		}
		if ($state > 10) 
		{
			$state_text .= "<br /><a href=\"api.php?action=refresh_api_key_admin&keyId=$key_id&user_id=$user_id\">Refresh API Key</a>";
		}
		
		
		
		if ($isAdmin)
			$state_text .= "<br /><a href=\"api.php?action=show_api_details&keyId=$key_id&user_id=$user_id\">Show Details</a>";
		
		$state_class=return_state_class($state);
		
		$sth2=$db->query("select character_name from api_characters where key_id='$key_id' order by 1");

		$characters = "";
		while($row = $sth2->fetch_array()) { 
			$characters.=$row['character_name'].", ";
		}
		
		$characters=substr($characters, 0, -2);		
		
		echo "<tr><td><b>$key_id</b> ($characters)</td><td>$state_text</td><td style=\"text-align: right;\">$timeDiffStr</td></tr>";
	}
	echo "</table>";
}

// see if there is any personal file entries for this user
$res4 = $db->query("SELECT p.uid, p.createdTs, p.createdBy_user_id, p.commentText, u.user_name 
FROM player_personal_file p, auth_users u WHERE u.user_id = p.createdBy_user_id AND u.user_id = $user_id
");

echo "<h3 id=\"personal\">Personal File</h3>";
if ($res4->num_rows == 0)
	echo "No entries available.<br />";
else
{
	echo "<table style=\"width: 100%\">";
	echo "<tr><th class=\"table_header\">By</th><th class=\"table_header\">Date</th><th class=\"table_header\">Comment</th></tr>";

	while ($crow = $res4->fetch_array())
	{
		$text = htmlspecialchars($crow['commentText']);
		$new_text = substr($text, 0, 50);
		if (strlen($text) > 50)
			$new_text .= "...";
		echo "<tr><td>" . $crow['user_name'] . "</td><td>" . $crow['createdTs'] . "</td>
		<td><a alt=\"Show Details\" title=\"Show Details\" href=\"api.php?action=player_personal_file&user_id=$user_id#" . $crow['uid'] . "\">" . $new_text . "</a></td>
		</tr>";
	}

	echo "</table>";
}

if (getPageAccessForThisUser('player_personal_file') == true)	
	echo "<a href=\"api.php?action=player_personal_file&user_id=$user_id#add\">Add another entry</a><br />";



$start_mtime = microtime(true);


        // kills per day
        // get kills per day for all chars over the last 6 months

		$kbqry = "SELECT k.`date` as d, SUM(number_kills) as kills FROM kills_stats_per_char k, api_characters c WHERE
		k.character_id = c.character_id AND c.user_id = $user_id AND k.`date`  > DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
		GROUP BY k.`date`
		ORDER BY k.`date`  ASC";

        $res = $db->query($kbqry);
        $kills_per_day = [];
        while ($row = $res->fetch_array())
        {
            $kills_per_day[$row['d']] = $row['kills'];
        }

        // fill with zeros if not active
        $interval = new DateInterval("P1D");
        $datesP = new DatePeriod(
            new DateTime(min(array_keys($kills_per_day))),
            $interval,
            new DateTime()
        );

        foreach ($datesP as $date) {
            $curdate =   $date->format('Y-m-d');
            if (!isset($kills_per_day[$curdate]))
                $kills_per_day[$curdate] = 0;
        }

        ksort($kills_per_day);



        echo "<h3>Kills Per Day (60 days)</h3>";
        printTimeHigh("Kills per Day (last 6 months)", strtotime(min(array_keys($kills_per_day))) * 1000, "24 * 3600 * 1000", $kills_per_day);

        echo "<h3 id=\"logonMinutes\">Logon Minutes</h3>";
		
		// logedonMinutes
		$qry = "SELECT `keyID`, MAX(`logonMinutes`) as `minutes`, DATE(`timestamp`) as `day`
				FROM `player_logonMinutes` 
				JOIN `api_characters` ON `player_logonMinutes`.`keyID` = `api_characters`.`key_id`
				WHERE `api_characters`.`user_id` = $user_id AND DATEDIFF(now(), timestamp) < 180
				GROUP BY `keyID`, `day`
				ORDER BY `keyID` ASC, `day` ASC";
		$res = $db->query($qry);
		
		$end_mtime = microtime(true) - $start_mtime;
		echo "<!-- 12th Query Time: $end_mtime -->";
		
		$minutes = array();
		if ($res && ($res->num_rows > 0)) {
			while (($row = $res->fetch_array()) != NULL) {
				if (!isset($minutes[$row["keyID"]])) {
					$minutes[$row["keyID"]] = array();
				}
				$minutes[$row["keyID"]][$row["day"]] = $row["minutes"];
			}
		}

		$deltasByDay = array();
		foreach ($minutes as $keyID => $arr) {
			$prev = 0;
			foreach ($arr as $date => $min) {
				if ($prev > 0) {
					if (!isset($deltasByDay[$date])) { $deltasByDay[$date] = 0; }
					if ((($min - $prev) / 60) > $deltasByDay[$date]) { $deltasByDay[$date] = (($min - $prev) / 60); }
				}
				$prev = $min;
			}
		}
		
		$interval = new DateInterval("P1D");
		$datesP = new DatePeriod(
			new DateTime(min(array_keys($deltasByDay))), 
			$interval, 
			new DateTime()
		);
		$dates = array();
		foreach ($datesP as $date) {
			$dates[] =  $date->format('Y-m-d');
		}
		$dates[] = max(array_keys($deltasByDay));
		foreach ($dates as $date) {
			if (!isset($deltasByDay[$date])) { $deltasByDay[$date] = 0; }
		}
		ksort($deltasByDay);
		
		
		
		echo "<div style=\"overflow: hidden;\">";
		printTimeHigh("Hours Logged on by Day (last 6 months)", strtotime(min(array_keys($deltasByDay))) * 1000, "24 * 3600 * 1000", $deltasByDay);
		
		$start_mtime = microtime(true);
		$qry = "SELECT `keyID`, `logonMinutes`, HOUR(`timestamp`) as `hour`
				FROM `player_logonMinutes` 
				WHERE `keyID` IN (
					SELECT DISTINCT `key_ID` 
					FROM `api_characters`
					WHERE `user_id` = $user_id
				) AND DATEDIFF(now(), timestamp) < 60 AND HOUR(timestamp) <> 0
				ORDER BY `keyID`, `timestamp` ASC";
		$res = $db->query($qry);
		
		$end_mtime = microtime(true) - $start_mtime;
		echo "<!-- 13th Query Time: $end_mtime -->";
		
		
		$hours = array_fill(0, 24, 0);
		$prevKeyID = 0;
		if ($res && ($res->num_rows > 0)) {
			while (($row = $res->fetch_array()) != NULL) {
				if ($prevKeyID != $row["keyID"]) {
					$prev = $row["logonMinutes"];
					$prevKeyID = $row["keyID"];
				} else {
					$delta = ($row["logonMinutes"] - $prev);
					$prev = $row["logonMinutes"];
					$curHour = $row["hour"];
					while ($delta > 60) {
						$hours[$curHour] += 60;
						$delta -= 60;
						$curHour -= 1;
						if ($curHour < 0) { $curHour += 24; }
					}
					$hours[$curHour] += $delta;
				}
			}
		}
		$sumY = 0;
		foreach ($hours as $y) { $sumY += $y; }
		$x = array();
		$y = array();
		foreach ($hours as $k => $v) {
			$x[] = $k;
			$y[] = ($v / $sumY) * 100;
		}
		$x[] = 24;
		$y[] = $y[0];
		printXYHigh("Activity by Time of Day (last 2 months)", $x, $y);
		echo "</div>";
		
		



// hide IP addresses for everyone but super admins
if ($isSuperAdmin) {
	echo "<h3>Known IP Addresses</h3>";
	echo "The following IP addresses were logging into this service for this toon/account (latest first):<br />";

	$sth = $db->query("SELECT DISTINCT(IP) as IP FROM log WHERE user_Id = $user_id ORDER BY time DESC LIMIT 0,20");

	while ($row = $sth->fetch_array()) {
		$ip = $row['IP'];
		echo "&nbsp;-&nbsp;$ip<br />";
	}
}

base_page_footer('', '');

?>
