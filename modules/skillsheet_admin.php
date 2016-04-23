<?php

$filter = intval($_REQUEST['filter']);
$typeID = intval($_REQUEST['typeID']);
$newLevel = intval($_REQUEST['newLevel']);
$character_id = intval($_REQUEST['character_id']);

$group_id = intval($_REQUEST['show_group_id']);

if ($filter == 0 || $typeID == 0 || $character_id == 0)
{
	exit;
}



if ($newLevel == 0)
{
	db_action("
DELETE FROM skill_filter_skills WHERE filter_id=$filter AND typeID=$typeID 
");

}
else
{
db_action("
INSERT INTO skill_filter_skills (filter_id, typeID, minLevel) VALUES ($filter, $typeID, $newLevel) ON duplicate key update minLevel=$newLevel
");
}


my_meta_refresh("api.php?action=skillsheet&filter=$filter&character_id=$character_id&show_group_id=$group_id&requirements=1&changed=$typeID#$typeID",0);


?>