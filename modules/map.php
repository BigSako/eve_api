<?php
$corp_id = intval($_REQUEST['corp_id']);

if ($corp_id == 0)
{
	echo "ERROR: expected corp_id as parameter";
	exit;
}



$db = connectToDB();

$user_id = $GLOBALS['userid'];

// check if the user that wants to view the map actually has a toon in that corp
$sql = "SELECT DISTINCT a.corp_id, c.corp_name 
FROM `api_characters` a, corporations c, corp_api_keys k
WHERE a.user_id = $user_id AND a.corp_id = c.corp_id AND c.corp_id = k.corp_id AND c.state <= 1 AND a.corp_id = $corp_id";

$res = $db->query($sql);

if ($res->num_rows == 0)
{
	echo "ERROR: not available";
	exit;
}


$noSVG = false;
if (isset($_REQUEST['noSVG']))
	$noSVG = true;



$res1 = $db->query("SELECT corp_name from corporations WHERE corp_id=$corp_id");

if ($res1->num_rows != 1)
{
	echo "ERROR: corp not found.";
	exit;
}

$row1 = $res1->fetch_array();

$corp_name = $row1["corp_name"];

$res2 = $db->query("SELECT last_executed FROM cronjobs WHERE id=1");
if ($res2->num_rows != 1)
{
	echo "ERROR (2): corp not found.";
	exit;
}
$row2 = $res2->fetch_array();

$last_update = $row2['last_executed'];

header ("Content-Type:text/xml");  
echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
echo "\n";
echo '<?xml-stylesheet href="/api/map.css" type="text/css"?>';
echo "\n";
?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" 
  "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="svgdoc" width="1500" 
height="1240" viewBox="0 0 750 620" style="background: #ffffff">


<g id="map" transform="translate(0,0) scale(1)">
<image x="5" y="5" width="222" height="50" xlink:href="/api/bn_logo.png" />
<text x="5" y="65" fill="black" style="font-size:14px; font-family: Verdana;">Office map for <?php echo $corp_name; ?></text>
<text x="5" y="75" fill="black" style="font-size:6px; font-family: Verdana;">Last Updated: <?php echo $last_update; ?></text>
<defs>

<?php


$width = 1000;
$height = 300;

$region_opacity = 0.5;


$offices_sql = "
select o.location, o.solarID, o.locID, m.solarSystemName FROM

