<?php

$db = connectToDB();


if (isset($_REQUEST["create"]) && ($_REQUEST["create"] == "1")) {
    $type = $db->real_escape_string($_REQUEST["type"]);
    $position = $db->real_escape_string($_REQUEST["position"]);
    $day = intval($_REQUEST["day"]);
    $hour = intval($_REQUEST["hour"]);
    $min = intval($_REQUEST["min"]);
    $entity_alliance_id = intval($_REQUEST['entity_alliance_id']);
    $additional_info = $db->real_escape_string($_REQUEST['additional_info']);
    // make sure to not allow html code in there!
    $additional_info = htmlentities($additional_info);

    // determine whether this timer is friendly or not
    $timer_is_friendly = 0;

    if (isset($_REQUEST['timer_is_friendly']))
        $timer_is_friendly = 1;

    $cur_eve_time = gmdate("Y-m-d H:i:s");

    $timeStr = "DATE_ADD('$cur_eve_time', INTERVAL '$day $hour:$min' DAY_MINUTE)";

    $solar_system_id = intval($_REQUEST["solar_system_id"]);
    $qry = "INSERT INTO `timerboard` (`type`, `solar_system_id`, `date`, `position`,`created_by_user_id`, `additional_info`, `alliance_id`, `is_friendly`)
            VALUES ('$type', $solar_system_id, $timeStr, '$position', " . $GLOBALS['userid'] . ", '$additional_info', $entity_alliance_id, $timer_is_friendly)";

    $res = $db->query($qry);
    if (!$res) {
        base_page_header('',"Timerboard - Create Timer","Timerboard - Create Timer");

        echo "<p><b>Error adding to Database</b>:<br/>";
        echo $db->error;
        echo "<br />qry=$qry<br/></p>";

        base_page_footer('', '');
        exit();
    }
    else {
        // on success, just redirect to the timerboard index page
        header('Location: api.php?action=timerboard');
        exit();
    }
}

base_page_header('',"Timerboard - Create Timer","Timerboard - Create Timer");


echo "<a href=\"api.php?action=timerboard\">Back to the timerboard</a><br />";




echo "<b>Note:</b> When you are creating a new timer, provide as many details as possible! Also always double check the timer!<br />";


// print "empty" timerboard form
print_timerboard_form();

base_page_footer('','');

?>