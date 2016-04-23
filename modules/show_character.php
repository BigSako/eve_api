<?php
	$db = connectToDB();

	if (!isset($_REQUEST['character_id']))
	{
		echo "ERROR: No character id set";
		exit();
	}
	
	$c_id = intval($_REQUEST['character_id']);
	
	// $c_id holds the character id of the character we are looking at
	base_page_header("",'Showing Character Information','Showing Character Information');
	$containerID = 0;
	echo '<script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>';
	echo '<script src="js/highcharts.js"></script>';
	echo '<script src="js/drilldown.js"></script>';

	
	// before we show member information, we need to make sure that the user is allowed to look at it
	if ($isAdmin)
	{
		// ok
	} else
	{
		$my_user_id = $GLOBALS['userid'];
		$sql = "SELECT c.user_id FROM api_characters c WHERE c.character_id = $c_id AND c.corp_id in (SELECT a.corp_id FROM api_characters a WHERE a.user_id = $my_user_id)";
		$sth = $db->query($sql);
		if ($sth->num_rows == 0)
		{
			echo "Not allowed<br />";
			base_page_footer('','<a href="api.php?action=human_resources">Back</a>');
			exit;
		}
	}
	
	$is_allowed_to_view_account = getPageAccessForThisUser('show_member');
	$canViewCharacterHistory = getPageAccessForThisUser('show_character_history');
	
	
	$start_mtime = microtime(true); 
	$sth = $db->query("SELECT c.character_name, c.corp_name, c.user_id, c.state, skillpoints,
				walletBalance, character_last_ship, character_location, jumpFatigue,jumpActivation
				FROM api_characters c 
				WHERE c.character_id = $c_id");
	$end_mtime = microtime(true) - $start_mtime;
	
	echo "<!-- First Query Time: $end_mtime -->";
	
	if ($sth->num_rows > 0)
	{	
		$row = $sth->fetch_array();
		$user_id = $row['user_id'];
		$this_char_name = $name = $row['character_name'];
		$corp = $row['corp_name'];
		$skillpoints = $row['skillpoints'];
		$ship = $row['character_last_ship'];
		$balance = $row['walletBalance'];
		$location = $row['character_location'];
		$jumpFatigue = $row['jumpFatigue'];
		$jumpActivation = $row['jumpActivation'];
		$jumpActivationTime = strtotime($jumpActivation)-strtotime($curEveTime);
		$jumpFatigueTime = strtotime($jumpFatigue)-strtotime($curEveTime);
		$jumpActivationString = secondsToTimeString($jumpActivationTime);
		$jumpFatigueString = secondsToTimeString($jumpFatigueTime);
		
		$balanceStr = number_format($balance, 2, '.', ',');
		
		echo "<table><tr><td style=\"vertical-align: top\">";
		
		echo "<h3>$name</h3><br />";		
		
		if ($c_id != -1)
			echo "<img src=\"//imageserver.eveonline.com/Character/" . $c_id . "_200.jpg\">";
		else
			echo "No characters available";
			
		echo "</td><td>";
		
		echo "<table width=\"100%\"><tr><td>Member User Id:</td><td>$user_id";
		
		if ($is_allowed_to_view_account)
		{
			echo " [<a href=\"api.php?action=show_member&user_id=$user_id\">Show Member Information</a>]";
		}
		
		
		echo "</td></tr>
			<tr><td>Character Name/ID:</td><td>$name ($c_id)</td></tr>
			<tr><td>Corporation:</td><td>$corp</td></tr>
			<tr><td>Skillpoints:</td><td>$skillpoints [<a href=\"api.php?action=skillsheet&character_id=$c_id\">Skillsheet</a>]</td></tr>";
		
		$location_link = "http://evemaps.dotlan.net/system/" . str_replace(' ', '_', $location);

		echo "<tr><td>Current Ship:</td><td>$ship</td></tr>
			<tr><td>Location:</td><td>$location <a target=\"_blank\" href=\"$location_link\"><img src=\"images/world.png\" /></a></td></tr>
			<tr><td>Wallet Balance:</td><td>$balanceStr ISK
			[<a href=\"api.php?action=player_wallet_data&character_id=$c_id\">Wallet Log</a>]</td></tr>";
			
		if ($jumpFatigueTime > 0)
		{
			echo "<tr><td><img src=\"images/jump_fatigue_timer.png\" width=\"32\" height=\"32\" title=\"Fatigue\"/> Fatigue: </td><td>$jumpFatigueString</td></tr>";
		}
			
		if ($jumpActivationTime > 0)
		{
			echo "<tr><td><img src=\"images/jump_activation_timer.png\" width=\"32\" height=\"32\" title=\"Jump Activation\"/>Next Jump Activation: </td><td>$jumpActivationString</td></tr>";
		}			
		

		echo "</table></td></tr>";
		
		 	
		// check if we have more information about this CHARACTER in corp_members table
		echo "<tr><td><b>API Key</b></td><td>";
		$sql = "SELECT a.keyid, a.state, a.paidUntil, a.is_allied FROM `player_api_keys` a, api_characters c where c.character_id = $c_id and c.key_id = a.keyid ";
		$api_key_res = $db->query($sql);
		$api_key_row = $api_key_res->fetch_array();
		
		$api_key_id = $api_key_row['keyid'];
		$api_key_state = "<b>" . return_state_text($api_key_row['state']) . "</b>";
		$api_paid_until = $api_key_row['paidUntil'];
		$is_allied = $api_key_row['is_allied'];
		
		if ($is_allied == 1)
		{
			$is_allied_str = "Limited (Allied) API Key";
		} else {
			$is_allied_str = "Full API Key";
		}
		
		echo "<table><tr><td>ID: $api_key_id</td><td>$api_key_state</td><td>$is_allied_str</td></tr><tr><td>Paid Until:</td><td colspan=\"2\">$api_paid_until</td></tr></table>";
		
		echo "</td></tr>";
		
		$res = $db->query("SELECT corp_id, character_name, shipType, location, logonDateTime, startDateTime, roles FROM corp_members WHERE character_id=$c_id");

			if ($res->num_rows >= 1)
		{
			$crow = $res->fetch_array();
			$logonDateTime = $crow['logonDateTime'];
			$startDateTime = $crow['startDateTime'];
			
			echo "<tr><td>Last Logon:</td><td>$logonDateTime</td></tr>
				<tr><td>Join Date:</td><td>$startDateTime</td></tr>";
		} else {
			echo "<b>Warning:</b> Character is not in corp_members table.<br />";
		}
		
		echo "<tr><td colspan=\"2\">";
		
		echo "<h3>Roles</h3>";
		$roles = $crow['roles'];
		$binarr = array_reverse(str_split(decbin($roles)));
	
		if ($binarr[0] == 1)
		{
			echo "<b>Director</b>";
		} else {

			for ($i = 0; $i < count($binarr); $i++)
			{
				if ($binarr[$i] == 1)
				{
					if ($roleDescriptionArray[$i] != "")
						echo $roleDescriptionArray[$i] . "  <br />";
				}
			}
		}
		echo "<br />";
		$start_mtime = microtime(true);
		// get roles and titles
		$res3 = $db->query("SELECT t.title as title FROM titles t, api_titles a WHERE t.id = a.title_id and a.user_id = $c_id");
		$end_mtime = microtime(true) - $start_mtime;
	
		echo "<!-- Third Query Time: $end_mtime -->";
		
		
		$knownTitles = "";
		while ($row3 = $res3->fetch_array())
		{
			$knownTitles .= $row3["title"] . "<br />";
		}
		echo "<h3>Known Titles</h3>" . $knownTitles;
		echo "<br />";	
		
		 
		 
		 echo "</td></tr></table>";

		 echo "<hr id=\"main_hr\"/>";
		 
	}
	else if ($c_id == -1) {
		print "This Account has no EvE Accounts linked.<br />";
	}
	else {		
		$start_mtime = microtime(true);
		$res = $db->query("SELECT corp_id, character_name, shipType, location, logonDateTime, startDateTime, roles FROM corp_members WHERE character_id=$c_id ");
		$end_mtime = microtime(true) - $start_mtime;
	
		echo "<!-- Fourth Query Time: $end_mtime -->";
		
		
		if ($res->num_rows == 1)
		{
			echo "There is no information available as there is no EvE Accounts registered. Displaying information from Corp API:<br /><br />";
			$row = $res->fetch_array();
			echo "<h3>" . $row['character_name'] . "</h3>";
			echo "Character ID: " . $c_id . "<br />";
			echo "Ship Type: " . $row['shipType'] . "<br />Location: " . $row['location'] . "<br />Last Logon: " . $row['logonDateTime'] . "<br />";
			echo "Join Date: " . $row['startDateTime'] . "<br />";

			echo "<b>Roles:</b>";
			$roles = $row['roles'];
			$binarr = array_reverse(str_split(decbin($roles)));
		
			if ($binarr[0] == 1)
			{
				echo "<b>Director</b>!!!";
			} else {

				for ($i = 0; $i < count($binarr); $i++)
				{
					if ($binarr[$i] == 1)
					{
						if ($roleDescriptionArray[$i] != "")
							echo $roleDescriptionArray[$i] . "  <br />";
					}
				}
			}
			echo "<br />";
			
		} else {
			echo "There is no information available in our database.";
		}
		
		echo "<br />";
		
	}
		 

	if ($user_id != 0 && $user_id != "")
	{
		$start_mtime = microtime(true);
		// get account information 
		$acc = $db->query("SELECT user_name, forum_id, ts3_user_id, email, has_regged_main FROM auth_users WHERE user_id = $user_id");
		$end_mtime = microtime(true) - $start_mtime;
	
		echo "<!-- 5th Query Time: $end_mtime -->";
		
		$acc_res = $acc->fetch_array();

		echo "<h3 id=\"account\">Account Information</h3>";

		echo "<table style=\"width: 100%\">";
		echo "<tr><th class=\"table_header\">Type</th><th class=\"table_header\">Value</th><th class=\"table_header\">Options</th></tr>";
		
		echo "<tr><td>E-Mail</td><td>" . $acc_res['email'] . "</td><td>&nbsp;</td></tr>
		<tr><td>Forum-Name</td><td>" . 
		 "<a href=\"/forum/member.php?" . $acc_res['forum_id'] ."\">" . $acc_res['user_name'] . "</a></td><td>&nbsp;</td></tr>";

		if ($isSuperAdmin == true)
		{
			// asume as user
			echo "<tr><td>Account</td><td><a href=\"api.php?action=asume_user&forum_id=" . $acc_res['forum_id'] . "\">Log on as this user</a></td></tr>";
		}

		$has_regged_main = intval($acc_res['has_regged_main']);
		if ($has_regged_main != 0)
		{
			// get the name of the main character
			$main = $db->query("SELECT a.character_name, a.corp_id FROM api_characters a WHERE a.character_id = $has_regged_main ");
			$main_row = $main->fetch_array();
			
			echo "<tr><td>Main Character</td><td>" . $main_row['character_name'] . "</td><td>&nbsp;</td></tr>";
		}
		else
		{
			echo "<tr><td>Main Character</td><td>User has not selected main character yet!</td><td>&nbsp;</td></tr>";
		}
		
		
		
		 if ($acc_res['ts3_user_id'] != '')
		 {
			//echo 'Teamspeak User ID: ' . $acc_res['ts3_user_id'] . '<br />';
			// what name is expected?
			if ($has_regged_main != 0)
			{
				$start_mtime = microtime(true);
				// get corp ticker
				$sql = "SELECT a.character_name, c.corp_name, c.corp_ticker, d.is_allied FROM api_characters a, corporations c, alliances d
						WHERE a.character_id = $has_regged_main AND a.corp_id = c.corp_id AND d.alliance_id = c.alliance_id AND
						(c.is_allowed_to_reg = 1 or d.is_allowed_to_reg = 1 or c.is_allied = 1 or d.is_allied = 1)";
				$name_res = $db->query($sql);
				$end_mtime = microtime(true) - $start_mtime;
				echo "<!-- 6th Query Time: $end_mtime -->";

				if ($name_res->num_rows == 1)
				{
					$name_row = $name_res->fetch_array();
					echo "<tr><td>Expected Teamspeak Username</td><td>" . substr($name_row['character_name'], 0, 30) . "</td><td>&nbsp;</td></tr>";
				} else {
					echo "<tr><td>Expected Teamspeak Username</td><td>NOT ALLOWED ON TEAMSPEAK</td><td>&nbsp;</td></tr>";
				}
			}
		 }


		echo "</table>";

		
		
		$start_mtime = microtime(true);
		 
		

		// get all information to this user_id
		echo "<h3 id=\"affill\">Affiliation</h3>";
		
		if ($is_allowed_to_view_account)
		{
			echo " <a href=\"api.php?action=show_member&user_id=$user_id\">Show Detailed Member Information</a>";
		}
		
		



		// get kills for all characters this month
		$kbqry = "SELECT c.character_name, SUM(k.number_kills) as kills FROM kills_stats_per_char k, api_characters c WHERE
		k.character_id = c.character_id AND k.`date` > DATE_SUB(CURDATE(),INTERVAL 1 MONTH)
		AND c.user_id = " . $user_id . " GROUP BY c.character_name";

		$kbres = $db->query($kbqry);

		$kills_1month = array();

		while ($kbrow = $kbres->fetch_array()) {
			$kills_1month[$kbrow['character_name']] = $kbrow['kills'];
		}


		// get kills for all characters this year
		$kbqry = "SELECT c.character_name, SUM(k.number_kills) as kills FROM kills_stats_per_char k, api_characters c WHERE
		k.character_id = c.character_id AND k.`date` > DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
		AND c.user_id = " . $user_id . " GROUP BY c.character_name";

		if (!$kbres = $db->query($kbqry))
		{
			echo "Error: " . $db->error . "<br />\nsql='$kbqry'<br />\n";
		}

		$kills_1year = array();

		while ($kbrow = $kbres->fetch_array()) {
			$kills_1year[$kbrow['character_name']] = $kbrow['kills'];
		}


		// get kills for all characters alltime
		$kbqry = "SELECT c.character_name, SUM(k.number_kills) as kills FROM kills_stats_per_char k, api_characters c WHERE
		k.character_id = c.character_id
		AND c.user_id = " . $user_id . " GROUP BY c.character_name";

		if (!$kbres = $db->query($kbqry))
		{
			echo "Error: " . $db->error . "<br />\nsql='$kbqry'<br />\n";
		}

		$kills_alltime = array();

		while ($kbrow = $kbres->fetch_array()) {
			$kills_alltime[$kbrow['character_name']] = $kbrow['kills'];
		}


		
		

		
		// check which other toons are affiliated with this
		echo "<br />The following characters are affiliated with " . $this_char_name . " via API.<br />";
		echo "<table style=\"width: 100%\">
		<tr>
			<th class=\"table_header\">Name</th>
			<th class=\"table_header\">Corp</th>
			<th class=\"table_header\">Killboard (1M / 1Y / A)</th>
			<th class=\"table_header\">Location</th><th class=\"table_header\">Ship</th>
			<th class=\"table_header\">Cyno Skill</th>
		</tr>";
		
		$start_mtime = microtime(true);
		$chars = $db->query("SELECT character_id, character_name, corp_id, corp_name, character_location, 
		character_last_ship, cyno_skill, walletBalance
		FROM api_characters WHERE user_id = $user_id ORDER BY character_name ASC");
		$end_mtime = microtime(true) - $start_mtime;
		echo "<!-- 11th Query Time: $end_mtime -->";
		$i = 0;
		$totalBalance = 0.0;
		$total_kills_onemonth = 0;
        $total_kills_oneyear = 0;
        $total_kills_alltime = 0;
		
		while ($row = $chars->fetch_array())
		{
			if (($i % 2) == 0)
			{
				$bgclass = "td_darkgray";
			} else {
				$bgclass = "td_lightgray";
			}	
			
			$totalBalance += $row['walletBalance'];
			
			
			if (isset($kills_1month[$row['character_name']]))
			{
				$kills_this_month = $kills_1month[$row['character_name']];
			}
			else
			{
				$kills_this_month = 0;
			}
			
			
			if (isset($kills_1year[$row['character_name']]))
			{
				$kills_this_year = $kills_1year[$row['character_name']];
			}
			else
			{
				$kills_this_year = 0;
			}

			if (isset($kills_alltime[$row['character_name']]))
			{
				$kills_every_year = $kills_alltime[$row['character_name']];
			} else {
				$kills_every_year = 0;
			}
			
			$total_kills_onemonth += $kills_this_month;
			$total_kills_oneyear += $kills_this_year;
			$total_kills_alltime += $kills_every_year;

			echo "<tr class=\"$bgclass\"><td><a href=\"api.php?action=show_member&character_id=" . $row['character_id'] . "\">" . $row['character_name'] . "</a> 
			(<a alt=\"Wallet Data\" title=\"Wallet Data\" href=\"api.php?action=player_wallet_data&character_id=" . $row['character_id'] . "\">W</a>
			 
		<!--	<a alt=\"EvE Mails\" title=\"EvE Mails\" href=\"\">E</a> -->
			)
			
			</td>
			<td>" . $row['corp_name'] . "</td><td>$kills_this_month / $kills_this_year / $kills_every_year
			<a target=\"_blank\" alt=\"ZKillboard\" title=\"ZKillboard\" href=\"https://zkillboard.com/character/" . $row['character_id'] . "/\">ZKillboard</a>
			</td><td>" . $row['character_location'] . "</td><td>" . $row['character_last_ship'] . "</td><td>" . $row['cyno_skill'] . "</td></tr>";
			
			
			$i++;
		}

		echo "</table>";
		
		echo "<b>Total Kills (1 <b>M</b>onth / 1 <b>Y</b>ear / <b>A</b>lltime):</b> $total_kills_onemonth / $total_kills_oneyear / $total_kills_alltime<br />";
		
		$balanceStr = number_format($totalBalance, 2, '.', ',');
		
		echo "<br /><b>Total Wallet Balance</b>: $balanceStr<br />";
		

		/* SELECT SUM(number_kills) as kills, SUM(number_losses) as losses FROM kills_stats_per_char k, api_characters c 
	WHERE k.character_id = c.character_id A

	*/

        // kills per day
        // get kills per day for all chars over the last 6 months
        $kbqry = "SELECT k.`date` as d, number_kills as kills FROM kills_stats_per_char k WHERE
		k.character_id = $c_id AND k.`date`  > DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
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


        echo "<h3>Kills Per Day for $name (Last 6 Months)</h3>";
        printTimeHigh("Kills per Day for $name (Last 6 months)", strtotime(min(array_keys($kills_per_day))) * 1000, "24 * 3600 * 1000", $kills_per_day);

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
		


		// hide audit log for everybody but admins
		if ($isAdmin) {
			echo "<h3>Audit Log</h3>";
			$sql = "SELECT l.user_id, u.user_name, l.logText, l.ip, l.logTimestamp
		FROM audit_log l, auth_users u
		WHERE
		u.user_id = l.user_id AND  l.ip <> 'cron'  AND l.user_id = $user_id ORDER BY l.logTimestamp DESC ";
			$sth = $db->query($sql);

			print("<table style=\"width:100%\">");
			print("<tr><td class=\"long_table_header\" colspan=\"5\">Log Entries</td></tr>\n");
			print("<tr><td width=\"150\" class='your_characters_header'>User</td>" .
				"<td width=\"150\" class='your_characters_header'>Timestamp</td>" .
				"<td class='your_characters_header'>Text</td>" .
				"<td class='your_characters_header'>IP</td></tr>");


			while ($srow = $sth->fetch_array()) {
				$user = $srow['user_id'];
				if ($user <= 0)
					$user = "Cron";
				else
					$user = "<a href=\"api.php?action=show_member&user_id=$user\">" . $srow['user_name'] . "</a>";

				echo "<tr><td>$user</td><td>" . $srow['logTimestamp'] . "</td>";

				echo "<td>" . $srow['logText'] . "</td>";

				if ($srow['ip'] == 'cron') {
					echo "<td>&nbsp;</td>";
				} else
					echo "<td>" . $srow['ip'] . "</td>";


				echo "</tr>";
			}


			echo "</table>";
		}

		if ($canViewCharacterHistory)
		{
			echo "<h3>Character History</h3>This character has been associated to the following accounts:";
			$sql = "SELECT h.userid, h.last_seen, a.user_name FROM character_history h, auth_users a 
			WHERE h.userid = a.user_id AND h.character_id = $c_id ORDER BY h.last_seen DESC";
			$res = $db->query($sql);

			if (!$res)
			{
				echo $sql . " failed! Error: " . $db->error;
			}

			echo "<table>";
			echo "<tr><th>User Name</th><th>Time</th></tr>";
			while ($row = $res->fetch_array())
			{
				if ($is_allowed_to_view_account)
				{
					echo "<tr><td><a href=\"api.php?action=show_member&user_id=" . $row['userid'] . "\">" . $row['user_name'] . "</a></td><td>" . $row['last_seen'] . "</td></tr>";
				} else {
					echo "<tr><td>" . $row['user_name'] . "</td><td>" . $row['last_seen'] . "</td></tr>";
				}
			}

			echo "</table>";
		}


		// hide IP addresses for everyone but admins
		if ($isSuperAdmin) {
			echo "<h3>Known IP Addresses</h3>";
			echo "The following IP addresses were logging into this service for this toon/account (latest first):<br />";

			$sth = $db->query("SELECT DISTINCT(IP) as IP FROM log WHERE user_Id = $user_id ORDER BY time DESC LIMIT 0,20");

			while ($row = $sth->fetch_array()) {
				$ip = $row['IP'];
				echo "&nbsp;-&nbsp;$ip<br />";
			}
		}
		
			

	}
	
      
	base_page_footer('','<a href="api.php?action=human_resources">Back</a>');


?>
