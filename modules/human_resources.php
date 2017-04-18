<?php
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
			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);

            // char is director in several corps, but only one of them is also available on services
			if ($res->num_rows == 1)
			{
				$row = $res->fetch_array();
                $corp_id = $row['corp_id'];
                header("Location: api.php?action=human_resources&corp_id=$corp_id");
			} else {
                // list all corps where this toon is director

                // display corp selection page
                base_page_header('',"Human Resources - Select Corporation","Human Resources - Select Corporation");

                echo "<ul>";

                while ($row = $res->fetch_array()) {
                    $corp_id = $row['corp_id'];
                    echo "<li><a href=\"api.php?action=human_resources&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
                }

                echo "</ul>";

                base_page_footer('','');
            }

			exit;
		}
	}


    $can_show_supers = false;


    if (getPageAccessForUser('big_toys', $GLOBALS['userid']) == true) {
        $can_show_supers = true;
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
		// the only reason for this to be true is if the user is a manager
		// managers are allowed to look at the HR page for the corps they are in, so we need to check if a toon is in this corp

		if ($corp_id != $SETTINGS['main_corp_id']) {
			echo "Not allowed";
			exit;
		}
	}


	$corprow = $res->fetch_array();


	base_page_header('',"$corprow[corp_name] Human Resources","$corprow[corp_name] Human Resources");

	// load JQuery & HighChart librarys
	echo '<script src="js/highcharts.js"></script>';
    echo '<script src="js/drilldown.js"></script>';
	$containerID = 0;

	$time_start = microtime(true);
	echo "<!-- Time Start: 0 -->";

	// query corp members to see how many total members there is
	$sth = $db->query("select count(uid) as max from corp_members where corp_id = $corp_id ");
	$result=$sth->fetch_array();
	$corp_max=$result['max'];

	$sth = $db->query("select count(uid) as max from corp_members where corp_id=$corp_id and state <= 10");
	$result=$sth->fetch_array();
	$corp_current=$result['max'];


    // determine number of mains
    $sql = "select u.user_name
				from corp_members c, api_characters a, auth_users u
				WHERE c.corp_id = $corp_id AND a.character_id = c.character_id AND a.user_id = u.user_id GROUP BY u.user_name";
    $sth = $db->query($sql);
    $corp_mains = $sth->num_rows;


	echo "Welcome to Human Resources for $corprow[corp_name]";

	if ($isAdmin == true)
	{
		echo " (<a href=\"api.php?action=human_resources&ignore_main_corp_id=true\">Show all corps</a>)";
	}
    echo ".<br />";
    // Check Corp API Key
    echo "<b>Corp API Key Status</b>: ";
    $sql = "SELECT state, access_mask, last_checked FROM corp_api_keys WHERE corp_id = $corp_id";
    $res = $db->query($sql);
    if ($res->num_rows == 0)
    {
        echo "<b>There is no Corp API Key entered for this corporation.</b> If you are the CEO, please visit
            <a href=\"api.php?action=show_corp_keys\">Corp Keys</a> and add your Corp API Key.<br />";
    } else {
        $row = $res->fetch_array();
        $state_text=return_state_text($row['state']);

        echo $state_text . " (" . $row['last_checked'] . ")<br />";


        $sql = "SELECT a.character_id, a.character_name FROM corporations c, api_characters a WHERE a.character_id = c.ceo AND c.corp_id = $corp_id";
        $res = $db->query($sql);
        if ($res->num_rows == 1)
        {
            echo "<b>CEO</b>: ";
            $row = $res->fetch_array();

            echo $row['character_name'] . "<br />";

        }
    }

    echo "<hr />";




	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Mark: $diff -->
EOF;




	print <<<EOF
	<li><a href="api.php?action=corp_movement&state=1&corp_id=$corp_id">Corp arrivals</a> | <a href="api.php?action=corp_movement&state=2&corp_id=$corp_id">Corp departures</a></li>
			<li><a href="api.php?action=member_session_tracking&corp_id=$corp_id">Member session tracking</a></li>
		</ul>

		<br />
		Some charts are interactive, you can either click on the title or on some parts of the chart!<br />

