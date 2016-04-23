<?php
include("funcs/starbases.php");

$corp_id = -1;

// check for corp id
if (!isset($_REQUEST['corp_id']))
{
    // check if is admin
    if ($isAdmin == true && !isset($_REQUEST['ignore_main_corp_id']))
    {
        $corp_id = $SETTINGS['main_corp_id'];
    }
    else if ($isAdmin == true && isset($_REQUEST['ignore_main_corp_id']))
    {
        // display corp selection page
        base_page_header('', "POS Status - Select Corporation","POS Status - Select Corporation");		

        $sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
        $res = $db->query($sql);

        while ($row = $res->fetch_array())
        {
            $corp_id = $row['corp_id'];
            echo "<li><a href=\"api.php?action=starbases&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
        }

        echo "</ul>";



        base_page_footer('','');

        exit;
    }
    else if (count($director_corp_ids) == 1)
    {
        // only one, so we can automatically redirect
        header('Location: api.php?action=starbases&corp_id=' . $director_corp_ids[0]);
        exit;
    } else if (count($director_corp_ids) > 0) // display possible corps to look at
    {
        $corp_ids = implode(',', $director_corp_ids);
        $sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
        $res = $db->query($sql);

        // char is director in several corps, but only one of them is also available on services
        if ($res->num_rows == 1)
        {
            $row = $res->fetch_array();
            $corp_id = $row['corp_id'];
            header("Location: api.php?action=starbases&corp_id=$corp_id");
        } else {
            // list all corps where this toon is director

            // display corp selection page
            base_page_header('',"Starbases - Select Corporation","Starbases - Select Corporation");

            echo "<ul>";

            while ($row = $res->fetch_array()) {
                $corp_id = $row['corp_id'];
                echo "<li><a href=\"api.php?action=starbases&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
            }

            echo "</ul>";

            base_page_footer('','');
        }

        exit;
    }
}



if (isset($_REQUEST['corp_id']))
    $corp_id = intval($_REQUEST['corp_id']);

if ($corp_id < 1)
    $corp_id = $SETTINGS['main_corp_id'];

$db = connectToDB();

$sql = "SELECT c.corp_name, c.ceo, c.corp_ticker, b.last_sb_update, b.last_asset_update 
	FROM corporations c, corp_api_keys b WHERE c.corp_id = $corp_id AND b.corp_id = c.corp_id";
$res = $db->query($sql);

if ($res->num_rows != 1)
{
    echo "invalid corp";
    exit;
}

if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
{
    // the only reason for this to be true is if the user is a manager, starbase engineer (group 21)
    // managers are allowed to look at the HR page for the corps they are in, so we need to check if a toon is in this corp

    if ($corp_id != $SETTINGS['main_corp_id']) {
        echo "Not allowed";
        exit;
    }
}


$corprow = $res->fetch_array();

$last_sb_update = $corprow['last_sb_update'];
$last_asset_update = $corprow['last_asset_update'];

if (isset($_REQUEST['filter']))
	$filter = $_REQUEST['filter'];
else
	$filter = "";

$where = "1=1";

switch($filter)
{
    case '':
        $where = "1=1";
        break;
    case 'offline':
        $where = "pos_state=1";
        break;
    case 'online':
        $where = "pos_state=4";
        break;
    case 'reinforced':
        $where = "pos_state=3";
        break;
}




		

base_page_header('',"POS Status " . $corprow['corp_name'],"POS Status " . $corprow['corp_name']);


if ($isAdmin == true)
{
    echo "Show <a href=\"api.php?action=starbases&ignore_main_corp_id=true\">other corporations</a>.<br />";
}

$db = connectToDB();

// see when this api key was last pulled
$sql = "SELECT keyid, state, last_checked, access_mask FROM corp_api_keys WHERE corp_id = $corp_id";
$res = $db->query($sql);

if ($res->num_rows == 0)
{
    echo "<b>ERROR</b>: This corporation has no API key entered. The data you are seeing here are probably VERY OLD.<br />";
} else {
    $row = $res->fetch_array();
    if ($row['state'] < 90) {
        echo "<b>Last Starbase List Pull</b>: $last_sb_update<br />";
        echo "<b>Last Asset Pull</b>: $last_asset_update<br />";
    }
    else
    {
        echo "<b>ERROR</b>: This corporation has an invalid API key entered. The data you are seeing here are probably VERY OLD.<br />";
    }
}



$staging = getStagingSystem();

$stagingSystem = $staging[1];


$counts = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
// get pos amount (only count state <= 10)
$res = $db->query("SELECT pos_state, COUNT(*) as cnt FROM starbases s WHERE corp_id = $corp_id AND state <= 10 GROUP BY pos_state ");

