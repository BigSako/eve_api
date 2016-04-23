<?php

	do_log("Entered your_groups",5);
	base_page_header('','Your Groups','Your Groups');
	
	echo "<b>Note:</b> It can take up to 30 minutes for your groups to synchronize on the forum and/or Teamspeak.<br /><br />";
	
	echo '<table style="width:95%">';

	print("<tr><th style=\"width: 150px\">Group Name</th>" .
		"<th>Group Description</th>" .
		"<th>Forum</th><th>Actions</th></tr>");
	
	
	// get all groups that the user is currently affiliated with (including join requests)
	$sth=db_action("select a.group_name, a.group_id, a.group_description, a.authorisers, b.state as state, a.forum_group_id " .
			"from groups a, group_membership b where a.group_id=b.group_id AND b.user_id='".$GLOBALS["userid"]."'");

	while($result=$sth->fetch_array()) {
		$forum_group_id = $result['forum_group_id'];

		if ($forum_group_id == 0)
		{
			//$forum_text = "Group is not affiliated to a forum-group";
			$forum_text = "N/a";
            $forum_group_name = "";
		} else
		{
			$forum_group_name = get_group_name_test($forum_group_id);
			// check if it is assigned
			if (is_member_of_group($GLOBALS["forum_id"], $forum_group_id))
			{
				$forum_text = "Assigned";
			} else {
				$forum_text = "Pending";
			}
		}
		
		$group_name=$result['group_name'];
		$group_id=$result['group_id'];
		if ($group_id == 0)
			continue;
			
		$state=$result['state'];
		$group_description=$result['group_description'];
		
		$state_text=return_group_state_text($state);
		$state_class=return_group_state_class($state);
		
		if($result['authorisers'] < 999 && $state!=97 && $state !=3) 
		{
			$state_text="$state_text - <a href=\"api.php?action=leave_group&group_id=$group_id\">Leave Group</a>";
		}
		print("<tr id='content_row'><td>$group_name</td>" .
			"<td>$group_description</td>" .
			"<td title=\"$forum_group_name\">$forum_text</td><td>$state_text</td></tr>");
    }
	print("</table><br><br>");

    echo "<h4>Available Groups</h4>";
	
	print("<table style=\"width:95%\">");
	print("<tr><th>Group Name</th>" .
		"<th>Group Description</th>" .
		"<th>Options</th></tr>");
		
	$sth=db_action("select a.group_id,a.group_name,a.group_description, a.auto_join, b.state as state " . 
		"from groups a, group_membership b where a.pre_req_group=b.group_id  AND a.hidden=0 and b.user_id='".$GLOBALS["userid"]."' 
		and b.state='0' and a.group_id not in (select group_id from group_membership where user_id='".$GLOBALS["userid"]."') and a.authorisers<>999");
	$i = 0;
	while($result=$sth->fetch_array()) {
		if (($i % 2) == 0)
		{
			$bg_class="td_darkgray";
		}
		else
		{
			$bg_class="td_lightgray";
		}
			
			
		$group_id=$result['group_id'];
		$group_name=$result['group_name'];
		$state=$result['state'];
		$state_text=return_group_state_text($state);
		$state_class=return_group_state_class($state);
		$group_description=$result['group_description'];
		$join_type=$result['auto_join'];
		// set join type apply or join
		if($join_type=='0') {$join_type='Apply';} else {$join_type='Join';}
		
		print("<tr class=\"$bg_class\" id='content_row'><td class=''>$group_name</td>" .
			"<td class=''>$group_description</td>" .
			"<td class='$state_class'><a href='api.php?action=join_group&group_id=$group_id'>$join_type</a></td></tr>");
			
		$i++;
	}
	print("</table>");
	
	
	base_page_footer('1','');
	
?>