EOF;
	echo "<div style=\"width: 100%; overflow: hidden;\">";
	$qry = "SELECT `character_id`, `character_name`, `forum_id`, `state`, `shipType`, `location`, `logonDateTime`
			FROM `corp_members`
			WHERE `corp_id` = $corp_id
			AND `state` != 0
			AND character_id <= 2099999999
			ORDER BY character_name ASC";
	$res = $db->query($qry);
	$datArray = array("The Good" => array("count" => $corp_current), "The Bad" => array("count" => $corp_max - $corp_current));
	if ($res && ($res->num_rows > 0)) {
		$datArray["The Bad"]["drill"] = array();
		while (($row = $res->fetch_array()) != NULL) {
			$datArray["The Bad"]["drill"][$row["character_name"]] = 1;
		}
	}

	echo "<table style=\"width: 100%\"><tr><th colspan=\"2\">API Keys and Members</th></tr>";

	echo "<tr><td style=\"vertical-align: top\">";

    echo "Total Characters: $corp_max<br />";
    echo "Characters with valid API Keys: " . ($corp_current) . "<br />";
    echo "<b><a href=\"api.php?action=member_audit&members=missing&corp_id=$corp_id\">Characters without valid API keys</a>: " . ($corp_max - $corp_current) . "</b><br />";
    echo "Actual Members (Main Characters): $corp_mains<br />";


    if (getPageAccessForUser('search', $GLOBALS['userid']) == true)
    {
        echo("<br />Search for a member:<br /><form method=\"post\" action=\"api.php?action=search\"><input style=\"width: 95%\" placeholder=\"Enter the members name\" type=\"text\" name=\"s\" /></form>");
    }


	echo "</td><td style=\"vertical-align: top\">";

	print <<<EOF
	Options:
<ul>
	<li><a href="api.php?action=member_audit&members=all&corp_id=$corp_id">List all members</a></li>
		  <li><a href="api.php?action=member_audit&members=registered&corp_id=$corp_id">List only API-validated members</a></li>
		  <li><a href="api.php?action=member_audit&members=missing&corp_id=$corp_id">List all missing members</a></li>
		  <li><a href="api.php?action=member_location&members=inactive&corp_id=$corp_id">List inactive members</a></li>
		  <li><a href="api.php?action=member_audit_role&members=all&corp_id=$corp_id">List members based on roles</a></li>
		  <li><a href="api.php?action=member_audit&members=all&corp_id=$corp_id&filter=true">List members based on their main</a></li>
		  <li><a href="api.php?action=activityByKills&corp_id=$corp_id">List members based on killboard activity</a></li>

</ul>
EOF;

	echo "</td></tr>";



	$qry = "SELECT `character_location`, COUNT(*) AS `count`
			FROM `api_characters`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			WHERE `corp_members`.`corp_id` = $corp_id
			GROUP BY `character_location`
			ORDER BY `count` DESC";
	$res = $db->query($qry);

	$datArray = array();
	$other = 0;
	$i = 0;
	while (($row = $res->fetch_array()) != NULL) {
		if ($i < 15) {
			$datArray[$row["character_location"]] = array("count" => $row["count"]);
		} else {
			if (!isset($datArray["Other"])) { $datArray["Other"] = array("count" => 0, "drill" => array()); }
			$datArray["Other"]["count"] += $row["count"];
			$datArray["Other"]["drill"][$row["character_location"]] = $row["count"];
		}
		$i++;
	}
	// echo "<a href=\"api.php?action=member_location&corp_id=$corp_id\">";
    echo "<tr><td colspan=\"2\">";
	printPieHigh("Locations of Chars (Top 15)", $datArray);

    echo "</td></tr>";

	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Location - Mark: $diff -->
