<?php
	$keyId = intval($_REQUEST['keyId']);
	
	if ($keyId <= 0)
	{
		exit;
	}

	
	
	$db = connectToDB();
	

	
	
	if ($_REQUEST['key_comment'])
	{
		$com = $db->real_escape_string($_REQUEST['key_comment']);
		$com = str_replace("<", "&lt;", $com);
		$com = str_replace(">", "&gt;", $com);
		
		$sql = "UPDATE player_api_keys SET comment='$com' WHERE keyid = $keyId and user_id = " . $GLOBALS['userid'] . " ";
		
		$db->query($sql);
	}
	
	$sql = "SELECT keyid, user_id, state, char_training, last_checked, paidUntil, logonMinutes, comment, access_mask
	FROM player_api_keys WHERE user_id = " . $GLOBALS['userid'] . " AND keyid = $keyId";
	
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		exit;
	}
	
	$result = $res->fetch_array();
	$characters='';
	$key_id=$result['keyid'];
	$state=$result['state'];
	$comment = $result['comment'];
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
	$sth2=db_action("select character_id,character_name,state from api_characters where key_id='$key_id' order by 1");
	
	$characters = '<table>';
	
	while($row = $sth2->fetch_array()) { //character= ($sth2->fetch_array())['character_name']) {
		$char_state = $row['state'];
		$state_text=return_group_state_text($char_state);
		$state_class=return_group_state_class($char_state);
		$character_id = $row['character_id'];
		
		if ($char_state != 0)
		{
			$characters.='<tr style=\"text-align: left;\" class="' . $state_class . '"><td>' . 
			$row['character_name'].' (' . $state_text . ')</td><td><a href="api.php?action=delete_toon_from_api&character_id='. $character_id . '">Delete</a></td></tr>';
		}
		else
		{
			$characters.='<tr><td>' . $row['character_name'].'</td><td>&nbsp;</td></tr>';
		}

	}
	
	$characters .= '</table>';
	
	
	$state_text=return_state_text($state);
	$state_class=return_state_class($state);
	
	if ($access_mask != '' && $state != 0)
	{
		$state_text .= "<br/>Access Mask: $access_mask";
	}	
	
	
	
	
	base_page_header('','API Key Details','API Key Details');

	print("<table id='your_api_keys' style=\"width: 100%\">");

	print("<tr><th class='table_header'>Name</th>
		<th class='table_header'>Value</th></tr>");
		
	echo "<tr><td>Key-ID</td><td>$key_id [<a href=\"api.php?action=delete_api_key&keyId=$key_id\">Delete</a>] [<a href=\"api.php?action=refresh_api_key&keyId=$key_id\">Refresh</a>]</td></tr>
		<tr><td>VCode</td><td><img src=\"images/icons/encrypted.png\" /> <b>Encrypted</b> </td></tr>
		<tr><td>Characters</td><td>$characters</td></tr>
		<tr><td>Comment</td><td>
			<form method=\"post\" action=\"api.php?action=api_key_details\">
			<input type=\"hidden\" name=\"keyId\" value=\"$keyId\">
			<textarea  cols=\"30\" rows=\"2\" name=\"key_comment\">$comment</textarea>
			<input type=\"submit\" value=\"Save\" />
			</form>
			
			
		</td></tr>
		<tr><td>Training</td><td class='$state_training_class'>$training</td></tr>
		<tr><td>Gametime</td><td class='$state_time_class'>$timeDiffStr</td></tr>
		<tr><td>API Status</td><td class='$state_class'>$state_text</td></tr>
		<tr><td>Access Mask</td><td>$access_mask</td></tr>
		<tr><td>Last access via API</td><td>$last_checked</td></tr>
		
		";
		
		
	print("</table>");

	
	
	base_page_footer('','<br /><br /><a href="api.php?action=user_api_keys">Back</a>');
?>