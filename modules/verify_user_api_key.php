<?php


// santize input for verifying the API Key
$api_userid=intval($_REQUEST['userid']);
$api_vcode=preg_replace("/ /","",sanitise($_REQUEST["vcode"]));

if ($api_userid == 0 || strlen($api_userid) < 4 || strlen($api_vcode) < 10)
{
    error_page("<h4>Invalid API Key</h4>","The length of either they key or the id is too short. <a href=\"api.php?action=add_user_api_key\">Go back</a>", 1);
} else {
    // check if $api_userid > min_apikeyid
    if ($api_userid < $SETTINGS['min_apikeyid'])
    {
        error_page("<h4>Invalid API Key</h4>","Your API Key seems to be too old. Please create a BRAND NEW key, do not reuse existing keys! <a href=\"api.php?action=add_user_api_key\">Go back</a>", 1);
    } else {
        // get key permissions from api
        $key_details = api_get_key_permissions($api_userid, $api_vcode);

        if ($key_details['status'] != 'OK')
        { // something went wrong:
            base_page_header('', 'Add API Key', 'Add API Key');
            print("There was an error validating your API key.<br />");
            print("The API server returned <i> " . $key_details['errorcode'] . ": " . $key_details['status'] . "</i>");
            print("<br />The reasons behind this could be one the following: <ul><li>Your API key is not valid</li><li>The EvE Online API Server was temporarily down</li></ul>");
            echo "<br /><a href=\"api.php?action=add_user_api_key\">Go Back</a>";
            base_page_footer('', '');
        } else { // status was ok, now check the access masks
            if ($key_details['context'] != 'Account') {
                base_page_header('', 'Add API Key', 'Add API Key');
                echo "The API Key you supplied is not an <b>Account API Key</b>! Please make sure to select Account, as shown in the picture below.<br />";
                echo "<img src=\"images/api_Key_reg.png\" />";
                echo "<br /><a href=\"api.php?action=add_user_api_key\">Go Back</a>";
                base_page_footer('', '');
            } else {
                // valid account api key, check access masks
                $member_api_key_accessmasks = explode(",", $SETTINGS["member_api_key_accessmask"]);
                $member_access_mask = $member_api_key_accessmasks[sizeof($member_api_key_accessmasks) - 1];

                $allied_access_mask = $SETTINGS["allied_api_key_accessmask"];

                $access_mask = $key_details['mask'] ;

                // check if key has proper access mask and is an account key // ($key_details['mask'] == '268435455' || $key_details['mask'] == '2684354559' || $key_details['mask'] == '2684354558' || $key_details['mask'] == '1073741823' )
                if (($access_mask == $member_access_mask || $access_mask == $allied_access_mask)) {
                    $apidb = connectToDB();

                    // check if this api key is already used or not
                    $res1 = $apidb->query("SELECT user_id FROM player_api_keys WHERE keyid = '$api_userid' ");
                    if ($res1->num_rows > 0) {
                        // somebody else is using this api key. we do not want to tell the user who this is, but we need to log this information!
                        $row = $res1->fetch_array();
                        $api_user_id = $row['user_id'];

                        $res2 = $apidb->query("SELECT forum_id, user_name, email FROM auth_users WHERE user_id = $api_user_id ");
                        $row = $res2->fetch_array();

                        $forum_id = $row['forum_id'];

                        if ($forum_id == $GLOBALS['forum_id']) {
                            error_page("Error: KEY ALREADY IN USE", "You already entered this API Key on your own account.");
                        } else {

                            $user_name = $row['user_name'];
                            $email = $row['email'];

                            $ip = $_SERVER["REMOTE_ADDR"];

                            $report = "ERROR: A user tried to enter an API that was already entered.\nIP: $ip\nKeyId: $api_userid\nVCODE: $api_vcode\n" .
                                "The user $user_name has already entered this API key.\n" .
                                "The user " . $GLOBALS['username'] . " tried to enter this API key. Please investigate this issue.\n";


                            audit_log($report);
                            addFleetbotPingForGroup("BC/DEBUG", $report);


                            error_page("Error: KEY ALREADY IN USE",
                                "Your API key is already in use by another user. If you bought this account, please contact the original owner to delete the API Key.");

                        }
                        exit;
                    }

                    // Okay, this api key is not in use by anyone else

                    // parse the XML file
                    $apikeyxml = simplexml_load_file($key_details['filename']);

                    // now check, if the users on this api key are already registered with the site
                    $inUse = false;
                    $selfUse = false;

                    $key_is_allowed = false;
                    $key_contains_allie = false;
                    $key_contains_regged = false;


                    foreach ($apikeyxml->result->key->rowset->row as $row) {
                        $character_name = preg_replace("/'/", "\\'", $row['characterName']);
                        $character_id = $row['characterID'];
                        $res2 = $apidb->query("SELECT uid, character_id, character_name, user_id, key_id
                    FROM api_characters WHERE character_id = $character_id and state=0");

                        if ($res2->num_rows > 0) {
                            $inUse = true;
                            //
                            // TODO: Send a mail/log this event
                            $row2 = $res2->fetch_array();
                            $ruserid = $row2['user_id'];
                            do_log("ERROR: user-id $ruserid already has key $api_userid registered, but " . $GLOBALS['userid'] . " tried to register that key...", 1);

                            audit_log("ERROR: user-id $ruserid already has key $api_userid registered, but " . $GLOBALS['userid'] . " tried to register that key...");
                            if ($ruserid == $GLOBALS['userid']) {
                                $selfUse = true;
                            }
                        } else {
                            // check if the person is allowed to register with this kind of key
                            $corp_id = intval($row['corporationID']);
                            $sql = "SELECT (c.is_allied OR a.is_allied) as allied,
                                (a.is_allowed_to_reg OR c.is_allowed_to_reg) as regged
                                FROM alliances a, corporations c
                                WHERE a.alliance_id = c.alliance_id
                                AND c.corp_id = $corp_id";
                            $allowed_res = $apidb->query($sql);
                            $row = $allowed_res->fetch_array();

                            if ($row['allied'] == 1)
                            {
                                $key_is_allowed = true;
                                $key_contains_allie = true;
                            }
                            if ($row['regged'] == 1)
                            {
                                $key_is_allowed = true;
                                $key_contains_regged = true;
                            }

                        }

                    }

                    // check if the key is good
                    if ($key_is_allowed == true)
                    {
                        if ($key_details['mask'] == $allied_access_mask && $key_contains_regged == true)
                        {
                            error_page("<h4>Problem with Access Mask</h4>",
                                "Your API Key seems to be valid, however the access mask is not okay. Please use access mask <i>$member_access_mask</i> (aka FULL MEMBER).
                                <a href=\"api.php?action=add_user_api_key\">Go back</a>", 1);
                            exit();
                        }
                    }



                    if ($inUse == true) {
                        if ($selfUse == false) {
                            error_page("Character already registered",
                                "Somebody else has already registered at least one of your characters. Please contact the IT-Team for help.");
                        } else {
                            error_page("Character already registered",
                                "It seems that you have already registered that character. If you want to add a new API, please delete the old API first.");
                        }

                    } else {



                        // looking good, api key is valid, now let's add it to the database, but before we do that:
                        // encrypt vcode!

                        $publicKey = file_get_contents("./funcs/public.key");

                        if ($publicKey == "") {
                            error_page("Encryption failed",
                                "Our encryption method seems to be failing at the moment, please try again later. Code 1.");
                            exit;
                        }

                        $vcode_encrypted = encrypt($api_vcode, $publicKey);

                        if ($vcode_encrypted == "") {
                            error_page("Encryption failed",
                                "Our encryption method seems to be failing at the moment, please try again later. Code 2.");
                            exit;
                        }

                        // add this valid api key to the database with status set to PENDING
                        $apidb->query("insert into player_api_keys (`keyid`,`vcode`,`user_id`,`state`, `access_mask`) values " .
                            "('$api_userid','$vcode_encrypted','" . $GLOBALS["userid"] . "','1', '$access_mask')");

                        $apidb->query("UPDATE auth_users SET has_regged_api = 1,state=0 WHERE user_id = " . $GLOBALS["userid"] . " ");

                        // add the chars to database too
                        foreach ($apikeyxml->result->key->rowset->row as $row) {
                            $character_name = preg_replace("/'/", "\\'", $row['characterName']);
                            $character_id = $row['characterID'];
                            $corp_id = $row['corporationID'];
                            $corp_name = preg_replace("/'/", "\\'", $row['corporationName']);
                            $sql = "INSERT INTO api_characters (character_id, character_name, corp_id, corp_name, user_id, key_id) VALUES
                                  ($character_id, '$character_name', $corp_id, '$corp_name', " . $GLOBALS["userid"] . ", $api_userid )";
                            if (!$apidb->query($sql))
                            {
                                echo "SQL Error: $sql<br />" . $apidb->error;
                            }
                        }

                        audit_log("User has succesfully entered api with keyid $api_userid.");

                        add_user_notification($GLOBALS["userid"], "You added the API Key with id $api_userid and access mask " . $key_details['mask'] . ".", $GLOBALS["userid"]);

                        base_page_header('', 'Add API Key', 'Add API Key');
                        if (!$key_is_allowed)
                        {
                            echo "Warning: This api key does not contain any characters that are allowed on the services.";
                        }


                        print("API successfully added.<br />" .
                            "Please visit the <a href=\"api.php?action=user_api_keys\">API</a> page to see details.<br />" .
                            "Keep in mind that it can take up to 30 minutes for your groups to be assigned.");
                        base_page_footer('1', '');
                    }
                } else {
                    if (!($key_details['mask'] >= '0')) {
                        $api_fail .= "<div class='api_fail'><li>User ID/Verification Code are not valid</li></div>";
                    } else {
                        $api_fail .= "<div class='api_fail'><li>Invalid Access Mask!</li></div>";
                    }
                    error_page("API Key Is Not Valid", "The API key you entered is not valid. It was not accepted for the following reasons: <div id='api_fail'>$api_fail</div>");
                }
            }

        }
    }

}


?>
