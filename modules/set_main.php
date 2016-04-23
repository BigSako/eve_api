<?php
	$forum_id = $GLOBALS["forum_id"];
	do_log("Entered set_main",5);
	$character_id=intval($_REQUEST["character_id"]); // BigSako - this needs to be an intval, just in case...
	db_action("update auth_users a, api_characters b set a.has_regged_main=b.character_id where 
	a.user_id='".$GLOBALS["userid"]."' and b.character_id='$character_id' and b.user_id='".$GLOBALS["userid"]."'");
	
	$res = db_action("SELECT a.character_name, c.corp_ticker FROM api_characters a, corporations c
	WHERE a.character_id = $character_id AND a.user_id = " . $GLOBALS["userid"] . " 
	AND c.corp_id = a.corp_id
	");
	
	// check if this is actually a valid character of the user
	if ($res->num_rows == 1) {	
		$row = $res->fetch_array();
		$main_name = $row['character_name'];
		$corp_ticker = $row['corp_ticker'];

		add_user_notification($GLOBALS["userid"], "You have selected $main_name as your new main character.",$GLOBALS['userid'] );
		
		
		setVBProfilePictuer($character_id, $forum_id, $corp_ticker . " - " . $main_name);
	
	}
	
	my_meta_refresh('api.php?action=select_main', 0);
	
?>