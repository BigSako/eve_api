<?php
// check for corp_id
	$corp_id = 1022115221;
	

base_page_header('',"Spartan Office Proximity","Spartan Office Proximity");






$startTime = microtime(true);
$qry = "SELECT * 
		FROM `corp_assets`
		WHERE `typeID` = 27
		AND `corp_id` = 1022115221";
$res = $db->query($qry);
$npcStations = array();
$conqStations = array();
if ($res) {
	while ($row = $res->fetch_array()) {
		if (($row["locationID"] >=  60000000) && ($row["locationID"] < 61000000)) {
			$npcStations[$row["locationID"]] = $row["locationID"];
		}
		if (($row["locationID"] >=  66000000) && ($row["locationID"] < 67000000)) {
			$npcStations[$row["locationID"]] = $row["locationID"] - 6000001;
		}
		if (($row["locationID"] >=   67000000) && ($row["locationID"] < 68000000)) {
			$conqStations[$row["locationID"]] = $row["locationID"] - 6000000;
		}
	}
}
$officeSystems = array();
$qry = "(
			SELECT `solarSystemID`
			FROM `eve_staticdata`.`staStations`
			WHERE `stationID` IN ('" . implode("', '", $npcStations) . "')
		) UNION (
			SELECT `solarSystemID`
			FROM `conqStations`
			WHERE `stationID` IN ('" . implode("', '", $conqStations) . "')
		)";
$res = $db->query($qry);
if ($res) {
	while ($row = $res->fetch_array()) {
		$officeSystems[] = $row["solarSystemID"];
	}
}
// we now have a array with all our office locations.
$lowsecSystems = array();
$systemNames = array();
$qry = "SELECT `solarSystemID`, `solarSystemName` 
		FROM `eve_staticdata`.`mapSolarSystems` 
		WHERE `security` < 0.5 
		AND `security` > 0";
$res = $db->query($qry);
if ($res) {
	while ($row = $res->fetch_array()) {
		$lowsecSystems[] = $row["solarSystemID"];
		$systemNames[$row["solarSystemID"]] = $row["solarSystemName"];
	}
}
$jumps = array();
$qry = "SELECT `fromSolarSystemID` , `toSolarSystemID`
		FROM `eve_staticdata`.`mapSolarSystemJumps`";
$res = $db->query($qry);
if ($res) {
	while ($row = $res->fetch_array()) {
		if (!isset($jumps[$row["fromSolarSystemID"]])) { $jumps[$row["fromSolarSystemID"]] = array(); }
		$jumps[(int) $row["fromSolarSystemID"]][] = (int) $row["toSolarSystemID"];
	}
}
$missing = array();
foreach ($lowsecSystems as $low) {
	if (!checkSystem($low)) { 
		$missing[] = $low;
	}
}



echo "<p> missing " . count($missing) . " out of " . count($lowsecSystems) . " Systems in lowsec </p>";
echo "<table><tr><th colspan=3>missing systems</th></tr>";
$qry = "SELECT `solarSystemID`, `solarSystemName`, `regionName`
		FROM `eve_staticdata`.`mapSolarSystems`
		JOIN `eve_staticdata`.`mapRegions` ON `mapSolarSystems`.`regionID` = `mapRegions`.`regionID`
		WHERE `solarSystemID` IN ('" . implode("', '", $missing) . "')
		ORDER BY `regionName`, `solarSystemName` ASC";
$res = $db->query($qry);
if ($res) {
	while ($row = $res->fetch_array()) {
		if (!isset($prevRegion)) $prevRegion = $row["regionName"];
		if ($prevRegion != $row["regionName"]) {
			$prevRegion = str_replace(" ", "_", $prevRegion);
			$dotlan = "http://evemaps.dotlan.net/map/$prevRegion/" . implode(",", $regSystems);
			echo "<tr><td colspan=3><a href=\"$dotlan\">Dotlan $prevRegion</td></tr>";
			$prevRegion = $row["regionName"];
			$regSystems = array();
		}
		$regSystems[] = $row["solarSystemName"];
		echo "  <tr>
					<td>
						<a href=\"javascript:CCPEVE.setDestination('$row[solarSystemID]')\">D</a> 
						<a href=\"javascript:CCPEVE.showInfo(5, $row[solarSystemID])\">I</a>
					</td>
					<td>$row[solarSystemName]</td>
					<td>$row[regionName]</td>
				</tr>";
	}
}	
echo "</table>";
printf("<p> page exec took %f seconds </p>", microtime(true) - $startTime);

function checkSystem($solarSystemID) {
	global $officeSystems, $jumps;
	if (in_array($solarSystemID, $officeSystems)) { return true; }
	$adj = array($solarSystemID);
	// go +1, +2 and +3
	for ($i = 0; $i < 3; $i++) {
		$tmp = $adj;
		foreach ($adj as $a) {
			$tmp = array_merge($tmp, $jumps[$a]);
		}
		$adj = array_unique($tmp);
		if (count(array_intersect($officeSystems, $adj)) > 0) { return true; }
	}
	return false;
}