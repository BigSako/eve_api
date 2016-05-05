<?php
/**
 * Created by PhpStorm.
 * User: ckreuz
 * Date: 06.02.16
 * Time: 18:14
 */



function curl_pull_from_zkillboard($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_ENCODING, "gzip");
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Hosted by s4ko88 (+s4ko88 AT gmail DOT com');

    $data = curl_exec($curl);

    // check for error
    if (!curl_errno($curl)) {
        curl_close($curl);
        return $data;
    }

    curl_close($curl);

    return "";
}


function parse_zkillboard_data($min_kill_id)
{
    global $globalDb, $SETTINGS;

    $cnt = 0;
	// beforeKillID vs. afterKillID
    if ($min_kill_id==-1)
    {
        $url = "https://zkillboard.com/api/" . $SETTINGS['zkillboard_api'];
    } else {
        $url = "https://zkillboard.com/api/" . $SETTINGS['zkillboard_api']  . "afterKillID/" . $min_kill_id . "/orderDirection/asc/";
    }

	echo "\tFetching from $url\n";

    $data = curl_pull_from_zkillboard($url);

    if ($data != "") {
        $data = json_decode($data, true);


        $mail_stmt = $globalDb->prepare("INSERT INTO kills_killmails
                      (external_kill_ID, external_source_type, solar_system_ID, kill_time, victim_ship_type_id, victim_character_id,
                      victim_character_name, victim_corp_id, victim_corp_name, victim_alliance_id, victim_alliance_name,
                      victim_faction_id, victim_faction_name, damage_taken, position_x, position_y, position_z,
                      zkb_location_id, zkb_hash, zkb_total_value, zkb_points)
                      VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");


        $item_stmt = $globalDb->prepare("INSERT INTO kills_killmails_items
                      (internal_kill_ID, type_id, flag, qty_dropped, qty_destroyed, singleton)
                      VALUES (?, ?, ?, ?, ?, ?)");

        $attacker_stmt = $globalDb->prepare("INSERT INTO kills_killmails_attackers
                      (internal_kill_ID, a_char_id, a_char_name, a_corp_id, a_corp_name, a_alliance_id,
                      a_alliance_name, a_faction_id, a_faction_name, damage_done, final_blow,
                      ship_type_id, weapon_type_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // iterate over all kills
        foreach ($data as $key => $val) {
            $victim = $val['victim'];
            $zkb = $val['zkb'];
            $position = $val['position'];
            $items = $val['items'];
            $attackers = $val['attackers'];

            $kill_source_type = 0; // zkb

            $external_kill_id = intval($val['killID']);
            $solar_system_ID = intval($val['solarSystemID']);
            $kill_time = $val['killTime'];

            $victim_ship_type_id = intval($victim['shipTypeID']);


            $victim_char_id = intval($victim['characterID']);
            $victim_char_name = $victim['characterName'];
            $victim_corp_id = intval($victim['corporationID']);
            $victim_corp_name = $victim['corporationName'];
            $victim_alliance_id = intval($victim['allianceID']);
            $victim_alliance_name = $victim['allianceName'];
            $victim_faction_id = intval($victim['factionID']);
            $victim_faction_name = $victim['factionName'];

            $damage_taken = intval($victim['damageTaken']);


            $position_x = doubleval($position['x']);
            $position_y = doubleval($position['y']);
            $position_z = doubleval($position['z']);

            $zkb_location_id = intval($zkb['locationID']);
            $zkb_hash = $zkb['hash'];
            $zkb_total_value = doubleval($zkb['totalValue']);
            $zkb_points = intval($zkb['points']);


            $mail_stmt->bind_param("iiisiisisisisiddddsdi", $external_kill_id, $kill_source_type, $solar_system_ID, $kill_time,
                                                        $victim_ship_type_id,
                                                        $victim_char_id, $victim_char_name, $victim_corp_id,
                                                        $victim_corp_name, $victim_alliance_id, $victim_alliance_name,
                                                        $victim_faction_id, $victim_faction_name, $damage_taken,
                                                        $position_x, $position_y, $position_z, $zkb_location_id,
                                                        $zkb_hash, $zkb_total_value, $zkb_points
                                                        );

            if (!$mail_stmt->execute())
            {
                echo "Error inserting:  $external_kill_id, $kill_source_type, $solar_system_ID, $kill_time,
                                                        $victim_ship_type_id,
                                                        $victim_char_id, $victim_char_name, $victim_corp_id,
                                                        $victim_corp_name, $victim_alliance_id, $victim_alliance_name,
                                                        $victim_faction_id, $victim_faction_name, $damage_taken,
                                                        $position_x, $position_y, $position_z, $zkb_location_id,
                                                        $zkb_hash, $zkb_total_value, $zkb_points\n";
                echo $globalDb->error;
                echo "\n";
                continue;
            }

            $internal_kill_id = $mail_stmt->insert_id;

            // handle all items of that killmail
            foreach ($items as $item_key => $item)
            {
                $type_id = intval($item['typeID']);
                $flag = intval($item['flag']);
                $dropped = intval($item['qtyDropped']);
                $destroyed = intval($item['qtyDestroyed']);
                $singleton = intval($item['singleton']);

                $item_stmt->bind_param("iiiiii", $internal_kill_id, $type_id, $flag, $dropped, $destroyed, $singleton);

                if (!$item_stmt->execute())
                {
                    echo "Error adding items\n";
                    echo "Vars =  $internal_kill_id, $type_id, $flag, $dropped, $destroyed, $singleton\n";
                    echo "Error = " . $globalDb->error . "\n";
                }
            }

            // handle all attackers of that killmail
            foreach ($attackers as $attacker_key => $attacker)
            {
                $a_char_id = $attacker['characterID'];
                $a_char_name = $attacker['characterName'];
                $a_corp_id = $attacker['corporationID'];
                $a_corp_name = $attacker['corporationName'];
                $a_alliance_id = $attacker['allianceID'];
                $a_alliance_name = $attacker['allianceName'];
                $a_faction_id = $attacker['factionID'];
                $a_faction_name = $attacker['factionName'];
                $damage_done = $attacker['damageDone'];
                $final_blow = $attacker['finalBlow'];
                $a_ship_type_id = $attacker['shipTypeID'];
                $a_weapon_type_id = $attacker['weaponTypeID'];

                /*  (internal_kill_ID, a_char_id, a_char_name, a_corp_id, a_corp_name, a_alliance_id,
                      a_alliance_name, a_faction_id, a_faction_name, damage_done, final_blow,
                      ship_type_id, weapon_type_id)
                */

                $attacker_stmt->bind_param("iisisisisiiii", $internal_kill_id, $a_char_id, $a_char_name, $a_corp_id,
                                                            $a_corp_name, $a_alliance_id, $a_alliance_name,
                                                            $a_faction_id, $a_faction_name, $damage_done, $final_blow,
                                                            $a_ship_type_id, $a_weapon_type_id);

                if (!$attacker_stmt->execute())
                {
                    if ($a_char_id != 0 && $a_char_id != '0' && $a_corp_name != 'CONCORD') // ignore concord errors
                    {
                        echo "Error adding attackers\n";
                        echo "Vars = $internal_kill_id, $a_char_id, $a_char_name, $a_corp_id, $a_corp_name, $a_alliance_id, $a_alliance_name,
                        $a_faction_id, $a_faction_name, $damage_done, $final_blow, $a_ship_type_id, $a_weapon_type_id\n";
                        echo "Error=" . $globalDb->error . "\n";
                    }
                }
            }

	$cnt++;


        }
    } else {
        echo "Nothing returned...";
    }
	return $cnt;
}


/** removes killmails that are older than 3 months */
function remove_old_killmails()
{
    global $globalDb;
    echo "Deleting old killmails...";
    $sql = "DELETE FROM kills_killmails WHERE TIMESTAMPDIFF(MONTH,kill_time,now()) > 3";
    $res = $globalDb->query($sql);

    if (!$res)
    {
        echo "Error, query = $sql\n";
        echo $globalDb->error;
        echo "\n";
    } else {
        echo "Res = $res\n";
        echo $res->num_rows;
    }
}



/** updates the killmails per day per character index */
function index_killmails()
{
    global $globalDb;

    // which one was the last day that we have in our kills_stats_per_char
    $sql = "SELECT MAX(date) as max_date FROM kills_stats_per_char";
    $res = $globalDb->query($sql);
    $row = $res->fetch_array();

    if ($row['max_date'] == NULL)
    {
        // nothing here, start with 1-1-1990
        $start_date = "1990-1-1";
    } else {
        // always get the max date from database, -1 month, in case any new killmails are added
        $start_date = $row['max_date']; // - 1 month
    }

    echo "start_date = " . $start_date;

    // we will go -1 month in the past from the last killmail, just in case we missed anything
    $sql = "SELECT internal_kill_ID, external_kill_ID, external_source_type, DATE(kill_time) as kill_date, 
            victim_character_id, victim_corp_id, victim_alliance_id, victim_ship_type_id
            FROM kills_killmails k WHERE 
            k.kill_time > ('$start_date' - INTERVAL 1 MONTH) 
            ORDER BY kill_time ASC";


    $last_date = NULL;

    $stored_data = array();
    $stored_data_corp = array();


    $res = $globalDb->query($sql);

    $cnt = 0;

    // iterate over all killmails
    while ($row = $res->fetch_array())
    {
        // get details of this kill
        $i_kill_id = $row['internal_kill_ID'];
        $ext_kill_id = $row['external_kill_ID'];
        $kill_type = $row['external_source_type'];
        $kill_date = $row['kill_date'];
        $victim_ship_type_id = $row['victim_ship_type_id'];
        $victim_char_id = $row['victim_character_id'];
        $victim_corp_id = $row['victim_corp_id'];
        $victim_alliance_id = $row['victim_alliance_id'];

        // ignore capsules (29), shuttles (31) and rookie ships (237)
        if (in_array($victim_ship_type_id, [29, 31, 237]))
            continue;


        if ($kill_date != $last_date && $last_date != NULL)
        {
            // if we switched date, we need to write per character statistics into our database (kills_stats_per_char)
            foreach ($stored_data[$last_date] as $char_id => $killmails)
            {            
                // flatten the killmail IDs, we only store them as a textfield in database
                $kill_list = implode(",", $killmails['kill_ids']);

                $sql = "INSERT INTO kills_stats_per_char 
                (`date`, character_id, corp_id, alliance_id, 
                number_kills, number_losses, number_kills_logi, number_kills_super, kill_ids)

                VALUES ('$last_date', $char_id, " . $killmails['corp_id'] . ", " . $killmails['alliance_id'] . ", " . $killmails['number_kills'] . ", " . $killmails['number_losses'] . ", " . $killmails['number_kills_as_logi'] . ", " . $killmails['number_kills_as_supercapital'] . ", '$kill_list')

                ON DUPLICATE KEY UPDATE 
                    number_kills=" . $killmails['number_kills'] . ", 
                    number_losses=" . $killmails['number_losses'] . ", 
                    number_kills_logi=" . $killmails['number_kills_as_logi'] . ", 
                    number_kills_super=" . $killmails['number_kills_as_supercapital'] . ", 
                    kill_ids='$kill_list'
                ";

                
                if (!$globalDb->query($sql))
                {
                    echo "MYSQL ERROR OCCURED; PRINTING DEBUG INFO\n";
                    echo $char_id . "\n";
                    print_r($killmails);

                    echo $sql . "\n";
                    echo $globalDb->error;
                    echo "\n";
                    return;
                }
                
            }

            // if we switched date, we need to write corp statistics into our database (kills_stats_per_corp)
            foreach ($stored_data_corp as $stats_corp_id => $stats_corp_data)
            {
                $stats_alliance_id = $stats_corp_data['alliance_id'];
                $num_kills = $stats_corp_data['number_kills'];

                $sql = "INSERT INTO kills_stats_per_corp (`date`, corp_id, alliance_id, number_kills) VALUES 
                ('$last_date', $stats_corp_id, $stats_alliance_id, $num_kills)
                ON DUPLICATE KEY UPDATE
                number_kills=$num_kills
                ";
                
                if (!$globalDb->query($sql))
                { 
                    echo "MYSQL ERROR OCCURED; PRINTING DEBUG INFO\n";
                    echo $$corp_id . "\n";
                    echo $sql . "\n";
                    echo $globalDb->error;
                    echo "\n";
                    return;
                } 
            }



            echo "Found a new day: $kill_date\n";

            echo "Processed $cnt killmails\n";
            $stored_data[$last_date] = NULL;
            $stored_data_corp = array();

            $cnt = 0;
        }

        $cnt++; // increase internal killmail coutner - just for debug and statistic output to console

        // make sure that the data structures in $stored_data exists
        if (!isset($stored_data[$kill_date]))
        {
            $stored_data[$kill_date] = array();
        }

        if (!isset($stored_data[$kill_date][$victim_char_id]))
        {
            $stored_data[$kill_date][$victim_char_id]['number_kills'] = 0;
            $stored_data[$kill_date][$victim_char_id]['number_kills_as_logi'] = 0;
            $stored_data[$kill_date][$victim_char_id]['number_kills_as_supercapital'] = 0;
            $stored_data[$kill_date][$victim_char_id]['number_losses'] = 0;
            $stored_data[$kill_date][$victim_char_id]['kill_ids'] = array();
            $stored_data[$kill_date][$victim_char_id]['corp_id'] = $victim_corp_id;
            $stored_data[$kill_date][$victim_char_id]['alliance_id'] = $victim_alliance_id;
        }

        $stored_data[$kill_date][$victim_char_id]['number_losses']++;

        // get all related characters for this killmail
        $sql2 = "SELECT a.a_char_id, a.a_corp_id, a.a_alliance_id, a.ship_type_id, t.groupID
        FROM kills_killmails_attackers a, eve_staticdata.invTypes t 
        WHERE internal_kill_ID = $i_kill_id AND t.typeID = a.ship_type_id ";
        $res2 = $globalDb->query($sql2);

        $corps_on_this_km = array(); // stores which corporations are on this killmail

        // iterate over all attackers
        while ($row2 = $res2->fetch_array())
        {
            $a_char_id = $row2['a_char_id'];
            $a_corp_id = $row2['a_corp_id'];
            $a_alliance_id = $row2['a_alliance_id'];
            $a_ship_type_id = $row2['ship_type_id'];
            $ship_group_id = $row2['groupID'];

            if (!isset($corps_on_this_km[$a_corp_id]))
            {
                $corps_on_this_km[$a_corp_id] = $a_alliance_id; // save that corp is on this killmail (but not how many members)
            }

            if (!isset($stored_data[$kill_date][$a_char_id]))
            {
                $stored_data[$kill_date][$a_char_id]['number_kills'] = 0;
                $stored_data[$kill_date][$a_char_id]['number_kills_as_logi'] = 0;
                $stored_data[$kill_date][$a_char_id]['number_kills_as_supercapital'] = 0;
                $stored_data[$kill_date][$a_char_id]['number_losses'] = 0;
                $stored_data[$kill_date][$a_char_id]['kill_ids'] = array();
                $stored_data[$kill_date][$a_char_id]['corp_id'] = $a_corp_id;
                $stored_data[$kill_date][$a_char_id]['alliance_id'] = $a_alliance_id;
            } 

            $stored_data[$kill_date][$a_char_id]['number_kills']++;
            $stored_data[$kill_date][$a_char_id]['kill_ids'][] = $kill_type + ":" + $ext_kill_id;   

            // special kills
            if ($ship_group_id == 832) // logi
            {
                $stored_data[$kill_date][$a_char_id]['number_kills_as_logi']++;
            }
            if ($ship_group_id == 659 || $ship_group_id == 30) // super carrier or titan
            {
                $stored_data[$kill_date][$a_char_id]['number_kills_as_supercapital']++;
            }
        }

        // now iterate over $corps_on_this_km and add them to $stored_data_corp
        foreach ($corps_on_this_km as $corp_id => $a_alliance_id)
        {
            if (!isset($stored_data_corp[$corp_id]))
            {
                $stored_data_corp[$corp_id] = array();
                $stored_data_corp[$corp_id]['number_kills'] = 1;
                $stored_data_corp[$corp_id]['alliance_id'] = $a_alliance_id;
            } else {
                $stored_data_corp[$corp_id]['number_kills'] += 1;
            }
        }

        $last_date = $kill_date;
    }

    // make sure to process the remaining killmails
    // if we switched date, we need to write per character statistics into our database (kills_stats_per_char)
    foreach ($stored_data[$last_date] as $char_id => $killmails)
    {            
        // flatten the killmail IDs, we only store them as a textfield in database
        $kill_list = implode(",", $killmails['kill_ids']);

        $sql = "INSERT INTO kills_stats_per_char 
        (`date`, character_id, corp_id, alliance_id, 
        number_kills, number_losses, number_kills_logi, number_kills_super, kill_ids)

        VALUES ('$last_date', $char_id, " . $killmails['corp_id'] . ", " . $killmails['alliance_id'] . ", " . $killmails['number_kills'] . ", " . $killmails['number_losses'] . ", " . $killmails['number_kills_as_logi'] . ", " . $killmails['number_kills_as_supercapital'] . ", '$kill_list')

        ON DUPLICATE KEY UPDATE 
            number_kills=" . $killmails['number_kills'] . ", 
            number_losses=" . $killmails['number_losses'] . ", 
            number_kills_logi=" . $killmails['number_kills_as_logi'] . ", 
            number_kills_super=" . $killmails['number_kills_as_supercapital'] . ", 
            kill_ids='$kill_list'
        ";

        
        if (!$globalDb->query($sql))
        {
            echo "MYSQL ERROR OCCURED; PRINTING DEBUG INFO\n";
            echo $char_id . "\n";
            print_r($killmails);

            echo $sql . "\n";
            echo $globalDb->error;
            echo "\n";
            return;
        }
        
    }

    // if we switched date, we need to write corp statistics into our database (kills_stats_per_corp)
    foreach ($stored_data_corp as $stats_corp_id => $stats_corp_data)
    {
        $stats_alliance_id = $stats_corp_data['alliance_id'];
        $num_kills = $stats_corp_data['number_kills'];

        $sql = "INSERT INTO kills_stats_per_corp (`date`, corp_id, alliance_id, number_kills) VALUES 
        ('$last_date', $stats_corp_id, $stats_alliance_id, $num_kills)
        ON DUPLICATE KEY UPDATE
        number_kills=$num_kills
        ";
        
        if (!$globalDb->query($sql))
        { 
            echo "MYSQL ERROR OCCURED; PRINTING DEBUG INFO\n";
            echo $$corp_id . "\n";
            echo $sql . "\n";
            echo $globalDb->error;
            echo "\n";
            return;
        } 
    }



    echo "Found a new day: $kill_date\n";

    echo "Processed $cnt killmails\n";
    $stored_data[$last_date] = NULL;
    $stored_data_corp = array();

    $cnt = 0;


    echo "\n";
}



?>
