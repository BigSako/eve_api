<?php

$group_id = intval($_REQUEST['group_id']);

$sth=db_action("select group_id as group_id, group_name, group_description, forum_group_id, authorisers, autoGenerated 
				FROM groups WHERE group_id = $group_id");
				
$row = $sth->fetch_array();
if (isGroupAdmin($row['authorisers']))
{
	if (isset($_REQUEST['hidden']))
	{
		$hidden = intval($_REQUEST['hidden']);
		if ($hidden != 0)
			$hidden = 1;
			
		audit_log("Changed group $group_id to hidden status $hidden");

		$sql = "UPDATE groups SET hidden=$hidden WHERE group_id=$group_id";
		db_action($sql);
	}
	if (isset($_REQUEST['forum_id']))
	{
		$forum_id = intval($_REQUEST['forum_id']);
		
		audit_log("Changed group $group_id forum id to $forum_id");

		$sql = "UPDATE groups SET forum_id=$forum_id WHERE group_id=$group_id";
		db_action($sql);
	}
}
else
{
	// do nothing
}

header('Location: /api/api.php?action=group_superadmin&action2=show&group_id=' . $group_id);



?>
