<?php

	do_log("Entered user_api_keys",5);
	base_page_header('','Your API Keys','Your API Keys');
	print("<div>From here, you can manage the API keys associated with your account.</div><br><div><b>All</b> characters in $SETTINGS[site_name] (plus alts) 
		must be covered. Click the link below to add a new API Key.<br>Note: If your API key turned invalid for no reason,
		you can try to press <i>Update</i> and then select <i>Refresh</i> and see if it works again.</div><br>");
	print("<div id='add_api_key'>Do you want to <a href='api.php?action=add_user_api_key'>add another API Key</a>?</div>");
	$db = connectToDB();
	$sth = $db->query("select `comment`, char_training,keyid,state,paidUntil, access_mask,last_status, is_allied from player_api_keys where user_id='".$GLOBALS["userid"]."'");
	$rows=$sth->num_rows;
	#print("<div id=''><div class='user_api_key header'>Key ID</div><div id='corp_status' class='user_api_key_state header'>Status</div></div>");
	print("<center>");

	print("<table id='your_api_keys' style=\"width: 95%\">");

	print("<tr><th>Key ID</th>
		<th>Comment</th>
		<th>Key Type</th>
		<th>Status</th>
		<th>Options</th></tr>");

	while($result=$sth->fetch_array()) {
		$characters='';
		$key_id=$result['keyid'];
		$state=$result['state'];
		$comment = $result['comment'];
		$char_training = intval($result['char_training']);
		$paid_until = $result['paidUntil'];
		$timeDiff = strtotime($paid_until)-strtotime($curEveTime);
		$timeDiffStr = secondsToTimeString($timeDiff);
		$access_mask = $result['access_mask'];
		$last_status = $result['last_status'];
		$is_allied = $result['is_allied'];

		if ($is_allied == 1)
		{
			$full_member = "Allied";
		} else {
			$full_member = "Full Member";
		}
		
		if ($timeDiff < 0)
		{
			$timeDiffStr = "Account inactive";
		}
		
		$state_time_class = "valid";
		
		if ($timeDiff < 60*60*4) // less than 4 hours
		{
			$state_time_class = "denied";
		} else if ($timeDiff < 60*60*24*4) // less than 4 days
		{
			$state_time_class = "warning";
		}
		
		$state_training_class = "valid";
		if ($char_training < 1)
		{
			$state_training_class = "warning";
		}
		
		$training = ($char_training > 0 ? "$char_training chars training":"No" );
		$sth2=db_action("select character_name from api_characters where key_id='$key_id' order by 1");
		
		while($row = $sth2->fetch_array()) { //character= ($sth2->fetch_array())['character_name']) {
			$characters.=$row['character_name']."<br />";
		}
		
		$characters=substr($characters, 0, -2);
		
		$state_text=return_state_text($state);
		$state_class=return_state_class($state);


        if ($last_status != "" && strlen($last_status) > 1)
        {
            $state_text .= "<br />" . $last_status;
        }
			
		
		echo "<tr id='content_row'><td class=''><b>$key_id</b><br />$characters</td><td>$comment</td>";
		echo "<td>$full_member<br />Mask: $access_mask</td><td>";
		
		// print status table
		echo "<table>
			<tr><td style=\"width: 100\">Training:</td><td style=\"width: 200\"class='$state_training_class'>$training</td></tr>
			<tr><td>Gametime:</td><td class='$state_time_class'>$timeDiffStr</td></tr>
			<tr><td>API Status:</td><td class='$state_class'>$state_text</td></tr>
		</table>";
			
			
		
			
		print("</td><td class=''>
		<a href=\"api.php?action=api_key_details&keyId=$key_id\">Update</a><br />
		<a href=\"api.php?action=delete_api_key&keyId=$key_id\">Delete</a>
		
		");
		
		if ($state >= 50)
		{
			echo "<br /><a href=\"api.php?action=refresh_api_key&keyId=$key_id\">Reactivate</a>";
		}
		
		print("</td></tr>");


	}
	print("</table>");
	base_page_footer('1','');

?>