(
SELECT 

CASE 
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.stationName
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000 
THEN (

SELECT s.stationName
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as location,



CASE 
WHEN a.location_id BETWEEN 66000000 AND 66015004 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000001
)
WHEN a.location_id BETWEEN 66015005 AND 66015120 THEN (
SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 66015121 AND 67999999 THEN (

SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id -6000000
)
WHEN a.location_id BETWEEN 60014861 AND 60014928 THEN (
SELECT c.solarSystemID
FROM conqStations AS c
WHERE c.stationID = a.location_id
)
WHEN a.location_id
BETWEEN 60000000 AND 61000000 
THEN (

SELECT s.solarSystemID
FROM staStations AS s
WHERE s.stationID = a.location_id
)
END as solarID


, a.location_id AS locID
FROM offices a
WHERE a.corp_id = $corp_id
ORDER BY location
) as o, eve_staticdata.mapSolarSystems m WHERE
o.solarID = m.solarSystemID AND m.regionID = #regionID#
ORDER BY m.solarSystemName
";


// query regions
$res = $db->query("SELECT x,y,z, regionID, regionName, regionName as displayName FROM eve_staticdata.mapRegions");

$no_offices_example = "";
$upto3_offices_example = "";
$one_office_example = "";
$plenty_offices_example = "";

$popups = "";

while ($row = $res->fetch_array())
{
	$id = $row['regionID'];
	$name = $row['regionName'];
	$displayName = $row['displayName'];
	
	
	$sys_name_dotlan = str_replace(' ', '_', $name);
	
	
	$cur_offices_sql = str_replace("#regionID#", $id, $offices_sql);
	$res2 = $db->query($cur_offices_sql);
	$officeCnt = $res2->num_rows;
	$systems = "";
	$systems_link = "";
	while ($row3 = $res2->fetch_array())
	{
		$systems .= $row3['solarSystemName'] . ", ";
		
		$systems_link .= $row3['solarSystemName'] . ",";
	}
	
	$systems = substr($systems, 0, -2);
	$systems_link = substr($systems_link, 0, -1);
	$office_text2 = "";
	
	if ($officeCnt == 0)
	{
		$office_class = "no_offices";
		$office_text = "No offices";
		if ($no_offices_example == "")
		{
			$no_offices_example = "#def" . $id;
		}
	}
	else if ($officeCnt == 1)
	{
		$office_class = "one_office";
		$office_text = $systems;
		
		if ($one_office_example == "")
		{
			$one_office_example = "#def" . $id;
		}
	}
	else if ($officeCnt <= 3)
	{
		$office_class = "low_offices";
		$office_text = substr($systems, 0, 18) . "...";
		
		if ($upto3_offices_example == "")
		{
			$upto3_offices_example = "#def" . $id;
		}
	}
	else if ($officeCnt > 3)
	{
		$office_class = "plenty_offices";
		$office_text = "$officeCnt offices:";
		$office_text2 = substr($systems, 0, 18) . "...";
		
		if ($plenty_offices_example == "")
		{
			$plenty_offices_example = "#def" . $id;
		}
	}
	
	$sec_class = "";
	$res3 = $db->query("SELECT AVG(m.security) as asec FROM eve_staticdata.mapSolarSystems m WHERE m.regionID = $id");
	$row3 = $res3->fetch_array();
	
	$sec = $row3['asec'];
	if ($sec > 0.0 && $sec < 0.5)
	{
		$sec_class = "lowsec";
	} else if ($sec < 0.0)
	{
		$sec_class = "nullsec";
	}
	

	$popups .= <<<EOF
	<text id="thepopup$id" x="80" y="10" font-size="6" fill="black" visibility="hidden">More Information:
		<set attributeName="visibility" from="hidden" to="visible" begin="link$id.mouseover" end="link$id.mouseout"/>
	</text>
EOF;
	
	
		
	echo <<<EOF
<symbol id="def$id">
	<a id="link$id" xlink:href="http://evemaps.dotlan.net/map/$sys_name_dotlan/$systems_link" target="_blank">
		<rect id="rect$id" x="7" y="3.5" rx="4" ry="4" width="60" height="26" class="s $sec_class $office_class"/>
		<text x="13" y="10" class="ss" text-anchor="start">$displayName</text>
		<text id="txta$id" x="10" y="17" class="st" text-anchor="start">$office_text</text>
		<text id="txtb$id" x="10" y="23" class="st" text-anchor="start">$office_text2</text>
	</a>

</symbol>
EOF;

}

?>

</defs>

<text x="10" y="20" id="debug" style="fill: #888888;"></text> 
<g id="popups">
<?php echo $popups;
?>
</g>
    <g
       id="jumps">
      <line
         id="j-10000001-10000001"
         x1="441.5"
         y1="371.5"
         x2="441.5"
         y2="371.5"
         class="regionJump" />
      <line
         id="j-10000036-10000001"
         x1="441.5"
         y1="371.5"
         x2="347.5"
         y2="385.5"
         class="regionJump" />
      <line
         id="j-10000030-10000001"
         x1="441.5"
         y1="371.5"
         x2="438.5"
         y2="309.5"
         class="regionJump" />
      <line
         id="j-10000012-10000001"
         x1="441.5"
         y1="371.5"
         x2="470.5"
         y2="416.5"
         class="regionJump" />
      <line
         id="j-10000047-10000001"
         x1="441.5"
         y1="371.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000028-10000001"
         x1="441.5"
         y1="371.5"
         x2="498.5"
         y2="318.5"
         class="regionJump" />
      <line
         id="j-10000011-10000001"
         x1="441.5"
         y1="371.5"
         x2="570"
         y2="328.5"
         class="regionJump" />
      <line
         id="j-10000002-10000002"
         x1="422.5"
         y1="214.5"
         x2="422.5"
         y2="214.5"
         class="regionJump" />
      <line
         id="j-10000033-10000002"
         x1="422.5"
         y1="214.5"
         x2="352.5"
         y2="213.5"
         class="regionJump" />
      <line
         id="j-10000016-10000002"
         x1="422.5"
         y1="214.5"
         x2="367.5"
         y2="156.5"
         class="regionJump" />
      <line
         id="j-10000032-10000002"
         x1="422.5"
         y1="214.5"
         x2="288.5"
         y2="269.5"
         class="regionJump" />
      <line
         id="j-10000029-10000002"
         x1="422.5"
         y1="214.5"
         x2="472.5"
         y2="178.5"
         class="regionJump" />
      <line
         id="j-10000027-10000002"
         x1="422.5"
         y1="214.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000042-10000002"
         x1="422.5"
         y1="214.5"
         x2="465.5"
         y2="268.5"
         class="regionJump" />
      <line
         id="j-10000003-10000002"
         x1="422.5"
         y1="214.5"
         x2="434.5"
         y2="136.5"
         class="regionJump" />
      <line
         id="j-10000003-10000003"
         x1="434.5"
         y1="136.5"
         x2="434.5"
         y2="136.5"
         class="regionJump" />
      <line
         id="j-10000029-10000003"
         x1="434.5"
         y1="136.5"
         x2="472.5"
         y2="178.5"
         class="regionJump" />
      <line
         id="j-10000010-10000003"
         x1="434.5"
         y1="136.5"
         x2="368.5"
         y2="106.5"
         class="regionJump" />
      <line
         id="j-10000034-10000003"
         x1="434.5"
         y1="136.5"
         x2="526.5"
         y2="149.5"
         class="regionJump" />
      <line
         id="j-10000004-10000004"
         x1="558.5"
         y1="77.5"
         x2="558.5"
         y2="77.5"
         class="regionJump" />
      <line
         id="j-10000005-10000005"
         x1="602.5"
         y1="474.5"
         x2="602.5"
         y2="474.5"
         class="regionJump" />
      <line
         id="j-10000006-10000005"
         x1="602.5"
         y1="474.5"
         x2="556.5"
         y2="432.5"
         class="regionJump" />
      <line
         id="j-10000009-10000005"
         x1="602.5"
         y1="474.5"
         x2="646.5"
         y2="396.5"
         class="regionJump" />
      <line
         id="j-10000061-10000005"
         x1="602.5"
         y1="474.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000025-10000005"
         x1="602.5"
         y1="474.5"
         x2="464.5"
         y2="464.5"
         class="regionJump" />
      <line
         id="j-10000006-10000006"
         x1="556.5"
         y1="432.5"
         x2="556.5"
         y2="432.5"
         class="regionJump" />
      <line
         id="j-10000009-10000006"
         x1="556.5"
         y1="432.5"
         x2="646.5"
         y2="396.5"
         class="regionJump" />
      <line
         id="j-10000008-10000006"
         x1="556.5"
         y1="432.5"
         x2="570.5"
         y2="382.5"
         class="regionJump" />
      <line
         id="j-10000007-10000007"
         x1="676.5"
         y1="298.5"
         x2="676.5"
         y2="298.5"
         class="regionJump" />
      <line
         id="j-10000009-10000007"
         x1="693.97784"
         y1="295.62708"
         x2="650.22784"
         y2="388.30902"
         class="regionJump" />
      <line
         id="j-10000011-10000007"
         x1="696.25128"
         y1="295.34457"
         x2="570.07153"
         y2="328.42847"
         class="regionJump" />
      <line
         id="j-10000018-10000007"
         x1="686.22455"
         y1="298.41348"
         x2="649.52728"
         y2="219.43053"
         class="regionJump" />
      <line
         id="j-10000021-10000007"
         x1="690.84277"
         y1="302.08569"
         x2="690.84277"
         y2="164.08569"
         class="regionJump" />
      <line
         id="j-10000008-10000008"
         x1="570.5"
         y1="382.5"
         x2="570.5"
         y2="382.5"
         class="regionJump" />
      <line
         id="j-10000012-10000008"
         x1="570.5"
         y1="382.5"
         x2="470.5"
         y2="416.5"
         class="regionJump" />
      <line
         id="j-10000011-10000008"
         x1="570.5"
         y1="382.5"
         x2="570"
         y2="328.5"
         class="regionJump" />
      <line
         id="j-10000009-10000008"
         x1="570.5"
         y1="382.5"
         x2="646.5"
         y2="396.5"
         class="regionJump" />
      <line
         id="j-10000009-10000009"
         x1="646.5"
         y1="396.5"
         x2="646.5"
         y2="396.5"
         class="regionJump" />
      <line
         id="j-10000027-10000009"
         x1="646.5"
         y1="396.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000018-10000009"
         x1="646.5"
         y1="396.5"
         x2="644.5"
         y2="217.5"
         class="regionJump" />
      <line
         id="j-10000010-10000010"
         x1="368.5"
         y1="106.5"
         x2="368.5"
         y2="106.5"
         class="regionJump" />
      <line
         id="j-10000016-10000010"
         x1="368.5"
         y1="106.5"
         x2="367.5"
         y2="156.5"
         class="regionJump" />
      <line
         id="j-10000023-10000010"
         x1="368.5"
         y1="106.5"
         x2="299.5"
         y2="112.5"
         class="regionJump" />
      <line
         id="j-10000015-10000010"
         x1="368.5"
         y1="106.5"
         x2="378.5"
         y2="66.5"
         class="regionJump" />
      <line
         id="j-10000011-10000011"
         x1="570"
         y1="328.5"
         x2="570"
         y2="328.5"
         class="regionJump" />
      <line
         id="j-10000012-10000011"
         x1="570"
         y1="328.5"
         x2="470.5"
         y2="416.5"
         class="regionJump" />
      <line
         id="j-10000028-10000011"
         x1="570"
         y1="328.5"
         x2="498.5"
         y2="318.5"
         class="regionJump" />
      <line
         id="j-10000027-10000011"
         x1="570"
         y1="328.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000042-10000011"
         x1="570"
         y1="328.5"
         x2="465.5"
         y2="268.5"
         class="regionJump" />
      <line
         id="j-10000029-10000011"
         x1="570"
         y1="328.5"
         x2="472.5"
         y2="178.5"
         class="regionJump" />
      <line
         id="j-10000012-10000012"
         x1="470.5"
         y1="416.5"
         x2="470.5"
         y2="416.5"
         class="regionJump" />
      <line
         id="j-10000014-10000012"
         x1="470.5"
         y1="416.5"
         x2="382.5"
         y2="486.5"
         class="regionJump" />
      <line
         id="j-10000013-10000013"
         x1="587.5"
         y1="185.5"
         x2="587.5"
         y2="185.5"
         class="regionJump" />
      <line
         id="j-10000066-10000013"
         x1="587.5"
         y1="185.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000034-10000013"
         x1="587.5"
         y1="185.5"
         x2="526.5"
         y2="149.5"
         class="regionJump" />
      <line
         id="j-10000040-10000013"
         x1="593.95392"
         y1="188.95746"
         x2="650.95392"
         y2="114.95746"
         class="regionJump" />
      <line
         id="j-10000027-10000013"
         x1="587.5"
         y1="185.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000018-10000013"
         x1="587.5"
         y1="185.5"
         x2="644.5"
         y2="217.5"
         class="regionJump" />
      <line
         id="j-10000014-10000014"
         x1="382.5"
         y1="486.5"
         x2="382.5"
         y2="486.5"
         class="regionJump" />
      <line
         id="j-10000047-10000014"
         x1="382.5"
         y1="486.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000049-10000014"
         x1="382.5"
         y1="486.5"
         x2="149.5"
         y2="426.5"
         class="regionJump" />
      <line
         id="j-10000025-10000014"
         x1="382.5"
         y1="486.5"
         x2="464.5"
         y2="464.5"
         class="regionJump" />
      <line
         id="j-10000061-10000014"
         x1="382.5"
         y1="486.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000022-10000014"
         x1="382.5"
         y1="486.5"
         x2="309.5"
         y2="511.5"
         class="regionJump" />
      <line
         id="j-10000031-10000014"
         x1="382.5"
         y1="486.5"
         x2="404.5"
         y2="530.5"
         class="regionJump" />
      <line
         id="j-10000050-10000014"
         x1="382.5"
         y1="486.5"
         x2="189.5"
         y2="482.5"
         class="regionJump" />
      <line
         id="j-10000015-10000015"
         x1="378.5"
         y1="66.5"
         x2="378.5"
         y2="66.5"
         class="regionJump" />
      <line
         id="j-10000035-10000015"
         x1="376.45123"
         y1="71.725731"
         x2="283.54877"
         y2="29.274271"
         class="regionJump" />
      <line
         id="j-10000055-10000015"
         x1="378.5"
         y1="66.5"
         x2="341.5"
         y2="15.5"
         class="regionJump" />
      <line
         id="j-10000066-10000015"
         x1="378.5"
         y1="66.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000045-10000015"
         x1="378.5"
         y1="66.5"
         x2="415.5"
         y2="18.5"
         class="regionJump" />
      <line
         id="j-10000016-10000016"
         x1="367.5"
         y1="156.5"
         x2="367.5"
         y2="156.5"
         class="regionJump" />
      <line
         id="j-10000033-10000016"
         x1="367.5"
         y1="156.5"
         x2="352.5"
         y2="213.5"
         class="regionJump" />
      <line
         id="j-10000069-10000016"
         x1="367.5"
         y1="156.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
      <line
         id="j-10000023-10000016"
         x1="367.5"
         y1="156.5"
         x2="299.5"
         y2="112.5"
         class="regionJump" />
      <line
         id="j-10000017-10000017"
         x1="497.5"
         y1="78.5"
         x2="497.5"
         y2="78.5"
         class="regionJump" />
      <line
         id="j-10000019-10000017"
         x1="497.5"
         y1="78.5"
         x2="480.5"
         y2="50.5"
         class="regionJump" />
      <line
         id="j-10000018-10000018"
         x1="644.5"
         y1="217.5"
         x2="644.5"
         y2="217.5"
         class="regionJump" />
      <line
         id="j-10000027-10000018"
         x1="644.5"
         y1="217.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000040-10000018"
         x1="645.99823"
         y1="216.12994"
         x2="645.99823"
         y2="112.87005"
         class="regionJump" />
      <line
         id="j-10000066-10000018"
         x1="649.75934"
         y1="219.61406"
         x2="600.7514"
         y2="119.15897"
         class="regionJump" />
      <line
         id="j-10000021-10000018"
         x1="644.5"
         y1="217.5"
         x2="676.5"
         y2="160.5"
         class="regionJump" />
      <line
         id="j-10000019-10000019"
         x1="480.5"
         y1="50.5"
         x2="480.5"
         y2="50.5"
         class="regionJump" />
      <line
         id="j-10000020-10000020"
         x1="249.5"
         y1="419.5"
         x2="249.5"
         y2="419.5"
         class="regionJump" />
      <line
         id="j-10000043-10000020"
         x1="249.5"
         y1="419.5"
         x2="278.5"
         y2="374.5"
         class="regionJump" />
      <line
         id="j-10000052-10000020"
         x1="249.5"
         y1="419.5"
         x2="219.5"
         y2="352.5"
         class="regionJump" />
      <line
         id="j-10000049-10000020"
         x1="249.5"
         y1="419.5"
         x2="149.5"
         y2="426.5"
         class="regionJump" />
      <line
         id="j-10000047-10000020"
         x1="249.5"
         y1="419.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000021-10000021"
         x1="676.5"
         y1="160.5"
         x2="676.5"
         y2="160.5"
         class="regionJump" />
      <line
         id="j-10000053-10000021"
         x1="693.71423"
         y1="160.80353"
         x2="697.78235"
         y2="51.146721"
         class="regionJump" />
      <line
         id="j-10000066-10000021"
         x1="676.5"
         y1="160.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000022-10000022"
         x1="309.5"
         y1="511.5"
         x2="309.5"
         y2="511.5"
         class="regionJump" />
      <line
         id="j-10000039-10000022"
         x1="309.5"
         y1="511.5"
         x2="284.5"
         y2="552.5"
         class="regionJump" />
      <line
         id="j-10000063-10000022"
         x1="309.5"
         y1="511.5"
         x2="96.5"
         y2="542.5"
         class="regionJump" />
      <line
         id="j-10000023-10000023"
         x1="299.5"
         y1="112.5"
         x2="299.5"
         y2="112.5"
         class="regionJump" />
      <line
         id="j-10000046-10000023"
         x1="299.5"
         y1="112.5"
         x2="275.5"
         y2="72.5"
         class="regionJump" />
      <line
         id="j-10000048-10000023"
         x1="299.5"
         y1="112.5"
         x2="208.5"
         y2="168.5"
         class="regionJump" />
      <line
         id="j-10000051-10000023"
         x1="299.5"
         y1="112.5"
         x2="172.5"
         y2="101.5"
         class="regionJump" />
      <line
         id="j-10000042-10000042"
         x1="465.5"
         y1="268.5"
         x2="465.5"
         y2="268.5"
         class="regionJump" />
      <line
         id="j-10000042-10000032"
         x1="465.5"
         y1="268.5"
         x2="288.5"
         y2="269.5"
         class="regionJump" />
      <line
         id="j-10000042-10000030"
         x1="465.5"
         y1="268.5"
         x2="438.5"
         y2="309.5"
         class="regionJump" />
      <line
         id="j-10000042-10000038"
         x1="460.61041"
         y1="264.58832"
         x2="342.61041"
         y2="336.58832"
         class="regionJump" />
      <line
         id="j-10000025-10000025"
         x1="464.5"
         y1="464.5"
         x2="464.5"
         y2="464.5"
         class="regionJump" />
      <line
         id="j-10000061-10000025"
         x1="464.5"
         y1="464.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000043-10000043"
         x1="278.5"
         y1="374.5"
         x2="278.5"
         y2="374.5"
         class="regionJump" />
      <line
         id="j-10000065-10000043"
         x1="278.5"
         y1="374.5"
         x2="172.5"
         y2="384.5"
         class="regionJump" />
      <line
         id="j-10000052-10000043"
         x1="278.5"
         y1="374.5"
         x2="219.5"
         y2="352.5"
         class="regionJump" />
      <line
         id="j-10000027-10000027"
         x1="530.5"
         y1="211.5"
         x2="530.5"
         y2="211.5"
         class="regionJump" />
      <line
         id="j-10000034-10000027"
         x1="530.5"
         y1="211.5"
         x2="526.5"
         y2="149.5"
         class="regionJump" />
      <line
         id="j-10000066-10000027"
         x1="530.5"
         y1="211.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000028-10000027"
         x1="530.5"
         y1="211.5"
         x2="498.5"
         y2="318.5"
         class="regionJump" />
      <line
         id="j-10000028-10000028"
         x1="498.5"
         y1="318.5"
         x2="498.5"
         y2="318.5"
         class="regionJump" />
      <line
         id="j-10000030-10000028"
         x1="498.5"
         y1="318.5"
         x2="438.5"
         y2="309.5"
         class="regionJump" />
      <line
         id="j-10000042-10000028"
         x1="498.5"
         y1="318.5"
         x2="465.5"
         y2="268.5"
         class="regionJump" />
      <line
         id="j-10000029-10000029"
         x1="472.5"
         y1="178.5"
         x2="472.5"
         y2="178.5"
         class="regionJump" />
      <line
         id="j-10000034-10000029"
         x1="472.5"
         y1="178.5"
         x2="526.5"
         y2="149.5"
         class="regionJump" />
      <line
         id="j-10000042-10000029"
         x1="472.5"
         y1="178.5"
         x2="465.5"
         y2="268.5"
         class="regionJump" />
      <line
         id="j-10000030-10000030"
         x1="438.5"
         y1="309.5"
         x2="438.5"
         y2="309.5"
         class="regionJump" />
      <line
         id="j-10000036-10000030"
         x1="438.5"
         y1="309.5"
         x2="347.5"
         y2="385.5"
         class="regionJump" />
      <line
         id="j-10000032-10000030"
         x1="438.5"
         y1="309.5"
         x2="288.5"
         y2="269.5"
         class="regionJump" />
      <line
         id="j-10000038-10000030"
         x1="438.5"
         y1="309.5"
         x2="347.5"
         y2="340.5"
         class="regionJump" />
      <line
         id="j-10000031-10000031"
         x1="404.5"
         y1="530.5"
         x2="404.5"
         y2="530.5"
         class="regionJump" />
      <line
         id="j-10000061-10000031"
         x1="404.5"
         y1="530.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000056-10000031"
         x1="404.5"
         y1="530.5"
         x2="398.5"
         y2="579.5"
         class="regionJump" />
      <line
         id="j-10000032-10000032"
         x1="288.5"
         y1="269.5"
         x2="288.5"
         y2="269.5"
         class="regionJump" />
      <line
         id="j-10000064-10000032"
         x1="288.5"
         y1="269.5"
         x2="220.5"
         y2="206.5"
         class="regionJump" />
      <line
         id="j-10000037-10000032"
         x1="288.5"
         y1="269.5"
         x2="225.5"
         y2="270"
         class="regionJump" />
      <line
         id="j-10000038-10000032"
         x1="288.5"
         y1="269.5"
         x2="347.5"
         y2="340.5"
         class="regionJump" />
      <line
         id="j-10000067-10000032"
         x1="288.5"
         y1="269.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000033-10000032"
         x1="288.5"
         y1="269.5"
         x2="352.5"
         y2="213.5"
         class="regionJump" />
      <line
         id="j-10000043-10000032"
         x1="288.5"
         y1="269.5"
         x2="278.5"
         y2="374.5"
         class="regionJump" />
      <line
         id="j-10000033-10000033"
         x1="352.5"
         y1="213.5"
         x2="352.5"
         y2="213.5"
         class="regionJump" />
      <line
         id="j-10000069-10000033"
         x1="352.5"
         y1="213.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
      <line
         id="j-10000064-10000033"
         x1="352.5"
         y1="213.5"
         x2="220.5"
         y2="206.5"
         class="regionJump" />
      <line
         id="j-10000043-10000033"
         x1="360.64932"
         y1="211.54416"
         x2="286.64932"
         y2="372.54416"
         class="regionJump"/>
      <line
         id="j-10000068-10000033"
         x1="352.5"
         y1="213.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000034-10000034"
         x1="526.5"
         y1="149.5"
         x2="526.5"
         y2="149.5"
         class="regionJump" />
      <line
         id="j-10000035-10000035"
         x1="281.5"
         y1="34.5"
         x2="281.5"
         y2="34.5"
         class="regionJump" />
      <line
         id="j-10000046-10000035"
         x1="273.45337"
         y1="19.903788"
         x2="279.30896"
         y2="70.960571"
         class="regionJump" />
      <line
         id="j-10000055-10000035"
         x1="261.02756"
         y1="24.494011"
         x2="341.59915"
         y2="15.400836"
         class="regionJump" />
      <line
         id="j-10000036-10000036"
         x1="347.5"
         y1="385.5"
         x2="347.5"
         y2="385.5"
         class="regionJump" />
      <line
         id="j-10000038-10000036"
         x1="347.5"
         y1="385.5"
         x2="347.5"
         y2="340.5"
         class="regionJump" />
      <line
         id="j-10000047-10000036"
         x1="347.5"
         y1="385.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000043-10000036"
         x1="347.5"
         y1="385.5"
         x2="278.5"
         y2="374.5"
         class="regionJump" />
      <line
         id="j-10000037-10000037"
         x1="225.5"
         y1="270"
         x2="225.5"
         y2="270"
         class="regionJump" />
      <line
         id="j-10000064-10000037"
         x1="225.5"
         y1="270"
         x2="220.5"
         y2="206.5"
         class="regionJump" />
      <line
         id="j-10000067-10000037"
         x1="225.5"
         y1="270"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000038-10000038"
         x1="347.5"
         y1="340.5"
         x2="347.5"
         y2="340.5"
         class="regionJump" />
      <line
         id="j-10000043-10000038"
         x1="347.5"
         y1="340.5"
         x2="278.5"
         y2="374.5"
         class="regionJump" />
      <line
         id="j-10000039-10000039"
         x1="284.5"
         y1="552.5"
         x2="284.5"
         y2="552.5"
         class="regionJump" />
      <line
         id="j-10000056-10000039"
         x1="284.5"
         y1="552.5"
         x2="398.5"
         y2="579.5"
         class="regionJump" />
      <line
         id="j-10000059-10000039"
         x1="284.5"
         y1="552.5"
         x2="219.5"
         y2="575.5"
         class="regionJump" />
      <line
         id="j-10000040-10000040"
         x1="644.5"
         y1="111.5"
         x2="644.5"
         y2="111.5"
         class="regionJump" />
      <line
         id="j-10000066-10000040"
         x1="644.5"
         y1="111.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000053-10000040"
         x1="644.70538"
         y1="111.2946"
         x2="687.59253"
         y2="51.194656"
         class="regionJump" />
      <line
         id="j-10000041-10000041"
         x1="121.5"
         y1="188.5"
         x2="121.5"
         y2="188.5"
         class="regionJump" />
      <line
         id="j-10000048-10000041"
         x1="121.5"
         y1="188.5"
         x2="208.5"
         y2="168.5"
         class="regionJump" />
      <line
         id="j-10000068-10000041"
         x1="121.5"
         y1="188.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000044-10000041"
         x1="121.5"
         y1="188.5"
         x2="113.5"
         y2="281.5"
         class="regionJump" />
      <line
         id="j-10000057-10000041"
         x1="121.5"
         y1="188.5"
         x2="76.5"
         y2="123.5"
         class="regionJump" />
      <line
         id="j-10000051-10000041"
         x1="121.5"
         y1="188.5"
         x2="172.5"
         y2="101.5"
         class="regionJump" />
      <line
         id="j-10000047-10000043"
         x1="278.5"
         y1="374.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000044-10000044"
         x1="113.5"
         y1="281.5"
         x2="113.5"
         y2="281.5"
         class="regionJump" />
      <line
         id="j-10000054-10000044"
         x1="113.5"
         y1="281.5"
         x2="93.5"
         y2="361.5"
         class="regionJump" />
      <line
         id="j-10000045-10000045"
         x1="415.5"
         y1="18.5"
         x2="415.5"
         y2="18.5"
         class="regionJump" />
      <line
         id="j-10000055-10000045"
         x1="415.5"
         y1="18.5"
         x2="341.5"
         y2="15.5"
         class="regionJump" />
      <line
         id="j-10000053-10000045"
         x1="414.01196"
         y1="16.089968"
         x2="688.36395"
         y2="49.711327"
         class="regionJump" />
      <line
         id="j-10000046-10000046"
         x1="275.5"
         y1="72.5"
         x2="275.5"
         y2="72.5"
         class="regionJump" />
      <line
         id="j-10000051-10000046"
         x1="275.5"
         y1="72.5"
         x2="172.5"
         y2="101.5"
         class="regionJump" />
      <line
         id="j-10000047-10000047"
         x1="347.5"
         y1="440.5"
         x2="347.5"
         y2="440.5"
         class="regionJump" />
      <line
         id="j-10000048-10000048"
         x1="208.5"
         y1="168.5"
         x2="208.5"
         y2="168.5"
         class="regionJump" />
      <line
         id="j-10000068-10000048"
         x1="208.5"
         y1="168.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000064-10000048"
         x1="208.5"
         y1="168.5"
         x2="220.5"
         y2="206.5"
         class="regionJump" />
      <line
         id="j-10000069-10000048"
         x1="208.5"
         y1="168.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
      <line
         id="j-10000051-10000048"
         x1="208.5"
         y1="168.5"
         x2="172.5"
         y2="101.5"
         class="regionJump" />
      <line
         id="j-10000049-10000049"
         x1="149.5"
         y1="426.5"
         x2="149.5"
         y2="426.5"
         class="regionJump" />
      <line
         id="j-10000065-10000049"
         x1="149.5"
         y1="426.5"
         x2="172.5"
         y2="384.5"
         class="regionJump" />
      <line
         id="j-10000050-10000049"
         x1="149.5"
         y1="426.5"
         x2="189.5"
         y2="482.5"
         class="regionJump" />
      <line
         id="j-10000054-10000049"
         x1="149.5"
         y1="426.5"
         x2="93.5"
         y2="361.5"
         class="regionJump" />
      <line
         id="j-10000050-10000050"
         x1="189.5"
         y1="482.5"
         x2="189.5"
         y2="482.5"
         class="regionJump" />
      <line
         id="j-10000060-10000050"
         x1="189.5"
         y1="482.5"
         x2="70.5"
         y2="470"
         class="regionJump" />
      <line
         id="j-10000051-10000051"
         x1="172.5"
         y1="101.5"
         x2="172.5"
         y2="101.5"
         class="regionJump" />
      <line
         id="j-10000057-10000051"
         x1="172.5"
         y1="101.5"
         x2="76.5"
         y2="123.5"
         class="regionJump" />
      <line
         id="j-10000058-10000051"
         x1="172.5"
         y1="101.5"
         x2="28.5"
         y2="233.5"
         class="regionJump" />
      <line
         id="j-10000069-10000051"
         x1="172.5"
         y1="101.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
      <line
         id="j-10000052-10000052"
         x1="219.5"
         y1="352.5"
         x2="219.5"
         y2="352.5"
         class="regionJump" />
      <line
         id="j-10000067-10000052"
         x1="219.5"
         y1="352.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000065-10000052"
         x1="219.5"
         y1="352.5"
         x2="172.5"
         y2="384.5"
         class="regionJump" />
      <line
         id="j-10000053-10000053"
         x1="679.5"
         y1="74.5"
         x2="679.5"
         y2="74.5"
         class="regionJump" />
      <line
         id="j-10000054-10000054"
         x1="93.5"
         y1="361.5"
         x2="93.5"
         y2="361.5"
         class="regionJump" />
      <line
         id="j-10000067-10000054"
         x1="93.5"
         y1="361.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000065-10000054"
         x1="93.5"
         y1="361.5"
         x2="172.5"
         y2="384.5"
         class="regionJump" />
      <line
         id="j-10000060-10000054"
         x1="93.5"
         y1="361.5"
         x2="70.5"
         y2="470"
         class="regionJump" />
      <line
         id="j-10000058-10000054"
         x1="93.5"
         y1="361.5"
         x2="28.5"
         y2="233.5"
         class="regionJump" />
      <line
         id="j-10000055-10000055"
         x1="341.5"
         y1="15.5"
         x2="341.5"
         y2="15.5"
         class="regionJump" />
      <line
         id="j-10000056-10000056"
         x1="398.5"
         y1="579.5"
         x2="398.5"
         y2="579.5"
         class="regionJump" />
      <line
         id="j-10000062-10000056"
         x1="398.5"
         y1="579.5"
         x2="469.5"
         y2="560.5"
         class="regionJump" />
      <line
         id="j-10000061-10000056"
         x1="398.5"
         y1="579.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000057-10000057"
         x1="76.5"
         y1="123.5"
         x2="76.5"
         y2="123.5"
         class="regionJump" />
      <line
         id="j-10000058-10000057"
         x1="76.5"
         y1="123.5"
         x2="28.5"
         y2="233.5"
         class="regionJump" />
      <line
         id="j-10000058-10000058"
         x1="28.5"
         y1="233.5"
         x2="28.5"
         y2="233.5"
         class="regionJump" />
      <line
         id="j-10000060-10000058"
         x1="28.5"
         y1="233.5"
         x2="70.5"
         y2="470"
         class="regionJump" />
      <line
         id="j-10000059-10000059"
         x1="219.5"
         y1="575.5"
         x2="219.5"
         y2="575.5"
         class="regionJump" />
      <line
         id="j-10000063-10000059"
         x1="219.5"
         y1="575.5"
         x2="96.5"
         y2="542.5"
         class="regionJump" />
      <line
         id="j-10000060-10000060"
         x1="70.5"
         y1="470"
         x2="70.5"
         y2="470"
         class="regionJump" />
      <line
         id="j-10000063-10000060"
         x1="70.5"
         y1="470"
         x2="96.5"
         y2="542.5"
         class="regionJump" />
      <line
         id="j-10000061-10000061"
         x1="479.5"
         y1="510.5"
         x2="479.5"
         y2="510.5"
         class="regionJump" />
      <line
         id="j-10000062-10000061"
         x1="479.5"
         y1="510.5"
         x2="469.5"
         y2="560.5"
         class="regionJump" />
      <line
         id="j-10000062-10000062"
         x1="469.5"
         y1="560.5"
         x2="469.5"
         y2="560.5"
         class="regionJump" />
      <line
         id="j-10000063-10000063"
         x1="96.5"
         y1="542.5"
         x2="96.5"
         y2="542.5"
         class="regionJump" />
      <line
         id="j-10000064-10000064"
         x1="220.5"
         y1="206.5"
         x2="220.5"
         y2="206.5"
         class="regionJump" />
      <line
         id="j-10000067-10000064"
         x1="220.5"
         y1="206.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000068-10000064"
         x1="220.5"
         y1="206.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000069-10000064"
         x1="220.5"
         y1="206.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
      <line
         id="j-10000065-10000065"
         x1="172.5"
         y1="384.5"
         x2="172.5"
         y2="384.5"
         class="regionJump" />
      <line
         id="j-10000067-10000065"
         x1="172.5"
         y1="384.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000066-10000066"
         x1="582.5"
         y1="124.5"
         x2="582.5"
         y2="124.5"
         class="regionJump" />
      <line
         id="j-10000067-10000067"
         x1="170"
         y1="329.5"
         x2="170"
         y2="329.5"
         class="regionJump" />
      <line
         id="j-10000068-10000067"
         x1="170"
         y1="329.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000068-10000068"
         x1="160.5"
         y1="240.5"
         x2="160.5"
         y2="240.5"
         class="regionJump" />
      <line
         id="j-10000069-10000069"
         x1="310.5"
         y1="174.5"
         x2="310.5"
         y2="174.5"
         class="regionJump" />
    </g>
    <g
       id="sysuse">
      <use
         id="sys10000001"
         x="413"
         y="357"
         width="70"
         height="30"
         xlink:href="#def10000001" />
      <use
         id="sys10000002"
         x="394"
         y="200"
         width="70"
         height="30"
         xlink:href="#def10000002" />
      <use
         id="sys10000003"
         x="406"
         y="122"
         width="70"
         height="30"
         xlink:href="#def10000003" />
      <use
         id="sys10000004"
         x="530"
         y="63"
         width="70"
         height="30"
         xlink:href="#def10000004"
         transform="translate(4.7266036,-14.994742)" />
      <use
         id="sys10000005"
         x="574"
         y="460"
         width="70"
         height="30"
         xlink:href="#def10000005" />
      <use
         id="sys10000006"
         x="528"
         y="418"
         width="70"
         height="30"
         xlink:href="#def10000006" />
      <use
         id="sys10000007"
         x="648"
         y="284"
         width="70"
         height="30"
         xlink:href="#def10000007"
         transform="translate(6.4539294,0)" />
      <use
         id="sys10000008"
         x="542"
         y="368"
         width="70"
         height="30"
         xlink:href="#def10000008" />
      <use
         id="sys10000009"
         x="618"
         y="382"
         width="70"
         height="30"
         xlink:href="#def10000009" />
      <use
         id="sys10000010"
         x="340"
         y="92"
         width="70"
         height="30"
         xlink:href="#def10000010" />
      <use
         id="sys10000011"
         x="534"
         y="314"
         width="70"
         height="30"
         xlink:href="#def10000011" />
      <use
         id="sys10000012"
         x="442"
         y="402"
         width="70"
         height="30"
         xlink:href="#def10000012" />
      <use
         id="sys10000013"
         x="559"
         y="171"
         width="70"
         height="30"
         xlink:href="#def10000013" />
      <use
         id="sys10000014"
         x="354"
         y="472"
         width="70"
         height="30"
         xlink:href="#def10000014" />
      <use
         id="sys10000015"
         x="350"
         y="52"
         width="70"
         height="30"
         xlink:href="#def10000015" />
      <use
         id="sys10000016"
         x="339"
         y="142"
         width="70"
         height="30"
         xlink:href="#def10000016" />
      <use
         id="sys10000017"
         x="469"
         y="64"
         width="70"
         height="30"
         xlink:href="#def10000017"
         transform="translate(-3.7486856,-3.9116719)" />
      <use
         id="sys10000018"
         x="616"
         y="203"
         width="70"
         height="30"
         xlink:href="#def10000018" />
      <use
         id="sys10000019"
         x="452"
         y="36"
         width="70"
         height="30"
         xlink:href="#def10000019"
         transform="translate(1.3038906,-5.3785489)" />
      <use
         id="sys10000020"
         x="221"
         y="405"
         width="70"
         height="30"
         xlink:href="#def10000020" />
      <use
         id="sys10000021"
         x="648"
         y="146"
         width="70"
         height="30"
         xlink:href="#def10000021"
         transform="translate(12.060988,1.6298633)" />
      <use
         id="sys10000022"
         x="281"
         y="497"
         width="70"
         height="30"
         xlink:href="#def10000022" />
      <use
         id="sys10000023"
         x="271"
         y="98"
         width="70"
         height="30"
         xlink:href="#def10000023" />
      <use
         id="sys10000025"
         x="436"
         y="450"
         width="70"
         height="30"
         xlink:href="#def10000025" />
      <use
         id="sys10000027"
         x="502"
         y="197"
         width="70"
         height="30"
         xlink:href="#def10000027" />
      <use
         id="sys10000028"
         x="470"
         y="304"
         width="70"
         height="30"
         xlink:href="#def10000028" />
      <use
         id="sys10000029"
         x="444"
         y="164"
         width="70"
         height="30"
         xlink:href="#def10000029" />
      <use
         id="sys10000030"
         x="410"
         y="295"
         width="70"
         height="30"
         xlink:href="#def10000030"
         transform="translate(-4.5636172,0.65194532)" />
      <use
         id="sys10000031"
         x="376"
         y="516"
         width="70"
         height="30"
         xlink:href="#def10000031" />
      <use
         id="sys10000032"
         x="260"
         y="255"
         width="70"
         height="30"
         xlink:href="#def10000032" />
      <use
         id="sys10000033"
         x="324"
         y="199"
         width="70"
         height="30"
         xlink:href="#def10000033" />
      <use
         id="sys10000034"
         x="498"
         y="135"
         width="70"
         height="30"
         xlink:href="#def10000034"
         transform="translate(-12.712934,-3.5856993)" />
      <use
         id="sys10000035"
         x="253"
         y="20"
         width="70"
         height="30"
         xlink:href="#def10000035"
         transform="translate(-25.588854,-11.572029)" />
      <use
         id="sys10000036"
         x="319"
         y="371"
         width="70"
         height="30"
         xlink:href="#def10000036" />
      <use
         id="sys10000037"
         x="197"
         y="248"
         width="70"
         height="30"
         xlink:href="#def10000037" />
      <use
         id="sys10000038"
         x="319"
         y="326"
         width="70"
         height="30"
         xlink:href="#def10000038" />
      <use
         id="sys10000039"
         x="256"
         y="538"
         width="70"
         height="30"
         xlink:href="#def10000039" />
      <use
         id="sys10000040"
         x="616"
         y="97"
         width="70"
         height="30"
         xlink:href="#def10000040"
         transform="translate(1.3038906,-7.1713985)" />
      <use
         id="sys10000041"
         x="93"
         y="174"
         width="70"
         height="30"
         xlink:href="#def10000041" />
      <use
         id="sys10000042"
         x="437"
         y="254"
         width="70"
         height="30"
         xlink:href="#def10000042" />
      <use
         id="sys10000043"
         x="250"
         y="360"
         width="70"
         height="30"
         xlink:href="#def10000043" />
      <use
         id="sys10000044"
         x="85"
         y="267"
         width="70"
         height="30"
         xlink:href="#def10000044" />
      <use
         id="sys10000045"
         x="387"
         y="4"
         width="70"
         height="30"
         xlink:href="#def10000045" />
      <use
         id="sys10000046"
         x="247"
         y="58"
         width="70"
         height="30"
         xlink:href="#def10000046" />
      <use
         id="sys10000047"
         x="319"
         y="426"
         width="70"
         height="30"
         xlink:href="#def10000047" />
      <use
         id="sys10000048"
         x="180"
         y="154"
         width="70"
         height="30"
         xlink:href="#def10000048" />
      <use
         id="sys10000049"
         x="121"
         y="412"
         width="70"
         height="30"
         xlink:href="#def10000049" />
      <use
         id="sys10000050"
         x="161"
         y="468"
         width="70"
         height="30"
         xlink:href="#def10000050" />
      <use
         id="sys10000051"
         x="144"
         y="87"
         width="70"
         height="30"
         xlink:href="#def10000051" />
      <use
         id="sys10000052"
         x="191"
         y="338"
         width="70"
         height="30"
         xlink:href="#def10000052"
         transform="translate(-4.5636172,-1.7928496)" />
      <use
         id="sys10000053"
         x="651"
         y="60"
         width="70"
         height="30"
         xlink:href="#def10000053"
         transform="translate(5.5319395,-28.120692)" />
      <use
         id="sys10000054"
         x="65"
         y="347"
         width="70"
         height="30"
         xlink:href="#def10000054" />
      <use
         id="sys10000055"
         x="313"
         y="1"
         width="70"
         height="30"
         xlink:href="#def10000055" />
      <use
         id="sys10000056"
         x="370"
         y="565"
         width="70"
         height="30"
         xlink:href="#def10000056" />
      <use
         id="sys10000057"
         x="48"
         y="109"
         width="70"
         height="30"
         xlink:href="#def10000057" />
      <use
         id="sys10000058"
         x="0"
         y="219"
         width="70"
         height="30"
         xlink:href="#def10000058" />
      <use
         id="sys10000059"
         x="191"
         y="561"
         width="70"
         height="30"
         xlink:href="#def10000059" />
      <use
         id="sys10000060"
         x="42"
         y="448"
         width="70"
         height="30"
         xlink:href="#def10000060" />
      <use
         id="sys10000061"
         x="451"
         y="496"
         width="70"
         height="30"
         xlink:href="#def10000061" />
      <use
         id="sys10000062"
         x="441"
         y="546"
         width="70"
         height="30"
         xlink:href="#def10000062" />
      <use
         id="sys10000063"
         x="68"
         y="528"
         width="70"
         height="30"
         xlink:href="#def10000063" />
      <use
         id="sys10000064"
         x="192"
         y="192"
         width="70"
         height="30"
         xlink:href="#def10000064" />
      <use
         id="sys10000065"
         x="144"
         y="370"
         width="70"
         height="30"
         xlink:href="#def10000065" />
      <use
         id="sys10000066"
         x="554"
         y="110"
         width="70"
         height="30"
         xlink:href="#def10000066" />
      <use
         id="sys10000067"
         x="134"
         y="315"
         width="70"
         height="30"
         xlink:href="#def10000067"
         transform="translate(-2.2818086,-7.8233439)" />
      <use
         id="sys10000068"
         x="132"
         y="226"
         width="70"
         height="30"
         xlink:href="#def10000068" />
      <use
         id="sys10000069"
         x="282"
         y="160"
         width="70"
         height="30"
         xlink:href="#def10000069"
         transform="translate(-9.1272345,-1.6298633)" />
    </g>
	
	<g
         id="g5074">
        <rect
           y="496.83084"
           x="576.15765"
           height="121.09242"
           width="150"
           id="legendRect"
           style="fill:#ffffff;stroke:#000000;stroke-width:2;stroke-linecap:butt;stroke-linejoin:round;stroke-miterlimit:4;stroke-opacity:1;stroke-dasharray:none">
        </rect>		
        <use
           transform="matrix(1,0,0,1.0123965,-2.5381391,30.370755)"
           xlink:href="<?php echo $no_offices_example; ?>"
           height="30"
           width="70"
           y="460"
           x="574"
           id="legend1" />
        <text
           id="text4949"
           y="513.28809"
           x="643.58472"
           style="font-size:6px;font-style:normal;font-weight:normal;line-height:125%;letter-spacing:0px;word-spacing:0px;fill:#000000;fill-opacity:1;stroke:none;font-family:Sans"
           xml:space="preserve"><tspan
             y="513.28809"
             x="643.58472"
             id="tspan4951">Region has no offices</tspan></text>
        <use
           transform="matrix(1,0,0,1.0123965,135.79025,68.676924)"
           xlink:href="<?php echo $one_office_example; ?>"
           height="30"
           width="70"
           y="450"
           x="436"
           id="legend2" />
        <text
           id="text4949-4"
           y="542.41083"
           x="644.51453"
           style="font-size:6px;font-style:normal;font-weight:normal;line-height:125%;letter-spacing:0px;word-spacing:0px;fill:#000000;fill-opacity:1;stroke:none;font-family:Sans"
           xml:space="preserve"><tspan
             y="542.41083"
             x="644.51453"
             id="tspan4951-0">Region has one office</tspan></text>
        <use
           transform="matrix(1,0,0,1.024793,130.11865,140.39772)"
           xlink:href="<?php echo $upto3_offices_example; ?>"
           height="30"
           width="70"
           y="402"
           x="442"
           id="legend3" />
        <text
           id="text4949-4-4"
           y="570.56335"
           x="644.68323"
           style="font-size:6px;font-style:normal;font-weight:normal;line-height:125%;letter-spacing:0px;word-spacing:0px;fill:#000000;fill-opacity:1;stroke:none;font-family:Sans"
           xml:space="preserve"><tspan
             y="570.56335"
             x="644.68323"
             id="tspan4951-0-8">Region has up to 3 offices</tspan></text>
        <use
           transform="translate(159.13347,224.79661)"
           xlink:href="<?php echo $plenty_offices_example; ?>"
           height="30"
           width="70"
           y="357"
           x="413"
           id="legend4" />
        <text
           id="text4949-4-4-2"
           y="598.28156"
           x="643.6192"
           style="font-size:6px;font-style:normal;font-weight:normal;line-height:125%;letter-spacing:0px;word-spacing:0px;fill:#000000;fill-opacity:1;stroke:none;font-family:Sans"
           xml:space="preserve"><tspan
             y="598.28156"
             x="643.6192"
             id="tspan4951-0-8-4">Region has more than 3 offices</tspan></text>
      </g>
  </g>
  

</svg>
