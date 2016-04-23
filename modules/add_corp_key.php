<?php
$publicKey = file_get_contents("funcs/public.key");

$db = connectToDB();

$vcode = $_REQUEST['corp_vcode'];
$keyid = intval($_REQUEST['corp_keyid']);
$corp_id = intval($_REQUEST['corp_id']);

if ($keyid == 0)
{
	exit;
}
if (strlen($vcode) < 5)
{
	header('Location: api.php?action=show_corp_keys');
	exit;
}



$vcode_encrypted = encrypt($vcode, $publicKey);

$sql = "INSERT INTO corp_api_keys (keyid, vcode, corp_id, state) VALUES ('$keyid', '$vcode_encrypted', '$corp_id', 1)";
$db->query($sql);

header('Location: api.php?action=show_corp_keys');




?>