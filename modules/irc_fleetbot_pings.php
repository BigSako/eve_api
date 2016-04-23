<?php

$db = connectToDB();

// select pings from the last 30 days
$sql = "SELECT timestamp, message, id FROM irc_ping_history WHERE groupname='BC/NORTHERN_COALITION' AND UNIX_TIMESTAMP()-timestamp < 2592000 ORDER BY timestamp DESC ";
$res = $db->query($sql);
	
	
if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'image')
{
	header("Content-type: image/png");
	
	$width = 800;
	$height = 65;

	$im     = imagecreate($width, $height);
	imagealphablending($img, true);


	$text_color = imagecolorallocate($im, 102,205,170);
	$black = imagecolorallocate($im, 0, 0, 0);

	$cnt = 0;
	$last_msg = "";
	while (($row = $res->fetch_array()) && $cnt < 3)
	{
		$dtime = gmdate("Y-m-d H:i:s", $row['timestamp']);
		$msg = $row['message'];

		$msg = str_replace('BROADCAST', '', $msg);
		$msg = str_replace('NORTHERN_COALITION', 'NC', $msg);
		$msg = str_replace('BURNING_NAPALM', 'SM3LL', $msg);
		$msg = str_replace("[/", "[", $msg);
		
		$msg = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $msg);
		
		if (strlen($msg) > 90)
			$msg = substr($msg, 0, 87) . "...";
		
		if ($last_msg == $msg)
		{
			continue;
		}
		$last_msg = $msg;

		$text = "" . $dtime . ":" . $msg;
		
		imagestring($im, 3, 5, 5+$cnt*20, $text, $black);
		
		$cnt++;
	}
	
	
	
	imagepng($im);
	imagedestroy($im); 

}
else
{

	base_page_header('',"IRC Fleetbot Broadcasts","IRC Fleetbot Broadcasts");

    // get telegram user settings
    $res2 = $db->query("select telegram_key,telegram_active,telegram_user_id,telegram_start_hour,telegram_stop_hour FROM auth_users WHERE user_id = " . $GLOBALS['userid'] . " ");
    $row2 = $res2->fetch_array();
    $telegram_active = $row2['telegram_active'];
    $telegram_key    = $row2['telegram_key'];
    $telegram_user_id = $row2['telegram_user_id'];
    $telegram_start_hour = $row2['telegram_start_hour'];
    $telegram_stop_hour = $row2['telegram_stop_hour'];




    echo "Please keep in mind, that there might be pings missing due to temporary problems, service outage, etc!<br />";
    if ($telegram_active == 1)
    {
        if ($telegram_start_hour != $telegram_stop_hour)
        {
            echo "You have an active <a href=\"api.php?action=service_accounts\">Telegram Account</a>.
            Fleetbot messages will be forwarded to you between $telegram_start_hour:00 and $telegram_stop_hour:00 EVE TIME (GMT).<br />
            Bold messages are the ones that you should have received during that time!<br />";
        } else {
            echo "You have an active <a href=\"api.php?action=service_accounts\">Telegram Account</a>. Fleetbot messages will be forwarded to you 24h/day.<br />";
        }
    }


	echo "<table style=\"width: 95%\"><tr><th width=\"170\">Time</th><th>Message</th></tr>";

	while ($row = $res->fetch_array())
	{
		$dtime = gmdate("Y-m-d H:i:s", $row['timestamp']);
        $cur_hour =  gmdate("H", $row['timestamp']);

        $do_send = false;
        if ($telegram_start_hour == $telegram_stop_hour) // means deactivated
        {
            $do_send = true;
        }
        else
        {
            // e.g., 08:00 - 17:00
            if ($telegram_start_hour < $telegram_stop_hour)
            {
                if ($telegram_start_hour <= $cur_hour && $cur_hour < $telegram_stop_hour)
                {
                    $do_send = true;
                }
            } else {
                // e.g., 17:00 - 03:00
                if ($telegram_start_hour >= $cur_hour) // it's later than the start time
                {
                    $do_send = true;
                } else if ($cur_hour < $telegram_stop_hour)
                {
                    $do_send = true;
                }
            }
        }



		$msg = $row['message'];
		
		$msg = str_replace('BROADCAST', '', $msg);
		$msg = str_replace('NORTHERN_COALITION', 'NC', $msg);
		$msg = str_replace('BURNING_NAPALM', 'SM3LL', $msg);
		$msg = str_replace("[/", "[", $msg);

        if ($do_send) {
            echo "<tr><td><b>$dtime</b></td><td><b>$msg</b></td></tr>";
        } else {
            echo "<tr><td>" . $dtime . "</td><td>" . $msg . "</td></tr>";
        }
	}

	echo "</table>";

	base_page_footer('', '');
	
}
?>
