<?php
header("Connection: Keep-alive");

echo "<html><body>";


$sql = "SELECT solarSystemID FROM eve_staticdata.`mapSolarSystems` WHERE regionID < 11000001";
$res = $db->query($sql);

$solarSystems = array();

while ($row = $res->fetch_array())
{
    $solarSystems[] = $row['solarSystemID'];
}

$n = sizeof($solarSystems);


$distances = array();
$alreadyDone = array();
for ($i = 0; $i < $n; $i++)
{
    for ($j = 0; $j < $n; $j++)
    {
	$to = $solarSystems[$i];
	$from = $solarSystems[$j];
        if ($i == $j)
        {
            $distances[$to][$from] = 0;
	    $alreadyDone[$to][$from] = true;
        } else {
            $distances[$to][$from] = -1;
            $alreadyDone[$to][$from] = false;
	    $alreadyDone[$from][$to] = false;
        }
	
    }
}

echo "Done reading solar systems...<br />\n";
flush();
ob_flush();



// see what is in the database already
$sql = "SELECT fromSolarSystemID, toSolarSystemID, numberOfJumps FROM sys_to_sys WHERE numberOfJumps >= 0";

$res = $db->query($sql);



$cntData = 0;

while ($row = $res->fetch_array())
{
    $from = $row['fromSolarSystemID'];
    $to = $row['toSolarSystemID'];
    $jumps = $row['numberOfJumps'];
    $distances[$from][$to] = $jumps;
    $distances[$to][$from] = $jumps;

    if ($jumps != -1) {
        $alreadyDone[$to][$from] = true;
	$alreadyDone[$from][$to] = true;
	$cntData++;
    }
}

echo "Done reading already existing data ($cntData)<br />\n";
flush();
ob_flush();

$jumps = array();

// Assuming a mysql conversion of the Static Data Dump
// in the database evesdd
$res = $db->query('SELECT `fromSolarSystemID`, `toSolarSystemID` FROM eve_staticdata.`mapSolarSystemJumps`');



while ($row = $res->fetch_array()) {
    $from = (int) $row['fromSolarSystemID'];
    $to   = (int) $row['toSolarSystemID'];

    if (!isset($jumps[$from])) {
        $jumps[$from] = array();
    }
    $jumps[$from][] = $to;
}

$start = microtime(true);

if (!($stmt = $db->prepare("INSERT INTO sys_to_sys (fromSolarSystemID, toSolarSystemID, numberOfJumps) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numberOfJumps=?"))) {
    echo "Error preparing statement...<br />";
    echo $db->error;
}


echo "starting now...<br />\n";
flush();
ob_flush();

for ($i = 0; $i < $n; $i++)
{
    echo "i=$i<br />\n";
    $skipped = 0;
    for ($j = $i+1; $j < $n; $j++) {
        if ($distances[$solarSystems[$i]][$solarSystems[$j]] == -1) {
            $from = $solarSystems[$i];
            $to = $solarSystems[$j];
            $path = shortest_path($from, $to, $jumps);
            $numberJumps = $path['distance'];

            $distances[$from][$to] = $numberJumps;
            $distances[$to][$from] = $numberJumps;

            $stmt->prepare("ssss", $from, $to, $numberJumps, $numberJumps);
            $stmt->execute();
            $stmt->prepare("ssss", $to, $from, $numberJumps, $numberJumps);
            $stmt->execute();

            $alreadyDone[$from][$to] = $alreadyDone[$to][$from] = true;

            /*
            // check paths inbetween
            for ($k = 1; $k < sizeof($path['jumps']) - 1; $k++) {
                $new_sys = $path['jumps'][$k];

                // we now know that the distance from $solarSystems[$i] to $new_sys is $k
                $distances[$solarSystems[$i]][$new_sys] = $k;
                $distances[$new_sys][$solarSystems[$i]] = $k;

                $distances[$solarSystems[$j]][$new_sys] = sizeof($path['jumps']) - $k - 1;
                $distances[$new_sys][$solarSystems[$j]] = sizeof($path['jumps']) - $k - 1;
            } */



        } else {
            $skipped++;
        }
    }
    $stop = microtime(true);
    echo "took me " . ($stop - $start) . " seconds for; skipped $skipped<br />\n";
    flush();
    ob_flush();
}


// add these to the database



echo "DONE!";


echo "</body></html>";

?>
