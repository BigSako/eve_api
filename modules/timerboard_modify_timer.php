<?php

date_default_timezone_set('UTC');


// are we updating a timer?
if (isset($_REQUEST['timerId']))
{
    $timerId = intval($_REQUEST['timerId']);

    if ($timerId > 0) {
        $db = connectToDB();



        // either update timer in database
        if (isset($_REQUEST["create"]) && ($_REQUEST["create"] == "1"))
        {

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

            $qry = "UPDATE `timerboard` SET
            `type` = '$type',
            `solar_system_id` = $solar_system_id,
            `date` = $timeStr,
            `position` = '$position',
            `additional_info` = '$additional_info',
            `alliance_id` = $entity_alliance_id,
            `is_friendly` = $timer_is_friendly
            WHERE timerID=$timerId";

            // todo: need to save who updated what

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



        } else  if (isset($_REQUEST['do'])) {
            // or show the edit form
            $do = $_REQUEST['do'];
            if ($do == "edit") {
                base_page_header('', "Timerboard - Modify Timer", "Timerboard - Modify Timer");

                // get details of this timer
                $sql = "SELECT t.timerID, t.solar_system_id, t.type, t.date, t.position, t.created_by_user_id, m.solarSystemName,
        t.additional_info, t.is_friendly, t.alliance_id, u.user_name, u.has_regged_main
		FROM `timerboard` t, eve_staticdata.mapSolarSystems m, auth_users u
		WHERE m.solarSystemID = t.solar_system_id AND u.user_id = t.created_by_user_id AND t.timerID = $timerId
		ORDER BY `date` ASC";

                $res = $db->query($sql);

                echo "<a href=\"api.php?action=timerboard\">Back to the timerboard</a>";


                if ($res->num_rows != 1) {
                    echo "<br />Error: Timer not found (Invalid ID).<br />";
                } else {
                    $row = $res->fetch_array();

                    print_timerboard_form('timerboard_modify_timer', $timerId,
                        $row['type'],$row['date'],
                        $row['solar_system_id'], $row['alliance_id'], $row['position'],
                        $row['is_friendly'], $row['additional_info']);
                }


                base_page_footer('', '');

                // exit, just in case
                exit();
            }
        }
    }

}


// if we got here: no timerid set, we redirect to the timerboard index
header('Location: api.php?action=timerboard');
exit();








?>