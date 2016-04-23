<?php

base_page_header("","Forum Members","Forum Members");
$mysqli = connectToDB();



$sth = $mysqli->query("select a.user_id, a.forum_id, a.user_name, a.email, a.has_regged_main, 
COUNT(p.keyid) as number_keys
FROM auth_users  a

LEFT JOIN
player_api_keys p
on p.user_id = a.user_id 
GROUP BY a.user_id
ORDER BY a.user_name ASC ");
$last_corp_id = -1;

echo "There are currently " . $sth->num_rows . " members registered on the forums. Click on the name to show details.<br /><br />";



echo "<table class=\"tablesorter\" id=\"registeredForumMembers\">
    <thead>
    <tr>
        <th>ID</th><th>Forum ID</th><th>Forum Name</th><th>Main Character</th><th>Number of API keys</th><th>Actions</th>
    </tr>
    </thead>";




$character_infos = "";



while($result=$sth->fetch_array())
{
    $forum_id = $result['forum_id'];
    $user_name = $result['user_name'];
    $email = $result['email'];
    $user_id = $result['user_id'];
    $main_char_id = $result['has_regged_main'];
	$number_keys = $result['number_keys'];

    echo "<tr><td>$user_id</td><td>$forum_id</td><td title=\"Click me for more information\"><a data-open=\"charlistModal" . $user_id . "\">$user_name</a></td>";

    $main_char_name = "<i>Not selected</i>";



    // print the hidden division
    $character_infos .= '<div class="reveal" id="charlistModal' . $user_id . '" data-reveal>';


    // get all api keys that are linked to this account
    $res3 = $mysqli->query("SELECT keyid, state, last_checked FROM player_api_keys WHERE user_id = $user_id");

    $character_infos .= "<h3>Details for " . $user_name . "</h3>";
    $character_infos .= "Open <a href=\"api.php?action=show_member&user_id=$user_id\">even more details</a> for this user.<br />";

    $character_infos .= "<h4>API Keys</h4>";
    if ($res3->num_rows == 0)
    {
        $character_infos .= "There are no API Keys associated with this account.<br />";
    }
    else
    {
        $character_infos .= "<table><tr><th>KeyId</th><th>State</th><th>Last Checked</th></tr>";
        while ($row = $res3->fetch_array())
        {
            $state = return_state_text($row['state']);
            $character_infos .= "<tr><td>" . $row['keyid'] . "</td><td>$state</td><td>" . $row['last_checked'] . "</td></tr>";
        }
        $character_infos .= "</table><br />";
    }


    $character_infos .= "<h4>Characters</h4>";
    // Get all characters linked to this account
    $res4 = $mysqli->query("select character_id, corp_id, corp_name, character_name, character_location, character_last_ship from api_characters where user_id=$user_id ");

    if ($res4->num_rows == 0)
    {
        $character_infos .= "There are no characters associated with this account.<br />";
    } else {
        $character_infos .= "<table><tr><th>Name</th><th>Corp</th><th>Ship</th><th>Location</th></tr>";
        while ($row = $res4->fetch_array()) {
            if ($row['character_id'] == $main_char_id)
            {
                $main_char_name = $row['character_name'];
            }

            $character_infos .= "<tr><td>" . $row['character_name'] . "</td><td>" . $row['corp_name'] . "</td><td>" . $row['character_last_ship'] . "</td><td>" . $row['character_location'] . "</td></tr>";
        }

        $character_infos .= "</table>";
    }


    // Get all groups currently assigned to this user
    $res5 = $mysqli->query("select g.group_name, g.group_id FROM group_membership m, groups g WHERE m.user_id = $user_id AND m.group_id = g.group_id");

    $character_infos .= "<h4>Groups</h4>";

    if ($res5->num_rows == 0)
    {
        $character_infos .= "There are no groups assigned to this account.<br />";
    } else {
        $character_infos .= "<table><tr><th>Group Name</th></tr>";

        while ($row = $res5->fetch_array()) {
            $character_infos .= "<tr><td>" . $row['group_name'] . "</td></tr>";
        }

        $character_infos .= "</table>";
    }

    $character_infos .= '<button class="close-button" data-close aria-label="Close reveal" type="button">
    <span aria-hidden="true">&times;</span>
  </button>
</div>';


    // PRINT MAIN CHARACTER ID

    echo "<td>$main_char_name</td><td>$number_keys</td>";





    // PRINT POSSIBLE actions

    echo "<td><a href=\"api.php?action=show_member&user_id=$user_id\">Show Details</a>";
    if ($isAdmin == true)
    {
        // asume as user
        echo " | <a href=\"api.php?action=asume_user&forum_id=$forum_id\">Log on as this user</a>";
    }


    echo "</td></tr>";


    echo "</tr>";
}
echo "</table><br />";


echo $character_infos;


echo '<script>
$(document).ready(function()
{
    $("#registeredForumMembers").tablesorter();
});

</script>';



base_page_footer('','');


?>