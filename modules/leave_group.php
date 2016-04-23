<?php


	$group_id=intval($_REQUEST['group_id']);
	// only update the membership IF state is currently 0 
	db_action("update group_membership set state='97' where group_id='$group_id' and state=0 and user_id='".$GLOBALS["userid"]."'");
	audit_log("User tried to remove himself from group $group_id");


	add_user_notification($GLOBALS['userid'], "You have left group $group_id. ", $GLOBALS['userid']);
	
	// just redirect them back to your groups
	my_meta_refresh("api.php?action=your_groups",0);

	
?>