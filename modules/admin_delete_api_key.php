<?php

	do_log("admin_delete_api_key", 5);
    base_page_header('','Delete Api Key', 'Delete Api Key');

	$confirm = $_REQUEST['confirm'];
	
	$keyId = intval($_REQUEST['keyId']);
	$user_id = intval($_REQUEST['user_id']);
	
	if ($confirm == 'yes')
	{
		// delete it
		echo "<h2>API key deleted</h2>";
		// first get the userid:
		$db = connectToDB();
		// make sure to check for $user_id, so people can not delete api keys that do not belong to them!
		$res = $db->query("SELECT user_id FROM player_api_keys WHERE keyid = $keyId AND user_id=$user_id");
		if ($res->num_rows == 1) {
			do_log("deleting api key with keyid $keyId from database.", 2);
			$rows = $res->fetch_array();
			$user_id = $rows['user_id'];
			
				
				do_log("------------- api key used to belong to user-id $user_id", 2);
				audit_log("Deleted API Key (keyid=$keyId) belonging to user $user_id ");
				
				// make sure to check for $user_id, so people can not delete api keys that do not belong to them!
				$db->query("DELETE FROM player_api_keys WHERE keyid = $keyId and user_id = $user_id");
				$db->query("DELETE FROM api_characters WHERE key_id = $keyId and user_id = $user_id");
				
				echo "Go <a href=\"api.php?action=show_member&user_id=$user_id\">back to the 'Show Member' page</a>.";
			
		}
		else {
			echo "Error - Could not find this api key.";
		}
		
	} else {
		// ask if really delete
		echo "<h2>Do you really want to delete the API key with ID $keyId?</h2>";
		echo "Deleting the API key can lead to the user losing access to the forum and this tool.<br />";
		echo "<a href=\"api.php?action=admin_delete_api_key&keyId=$keyId&user_id=$user_id&confirm=yes\">Yes, I want to delete it.</a> - <a href=\"api.php?action=api_key_details&keyId=$keyId&user_id=$user_id\">No, take me back!</a>";
	}


        base_page_footer('1','');



?>
