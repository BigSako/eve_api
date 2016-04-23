<?php



base_page_header('', 'Search', 'Search');
echo "You can only search for characters or IP addresses the moment<br />";

echo "<form method=\"post\" action=\"api.php?action=search\"><input type=\"text\" name=\"s\" /> <input type=\"submit\" value=\"Search\" /></form>";



if (isset($_REQUEST['s']) && $_REQUEST['s'] != '')
{
	
	$db = connectToDB();

	// display search result
	$s = $_REQUEST['s'];

	
	$s = $db->real_escape_string($s);


	// search for members
	$sql = "SELECT c.uid, c.character_id, c.character_name, c.corp_id, c.corp_name, c.user_id FROM api_characters c WHERE character_name LIKE '%$s%' LIMIT 20 ";
	
	echo "<hr>";
	echo "<h3>Characters</h3>";
	$res = $db->query($sql);

	if ($res->num_rows == 0)
	{
		echo "No results";
	} else {
		if ($res->num_rows == 20)
			echo "Please be more specific with your search. Displaying only 20 items. ";
		echo "<ul>";
		while ($row = $res->fetch_array())
		{
			echo "<li><a href=\"api.php?action=show_member&character_id=" . $row['character_id'] . "\">" . $row['character_name'] . "</a> (" . $row['corp_name'] . ")</li>";
		}
		echo "</ul>";
	}

	// search for corporation names
	$sql = "SELECT c.uid, c.character_id, c.character_name, c.corp_id, c.corp_name, c.user_id FROM api_characters c WHERE corp_name LIKE '%$s%' LIMIT 20 ";
	
	echo "<hr>";
	echo "<h3>Corporations / Characters</h3>";
	$res = $db->query($sql);

	if ($res->num_rows == 0)
	{
		echo "No results";
	} else {
		if ($res->num_rows == 20)
			echo "Please be more specific with your search. Displaying only 20 items. ";
		echo "<ul>";
		while ($row = $res->fetch_array())
		{
			echo "<li><a href=\"api.php?action=show_member&character_id=" . $row['character_id'] . "\">" . $row['character_name'] . "</a> (" . $row['corp_name'] . ")</li>";
		}
		echo "</ul>";
	}

	// search for IP addresses
	$sql = "SELECT DISTINCT a.user_name, a.user_id, l.IP FROM log l, auth_users a WHERE l.IP LIKE '%$s%' AND l.user_id = a.user_id ";
	
	echo "<hr>";
	echo "<h3>Other results</h3>";
	$res = $db->query($sql);

	if ($res->num_rows != 0)
	{
		echo "<ul>";
		while ($row = $res->fetch_array())
		{
			$ip = $row['IP'];
			echo "<li><a href=\"api.php?action=show_member&user_id=" . $row['user_id'] . "\">" . $row['user_name'] . "</a> (IP: $ip)</li>";
		}
		echo "</ul>";

	}

}



base_page_footer('', '');






?>