EOF;


    // age of members
    $datArray = array(
        "< 3 Mo" => getMembersBetween("3 MONTH", "0 DAY"),
        "3-6 Mo" => getMembersBetween("6 MONTH", "3 MONTH"),
        "6-9 Mo" => getMembersBetween("9 MONTH", "6 MONTH"),
        "9-12 Mo" => getMembersBetween("1 YEAR", "9 MONTH"),
        "1-2 Years" => getMembersBetween("2 YEAR", "1 YEAR"),
        "2-5 Years" => getMembersBetween("5 YEAR", "2 YEAR"),
        "> 5 Years" => getMembersBetween("20 YEAR", "5 YEAR"),
    );

    $mainCount = 0;
    foreach ($datArray as $k => $v) { $mainCount += $v["count"]; }
    echo "<tr><td colspan=\"2\">";
    printPieHigh("$mainCount Members by Membership Duration", $datArray);
    echo "</td></tr>";


    $diff = microtime(true) - $time_start;
print <<<EOF
	<!-- Mark: $diff -->
EOF;


echo "</table>";



// capital ships
echo "<table style=\"width: 100%\"><tr><th>Capital Ships</th></tr>";

if ($can_show_supers) {
    $diff = microtime(true) - $time_start;
    print <<<EOF
	<!-- API Key PIechart - Mark: $diff -->
EOF;

    $qry = "SELECT `shipType`, COUNT(*) as `count`
			FROM `corp_members`
			WHERE `corp_id` = '$corp_id'
			AND `shipType` IN ('Aeon', 'Hel', 'Nyx', 'Wyvern', 'Revenant', 'Vendetta', 'Avatar', 'Erebus', 'Ragnarok', 'Leviathan', 'Vanquisher')
			GROUP BY `shipType`
			ORDER BY `count` DESC";
    $res = $db->query($qry);
    $data = array();
    $legend = array();
    while (($row = $res->fetch_array()) != NULL) {
        $data[] = $row["count"];
        $legend[] = $row["shipType"];
    }
    // echo "<a href=\"api.php?action=big_toys&corp_id=$corp_id\">";
    $datArray = array("Supers" => array("count" => 0, "drill" => array()), "Titans" => array("count" => 0, "drill" => array()));
    for ($i = 0; $i < count($data); $i++) {
        if (in_array($legend[$i], array("Avatar", "Erebus", "Ragnarok", "Leviathan"))) {
            $datArray["Titans"]["count"] += $data[$i];
            $datArray["Titans"]["drill"][$legend[$i]] = $data[$i];
        } else {
            $datArray["Supers"]["count"] += $data[$i];
            $datArray["Supers"]["drill"][$legend[$i]] = $data[$i];
        }
    }
    echo "<tr><td>";
    printPieHigh("<a href=\"api.php?action=big_toys&corp_id=$corp_id\">Big Toys</a>", $datArray);
    echo "</td></tr>";
    // echo "</a>";

    $diff = microtime(true) - $time_start;
    print <<<EOF
	<!-- Supers - Mark: $diff -->
EOF;

}

    $qry = "SELECT `auth_users`.`user_id`
                FROM `auth_users`
                JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
                JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
                JOIN `player_supercarriers` ON `api_characters`.`character_id` = `player_supercarriers`.`character_id`
                JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_supercarriers`.`typeID`
                WHERE `eve_staticdata`.`invTypes`.`groupID` = 659
                AND `corp_members`.`corp_id` = $corp_id
                GROUP BY `auth_users`.`user_id`";
    $res = $db->query($qry);
    $supers = $res->num_rows;

    $qry = "SELECT `auth_users`.`user_id`
                    FROM `auth_users`
                    JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
                    JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
                    JOIN `player_supercarriers` ON `api_characters`.`character_id` = `player_supercarriers`.`character_id`
                    JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_supercarriers`.`typeID`
                    WHERE `eve_staticdata`.`invTypes`.`groupID` = 30
                    AND `corp_members`.`corp_id` = $corp_id
                    GROUP BY `auth_users`.`user_id`";
    $res = $db->query($qry);
    $titans = $res->num_rows;

	$qry = "SELECT `auth_users`.`user_id`
			FROM `auth_users`
			JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			JOIN `player_supercarriers` ON `api_characters`.`character_id` = `player_supercarriers`.`character_id`
			JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_supercarriers`.`typeID`
			WHERE `eve_staticdata`.`invTypes`.`groupID` = 485
			AND `corp_members`.`corp_id` = $corp_id
			GROUP BY `auth_users`.`user_id`";
	$res = $db->query($qry);
	$dreads = $res->num_rows;

	$qry = "SELECT `auth_users`.`user_id`
			FROM `auth_users`
			JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			JOIN `player_supercarriers` ON `api_characters`.`character_id` = `player_supercarriers`.`character_id`
			JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_supercarriers`.`typeID`
			WHERE `eve_staticdata`.`invTypes`.`groupID` = 547
			AND `corp_members`.`corp_id` = $corp_id
			GROUP BY `auth_users`.`user_id`";
	$res = $db->query($qry);
	$carriers = $res->num_rows;

	$qry = "SELECT `auth_users`.`user_id`
			FROM `auth_users`
			JOIN `api_characters` ON `auth_users`.`user_id` = `api_characters`.`user_id`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			JOIN `player_supercarriers` ON `api_characters`.`character_id` = `player_supercarriers`.`character_id`
			JOIN `eve_staticdata`.`invTypes` ON `eve_staticdata`.`invTypes`.`typeID` = `player_supercarriers`.`typeID`
			WHERE `eve_staticdata`.`invTypes`.`groupID` = 1538
			AND `corp_members`.`corp_id` = $corp_id
			GROUP BY `auth_users`.`user_id`";
	$res = $db->query($qry);
	$faux = $res->num_rows;

    echo "<tr><td>";
	printPieHigh("Members with at least One",
        array(
            "Dread" => array("count" => $dreads),
            "Carrier" => array("count" => $carriers),
            "Super" => array("count" => $supers),
            "Titan" => array("count" => $titans),
            "Force Aux" => array("count" => $faux)
        ));

    echo "<b>More:</b> Show all members with at least one 
    	<a href=\"api.php?action=member_audit_byship&members=dread&corp_id=$corp_id\">Dread</a> /
    	<a href=\"api.php?action=member_audit_byship&members=carrier&corp_id=$corp_id\">Carrier</a> /
    	<a href=\"api.php?action=member_audit_byship&members=faux&corp_id=$corp_id\">Force Aux</a>.
    	";

