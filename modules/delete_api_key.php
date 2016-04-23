<?php




if (isset($_REQUEST['confirm']))
    $confirm = $_REQUEST['confirm'];
else
    $confirm = "no";

$keyId = intval($_REQUEST['keyId']);

if ($confirm == 'yes')
{
    base_page_header('','API Key deleted', 'API Key deleted');

    // first get the userid:
    $db = connectToDB();
    // make sure to check for $user_id, so people can not delete api keys that do not belong to them!
    $res = $db->query("SELECT user_id FROM player_api_keys WHERE keyid = $keyId");
    if ($res->num_rows == 1) {
        do_log("deleting api key with keyid $keyId from database.", 2);
        $rows = $res->fetch_array();
        $user_id = $rows['user_id'];

        if ($user_id == $GLOBALS['userid'])
        {
            do_log("------------- api key used to belong to user-id $user_id", 2);

            // make sure to check for $user_id, so people can not delete api keys that do not belong to them!
            $db->query("DELETE FROM player_api_keys WHERE keyid = $keyId and user_id = $user_id");
            $db->query("DELETE FROM api_characters WHERE key_id = $keyId and user_id = $user_id");

            add_user_notification($GLOBALS["userid"], "You removed the API Key with id " . $keyId . "!", $GLOBALS["userid"]);
            echo "Go <a href=\"api.php?action=user_api_keys\">back to the main page</a>.";


        } else {
            echo "Error: The API key you are trying to delete does not belong to you.";
        }
    }
    else {
        echo "Error - Could not find this api key.";
    }

} else {
    // ask if really delete
    base_page_header('','Really Delete Api Key?', 'Really Delete Api Key?');

    echo "Do you really want to delete the API key with ID $keyId?<br />";
    echo "Deleting your API key can lead to you losing access to this site and the connected service accounts.<br /><br />";
    echo "<ul><li>
<a href=\"api.php?action=delete_api_key&keyId=$keyId&confirm=yes\">Yes, I know what I am doing and want to delete it.</a></li><li>
<a href=\"api.php?action=user_api_keys\">No, take me back!</a></li></ul>";
}

echo "<br />";
base_page_footer('1','');



?>