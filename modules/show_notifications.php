<?php
base_page_header('',"Show Notifications","Show Notifications");


$db = connectToDB();

$sql = "SELECT notification_id, datetime, message, responsible_user_id FROM `player_notification` WHERE user_id = " . $GLOBALS['userid'] . " ORDER BY datetime DESC ";

$res = $db->query($sql);


echo "<table><tr><th>Time</th><th>Message</th><th>Responsible</th></tr>";

while ($row = $res->fetch_array())
{
    if ($row['responsible_user_id'] == $GLOBALS['userid'])
    {
        $responsible = "You";
    } else if ($row['responsible_user_id'] == 0) {
        $responsible = "CronJob (Auto)";
    } else {
        $responsible = "User with ID " . $row['responsible_user_id'] ;
    }
    echo "<tr><td>" . $row['datetime'] . "</td><td>" . $row['message'] . "</td><td>" . $responsible . "</td></tr>";
}


echo "</table>";


// make notifications read
$sql = "UPDATE player_notification SET unread = 0 WHERE user_id = " . $GLOBALS['userid'];

$db->query($sql);

base_page_footer('', '');

?>