<?php
// check for corp_id
date_default_timezone_set('UTC');

base_page_header('',"Timerboard","Timerboard");
$db = connectToDB();


if (getPageAccessForUser('timerboard_create_timer', $GLOBALS['userid']) == true) {
	echo "You can <a href=\"api.php?action=timerboard_create_timer\">Create a new timer</a><br />";
}
echo "<b>Note</b>: This thing is work in progress, please be patient while features are being added! Thanks!<br />";

$can_modify_timer = false;
$can_delete_timer = false;


if (getPageAccessForUser('timerboard_modify_timer', $GLOBALS['userid']) == true) {
    $can_modify_timer = true;
}

if (getPageAccessForUser('timerboard_delete_timer', $GLOBALS['userid']) == true) {
    $can_delete_timer = true;
}




$timeFilter = " `date` > DATE_SUB(NOW(), INTERVAL 5 HOUR)";
if (!isset($_REQUEST["showOldTimers"])) {
	$timeFilter = "1=1";
}

$alliances = get_all_alliances();

$qry = "SELECT t.timerID, t.solar_system_id, t.type, t.date, t.position, t.created_by_user_id, m.solarSystemName,
        t.additional_info, t.is_friendly, t.alliance_id, u.user_name, u.has_regged_main
		FROM `timerboard` t, eve_staticdata.mapSolarSystems m, auth_users u
		WHERE $timeFilter and m.solarSystemID = t.solar_system_id AND u.user_id = t.created_by_user_id
		ORDER BY `date` ASC";
$res = $db->query($qry);



echo "<table style=\"width: 95%\">
		<tr><th>Objective Type</th><th>Location</th><th>Owned by</th><th>Date</th>
		<th>Additional Info</th><th>Options</th></tr>";



$additional_info = "";
$delete_timer = "";

while (($row = $res->fetch_array()))
{
    $timerId = $row['timerID'];
    $is_friendly = $row['is_friendly'];
    $system_name = $row['solarSystemName'];
    $dotlan_link = generate_dotlan_link_system($system_name);
    $alliance_id = $row['alliance_id'];

    $main_char_id = $row['has_regged_main'];

    $additional_text_info = htmlspecialchars($row['additional_info']);

    $sql2 = "SELECT character_name FROM api_characters WHERE character_id = $main_char_id";

    $res2 = $db->query($sql2);
    if ($res2->num_rows == 1)
    {
        $row2 = $res2->fetch_array();
        $main_char_name = $row2['character_name'];
    } else {
        $main_char_name = "Unknown";
    }

    if ($alliance_id != 0)
    {
        $alliance_name = $alliances[$alliance_id]['name'] . " / " . $alliances[$alliance_id]['ticker'];
    } else {
        $alliance_name = "None";
    }

    $username = $row['user_name'];

    $date = strtotime($row['date']);
    $time_remaining = $date - time();
    $date = date('d/m/Y - H:i:s', $date);

    $expired = false;
    $bold = false;

    if ($time_remaining > 0) {
        $days = intval($time_remaining / 86400);

        $time_remaining -= ((int)$days * 86400);

        $hours = intval($time_remaining / 3600);

        $time_remaining -= ((int)$hours * 3600);

        $min = intval($time_remaining / 60);
        if ($days < 1.0)
        {
            $bold = true;
        }
    } else {
        $expired = true;
        $bold = true;
    }

    if ($is_friendly)
    {
        $color = "blue";
        $friendly_text = "Friendly";
    } else {
        $color = "red";
        $friendly_text = "Hostile";
    }

    $type = $friendly_text . " " . htmlspecialchars($row["type"]);

    $position = htmlspecialchars($row['position']);

    echo "<tr style=\"color: $color\">
            <td>$type</td>
            <td>" . $dotlan_link . "<br />" . $position . "</td>
            <td>$alliance_name</td><td>";
    if ($bold)
        echo "<b>";

    $time_until = "expired ($date)";

    if (!$expired)
        $time_until = "in $days days, $hours hours, $min mins<br />$date";

    echo $time_until;

    if ($bold)
        echo "</b>";


    $delete_timer .= '<div class="reveal" id="delete_timer_' . $timerId . '" data-reveal>
        Do you really want to delete the following timer: <br />' . $type . ' owned by ' . $alliance_name . ' at ' . $date . '<br />
            <a href="api.php?action=timerboard_delete_timer&do=delete2&timerId=' . $timerId . '">Yes!</a> &nbsp; <a data-close>No, take me back</a></div>';


    // create a division which shows some additional information
    $additional_info .= '<div class="reveal" id="show_additional_info_' . $timerId . '" data-reveal>
    <h3>' . $type . ' in ' . $system_name . ' </h3>
            ' . $type . ' owned by ' . $alliance_name . '<br />
            Location: ' . $dotlan_link . ' ' . $position . '<br />
            Date: ' . $time_until . '<br />
            Timer Created By: ' . $main_char_name . ' ('. $username . ')<hr /><b>Info:</b><br />' . $additional_text_info . '</div>';

    echo ' </td><td><a data-open="show_additional_info_' . $timerId . '">Click</a></td>';

    echo "<td>";

    if ($can_modify_timer)
    {
        echo "<a href=\"api.php?action=timerboard_modify_timer&do=edit&timerId=$timerId\">Modify</a> ";
    }
    if ($can_delete_timer)
    {
        echo "<a data-open=\"delete_timer_" . $timerId . "\">Delete</a>";
    }

    echo "</td>
    </tr>";
}

echo "</table>";


if ($can_delete_timer)
{
    echo $delete_timer;
}


echo $additional_info;

echo "<br /><br />";


base_page_footer('','');

?>