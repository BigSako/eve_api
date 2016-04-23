<?php
$publicKey = file_get_contents("funcs/public.key");
//echo "public_key = " . $publicKey . "<br />";


$vcode = $_REQUEST['vcode'];

if ($vcode != '') {

$vcode_encrypted = encrypt($vcode, $publicKey);


echo '<input type="text" value="'.$vcode_encrypted.'" />';
}

echo "<br />";
echo '<form method="post" action="api.php?action=encrypt_api"><input type="text" name="vcode" /><input type="submit" value="Go!" /></form>';


/*
$res = $db->query("SELECT uid,keyid,vcode FROM player_api_keys WHERE state <> 37");
while ($row = $res->fetch_array())
{
	$uid = $row['uid'];
	$vcode = $row['vcode'];
	
	$vcode_encrypted = encrypt($vcode, $publicKey);
	echo "$uid - enc=" . $vcode_encrypted . "<br />";
	$db->query("UPDATE player_api_keys SET vcode='$vcode_encrypted' WHERE uid=$uid");
}
*/

/*
$res = $db->query("SELECT uid,keyid,vcode FROM corp_api_keys WHERE state <> 37");
while ($row = $res->fetch_array())
{
	$uid = $row['uid'];
	$vcode = $row['vcode'];
	
	$vcode_encrypted = encrypt($vcode, $publicKey);
	echo "$uid - enc=" . $vcode_encrypted . "<br />";
	$db->query("UPDATE corp_api_keys SET vcode='$vcode_encrypted' WHERE uid=$uid");
}
*/



?>