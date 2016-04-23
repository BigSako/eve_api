<?php




	do_log("in refresh_api_key", 5);
	
	$keyId = intval($_REQUEST['keyId']);

	
		
		$db = connectToDB();
		// make sure to check for $user_id, so people can not refresh api keys that do not belong to them!
		$res = $db->query("SELECT user_id FROM player_api_keys WHERE keyid = $keyId AND state >= 50");
		if ($res->num_rows == 1) {
			do_log("refreshing api key with keyid $keyId.", 5);
			$rows = $res->fetch_array();
			$user_id = $rows['user_id'];
			
			if ($user_id == $GLOBALS['userid']) 
			{			
				do_log("------------- api key belongs to user-id $user_id", 5);
				
				// make sure to check for $user_id, so people can not delete api keys that do not belong to them!
				$db->query("UPDATE player_api_keys set state=1 WHERE keyid = $keyId and user_id = $user_id");
			} 
		}

	my_meta_refresh("api.php?action=user_api_keys", 0);

?>