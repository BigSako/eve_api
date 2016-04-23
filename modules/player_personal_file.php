<?php

if (isset($_REQUEST['user_id']))
{
	// check if is allowed to look at user_id
	$user_id = intval($_REQUEST['user_id']);

	$db = connectToDB();


	// before we show member information, we need to make sure that the user is allowed to look at it
	if ($isAdmin)
	{
		// ok
	} else
	{
		$my_user_id = $GLOBALS['userid'];
		$sql = "SELECT c.user_id FROM api_characters c WHERE c.user_id = $user_id AND c.corp_id in (SELECT a.corp_id FROM api_characters a WHERE a.user_id = $my_user_id)";
		$sth = $db->query($sql);
		if ($sth->num_rows == 0)
		{
			base_page_header('', 'Player Personal File', 'Player Personal File', '');
			echo "Not allowed<br />";
			base_page_footer('','<a href="api.php?action=show_member&user_id=' . $user_id . '">Back</a>');
			exit;
		}
	}





	if (isset($_REQUEST['comment_text']))
	{
		$global_uid = $GLOBALS['userid'];
		$commentText = $db->real_escape_string($_REQUEST['comment_text']);
		$sql = "INSERT INTO player_personal_file (createdBy_user_id, commentText, user_id) VALUES ($global_uid, '$commentText', $user_id) ";
		$db->query($sql);
	}

	$left_menu = "<a href=\"api.php?action=show_member&user_id=$user_id\">Back to details</a>";
	base_page_header('', 'Player Personal File', 'Player Personal File', $left_menu);

	$sth = $db->query("SELECT user_name FROM auth_users WHERE user_id = $user_id ");
	if ($sth->num_rows == 0)
		echo "Not found";
	else
	{
		
		$user_row = $sth->fetch_array();

		// see if there is any personal file entries for this user
		$res4 = $db->query("SELECT p.uid, p.createdTs, p.createdBy_user_id, p.commentText, u.user_name 
			FROM player_personal_file p, auth_users u WHERE u.user_id = p.createdBy_user_id AND u.uid = $user_id
			");

		echo "<h3>Personal File: " . $user_row['user_name'] . "</h3>";
		if ($res4->num_rows == 0)
			echo "No entries available.<br />";
		else
		{
			echo "<table style=\"width: 100%\">";
			echo "<tr><th width=\"150\" class=\"table_header\">By</th><th width=\"150\" class=\"table_header\">Date</th><th class=\"table_header\">Comment</th></tr>";

			while ($crow = $res4->fetch_array())
			{
				$text = htmlspecialchars($crow['commentText']);
				$text = str_replace("\n", "<br />", $text);
				echo "<tr id=\"" . $crow['uid'] . "\"><td>" . $crow['user_name'] . "</td><td>" . $crow['createdTs'] . "</td><td>" . $text . "</td></tr>";
			}

			echo "</table>";
		}
		// print form to add another entry
		echo "<h3 id=\"add\">Add another entry</h3>";

		echo "<form method=\"post\" action=\"api.php?action=player_personal_file&do=add&user_id=$user_id\">
		<textarea name=\"comment_text\" rows=\"10\" cols=\"40\"></textarea><br />
		<input type=\"submit\" value=\"Save\" />

		</form>";
	}


	base_page_footer('', '');
} 
else 
{
	// show all IF admin
	if ($isAdmin == true)
	{
		// display all player personal files
	} else 
	{
		base_page_header('', 'Player Personal File', 'Player Personal File', '');

		echo "Sorry, you need to be the administrator to see all player personal files. Please specify a user id!";

		base_page_footer('', '');
	}
}


?>
