<?php
$character_id = intval($_REQUEST['character_id']);


$width = 960;
$height = 560;

if ($_REQUEST['width'])
{
	$width = $_REQUEST['width'];
}
if ($_REQUEST['height'])
{
	$height = $_REQUEST['height'];
}


if ($character_id == 0)
{
	echo "ERROR: expected character_id as parameter";
	exit;
}

$db = connectToDB();

$res1 = $db->query("SELECT walletBalance, updateTime from walletBalance_log WHERE character_id=$character_id ORDER BY updateTime ASC");

if ($res1->num_rows < 1)
{
	echo "ERROR: no data for character not found.";
	exit;
}


$data = array();
$stamp = array();

$min = 9999999999999.9;
$max = -9999999999999.9;

$cnt = 0;
// go through the data
while ($row = $res1->fetch_array())
{
	$data[$cnt] = $row['walletBalance'];
	$stamp[$cnt] = $row['updateTime'];
	
	if ($row['walletBalance'] < $min)
	{
		$min = $row['walletBalance'];
	}
	
	if ($row['walletBalance'] > $max)
	{
		$max = $row['walletBalance'];
	}
	
	$cnt++;
}
// create path

$wallet_width = ($max-min);



$store = array();
$polyLine = "";

$begin = $stamp[0];
$end = $stamp[$cnt-1];


for ($i = 0; $i < $cnt; $i++)
{
	//echo $data[$i];
	//echo "<br />\n";
	$store[$i] = 100 - ($data[$i]-$min) / $wallet_width * 100 + 5;

	$x = $i * 210/$cnt + 29;
	
	$polyLine .= "" . $x . "," . $store[$i] . " ";
}



header ("Content-Type:text/xml");  
echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
echo "\n";
echo '<?xml-stylesheet href="/api/map.css" type="text/css"?>';
echo "\n";
?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" 
  "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<?php 

echo <<<EOF
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="svgdoc" width="$width" 
height="$height" viewBox="0 0 240 140" style="background: #ffffff">
EOF;
?>
<rect x="0" y="0" width="240" height="140"
  style="fill:#ffffff;stroke-width:0.5;stroke:rgb(0,0,0)"/>


<rect x="29" y="5" width="210" height="100"
  style="fill:#c0c0c0;stroke-width:0.5;stroke:rgb(0,0,0)"/>
  
<text x="1" y="7" fill="red" style="font-size: 2pt; font-family: Arial;"><?php echo $max; ?></text>
<text x="1" y="105" fill="red" style="font-size: 2pt; font-family: Arial;"><?php echo $min; ?></text>

<text x="30" y="107" fill="red" style="font-size: 2pt; font-family: Arial; writing-mode: tb;"><?php echo $begin; ?></text>
<text x="238" y="107" fill="red" style="font-size: 2pt; font-family: Arial; writing-mode: tb;"><?php echo $end; ?></text>

<polyline points="<?php echo $polyLine; ?>"
  style="fill:none;stroke:blue;stroke-width:0.3" />

</svg>