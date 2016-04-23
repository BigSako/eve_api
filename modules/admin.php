<?php

// determine who is allowed to reg
$db = connectToDb();

$where = "1=1";
switch ($_REQUEST['filter'])
{
	case 'allowed':
		$where = "is_allowed_to_reg=1";
		break;
}


if (isset($_REQUEST['allowAccessAllianceId']))
{
	$allianceID = intval($_REQUEST['allowAccessAllianceId']);
	$db->query("UPDATE alliances SET is_allowed_to_reg=1 WHERE alliance_id = $allianceID");
}


if (isset($_REQUEST['denyAccessAllianceId']))
{
	$allianceID = intval($_REQUEST['denyAccessAllianceId']);
	$db->query("UPDATE alliances SET is_allowed_to_reg=0 WHERE alliance_id = $allianceID");
}




$sql = "SELECT alliance_id, alliance_name, alliance_ticker, can_use_as_main, is_allowed_to_reg, member_count, state FROM alliances WHERE $where ORDER BY alliance_name ASC";

$res = $db->query($sql);

base_page_header('','Admin','Admin');

echo "Filter: <a href=\"api.php?action=admin\">All</a> | <a href=\"api.php?action=admin&filter=allowed\">Only Allowed</a><br/><br />";

echo "<table style=\"width: 100%\">
	<tr><th class=\"table_header\">Alliance Name</th>
	<th class=\"table_header\">Ticker</th>
	<th class=\"table_header\">Member Count</th>
	<th class=\"table_header\">Allowed to reg</th>
	</tr>";
	
while ($row = $res->fetch_array())
{
	$alliance_id = $row['alliance_id'];
	$alliance_name = $row['alliance_name'];
	$alliance_ticker = $row['alliance_ticker'];
	$can_use_as_main = $row['can_use_as_main'];
	$is_allowed_to_reg = $row['is_allowed_to_reg'];
	$member_count = $row['member_count'];
	
	if ($is_allowed_to_reg == '1')
	{
		$allowed_to_reg = "Yes - <a href=\"api.php?action=admin&denyAccessAllianceId=$alliance_id\">Deny access</a>";
	}
	else
	{
		$allowed_to_reg = "No - <a href=\"api.php?action=admin&allowAccessAllianceId=$alliance_id\">Allow access</a>";
	}
	
	
	echo "<tr><td>$alliance_name</td><td>$alliance_ticker</td><td>$member_count</td><td>$allowed_to_reg</td></tr>";
}

echo "</table>";

base_page_footer('', '');

?>