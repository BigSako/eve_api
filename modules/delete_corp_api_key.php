<?php
	do_log("delete__corp_api_key", 5);
    

	if (isset($_REQUEST['confirm']))
		$confirm = $_REQUEST['confirm'];
	else
		$confirm = '';
		
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
	
	base_page_header('','Delete Corp Api Key', 'Delete Corp Api Key');
	
	
	if ($confirm == 'yes')
	{
		// delete it
		echo "<h2>API key deleted</h2>";
		
		$db = connectToDB();
		// make sure to check for $user_id, so people can not delete api keys that do not belong to them!
		$res = $db->query("DELETE FROM corp_api_keys WHERE keyid = $key_id AND corp_id = $corp_id ");

		audit_log("Deleted Corp API Key with keyid=$key_id ");
		
		echo "Done! - <a href=\"api.php?action=show_corp_keys\">Go back</a><br />";
		
	} else {
		// ask if really delete
		echo "<h2>Do you really want to delete the API key with ID $key_id?</h2>";
		echo "<a href=\"api.php?action=delete_corp_api_key&key_id=$key_id&corp_id=$corp_id&confirm=yes\">Yes, I want to delete it.</a> - <a href=\"api.php?action=show_corp_keys\">No, take me back!</a>";
	}


       base_page_footer('1','');



?>