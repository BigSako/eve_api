<?php
/*************************************************
* SM3LL.net API Main File                        *
* Module Code, main Page ...                     *
*************************************************/
require('config.php');

define('LOGFILE',LOGDIR ."web.log");
define('DEBUG',8);


function sanitize($str)
{
	// remove any html
	$str = str_replace('<', "&lt;", $str);
	$str = str_replace('>', "&gt;", $str);
	
	return $str;
}
	

require('funcs/basics.php');
init(); // includes all the things

$mysqli = connectToDB();

$url = $SETTINGS['fleet_link_url']; //"http://sm3ll.net/logi_tracker/";
$secret_str = $SETTINGS['secret_string'];


// Obtain user IP as global variable
$ip = $_SERVER['REMOTE_ADDR'];


?>

<!DOCTYPE html>
<html lang="en" dir="ltr" class="client-nojs">
<head>
	<title>IGB Fleet Tracker</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript">
		function init()
		{
			CCPEVE.requestTrust('<?php echo $url; ?>');
		}
	
	</script>
	<style type="text/css">
		html, body {
			margin:0; padding:0;
			height:100%;
		}
		 
		body {
			font-family: 'Noto Sans', sans-serif;
			color: #FFF;
			font-size: 12px;
			
			background-color: #000;
		}
		
		a {
			color: #FFF;
			font-weight: bold;
		}
		
		a:hover
		{
			color: yellow;
		}
	
		#header
		{
			width: 368px;
			height: 80px;
			margin-top: 6px;
			margin-left: 6px;
		}
		
		#content
		{
			position:relative;
			margin-top:3px;
		}
		
		th
		{
			text-align: left;
		}
		
		.td_header
		{
			background-color: #222222;
		}
		
		.td_darkgray
		{
			background-color: #454545;
		}
		
		.td_lightgray
		{
			background-color: #333333;
		}
	</style>
</head>
<body onload="init()">
<center>
<div id="header">
	<!-- <img src="https://ncdot.co.uk/home/images/nc-logo.png" width="368" height="80"> -->
