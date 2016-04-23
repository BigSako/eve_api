<?php
$db = connectToDB();

$sql = "SELECT keyid, state FROM player_api_keys WHERE user_id = $userid;";


$valid = 0;
$invalid = 0;

$res = $db->query($sql);

while ($row = $res->fetch_array())
{
	$state = $row['state'];
	
	if ($state == 0 || $state == 1 || $state == 2)
		$valid++;
	else
		$invalid++;
}


$color = "green";
$text = "";
if ($valid == 0)
{ // no valid keys
	$color = "red";
	$text = "Please add a valid API Key!";
}
else if ($invalid == 0)
{ // display in green that everything is okay
	$color = "green";
	$text = "$valid API Keys valid.";
} else {
	$color = "yellow";
	$text = "You have $invalid invalid API Keys.";
}



header("Content-type: image/png");

$width = 250;
$height = 30;

$im     = imagecreate($width, $height);
imagealphablending($img, true);

$black = imagecolorallocate($im, 0, 0, 0);
imagecolortransparent($im, $black);

$icon = "";

switch ($color)
{
	case "green":
		$text_color = imagecolorallocate($im, 102,205,170);
		$icon     = imagecreatefrompng("images/okay_small.png");
		break;
	case "red":
		$text_color = imagecolorallocate($im, 139,35,35);
		$icon     = imagecreatefrompng("images/notokay_small.png");
		break;
	case "yellow":
		$text_color = imagecolorallocate($im, 255,165,0);
		$icon     = imagecreatefrompng("images/notokay_small.png");
		break;
}

imagecopy($im, $icon, 0, 0, 0, 0, 24, 23);

imagestring($im, 3, 27, 5, $text, $text_color);
imagepng($im);
imagedestroy($im); 

?>