echo "</td></tr>";

	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Carriers and Dreads - Mark: $diff -->
EOF;


    echo "</table>";


	$qry = "SELECT `user_name`, `user_id`
			FROM `auth_users`
			WHERE `user_id` IN (SELECT DISTINCT(`user_id`)
							FROM `api_characters`
							JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
							WHERE `api_characters`.`corp_id` = $corp_id
							AND `corp_members`.`corp_id` = $corp_id)
			ORDER BY `user_name` ASC";
	$res = $db->query($qry);
	$accountNames = array();
	$kills1 = array();
	$kills2 = array();
	if ($res->num_rows > 0) {
		while (($row = $res->fetch_array()) != NULL) {
			$accountNames[$row["user_id"]] = $row["user_name"];
			$kills1[$row["user_name"]] = 0;
			$kills2[$row["user_name"]] = 0;
		}
	}

	echo "<a href=\"api.php?action=long_time_corp_activity&corp_id=$corp_id\">Show Long Term Corp Statistics</a><br />";

	// new killboard stats
	// start with kills per day



	// get kills per day for this corp over the last 2 months
	$kbqry = "SELECT k.`date` as d, number_kills as kills FROM kills_stats_per_corp k WHERE
		k.corp_id = $corp_id AND k.`date`  > DATE_SUB(CURDATE(),INTERVAL 2 MONTH)
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

	printTimeHigh("Kills per Day (60 days)", strtotime(min(array_keys($kills_per_day))) * 1000, "24 * 3600 * 1000", $kills_per_day);



	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Mark: $diff -->