</div>
</center>
<div id="content">
<?php
	$mod = $_REQUEST['mod'];

	if ($_SERVER['HTTP_EVE_TRUSTED'] == true && $_SERVER['HTTP_EVE_CHARID'] != '' && $_SERVER['HTTP_EVE_CORPNAME'] != '')
	{
		
		if ($mysqli->connect_errno) {
			printf("Connect to database failed failed: %s\n", $mysqli->connect_error);
		} else {
	
			$char_id = intval($_SERVER['HTTP_EVE_CHARID']);
			$char_name = $mysqli->real_escape_string(sanitize($_SERVER['HTTP_EVE_CHARNAME']));
			$corp_id   = intval($_SERVER['HTTP_EVE_CORPID']);
			$corp_name = $mysqli->real_escape_string(sanitize($_SERVER['HTTP_EVE_CORPNAME']));
			$ship_type = $mysqli->real_escape_string(sanitize($_SERVER['HTTP_EVE_SHIPTYPENAME']));
			$location  = $mysqli->real_escape_string(sanitize($_SERVER['HTTP_EVE_SOLARSYSTEMNAME']));
			$alliance_name = $mysqli->real_escape_string(sanitize($_SERVER['HTTP_EVE_ALLIANCENAME']));
			$alliance_id = intval($_SERVER['HTTP_EVE_ALLIANCEID']);
			
			$fleet_link = $mysqli->real_escape_string($_REQUEST['fleet_link']);
			
			echo "<center><table><tr><td style=\"vertical-align: top\">";
			echo "<img src=\"//imageserver.eveonline.com/Character/" . $char_id . "_256.jpg\" />";
			echo "</td><td style=\"vertical-align: top\"><b>Details:</b><br />Name: $char_name<br />Corp: $corp_name<br />
			Alliance: $alliance_name (ID: $alliance_id)<br />Ship Type: $ship_type<br />Location: $location<br /><br />";
			
			
			// user stuff
			if ($fleet_link != "")
			{
				if ($mod != 'confirm')
				{
					// provide link
					$sql = "SELECT fleet_id, fleet_name, openTimestamp, closeTimestamp, restrict_to_alliance
						FROM fleet WHERE fleet_tracker_id='$fleet_link' ";
					$res = $mysqli->query($sql);
					
					// check if this is an actual fleet
					if ($res->num_rows == 1)
					{
						$row = $res->fetch_array();
						// yes it is
						$fleet_id = $row['fleet_id'];
						$fleet_name = $row['fleet_name'];
						$closeTimestamp = $row['closeTimestamp'];
						$restrict_to_alliance = $row['restrict_to_alliance'];
						
						
						if ($restrict_to_alliance != -1 && $restrict_to_alliance != $alliance_id)
						{
							echo "This fleet is restricted. You are not allowed to participate.";							
						} else 
						{	
							
							echo "<br /><br /><span style=\"color: green\"><b>Confirm Participation for this fleet:</b><br />";
							$actions = "";
							if ($closeTimestamp != NULL)
							{
								echo "Participation Tracker closed at $closeTimestamp.<br />";
							}
							else {
								// generate a verification string (to prevent cheating or auto clicking this link)
								$confirm_string = sha1($secret_str . $fleet_link . $char_id . $ship_type);
								echo "Fleet-Name: " . $row['fleet_name'] . "<br />
									<a style=\"font-size: 14pt; color: green\" href=\"?mod=confirm&fleet_link=$fleet_link&confirm_string=$confirm_string\">
										&gt; &gt; Confirm! &lt; &lt;
									</a>";
							}
							echo "</span>";
						}
					}
				} else if ($mod == 'confirm')
				{
					// confirm participation
					$verification_string = sha1($secret_str . $fleet_link . $char_id . $ship_type);
					
					$confirm_string = $_REQUEST['confirm_string'];
					if ($verification_string === $confirm_string)
					{
						// okay, he didnt cheat, so let's go ahead and get fleet data
						// provide link
						$sql = "SELECT fleet_id, fleet_name, openTimestamp, closeTimestamp, restrict_to_alliance FROM fleet WHERE 
							fleet_tracker_id='$fleet_link' ";
						$res = $mysqli->query($sql);
						
						if ($res->num_rows == 1)
						{
							$row = $res->fetch_array();
							$fleet_id = $row['fleet_id'];
							$fleet_name = $row['fleet_name'];
							$closeTimestamp = $row['closeTimestamp'];
							$restrict_to_alliance = $row['restrict_to_alliance'];
						
						
							if ($restrict_to_alliance != -1 && $restrict_to_alliance != $alliance_id)
							{
								echo "This fleet is restricted. You are not allowed to participate.";	
							} else {
								$ip = $mysqli->real_escape_string($_SERVER['REMOTE_ADDR']);
								
								// check if fleet is still open
								if ($closeTimestamp === NULL)
								{
									// all good, let's insert it into the database
									$sql = "INSERT INTO participation (fleet_id, character_id, character_name, corp_id, 
											corp_name, alliance_id, alliance_name,
											ship_name,
											ip, location) VALUES (
											$fleet_id, $char_id, '$char_name', $corp_id, '$corp_name', 
											$alliance_id, '$alliance_name',
											'$ship_type', '$ip', '$location'
											) ON DUPLICATE KEY UPDATE ship_name='$ship_type', location='$location' ";
											
									$res = $mysqli->query($sql);
									
									if ($res == 1)
									{
										echo "Your participation for this fleet has been tracked. Thank you!";
									} else {
										echo "Could not track your participation for this fleet.";
										if ($mysqli->error)
										{
											echo $mysqli->error;
										}
									}
									
								} else {
									echo "Sorry, tracking for this fleet has been closed.";
								}
							}
							
						}
						
					}
					else
					{
						echo "Invalid request, please go back to the <a href=\"/\">main site</a>.";
					}
					
				}
			}
			
			// display recent participation
			echo "<br /><br /><b>Recent participation:</b><br />";
			$sql = "SELECT ship_name, curTimestamp, location, f.fleet_name FROM participation p, fleet f 
			WHERE f.fleet_id =  p.fleet_id AND character_name = '$char_name' ORDER BY curTimestamp DESC LIMIT 10";
			$res = $mysqli->query($sql);
			
			echo "<table style=\"width: 100%\">
					<tr><th>Fleet Name</th><th>Date</th><th>Ship</th><th>Location</th></tr>";
			while ($row = $res->fetch_array())
			{
				$ship_name = $row['ship_name'];
				$location  = $row['location'];
				$fleet_name = $row['fleet_name'];
				$curTimestamp = $row['curTimestamp'];
				echo "<tr>
						<td>$fleet_name</td><td>$curTimestamp</td><td>$ship_name</td><td>$location</td>
					</tr>";
			}
			
			echo "</td></tr></table>";
			
			// how often has this user participated in fleets this month:
			$sql = "SELECT COUNT(*) as cnt from participation WHERE YEAR(curtimestamp) = YEAR(now()) AND 
					MONTH(curTimestamp) = MONTH(now()) and character_name = '$char_name'";
			
			$res = $mysqli->query($sql);
			$row = $res->fetch_array();
			$cnt = $row['cnt'];
			echo "You have participated in $cnt fleets this month.<br />";
			
			
			echo "</td></tr></table></center>";
		}
	}
?>


<hr />
<b>(c)</b> 2013 by Burning Napalm<br />
contact <a href="#" onclick="CCPEVE.showInfo(1377, 876593522);">Sur Jel</a> or 
<a href="#" onclick="CCPEVE.showInfo(1377, 1352400035);">BigSako</a> ingame if you are having problems or you need admin access.


</body>

</html>
