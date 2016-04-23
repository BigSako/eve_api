<?php
//DASHBOARD

$username=$GLOBALS["username"];
$db = connectToDB();


base_page_header('','API Services Dashboard','API Services Dashboard');


echo "<table><tr><td> </td><td> </td></tr>";



// check for valid/invalid api keys
$sql = "SELECT keyid, state FROM player_api_keys WHERE user_id = $userid;";

$valid = $invalid = 0;

$res = $db->query($sql);
while ($row = $res->fetch_array())
{
	$state = $row['state'];

	if ($state == 0 || $state == 1)
		$valid++;
	else
		$invalid++;
}

if ($valid == 0)
{
	echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>You do not have any <a href=\"api.php?action=user_api_keys\">API Keys</a> entered!</td></tr>";
} else if ($invalid == 0)
{
	echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You have $valid valid  <a href=\"api.php?action=user_api_keys\">API Keys</a>.</td></tr>";
} else {
	echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>You have $invalid invalid <a href=\"api.php?action=user_api_keys\">API Keys</a>. Please check them!</td></tr>";
}




// check if has selected main character
$sth=$db->query("select has_regged_api, ts3_user_id from auth_users where user_id='$userid'");
$result=$sth->fetch_array();

$ts3_user_id = $result['ts3_user_id'];

if ($result['has_regged_api'] == 0) 
{
	echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>Please <a href=\"api.php?action=select_main\">select your main character</a>!</td></tr>";
} else {
	echo "<tr><td><img src=\"images/okay_small.png\"></td><td>Hello <a href=\"api.php?action=select_main\">" . $GLOBALS["existing_main_name"] . "</a>!
	(do you want to select a different <a href=\"api.php?action=select_main\"> main character</a>?)</td></tr>";
}



// check if user is allowed to access service accounts
$allowed = getPageAccessForThisUser("service_accounts");

if ($allowed == false)
{
	echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>None of the registered API Keys has a character that is allowed on our services.</td></tr>";
}
else
{
	// write something about teamspeak
	if ($ts3_user_id == '' || $ts3_user_id == 0 || $ts3_user_id == -1)
	{
		echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>You do not have your <a href=\"api.php?action=service_accounts\">Teamspeak Account</a> registered.</td></tr>";
	} else {
		echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You are registered on our <a href=\"api.php?action=service_accounts\">Corporation Teamspeak</a> server.</td></tr>";
	}
}


$allowed_hr = getPageAccessForThisUser("human_resources");
$allowed_pos = getPageAccessForThisUser("starbases");

if ($allowed_hr)
{
	echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You are authorized to look at <a href=\"api.php?action=human_resources\">Human Resources</a>.</td></tr>";
}
if ($allowed_pos)
{
	echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You are authorized to look at the <a href=\"api.php?action=starbases\">Starbase List</a>.</td></tr>";
}


// check if all accounts are training
$res = $db->query("select key_id, MAX(training_active) as training_active" .
                " from api_characters where user_id='".$GLOBALS["userid"]."'  AND state <> 99 GROUP BY key_id order by key_id");

$is_training = 0;
$num_accounts = 0;
$last_key_id = -1;
$local_is_training = 0;
$not_training_str = "";
$every_account_is_training = true;
$characters_on_account = "";
while ($row = $res->fetch_array())
{
	if ($row['training_active'] == 0)
	{
		$key_id = $row['key_id'];
		$sql = "SELECT `comment` as comd, paidUntil FROM player_api_keys WHERE keyid = $key_id";
		$res2 = $db->query($sql);
		$row2 = $res2->fetch_array();
                $timeDiff = strtotime($row2['paidUntil'])-strtotime($curEveTime);
		if ($timeDiff < 0) // account is inactive
			continue;
		$extra_str = "";
		if ($row2['comd'] != "")
			$extra_str = "(" . $row2['comd'] . ")";

		$not_training_str .= "Account $key_id $extra_str has no active Skill Queues.<br />";
		$every_account_is_training = false;
	}

}

//$every_account_is_training = $num_accounts <= $is_training;

if ($every_account_is_training)
{
    echo "<tr><td><img src=\"images/okay_small.png\"></td><td>All your <a href=\"api.php?action=your_characters\">Characters/Accounts</a> have an active Skill Queue.</td></tr>";
}
else
{
	echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>At least one of your <a href=\"api.php?action=your_characters\">Characters/Accounts</a> does not have an active Skill Queue:</td></tr>";
	// print account names
	echo "<tr><td></td><td>$not_training_str</td></tr>";
}

if ($allowed != false)
{	// only show this information if character is allowed
	// check if the user has ANY character at the staging system
	$staging = getStagingSystem();
	
	$staging_id = $staging[0];
	$staging_Name = $staging[1];
	
	$res = $db->query("select key_id, character_name, character_location " .
                " from api_characters where user_id='".$GLOBALS["userid"]."'  AND state <> 99  ");
	$located = $not_located = 0;
	while ($row = $res->fetch_array())
	{
        $char_loc = $row['character_location'];

		if ($char_loc == $staging_Name || strstr($char_loc, $staging_Name) != '')
		{
			$located++;
		} else {
			$not_located++;
		}
	}
	
	if ($located == 0)
	{
		echo "<tr><td><img src=\"images/notokay_small.png\"></td><td>You do not have any <a href=\"api.php?action=your_characters\">Characters</a> in our current staging system ($staging_Name)!</td></tr>";
	} else 
	{
	   echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You have $located <a href=\"api.php?action=your_characters\">Characters</a> in our current staging system ($staging_Name)!</td></tr>";
	}
	
	$allowed_staging = getPageAccessForThisUser("staging_system");
	if ($allowed_staging)
	{
		echo "<tr><td><img src=\"images/okay_small.png\"></td><td>You are authorized to modify the  <a href=\"api.php?action=staging_system\">Staging System</a>.</td></tr>";
	}
}

echo "</table><br />"; // notifications table end

	echo "From here, you can add/manage your API keys, view character data, manage your group memberships 
		and configure your access to the $SETTINGS[site_name] services such as the Forums and Teamspeak. If you are just trying to apply to one of the corporations, please do so in the forum.<br />
	Please use the menus at the top of the screen to select an action.";
	
base_page_footer('','');



?>
