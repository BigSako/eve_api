<?php

$sth = db_action("SELECT c.character_name as character_name, c.corp_name as corp_name, d.corp_ticker,
				a.has_regged_main as character_id, a.forum_id as forum_id 
				FROM auth_users a, api_characters c, corporations d
				WHERE c.character_id = a.has_regged_main AND c.corp_id = d.corp_id");

while ($row = $sth->fetch_array())
{
	echo " " . $row['character_id'] . ", " .  $row['forum_id'] . ", " . $row['character_name'] . "<br />\n";
	
	
	$corp_ticker = $row['corp_ticker'];
	
	if ($corp_ticker == "")
	{
		$name = $row["corp_name"] . " - " . $row["character_name"];
	} else {
		$name = $row["corp_ticker"] . " - " . $row["character_name"];
	}
	
	setVBProfilePictuer($row['character_id'], $row['forum_id'], $name);
}




?>