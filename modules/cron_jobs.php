<?php
	base_page_header('',"Cron Jobs","Cron Jobs");
	
	$db = connectToDB();
	
	
	
	$cron_file = EXTERNAL_PATH . "/cron.lock";

	if (file_exists($cron_file))
	{
		ECHO "<b>Note:</b> cron.lock exists, cronjob probably running at the moment.<br />\n";
	}
	
	if (isset($_REQUEST['force_run']))
	{
		$id = intval($_REQUEST['force_run']);
		$sql = "UPDATE cronjobs SET last_executed = 0 WHERE id=$id";
		$db->query($sql);
	}

	$sql = "SELECT id, name, last_executed, time_inbetween, status, `description` FROM cronjobs ORDER BY `order` ASC";

	
	echo "<table style=\"width: 100%\"><tr><th class=\"your_characters_header\">Jobname</th><th class=\"your_characters_header\">Last Executed</th>" .
			"<th class=\"your_characters_header\">Status</th></th><th class=\"your_characters_header\">Time inbetween in minutes</th></tr>";
			
		
	
	
	$res = $db->query($sql);
	
	
	
	while ($row = $res->fetch_array())
	{
		$id = $row['id'];
		$name = $row['name'];
		$last_executed = $row['last_executed'];
		$time_inbetween = $row['time_inbetween'];
		$desc = $row['description'];

		if ($time_inbetween == 0)
			$time_inbetween = "Always";
		else
			$time_inbetween = secondsToTimeString($time_inbetween*60);
		
		$status = $row['status'];
		
		if ($status == "Running")
			$status = "<b>Running...</b>";
		else if ($status == "OK")
			$status = "<b>OK</b>";


		
		if ($last_executed == 0)
		{
			$timeDiffStr = "Scheduled to run now";
		} else {
			$time_since_last_execution = time() - strtotime($last_executed);
			$timeDiffStr = secondsToTimeString($time_since_last_execution);
		}
		
		echo "<tr><td>$name</td><td>$timeDiffStr ($last_executed) ";
		
		if ($time_inbetween != "Always")
			echo "<b><a href=\"api.php?action=cron_jobs&force_run=$id\">FORCE RUN</a></b>";
			
		echo "</td><td>$status</td><td>$time_inbetween</td></tr>";

        if ($desc != '')
        {
            echo "<tr><td></td><td colspan=\"3\">$desc</td></tr>";
        }
	}
	
	
	echo "</table>";

	base_page_footer('1','');
?>