EOF;
	// logedonMinutes
	$qry = "SELECT `keyID`, MAX(`logonMinutes`) as `minutes`, DATE(`timestamp`) as `day`
			FROM `player_logonMinutes`
			JOIN `api_characters` ON `player_logonMinutes`.`keyID` = `api_characters`.`key_id`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
			WHERE `api_characters`.`corp_id` = $corp_id
			AND `corp_members`.`corp_id` = $corp_id AND DATEDIFF( NOW( ) ,  `timestamp` ) <60
			GROUP BY `keyID`, `day`
			ORDER BY `keyID` ASC, `day` ASC";
	$res = $db->query($qry);
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
				$deltasByDay[$date] += (($min - $prev) / 60);
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

echo "<!--";
print_r($deltasByDay);
echo "-->";

	printTimeHigh("Total Hours Logged on by Day (60 days)", strtotime(min(array_keys($deltasByDay))) * 1000, "24 * 3600 * 1000", $deltasByDay);

	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Hours logged on - Mark: $diff -->
EOF;






	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Mark: $diff -->
EOF;
	// logged on players
	$qry = "SELECT DATE_FORMAT(`logonDateTime`,'%Y-%m-%d') as datePoint, YEAR( logonDateTime ) AS y, MONTH( logonDateTime ) AS m, DAY( logonDateTime ) AS d, COUNT( DISTINCT (
character_id
) ) AS cnt
FROM session_tracking
WHERE corp_id = $corp_id AND DATEDIFF( NOW( ) , logonDateTime ) <60
GROUP BY YEAR( logonDateTime ) , MONTH( logonDateTime ) , DAY( logonDateTime )
ORDER BY YEAR( logonDateTime )  , MONTH( logonDateTime ) , DAY( logonDateTime )
 ";


	$res = $db->query($qry);
	$playerCount = array();
	if ($res && ($res->num_rows > 0)) {
		while (($row = $res->fetch_array()) != NULL) {
			$playerCount[  $row['datePoint']  ] = $row['cnt'];
		}
	}

	ksort($playerCount);


	printTimeHigh("<a href=\"api.php?action=member_session_tracking&corp_id=$corp_id\">Players Logged on by Day (60 days)</a>",
		strtotime(min(array_keys($playerCount))) * 1000, "24 * 3600 * 1000", $playerCount);



echo "<!-- ";
	print_r(strtotime(array_keys($playerCount)) * 1000);
	echo "-->";

	$diff = microtime(true) - $time_start;
	print <<<EOF
	<!-- Members logged on per days - Mark: $diff -->
EOF;






	echo "</div>";

	// get last executed
	$sql = "SELECT last_executed FROM cronjobs WHERE name='corp_members'";
	$res = $db->query($sql);

	$row = $res->fetch_array();
	echo "Last updated: $row[last_executed]<br /><br />";

	$diff = microtime(true) - $time_start;
	echo "<!-- End: $diff -->";

	base_page_footer('','');



function getMembersBetween($from, $to) {
	global $db;
	global $corp_id;
	$qry = "SELECT `user_name`
			FROM (	SELECT `user_name`, MIN(`corp_members`.`startDateTime`) as `joined`
					FROM `api_characters`
					JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
					JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
					WHERE `api_characters`.`corp_id` = $corp_id
					AND `corp_members`.`corp_id` = $corp_id
					GROUP BY `user_name`) as `blub`
			WHERE `joined` > DATE_SUB(CURDATE(),INTERVAL $from)
			AND `joined` <= DATE_SUB(CURDATE(), INTERVAL $to)";
	$res = $db->query($qry);
	$ret = array("count" => 0, "drill" => array());
	if ($res && ($res->num_rows > 0)) {
		while (($row = $res->fetch_array()) != NULL) {
			$ret["count"]++;
			$ret["drill"][$row["user_name"]] = 1;
		}
	}
	return $ret;
}
?>
