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




// get account names
$db = connectToDB();

$extra_header = "All Kills";

if (isset($_REQUEST['onlyShowNewGuys']))
{
	$onlyShowNewGuys = true;
	$extra_header = "Only New Guys";
}
else
	$onlyShowNewGuys = false;


if (isset($_REQUEST['filterLogis']))
{	
	$filterLogis = true;
	$extra_header = "Only Logi Pilots";
}
else
	$filterLogis = false;

if (isset($_REQUEST['filterSupers'])) {
	$filterSupers = true;
	$extra_header = "Only Super and Titan Pilots";
}
else
	$filterSupers = false;



base_page_header('',"Activity by Kills - $extra_header","Activity by Kills - $extra_header");


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
if ($res->num_rows > 0) {
	while (($row = $res->fetch_array()) != NULL) {
		$accountNames[$row["user_id"]] = $row["user_name"];
	}
}

// get newbros
$newGuys = array();
$qry = "SELECT `user_id`
		FROM (	SELECT api_characters.`user_id`, MIN(`corp_members`.`startDateTime`) as `joined`
				FROM `api_characters` 
				JOIN `corp_members` ON `api_characters`.`character_id` = `corp_members`.`character_id`
				JOIN `auth_users` ON `api_characters`.`user_id` = `auth_users`.`user_id`
				WHERE `api_characters`.`corp_id` = $corp_id
				AND `corp_members`.`corp_id` = $corp_id
				GROUP BY `user_name`) as `blub`
		WHERE `joined` > DATE_SUB(CURDATE(),INTERVAL 3 MONTH)";
$res = $db->query($qry);

if (!$res)
    echo $qry;
if ($res->num_rows > 0) {
	while (($row = $res->fetch_array()) != NULL) {
		$newGuys[] = $row["user_id"];
	}
}


// initialize kills array
$kills['1m'] = array();
$kills['3m'] = array();
$kills['6m'] = array();
$kills['12m'] = array();
$kills['24m'] = array();
$kills['36m'] = array();
$kills['all'] = array();


foreach ($accountNames as $user_id => $user_name)
{
	$kills['1m'][$user_id] = 0;
	$kills['3m'][$user_id] = 0;
	$kills['6m'][$user_id] = 0;
	$kills['12m'][$user_id] = 0;
	$kills['24m'][$user_id] = 0;
	$kills['36m'][$user_id] = 0;
	$kills['all'][$user_id] = 0;
}


// determine which kill count we want
$query_column = "number_kills";

if ($filterSupers)
{
	$query_column = "number_kills_super";
}
if ($filterLogis)
{
	$query_column = "number_kills_logi";
}



// for each character in our corp, select from the history table
$sql = "SELECT a.user_id, sum(k.$query_column) sum_kills, k.`date` FROM api_characters c, auth_users a, kills_stats_per_char k
WHERE a.user_id = c.user_id AND k.character_id = c.character_id AND c.corp_id = $corp_id AND k.corp_id = $corp_id 
GROUP BY a.user_id, k.`date`
ORDER BY a.user_id, k.`date`
";


$res = $db->query($sql);



while ($row = $res->fetch_array())
{
	$user_id = $row['user_id'];
	$num_kills   = $row['sum_kills'];
	$kills_date = $row['date'];


	$timediff = $diff = abs(time() - strtotime($kills_date));

	$timediff_days = floor($timediff / (60 * 60 * 24 * 30));

	$kills['all'][$user_id] += $num_kills;

	if ($timediff_days <= 1)
		$kills['1m'][$user_id] += $num_kills;
	
	if ($timediff_days <= 3)
		$kills['3m'][$user_id] += $num_kills;

	if ($timediff_days <= 6)
		$kills['6m'][$user_id] += $num_kills;

	if ($timediff_days <= 12)
		$kills['12m'][$user_id] += $num_kills;

	if ($timediff_days <= 24)
		$kills['24m'][$user_id] += $num_kills;

	if ($timediff_days <= 36)
		$kills['36m'][$user_id] += $num_kills;



}


echo "<b>Filter:</b> ";

echo "<a href=\"api.php?action=activityByKills&corp_id=$corp_id&onlyShowNewGuys=1\">Show Only New Guys</a> | ";
echo "<a href=\"api.php?action=activityByKills&corp_id=$corp_id&filterLogis=1\">Show Only Killmails of Logi Pilots</a> | ";
echo "<a href=\"api.php?action=activityByKills&corp_id=$corp_id&filterSupers=1\">Show Only Killmails of Super and Titan Pilots</a> | ";
echo "<a href=\"api.php?action=activityByKills&corp_id=$corp_id\">Reset</a>";

echo "<br />";
echo "Table is sorted alphabetically! Click at the column headers to sort it by the selected column!<br />";

echo "<hr />";

echo "<table  class=\"tablesorter\" id=\"membersTable\"><thead>
<tr><th width=\"120\">Member</th>
<th width=\"70\">1 Mo</th>
<th width=\"70\">3 Mo</th>
<th width=\"70\">6 Mo</th>
<th width=\"70\">12 Mo</th>
<th width=\"70\">24 Mo</th>
<th width=\"70\">36 Mo</th>
<th width=\"100\">All Time</th>
</tr></thead>";



$cnt = 0;

foreach ($accountNames as $user_id => $user_name)
{
	if ($onlyShowNewGuys == false || ($onlyShowNewGuys == true && in_array($user_id, $newGuys)))
	{
		echo "<tr><td><a href=\"api.php?action=show_member&user_id=" . $user_id . "\">$user_name</a></td>";

		echo "<td>" . $kills['1m'][$user_id] . "</td>";
		echo "<td>" . $kills['3m'][$user_id] . "</td>";
		echo "<td>" . $kills['6m'][$user_id] . "</td>";
		echo "<td>" . $kills['12m'][$user_id] . "</td>";
		echo "<td>" . $kills['24m'][$user_id] . "</td>";
		echo "<td>" . $kills['36m'][$user_id] . "</td>";
		echo "<td>" . $kills['all'][$user_id] . "</td>";

		echo "</tr>";

		$cnt++;
	}
}
echo "</table>";




echo "</table>";
echo "Printed $cnt / " . count($accountNames) . " accounts, where " . count($newGuys) . " are new guys!<br />";
echo "<hr />";
echo "* = Member is in corp for less than 3 months";


echo '<script>
$(document).ready(function()
{
	$("#membersTable").tablesorter();
});

</script>';

base_page_footer('','');

?>
