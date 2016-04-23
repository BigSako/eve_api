<?php
$keyId = intval($_REQUEST['keyId']);
$user_id = intval($_REQUEST['user_id']);

base_page_header("",'Showing API Information','Showing API Information');



	$sql = "SELECT keyid, user_id, state, char_training, last_checked, paidUntil, logonMinutes, comment, access_mask
	FROM player_api_keys WHERE user_id = " . $user_id . " AND keyid = $keyId";
	
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		exit;
	}
	
	$result = $res->fetch_array();
	$characters='';
	$key_id=$result['keyid'];
	$state=$result['state'];
	$comment = htmlspecialchars($result['comment']);
	$char_training = intval($result['char_training']);
	$paid_until = $result['paidUntil'];
	$timeDiff = strtotime($paid_until)-strtotime($curEveTime);
	$timeDiffStr = secondsToTimeString($timeDiff);
	$access_mask = $result['access_mask'];
	$last_checked = $result['last_checked'];
	
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
	
	if ($access_mask != '' && $state != 0)
	{
		$state_text .= "<br/>Access Mask: $access_mask";
	}	
	
	
	$filename = $filename=TMPDIR."$key_id.APIKeyInfo.xml.aspx";;
	
	$api_xml_data = file_get_contents ($filename);
	$api_xml_data = htmlspecialchars ($api_xml_data);
	
	print("<table id='your_api_keys' style=\"width: 100%\">");

	print("<tr><th class='table_header'>Name</th>
		<th class='table_header'>Value</th></tr>");
		
	echo "<tr><td>Key-ID</td><td>$key_id [<a href=\"api.php?action=admin_delete_api_key&keyId=$key_id&user_id=$user_id\">Delete</a>] [<a href=\"api.php?action=refresh_api_key_admin&keyId=$key_id&user_id=$user_id\">Refresh</a>]</td></tr>
		<tr><td>VCode</td><td><img src=\"images/icons/encrypted.png\" /> <b>Encrypted</b> </td></tr>
		<tr><td>Characters</td><td>$characters</td></tr>
		<tr><td>Comment</td><td>
		$comment
			</form>
			
			
		</td></tr>
		<tr><td>Training</td><td class='$state_training_class'>$training</td></tr>
		<tr><td>Gametime</td><td class='$state_time_class'>$timeDiffStr</td></tr>
		<tr><td>API Status</td><td class='$state_class'>$state_text</td></tr>
		<tr><td>Access Mask</td><td>$access_mask</td></tr>
		<tr><td>Last access via API</td><td>$last_checked</td></tr>
		<tr><td>API XML Data</td><td>
		
		<textarea rows=\"20\" cols=\"40\">
		$api_xml_data
		
		</textarea>
		
		</td></tr>
		";
		
		
	print("</table>");

	
	
	base_page_footer('','<br /><br /><a href="api.php?action=show_member&user_id=' . $user_id . '">Back</a>');



?>