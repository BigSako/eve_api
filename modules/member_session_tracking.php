<?php

	$corp_id = intval($_REQUEST['corp_id']);

	if ($corp_id < 1)
		exit;
			
			

	$db = connectToDB();


	$sql = "SELECT YEAR( logonDateTime ) AS y, MONTH( logonDateTime ) AS m, DAY( logonDateTime ) AS d, COUNT( DISTINCT (
character_id
) ) AS cnt
FROM session_tracking
WHERE corp_id = $corp_id
GROUP BY YEAR( logonDateTime ) , MONTH( logonDateTime ) , DAY( logonDateTime ) 
ORDER BY YEAR( logonDateTime ) DESC , MONTH( logonDateTime ) DESC , DAY( logonDateTime ) DESC 
";

	base_page_header('',"Member Session Tracking","Member Session Tracking");


	$res = $db->query($sql);

	echo "<table>";
	echo "<tr><td class=\"your_characters_header\">Date</td>
		<td class=\"your_characters_header\">Characters online</td>
		</tr>";

	$last_date = "";

	while ($row = $res->fetch_array())
	{
		$date = $row['y'] . '-' . $row['m'] . '-' . $row['d'];
		
		echo "<tr><td>$last_date</td><td>" . $row['cnt'] . "</td></tr>";
		
		
		$cnt++;
		
		
		$last_date = $date;
	}

	echo "</table>";
	
	echo "<br /> <br />";

	base_page_footer('',"<a href=\"api.php?action=human_resources&corp_id=$corp_id\">Back</a>");


?>