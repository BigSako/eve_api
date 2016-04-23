<?php
	do_log("refresh_corp_api_key", 5);
		
	if (isset($_REQUEST['key_id']) && isset($_REQUEST['corp_id']))
	{
		$key_id = intval($_REQUEST['key_id']);
		$corp_id = intval($_REQUEST['corp_id']);
	} else 
	{
		exit;
	}

	$where = "1=1";
	
	// let's see what corp_ids we have access to
	if ($isAdmin == true)
	{
		// everything OK
	} else {
		if (count($director_corp_ids) > 0)
		{
			// check if corp_id is in $director_corp_ids
			if (in_array($corp_id, $director_corp_ids))
			{
				// OK
			} else {
				// NOT OK
				exit;
			}
		}
		else 
		{
			exit;
		}
	}
	
	base_page_header('','Refresh Corp Api Key', 'Refresh Corp Api Key');
	
	
	
		// delete it
		echo "<h2>API key flagged for refresh</h2>";
		
		$db = connectToDB();
		// make sure to check for $user_id, so people can not delete api keys that do not belong to them!
		$res = $db->query("UPDATE corp_api_keys set state=1 WHERE keyid = $key_id AND corp_id = $corp_id ");
		
		echo "Done! - <a href=\"api.php?action=show_corp_keys\">Go back</a><br />";

		audit_log("Refreshing corp key with keyid=$key_id ");
		

       base_page_footer('1','');



?>