<?php
base_page_header("", "Add API Key", "Add API Key");

$db = connectToDB();

$member_api_key_accessmasks = explode(",",$SETTINGS["member_api_key_accessmask"]);
$member_access_mask = $member_api_key_accessmasks[sizeof($member_api_key_accessmasks)-1];

$allied_access_mask = $SETTINGS["allied_api_key_accessmask"];

echo "<p id=\"create_api_link\">To access our services (Forum, TeamSpeak), please add <b>API Keys for all your accounts</b>!
We require Full <b>Account</b> API Keys for Full Members (Access Mask $member_access_mask), and limited API Keys (Access Mask $allied_access_mask) for allied members.<br />
To create a new API key on the EvE Online Website, please click
<a target=\"_blank\" href=\"https://community.eveonline.com/support/api-key/CreatePredefined?accessMask=$member_access_mask\">Here for Full Members</a>";

// who is a full member?
$sql = "SELECT alliance_id, alliance_name FROM alliances WHERE is_allowed_to_reg = 1";
$res = $db->query($sql);

if ($res->num_rows > 0)
{
    echo " (";
    while ($row = $res->fetch_array())
    {
        echo $row['alliance_name'] . " ";
    }
    echo ") ";
}


// are there any allies?
$sql = "SELECT count(*) as allied_cnt FROM alliances a, corporations c WHERE a.alliance_id = c.alliance_id and (c.is_allied = 1 or a.is_allied = 1)";
$res = $db->query($sql);
$row = $res->fetch_array();

if ($row['allied_cnt'] > 0) {
    echo "
or <a target=\"_blank\" href=\"https://community.eveonline.com/support/api-key/CreatePredefined?accessMask=$allied_access_mask\">Here for Allies</a>";
}
echo ". <b>DO NOT REUSE AN OLD KEY, MAKE A NEW KEY FOR THIS SITE!</b></p>
<p>
Make sure to select <b>Type: All</b> and tick the checkbox with <b>No Expiry</b>, as shown in the image below:<br />
<img src=\"images/api_key_reg.png\" /><br />
<b>Advise:</b> Name your API Keys (e.g., with the URL of the website or the name of the App you are using)!<br /></p>
<form method=post action=\"api.php?action=verify_user_api_key\">
<table>
<tr>
    <td>Key ID:</td>
    <td><input type=\"number\" name=\"userid\" placeholder=\"Enter API Key Id\"/></td>
</tr>
<tr>
    <td>Verification Code:</td>
    <td><input type=\"text\" name=\"vcode\" placeholder=\"Enter verification code\"/></td>
</tr>
<tr>
    <td></td>
    <td><input type=\"submit\"></td>
</table>
</div>
</form>
";



base_page_footer("", "");

	
	
?>
