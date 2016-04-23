<?php




	do_log("in refresh_api_key_admin", 5);
	
	$keyId = intval($_REQUEST['keyId']);
	$user_id = intval($_REQUEST['user_id']);

	
		
	$db = connectToDB();

	do_log("refreshing api key with keyid $keyId.", 5);
	

	
	$db->query("UPDATE player_api_keys set state=1 WHERE keyid = $keyId");
	
	

	my_meta_refresh("api.php?action=show_member&user_id=$user_id", 0);

?>