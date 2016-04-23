<?php
	do_log("Entered select_main",5);
	
	$db = connectToDB();
	
	$res= $db->query("select has_regged_main from auth_users where user_id='".$GLOBALS["userid"]."'");
	
	if ($res->num_rows != 1)
	{
		exit;
	}

	$result=$res->fetch_array();
	
	
	
	$existing_main=$result['has_regged_main'];	
	
	$res = $db->query("select a.character_id, a.character_name, a.corp_id, a.corp_name 
		from api_characters a, corporations b, alliances c 
			where a.user_id='".$GLOBALS["userid"]."' and a.corp_id=b.corp_id 
					and b.alliance_id=c.alliance_id and (c.can_use_as_main=1 or c.is_allied=1 or b.is_allied=1)
ORDER BY a.character_name");
	
	base_page_header('',"Select Your 'Main' Character",'Select Your Main');
	
	
	print("<div id='select_main_overview'>Selecting your 'Main' character determines how you will be known across the various $SETTINGS[site_name] Services (work in progress).<br></div>");
	
	echo "<table style=\"width: 100%\"><tr>
	<th class=\"your_characters_header\">Picture</th>
	<th style=\"width: 50%\" class=\"your_characters_header\">Name</th>
	<th class=\"your_characters_header\">Corporation</th><th class=\"your_characters_header\">Actions</th>
	</tr>";

	while($result=$res->fetch_array()) {
		$character_id=$result['character_id'];
		$character_name=$result['character_name'];
		$corp_id=$result['corp_id'];
		$corp_name=$result['corp_name'];

		if($existing_main==$character_id) {
			$button="<b>Current Main</b>";
		} else {
			$button="<a href=\"api.php?action=set_main&character_id=$character_id\">Set as main</a>";
		}
		echo "<tr>
		<td><img src=\"https://image.eveonline.com/Character/" . $character_id. "_64.jpg\" /></td>	
		<td><b>$character_name</b></td>
		<td>$corp_name</td>			
		<td>$button</td></tr>";
		//print("<div><form id='set_main_form' method=post action='api.php'><input type=hidden name='action' value='set_main'><input type=hidden name='character_id' value='$character_id'><div class='select_main charname'>$character_name</div><div class='select_main corpname'>$corp_name</div><div class='select_main actions'>$button</div></form></div>");
	}
	
	echo "</table>";
	
	base_page_footer('1','');
	
?>
