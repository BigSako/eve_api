<?php

function update_user_title($forum_user_id, $title)
{
	// not implemented
}


function create_forum_profile_link($forum_user_id)
{
	global $SETTINGS;
	// http://sm3ll.net/forum/member.php?20
	return $SETTINGS['forum_url'] . "/member.php?" + $forum_user_id;
}



/** lookup_form_id() takes the sessionhash (cookie) as a parameter, connects
    to the forums and returns the actual forum user id, if any
	make sure the parameter is already santised and is not an SQL Injection */
function lookup_forum_id($forum_hash)
{
	do_log("lookup_forum_id called",5);
	
	$forum_id = -1;
	$db = connectToForumDB();
	
	$sth=$db->query("select `userid` from `session` where `sessionhash`='$forum_hash' LIMIT 1");

	$result=$sth->fetch_array();
	$forum_id=intval($result['userid']);
	
	// update the sessions last_use parameter so it does not expire
	$time = time();
	$sth2 = $db->query("update `session` SET lastactivity=$time, location='/api/' where  `sessionhash`='$forum_hash'");

	do_log("User id: $forum_id",5);
	return($forum_id);
}


function get_group_name_test($group_id)
{
	if ($group_id == 0)
		return "No Group";
	$group_id = intval($group_id);
	$res = db_action_forum("SELECT title FROM usergroup WHERE usergroupid = $group_id ");
	if ($res->num_rows == 1)
	{
		$row = $res->fetch_array();
		return $row['title'];
	}
	return "Unknown Group";
}



function get_forum_details($forum_user_id)
{
	$sth=db_action_forum("select username,email from user where userid='$forum_user_id'");
	$result=$sth->fetch_array();
	$username=$result['username'];
	$email=$result['email'];
	
	return $result;
}




function setVBProfilePictuer($character_id, $forum_id, $main_name)
{			
	$db = connectToForumDB();
	
	$file_size_128 = 0;
	$file_size_64 = 0;
	// insert this to the forum
	$profile_pic_128 = getEvEAPIProfilePicture128($character_id, $file_size_128);
	$profile_pic_64 = getEvEAPIProfilePicture64($character_id, $file_size_64);
	$db->query("DELETE FROM customprofilepic WHERE userid = $forum_id ");
	$db->query("DELETE FROM customavatar WHERE userid = $forum_id ");
	
	$filename128 = "" . $character_id . "_128.jpg";
	$filename64  = "" . $character_id . "_64.jpg";
	
	$profile_pic_64  = $db->real_escape_string($profile_pic_64);
	$profile_pic_128 = $db->real_escape_string($profile_pic_128);
	$curtime = time();
	
	do_log("switching forum pic for forum_id $forum_id to $filename128" , 5);
	
	//  (20, 1375992824, '962203149_128.jpg', 1, 6071, 128, 128, 
	$db->query("INSERT INTO customprofilepic (userid, dateline, filename, visible, filesize, width, height, filedata) " .
					" VALUES ($forum_id, $curtime, '$filename128', 1, $file_size_128, 128, 128, '$profile_pic_128')");
					
	$db->query("INSERT INTO customavatar (userid, dateline, filename, visible, filesize, width, width_thumb, height, height_thumb, filedata, filedata_thumb) " .
					" VALUES ($forum_id, $curtime, '$filename64', 1, $file_size_128, 128, 64, 128, 64, '$profile_pic_128', '$profile_pic_64')");
					
					
	$db->query("UPDATE user SET usertitle = '$main_name' WHERE userid = $forum_id ");
}


function create_forum_group($groupName)
{
	// 
}



function add_forum_group_membership($user,$group)
{
	$groups=get_forum_group_membership($user);
	$groups[$group]=1;
	build_forum_group_membership($user,$groups);
}

function remove_forum_group_membership($user,$group)
{
	$groups=get_forum_group_membership($user);
	unset($groups[$group]);
	build_forum_group_membership($user,$groups);
}

function build_forum_group_membership($user,$groups)
{
	$group_text='';
	ksort($groups);
	foreach(array_keys($groups, '1') as $group) {
		if($group!='') {
			do_log("Group: $group",5);
			$group_text=$group_text."$group,";
		}
	}
	$group_text=rtrim($group_text,',');
	do_log("I want to apply the following groups text to user $user: '$group_text'",5);
	db_action_forum("update user set membergroupids='$group_text' where userid='$user'");
}

function get_forum_group_membership($user)
{
	$sth=db_action_forum("select membergroupids from user where userid='$user'");
	while($result=$sth->fetch_array()) {
		$groups=$result['membergroupids'];
		do_log("Current groups for $user: $groups",5);
		foreach(explode(',',$groups) as $group) {
			$new_groups[$group]=1;
		}
	}
	return $new_groups;
}


function is_member_of_group($user, $group)
{
	$sth=db_action_forum("select membergroupids from user where userid='$user' ");
	$result=$sth->fetch_array();
	$groups=$result['membergroupids'];
	$groups = explode(',', $groups);
	
	return (in_array($group, $groups));
}



function remove_all_forum_groups($forum_id)
{
	do_log("setting extra group membership of user $forum_id to NONE",8);
	db_action_forum("update user set membergroupids='' WHERE userid=$forum_id ");
}

function set_forum_group($forum_id,$group_id)
{
	do_log("setting basic group membership of user $forum_id to $group_id",8);
	db_action_forum("update user set usergroupid=$group_id WHERE userid=$forum_id ");
}




?>
