<?php
	$corp_id = -1;

	// check for corp id
	if (!isset($_REQUEST['corp_id']))
	{
		exit();
	}

	if (!isset($_REQUEST['year']))
	{
		$start_date = date('Y-m-d', time()-365*24*60*60);
		$end_date = date('Y-m-d');
		$year = intval(date('Y'));
	} else {
		$year = intval($_REQUEST['year']);
		$start_date = "$year-1-1";
		$end_date = "$year-12-31";
	}

	 
	$corp_id = intval($_REQUEST['corp_id']);

	$db = connectToDB();

	$sql = "SELECT corp_name, ceo, corp_ticker FROM corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);

	if ($res->num_rows != 1)
	{
		echo "invalid corp";
		exit;
	}

	if (!in_array($corp_id, $director_corp_ids) && !$isAdmin)
	{
		// the only reason for this to be true is if the user is a manager
		// managers are allowed to look at the HR page for the corps they are in, so we need to check if a toon is in this corp
		echo "Not allowed";
		exit;		
	}


	$corprow = $res->fetch_array();


	base_page_header('',"$corprow[corp_name] Human Resources - Long Time History","$corprow[corp_name] Human Resources - Long Time History");


	$current_year = intval(date('Y'));

	echo "<a href=\"api.php?action=long_time_corp_activity&corp_id=$corp_id&year=" . ($year - 1) . "\">&lt; " . ($year - 1)  . "</a>";

	if ($year < $current_year)
	{
		echo " | <a href=\"api.php?action=long_time_corp_activity&corp_id=$corp_id&year=" . ($year + 1) . "\">" . ($year + 1)  . " &gt;</a>";
	}

	// load JQuery & HighChart librarys
	echo '<script src="js/highcharts.js"></script>';
    echo '<script src="js/drilldown.js"></script>';
	$containerID = 0;


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


	// new killboard stats
	// start with kills per day



	// get kills per day for this corp over the last 24 months
	$kbqry = "SELECT k.`date` as d, number_kills as kills FROM kills_stats_per_corp k WHERE
		k.corp_id = $corp_id AND k.`date` >= '$start_date' and k.`date` <= '$end_date'
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
		new DateTime($start_date),
		$interval,
		new DateTime($end_date)
	);

	foreach ($datesP as $date) {
		$curdate =   $date->format('Y-m-d');
		if (!isset($kills_per_day[$curdate]))
			$kills_per_day[$curdate] = 0;
	}

	ksort($kills_per_day);

	printTimeHigh("Kills per Day (360 days)", strtotime(min(array_keys($kills_per_day))) * 1000, "24 * 3600 * 1000", $kills_per_day);


	// logedonMinutes
	$qry = "SELECT `keyID`, MAX(`logonMinutes`) as `minutes`, DATE(`timestamp`) as `day`
			FROM `player_logonMinutes`
			JOIN `api_characters` ON `player_logonMinutes`.`keyID` = `api_characters`.`key_id`
			JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
			JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
			WHERE `api_characters`.`corp_id` = $corp_id
			AND `corp_members`.`corp_id` = $corp_id AND `timestamp` > '$start_date' AND `timestamp` < '$end_date'
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
			new DateTime($start_date),
			$interval,
			new DateTime($end_date)
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

	printTimeHigh("Total Hours Logged on by Day (360 days)", strtotime(min(array_keys($deltasByDay))) * 1000, "24 * 3600 * 1000", $deltasByDay);




	// logged on players
	$qry = "SELECT DATE_FORMAT(`logonDateTime`,'%Y-%m-%d') as datePoint, YEAR( logonDateTime ) AS y, MONTH( logonDateTime ) AS m, DAY( logonDateTime ) AS d, COUNT( DISTINCT (
character_id
) ) AS cnt
FROM session_tracking
WHERE corp_id = $corp_id AND (logonDateTime >= '$start_date' and logonDateTime <= '$end_date')
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


	printTimeHigh("<a href=\"api.php?action=member_session_tracking&corp_id=$corp_id\">Players Logged on by Day (360 days)</a>",
		strtotime(min(array_keys($playerCount))) * 1000, "24 * 3600 * 1000", $playerCount);


	echo "</div>";

	// get last executed
	$sql = "SELECT last_executed FROM cronjobs WHERE name='corp_members'";
	$res = $db->query($sql);

	$row = $res->fetch_array();
	echo "Last updated: $row[last_executed]<br /><br />";

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
