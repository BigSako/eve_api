<?php
	$corp_id = intval($_REQUEST['corp_id']);
	
	if ($corp_id < 1)
		exit;
		
	if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
	{
		echo "Not allowed";
		exit;
	}

	$members = $_REQUEST['members'];
	
	$where = "";
	$title = "";
	
	switch ($members)
	{
		case "":
			$where = "1=1";
			$title = "Member Location";
			break;
		case "inactive":
			$where = "key_id IN (SELECT keyid FROM `player_api_keys` p WHERE paidUntil < now())";
			$title = "Location of inactive members";
			break;
		default:
			exit;
	}

	do_log("Entered member_location",5);
	$custom_javascript=<<<EOF
	
<script>
function toggle_item(item_id)
{
	if( document.getElementById(item_id).style.display!='' ){
		document.getElementById(item_id).style.display = '';
	}else{
		document.getElementById(item_id).style.display = 'none';
	}
}
</script>

EOF;
	
	base_page_header($custom_javascript,'Member Location (Registered only)','Member Location (Registered only)');
	$last_character_last_location = "";
	$sth=db_action("SELECT character_id, character_name, character_last_ship, character_location FROM api_characters WHERE corp_id = $corp_id AND $where ORDER BY character_location ASC, character_name ASC");
	$index=0;
	while($result=$sth->fetch_array()) {
		$character_id=$result['character_id'];
		$character_name=$result['character_name'];
		$character_location=$result['character_location'];
		$character_last_ship=$result['character_last_ship'];
	

		if($character_location!=$last_character_last_location) {
			if($last_character_last_location) {
				print("</div><div class='blank_row'>&nbsp</div>");
			}
			$index++;
			$sth2=db_action("SELECT count(*) as count FROM `api_characters` WHERE character_location='".$character_location."' AND corp_Id = $corp_id AND $where");
			$result2=$sth2->fetch_array();
			$count=$result2['count'];
			print("<div id='corp_name_header' onClick='toggle_item($index)'>$character_location - $count</div>\n");
			# print("<div id='$character_last_ship' style='display: none;'><div><div class='character_name header'>Character Name</div><div id='character_status' class='character_state header'>API State</div></div>\n");
			print("<div id='$index' style='display: none;'><div><div class='character_name header'>Character Name</div><div id='character_status' class='character_state header'>Location</div></div>\n");
		}
		print("<div><div class='character_name name'><a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a></div><div id='character_status' class='character_state $state_class'>$character_last_ship</div></div>\n");
		$last_character_last_location=$character_location;
		}
	print("</div><br /><br />");
		base_page_footer('','<a href="api.php?action=human_resources">Back</a>');


?>