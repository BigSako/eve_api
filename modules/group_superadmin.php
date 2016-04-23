<?php


function group_superadmin_main()
{
    global $isAdmin;
    base_page_header('','Group Super Admin','Group Super Admin');
    print("<table style=\"width:100%\">");
    echo "<tr><th width=\"150\" class='your_characters_header'>Group Name</th>
			<th class='your_characters_header'>Group Description</th>
			<th width=\"40\" class='your_characters_header'>Members</th>
			<th width=\"40\" class='your_characters_header'>Applicants</th>
			<th width=\"90\" class='your_characters_header'>Forum ID</th>
			<th width=\"90\" class='your_characters_header'>TS3 ID</th>
			<th width=\"90\" class='your_characters_header'>Discord ID</th>

			</tr>";


    // get all groups that the user is currently affiliated with (including join requests)
    $sth=db_action("select group_id as group_id, group_name, group_description, forum_group_id, ts3_group_id, discord_group_id, authorisers, autoGenerated
				FROM groups WHERE group_id <> 0 ORDER BY autoGenerated ASC, group_name");

    $last_auto = 0;

    while($result=$sth->fetch_array()) {
        $group_name=$result['group_name'];
        $group_id=$result['group_id'];
        $group_description=$result['group_description'];
        $forum_group_id=$result['forum_group_id'];
        $ts3_group_id=$result['ts3_group_id'];
        $discord_group_id=$result['discord_group_id'];
        $authorisers = $result['authorisers'];
        $autoGenerated = $result['autoGenerated'];

        // get current member count
        $sth2 = db_action("SELECT COUNT(*) as members FROM group_membership WHERE group_id=$group_id AND state=0 ");
        $row = $sth2->fetch_array();

        $members = $row['members'];

        // get applied members count
        $sth2 = db_action("SELECT COUNT(*) as members FROM group_membership WHERE group_id=$group_id AND state=3 ");
        $row = $sth2->fetch_array();
        $applications = $row['members'];

        $is_gAdmin = isGroupAdmin($authorisers);

        if ($autoGenerated != $last_auto)
        {
            if ($autoGenerated == 1)
            {
                echo "<tr><td class=\"long_table_header\" colspan=\"5\"><h3>Auto Generated Groups</h3></td></tr>";
            }
        }


        if ($authorisers == 999 || $authorisers == -1)
        {
            $group_name = "<i>$group_name**</i>";
        }
        else if ($is_gAdmin)
        {
            $group_name .= "*";
        }



        
		print("<tr id='content_row'><td class=''><a href=\"api.php?action=group_superadmin&action2=show&group_id=$group_id\">$group_name</a></td>" .
			"<td>$group_description</td>" .
			"<td style=\"text-align: right\">$members</td>" .
			"<td style=\"text-align: right\">$applications</td>" .
			"<td style=\"text-align: right\">$forum_group_id</td>" .
			"<td style=\"text-align: right\">$ts3_group_id</td>" .
			"<td style=\"text-align: right\">$discord_group_id</td>" .
			"</tr>");

        

        $last_auto = $autoGenerated;
    } // end while
    print("</table><br /><br />");

    echo "<b>*</b> - You can administrate this group!</br />
			<b>**</b> - Group is automatically handled by API!<br /><br />";



    base_page_footer('1','');
}


function group_show($group_id)
{

    // get group details
    $sth=db_action("select group_name, group_description, forum_group_id, ts3_group_id, discord_group_id, hidden, authorisers FROM groups WHERE group_id=$group_id");
    if ($sth->num_rows == 1)
    {
        $row = $sth->fetch_array();

        $hidden = $row['hidden'];

        $forum_group_id = $row['forum_group_id'];

        $admin_forum_unassign = "";
        $authorisers = $row['authorisers'];


        // get that group name
        if ($authorisers == 999 || $authorisers == 0)
        {
            $group_leader = "None!";
        } else {
            $sql = "SELECT group_name FROM groups WHERE group_id=$authorisers";
            $res2 = db_action($sql);
            if ($res2->num_rows == 1) {
                $row2 = $res2->fetch_array();
                $group_leader = $row2['group_name'];
            } else {
                $group_leader = "Unknown";
            }

        }


        $is_gAdmin = isGroupAdmin($authorisers);
        if ($is_gAdmin == true)
        {
            $hidden_text = ($row['hidden'] == 0?
                "Public Group (Users can apply - <a href=\"api.php?action=group_superadmin_change&group_id=$group_id&hidden=1\">Hide Group</a>)":
                "Hidden Group (Users can not apply - <a href=\"api.php?action=group_superadmin_change&group_id=$group_id&hidden=0\">Make Group Public</a>)");
            if ($row['forum_group_id'] != 0)
            {
                $admin_forum_unassign = " - <a href=\"api.php?action=group_superadmin_change&group_id=$group_id&forum_id=0\">Unlink</a>";
            }
        } else
        {
            $hidden_text = ($row['hidden'] == 0?
                "Public Group (Users can apply)":
                "Hidden Group");
        }

        base_page_header('',"Details of group " . $row['group_name'] . "" ,"Details of group " . $row['group_name'] . "");

        echo '<form method="post" action="api.php?action=group_superadmin">';

        echo "<table><tr><td>Description:</td><td><input type=\"text\" name=\"group_description_text\" size=\"100\" value=\"". $row['group_description'] . "\" /></td></tr>";
        echo "<tr><td>Group Type</td><td>$hidden_text</td></tr>";
        echo "<tr><td>Group Leader:</td><td>$group_leader</td></tr>";
        echo "<tr><td>Forum Group ID:</td><td>
					<input type=\"text\" name=\"group_forum_id\" size=\"5\" value=\"" . $forum_group_id . "\" />
					( " . get_group_name_test($row['forum_group_id']) . "$admin_forum_unassign)</td></tr>";
        echo "<tr><td>TeamSpeak3 Group ID:</td><td><input type=\"text\" name=\"group_ts3_id\" size=\"5\" value=\"" . $row['ts3_group_id'] . "\" /></td></tr>";
        echo "<tr><td>Discord Group ID:</td><td><input type=\"text\" name=\"group_discord_id\" size=\"5\" value=\"" . $row['discord_group_id'] . "\" /></td></tr>";

        echo "<tr><td>&nbsp;</td><td><input type=\"submit\" value=\"Save\" /></td></tr></table>";
        echo "<input type=\"hidden\" name=\"action2\" value=\"save\" /> <input type=\"hidden\" name=\"group_id\" value=\"$group_id\" />";


        echo '</form>';
        echo '<hr/>';


        echo '<h3>The following members are currently in this group</h3>';
        $sth=db_action("select a.user_id as user_id, a.forum_id, a.user_name as name, a.has_regged_main as main_id, c.character_name as main_character_name
            FROM group_membership m, auth_users a, api_characters c
            WHERE a.user_id = m.user_id AND m.group_id=$group_id AND m.state = 0 AND c.character_id = a.has_regged_main");

        if ($sth->num_rows == 0)
        {
            echo "There are currently no members in this group.<br />";
        } else
        {
            echo "<table><tr><td class=\"your_characters_header\">Name</td><td class=\"your_characters_header\">Action</td><td class=\"your_characters_header\">Forum?</td></tr>";

            while ($row = $sth->fetch_array())
            {
                $user_id = $row['user_id'];
                $users_forum_id = $row['forum_id'];
                $name = $row['name'];
                $main_character_name = $row['main_character_name'];


                if ($forum_group_id == 0)
                {
                    //$forum_text = "Group is not affiliated to a forum-group";
                    $forum_text = "No forum_id set";
                } else
                {
                    $forum_text = "<br />" . get_group_name_test($forum_group_id);
                    // check if it is assigned
                    if (is_member_of_group($users_forum_id, $forum_group_id))
                    {
                        $forum_text .= " - Assigned on forums!";
                    } else {
                        $forum_text .= " - not assigned yet...";
                    }
                }



                echo "<tr><td style=\"height: 50px;\"><a href=\"api.php?action=show_member&user_id=$user_id\">$main_character_name ($name)</a></td>
					<td><a href=\"api.php?action=group_superadmin&action2=demote&user_id=$user_id&group_id=$group_id\" class=\"button-link\">Remove</a>
					</td><td>$forum_text</td></tr>";
            }

            echo "</table>";
        }

        echo "<hr />";




        // check if the current user is authorised to add people to the group
        if (isGroupAdmin($authorisers))
        {
            echo '<h3>The following members have applied to this group</h3>';
            $sth=db_action("select a.user_id as user_id, a.forum_id, a.user_name as name, a.has_regged_main as main_id, c.character_name as main_character_name
            FROM group_membership m, auth_users a, api_characters c
            WHERE a.user_id = m.user_id AND m.group_id=$group_id AND m.state = 3 AND c.character_id = a.has_regged_main");

            if ($sth->num_rows == 0)
            {
                echo "There are currently no applicants to this group.<br />";
            } else
            {
                echo "<table><tr><td class=\"your_characters_header\">Name</td><td class=\"your_characters_header\">Action</td></tr>";

                while ($row = $sth->fetch_array())
                {
                    $user_id = $row['user_id'];
                    $name = $row['name'];
                    $main_character_name = $row['main_character_name'];
                    echo "<tr><td style=\"height: 50px;\"><a href=\"api.php?action=show_member&user_id=$user_id\">$main_character_name ($name)</a></td><td><a href=\"api.php?action=group_superadmin&action2=promote&user_id=$user_id&group_id=$group_id\" class=\"button-link\">Accept</a> <a href=\"api.php?action=group_superadmin&action2=demote&user_id=$user_id&group_id=$group_id\" class=\"button-link\">Remove</a></td></tr>";
                }

                echo "</table>";
            }




            echo '<h3>Members of this group are allowed to see the following pages:</h3>';
            $sth = db_action("SELECT page_name FROM api_page_access a WHERE a.group_id = $group_id");
            if ($sth->num_rows == 0)
            {
                echo "There are no special pages assigned to this group.<br />";
            } else {
                echo "<ul>";
                while ($row = $sth->fetch_array())
                {
                    echo "<li>" . $row['page_name'] . "</li>";
                }
                echo "</ul>";
            }



            echo "<hr />";
            echo "<h3>Add a user to this group manually</h3>";
            // get all users that are not yet affiliated with this group
            $sth = db_action("SELECT a.user_id as user_id, a.user_name as name, c.character_name FROM auth_users a, api_characters c
				WHERE a.has_regged_main = c.character_id AND a.user_id NOT IN (SELECT m.user_id FROM group_membership m WHERE m.group_id = $group_id) ");

            echo "<table><tr><td class=\"your_characters_header\">Name</td><td class=\"your_characters_header\">Action</td></tr>";

            while ($row = $sth->fetch_array())
            {
                $user_id = $row['user_id'];
                $name = $row['name'];
                $character_name = $row['character_name'];
                echo "<tr><td style=\"height: 50px;\"><a href=\"api.php?action=show_member&user_id=$user_id\">$character_name ($name)</a></td>
						<td>
							<a href=\"api.php?action=group_superadmin&action2=promote&user_id=$user_id&group_id=$group_id&manual=1\" class=\"button-link\">Promote manually</a>
						</td></tr>";
            }

            echo "</table>";

        }


        echo "<br />";
        base_page_footer('',"<a href=\"api.php?action=group_superadmin\">Back to Group Admin</a>");
    }
}

function group_update($group_id, $group_description, $forum_id, $ts3_id, $discord_id)
{
    $db = connectToDB();
    $group_description = $db->real_escape_string($group_description);
    $group_description = str_replace("<", "&lt;", $group_description);
    $group_description = str_replace(">", "&gt;", $group_description);

    $sth=$db->query("UPDATE groups SET group_description='$group_description', forum_group_id=$forum_id, ts3_group_id=$ts3_id, discord_group_id=$discord_id WHERE group_id=$group_id");

    audit_log("Group $group_id was updated.");

    my_meta_refresh("api.php?action=group_superadmin&action2=show&group_id=$group_id", 0);
}

function promote_user($group_id,$user_id, $manual)
{
    // check if the user is allowed to do this first!
    $db = connectToDB();
    $res = $db->query("SELECT authorisers FROM groups WHERE group_id = $group_id");
    if ($res->num_rows == 1)
    {
        $row = $res->fetch_array();
        $authorisers = $row['authorisers'];
        if (isGroupAdmin($authorisers))
        {
            if ($manual == 1)
            {
                $db->query("INSERT INTO group_membership (user_id, group_id, state) VALUES ($user_id, $group_id, 0) ");
            }
            else if ($manual == 0)
            {
                $db->query("UPDATE group_membership SET state=0 WHERE user_id=$user_id AND group_id=$group_id AND state=3 ");
            }

            audit_log("User with id $user_id was added to group $group_id.");
            add_user_notification($user_id, "You have been added to group $group_id.",$GLOBALS['userid'] );

            my_meta_refresh("api.php?action=group_superadmin&action2=show&group_id=$group_id", 0);
        }
    }

}


function demote_user($group_id,$user_id)
{
    // check if the user is allowed to do this first!
    $db = connectToDB();
    $res = $db->query("SELECT authorisers FROM groups WHERE group_id = $group_id");
    if ($res->num_rows == 1)
    {
        $row = $res->fetch_array();
        $authorisers = $row['authorisers'];
        if (isGroupAdmin($authorisers))
        {
            //$db->query("DELETE FROM group_membership WHERE user_id=$user_id AND group_id=$group_id ");
            // dont delete, need to set it to deleting
            $db->query("UPDATE group_membership SET previous_state=0, state=97 WHERE user_id=$user_id AND group_id=$group_id ");


            audit_log("User with id $user_id was removed from group $group_id.");
            add_user_notification($user_id, "You have been removed from group $group_id.",$GLOBALS['userid'] );

            my_meta_refresh("api.php?action=group_superadmin&action2=show&group_id=$group_id", 0);
        } else {
		echo "You are not allowed to do that!";
	}
    }
}



do_log("Entered group_superadmin",5);

if (isset($_REQUEST['action2']))
    $action2 = $_REQUEST['action2'];
else
    $action2 = "";

if ($action2 == "")
{
    group_superadmin_main();
} else if ($action2 == "show")
{
    $group_id = intval($_REQUEST['group_id']);
    group_show($group_id);
} else if ($action2 == "save")
{
    $group_id = intval($_REQUEST['group_id']);
    $group_description = $_REQUEST['group_description_text'];
    $forum_id = intval($_REQUEST['group_forum_id']);
    $ts3_id   = intval($_REQUEST['group_ts3_id']);
    $discord_id = intval($_REQUEST['group_discord_id']);
    group_update($group_id, $group_description, $forum_id, $ts3_id, $discord_id);
} else if ($action2 == "promote")
{
    $group_id = intval($_REQUEST['group_id']);
    $user_id = intval($_REQUEST['user_id']);
    $manual = intval($_REQUEST['manual']);

    promote_user($group_id, $user_id, $manual);
} else if ($action2 == "demote")
{
    $group_id = intval($_REQUEST['group_id']);
    $user_id = intval($_REQUEST['user_id']);

    demote_user($group_id, $user_id);
}









?>
