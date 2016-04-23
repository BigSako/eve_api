<?php

	$group_id=intval($_REQUEST['group_id']);
	$sth=db_action("select auto_join,group_name,hidden from groups where group_id='$group_id'");
	
	if ($sth->num_rows == 1)
	{	
		$row=$sth->fetch_array();
		$auto_join = $row['auto_join'];
		$hidden = $row['hidden'];
		
		// dont allow joining hidden groups
		if ($hidden == 1)
		{
			audit_log("Error: User tried to join the hidden group with group_id $group_Id");
			exit;
		}
		// only if autojoin is active
		if($auto_join==1) {
			db_action("insert into group_membership (`group_id`,`user_id`,`state`) values " .
				"('$group_id','".$GLOBALS["userid"]."','0') on duplicate key update state='0'");
				
			audit_log("User joined group $group_id.");
				
			// this should have worked, so redirect
			my_meta_refresh("api.php?action=your_groups",0);
		} else {
			db_action("insert into group_membership (`group_id`,`user_id`,`state`) values " .
				"('$group_id','".$GLOBALS["userid"]."','3') on duplicate key update state='3'");
			
			base_page_header('','Group Join Request','Group Join Request');
			
			audit_log("User requested to join group $group_id.");
			
			echo "A request to join the group " . $row['group_name'] . " has been submitted and will be reviewed shortly.<br /><br />";
			
			base_page_footer('','<a href="api.php?action=your_groups">Back to Your Groups</a>');
		}
		
			

		
		
	}

	
?>