$total_cnt = 0;
while ($row = $res->fetch_array())
{
    $counts[$row['pos_state']] = $row['cnt'];
    $total_cnt += $row['cnt'];
}



// get all pos
$res = $db->query("SELECT r.regionName, m.solarSystemName, i.typeName, locationID, moonID, pos_state, stateTimestamp, d.itemName as moonName,
                        onlineTimestamp, standingOwnerID, s.itemID as posItemID
             FROM starbases s, eve_staticdata.mapSolarSystems m, eve_staticdata.mapRegions r, eve_staticdata.invTypes i, eve_staticdata.mapDenormalize d
WHERE s.corp_id = $corp_id AND m.solarSystemID = s.locationID AND m.regionID = r.regionID AND i.typeID = s.typeID 
AND d.itemID = moonID AND s.state <= 10 AND $where ORDER BY r.regionName, m.solarSystemName, d.itemName");

if ($res->num_rows == 0)
{
    echo "No POSes found.";
} else {
    echo "<b>Filter:</b> <a href=\"api.php?action=starbases&corp_id=$corp_id\">All (" . $total_cnt . ")</a> |
        <a href=\"api.php?action=starbases&filter=offline&corp_id=$corp_id\">Offline (" . $counts[1] . ")</a> |
            <a href=\"api.php?action=starbases&filter=online&corp_id=$corp_id\">Online  (" . $counts[4] . ")</a> |
            <a href=\"api.php?action=starbases&filter=reinforced&corp_id=$corp_id\">Reinforced (" . $counts[3] . ")</a> <br /><br />";
	
	echo "Click on the location (planet / moon) for more information of the selected starbase!<br />";
	
    echo "<table id='your_api_keys' style=\"width: 95%\">";
    echo "<tr>
            <th>Region</th>
            <th>Location</th>
            <th>Silos</th>
            <th>Fuel</th>
            <th>Towertype</th>
            <th>Status</th>
            <th>Dotlan</th>
          </tr>";

    $last_region = '';

    $estimated_value = 0.0;

    $monthly_income = 0.0;

    while ($row = $res->fetch_array()) {
        $regionName = $row['regionName'];
        $solarSystemname = $row['solarSystemName'];

        $has_office_in_system_sql = get_offices_close_to($solarSystemname, $corp_id, 0);

        echo "<!-- SQL='$has_office_in_system_sql' -->\n";
        $res_office = $db->query($has_office_in_system_sql);
        if ($res_office && $res_office->num_rows >= 1) {
            $has_office = true;
        } else {
            echo "<!-- mysql error: " . $db->error . "-->";
            $has_office = false;
        }

        $posItemID = $row['posItemID'];
        $typeName = $row['typeName'];
        $pos_state = $row['pos_state'];
        $stateTimestamp = $row['stateTimestamp'];
        $onlineTimestamp = $row['onlineTimestamp'];
        $standingOwnerID = $row['standingOwnerID'];
        $moonName = $row['moonName'];
        $locationID = $row['locationID'];
        $moonID = $row['moonID'];

        $names = getTowerNameAndLocation($db, $corp_id, $locationID, $posItemID);

        $x = $names[1];
        $y = $names[2];
        $z = $names[3];


        $silo_base_size = 20000.0;

        $tower_silo_bonus = 1.0;
        $tower_fuel_bonus = 1.0; // affected by sov afaik

        if (preg_match("/Gallente Control Tower/", $typeName) || preg_match("/Serpentis Control Tower/", $typeName) || preg_match("/Shadow Control Tower/", $typeName)) {
            $tower_silo_bonus = 2;
        } else if (preg_match("/Amarr Control Tower/", $typeName) || preg_match("/Blood Control Tower/", $typeName) || preg_match("/Sansha Control Tower/", $typeName)) {
            $tower_silo_bonus = 1.5;
        }

        $silo_size = $silo_base_size * $tower_silo_bonus;


        // based on the locationID, let's get all silos
        $asset_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName, i.capacity, x, y, z
                FROM corp_assets a, eve_staticdata.invTypes i
                WHERE a.locationID = $locationID AND typeName LIKE '%Silo%'
                AND i.typeID = a.typeID ORDER BY typeName ASC";

        $subRes = $db->query($asset_sql);

        $subItems = "";

        $silo_content_price = 0;

        while ($subRow = $subRes->fetch_array()) {
            //$subTypeName = $subRow['typeName'];
            $subTypeName = "";
            $itemID = $subRow['itemID'];
            $capacity = $subRow['capacity'];
            $parentItemID = $subRow['parentItemID'];

            $silo_x = $subRow['x'];
            $silo_y = $subRow['y'];
            $silo_z = $subRow['z'];

            //echo "Diff:" . abs($silo_x - $x) . ", " .  abs($silo_y - $y)  . ", " .  abs($silo_z - $z) . "\n";
            // chekc how far away the structure is from the tower - if it is too far, then it's on a different tower and we need to skip it here

            if (max(abs($silo_x - $x), abs($silo_y - $y), abs($silo_z - $z)) > 50000) {
                continue;
            }


            $silo_size = $capacity * $tower_silo_bonus;

            // let's get all contents
            $content_sql = "SELECT a.itemID, a.parentItemID, a.typeID, a.flag, a.singleton, a.rawQuantity, a.quantity , i.typeName
                FROM corp_assets a, eve_staticdata.invTypes i
                WHERE a.parentItemID = $itemID
                AND i.typeID = a.typeID ORDER BY typeName ASC";

            $contentRes = $db->query($content_sql);


            while ($contentRow = $contentRes->fetch_array()) {
                $quantity = $contentRow['quantity'];
                $silo_content_type_id = $contentRow['typeID'];
                $silo_content_type_name = $contentRow['typeName'];
                $price = request_price($silo_content_type_id);
                $silo_content_type_name = $contentRow['typeName'];

                $estimated_value += $price['buy'] * $quantity;

                if ($price['buy'] != 0)
                    $silo_content_price = $price['buy'];


                // each item (moongoo) in silo has 1 m3
                // so calculate percentage this way:
                $perc = round($quantity / $silo_size * 100);

                $perc_img = getPercentageImage($perc);

                $contentTypeName = $contentRow['typeName'];
                $subTypeName .= "<img title=\"$perc %, $silo_content_type_name\" alt=\"$perc %, $silo_content_type_name\" src=\"images/$perc_img\" /> $silo_content_type_name<br />";
            }


            $subItems .= $subTypeName . " ";
        }
        if ($subItems == "") {
            $subItems = "Staging POS";
        }

        $monthly_income += $silo_content_price;

        $pos_state_text = "Online";
        $timer = "";
        $pos_state_class = "";

        switch ($pos_state) {
            case 0:
                $pos_state_text = "Unanchored";
                $timer = "<br />N/a? $stateTimestamp $onlineTimestamp";
                $pos_state_class = "invalid";
                break;
            case 1:
                $pos_state_text = "Anch./Offl.";
                $timer = "<br />since $stateTimestamp";
                $pos_state_class = "warning";
                break;
            case 2:
                $pos_state_text = "Onlining";
                $timer = "<br />until $onlineTimestamp";
                $pos_state_class = "";
                break;
            case 3:
                $pos_state_text = "Reinforced";
                $timer = "<br />until $stateTimestamp";
                $pos_state_class = "warning";
                break;
            case 4:
                $pos_state_text = "Online";
                $timer = "";
                $pos_state_class = "active";
                break;
        }


        echo "\n<tr><td>";

        if ($last_region != $regionName) {
            echo generate_dotlan_link_region($regionName);
        }
        echo "</td><td>";
        //echo "<a href=\"api.php?action=locator&action2=find&system_name=$solarSystemname\">$moonName</a>";
        echo generate_dotlan_link_system($solarSystemname) . "<br />";
        echo "<a href=\"api.php?action=starbase_detail&itemID=$posItemID&locationID=$locationID&moonID=$moonID&corp_id=$corp_id\">$moonName</a>"; // $names[0];
        echo "</td>";

        $typeName = str_replace("Control Tower", "", $typeName);


        // based on the locationID and ItemID , let's get fuel status
        $block_status = getTowerFuelStatus($db, $corp_id, $locationID, $posItemID, 'Fuel Block');

        $perc = $block_status[0] / ($block_status[1] / 5) * 100;

        $perc_img = getPercentageImage($perc, true);
        $fuelBlockStr = sprintf("%5s/%5s", $block_status[0], ($block_status[1] / 5));
        echo "<td>$subItems</td><td><img src=\"images/$perc_img\" title=\"$fuelBlockStr\"/>";

        if ($has_office) {
            echo "<img alt=\"corp office in system\" src=\"images/storage.png\" />";
        }


        echo "</td>";

        $last_region = $regionName;

        echo "<td>$typeName</td><td class=\"$pos_state_class\">$pos_state_text $timer</td>";
        echo "<td><a target=\"_blank\" href=\"http://evemaps.dotlan.net/jump/Rhea,544/$stagingSystem" . ":" . "$solarSystemname\">Stage</a></td>";
        echo "</tr>";
    }


    echo "</table>";

    $estimated_value_str = number_format($estimated_value, 2, '.', ',');

    $monthly_income_str = number_format($monthly_income * 100 * 24 * 30, 2, '.', ',');
    echo "<br><br>Total amount of ISK in MoonGoo currently in POS's: $estimated_value_str ISK<br />";
    echo "Estimated income per month in ISK: $monthly_income_str ISK<br />";
}

echo "<br /><br />";




base_page_footer('1','');
?>
