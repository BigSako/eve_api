<?php
$publicKey = file_get_contents("funcs/public.key");
//echo "public_key = " . $publicKey . "<br />";

$db = connectToDB();

$vcode = $_REQUEST['vcode'];
$keyid = intval($_REQUEST['keyid']);
$user_id      = intval($_REQUEST['user_id']);
$comment = $db->real_escape_string($_REQUEST['comment']);

if ($vcode != '' && $keyid != 0 && $userid != 0) {
	$vcode_encrypted = encrypt($vcode, $publicKey);	
	
	audit_log("Adding API key with keyid $keyid manually for user $user_id.");
	
	$db->query("INSERT INTO player_api_keys (keyid, vcode, user_id, state, `comment`)
	VALUES ($keyid, '$vcode_encrypted', $user_id, 1, '$comment')");
}

my_meta_refresh("api.php?action=show_member&user_id=$user_id", 0);

?>

