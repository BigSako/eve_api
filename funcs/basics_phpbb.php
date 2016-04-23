<?php



function update_user_title($forum_user_id, $title)
{
	$db = connectToForumDB();
	$title = $db->real_escape_string($title);
	$sth = $db->query("INSERT INTO phpbb_profile_fields_data (user_id, pf_phpbb_affiliation) VALUES ('$forum_user_id', '$title')
			ON DUPLICATE KEY UPDATe pf_phpbb_affiliation='$title' ");
}


function create_forum_profile_link($forum_user_id)
{
	global $SETTINGS;
	// http://sm3ll.net/forum/member.php?20
	return $SETTINGS['forum_url'] . "/memberlist.php?mode=viewprofile&u=" + $forum_user_id;
}


/** lookup_form_id() takes the sessionhash (cookie) as a parameter, connects
    to the forums and returns the actual forum user id, if any
	make sure the parameter is already santised and is not an SQL Injection */
function lookup_forum_id($forum_hash)
{
	do_log("lookup_forum_id called",5);
	
	$forum_id = -1;
	$db = connectToForumDB();
	
	$sth=$db->query("select `session_user_id` from `phpbb_sessions` where `session_id`='$forum_hash' LIMIT 1");

	$result=$sth->fetch_array();
	$forum_id=intval($result['session_user_id']);
	if ($forum_id <= 1) // guest user
	{
		$forum_id = 0;
	}
	
	// update the sessions last_use parameter so it does not expire
	$time = time();
	$sth2 = $db->query("update `phpbb_sessions` SET session_last_visit=$time, session_page='/api/' where  `sessionhash`='$forum_hash'");

	do_log("User id: $forum_id",5);
	return($forum_id);
}


function get_forum_details($forum_user_id)
{
	$sth=db_action_forum("select username,user_email as email from phpbb_users where user_id='$forum_user_id'");
	$result=$sth->fetch_array();
	
	$username=$result['username'];
	$email=$result['email'];
	
	return $result;
}




function add_forum_group_membership($user,$group)
{
	//echo "trying to add user $user to group $group\n";
	group_user_add($group, array($user));
	//echo "Done\n";
}

function remove_forum_group_membership($user,$group)
{
	//echo "trying to del user $user from group $group\n";
	group_user_del($group, array($user));
	//echo "Done\n";
}



function is_member_of_group($user, $group)
{
	$sth=db_action_forum("select group_id from phpbb_user_group where user_id='$user' and group_id = $group");
	
	return $sth->num_rows == 1;
}




function setVBProfilePictuer($character_id, $forum_id, $main_name)
{			
	$sql = "INSERT INTO phpbb_profile_fields_data 
		(user_id, pf_charname) VALUES ($forum_id, '$main_name') ON DUPLICATE KEY
		UPDATE pf_charname = '$main_name'";
	$sth = db_action_forum($sql);
	
	$url = "https://image.eveonline.com/Character/" . $character_id . "_128.jpg";
	
	$sql = "UPDATE phpbb_users SET user_avatar='$url', user_avatar_type=2, user_avatar_width=128, user_avatar_height=128 WHERE
	user_id = $forum_id ";
	$sth = db_action_forum($sql);
	
}




function create_forum_group($groupName)
{
	// create the rank first
	//db_action_forum("INSERT INTO phpbb_ranks (rank_title, rank_min, rank_special, rank_image) VALUES ('$groupName', 0, 1, '') ");

	//db_action_forum("INSERT INTO phpbb_groups (group_type, group_founder_manage, group_skip_auth, group_name, group_desc, group_desc_
}



function get_forum_group_membership($user)
{
	do_log("Adding user to group",1);
	$sth=db_action_forum("select group_id from phpbb_user_group where user_id='$user'");
	
	$new_groups = array();
	
	while($result=$sth->fetch_array()) 
	{
		$group_id = $result['group_id'];
		$new_groups[$group_id]=1;	
	}
	return $new_groups;
}


function get_group_name_test($group_id)
{
	if ($group_id == 0)
		return "No Group";
	$group_id = intval($group_id);
	$res = db_action_forum("SELECT group_name FROM phpbb_groups WHERE group_id = $group_id ");
	if ($res->num_rows == 1)
	{
		$row = $res->fetch_array();
		return $row['group_name'];
	}
	return "Unknown Group";
}


function remove_all_forum_groups($forum_id)
{
	do_log("setting extra group membership of user $forum_id to NONE",8);

	$res = db_action_forum("SELECT group_id FROM phpbb_user_group WHERE user_id=$forum_id");
	
	while ($row = $res->fetch_array())
	{
		remove_forum_group_membership($forum_id, $row['group_id']);
	}
}

function set_forum_group($forum_id,$group_id)
{
	do_log("setting basic group membership of user $forum_id to $group_id",8);
	db_action_forum("update phpbb_users set group_id=$group_id WHERE user_id=$forum_id ");
}


?>
