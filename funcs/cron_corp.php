<?php


/**
 * Check the corp api key for validity and access mask
 */
function check_corp_api_keys()
{
    global $globalDb, $SETTINGS;

    $sql = "UPDATE corp_api_keys SET state = 1, last_checked=now() WHERE state <> 99";
    $globalDb->query($sql);

    $sql = "SELECT keyid, vcode FROM corp_api_keys WHERE state = 1";
    $res = $globalDb->query($sql);

    while ($row = $res->fetch_array())
    {
        // check this corp api key
        $key_id = $row['keyid'];
        $vcode = decrypt_vcode($row['vcode']);

        $api_key_status = api_get_key_permissions($key_id, $vcode);

        if (isset($api_key_status['errorcode']) && $api_key_status['errorcode'] == 221)
        {
            // this error shouldnt happen, this is usually a bug by ccp
            // set state to 2 - temporary error
            $globalDb->query("UPDATE corp_api_keys SET state = 2 WHERE keyid = $key_id ");
            continue;
        }

        // get the access mask and compare the result
        $access_mask = intval($api_key_status['mask']);

        if ($access_mask != intval($SETTINGS['corp_api_key_accessmask']))
        {
            $globalDb->query("UPDATE corp_api_keys SET access_mask=$access_mask, state = 99 WHERE keyid = $key_id ");
            $msg = "CRON Corp Error: Corp API KeyID: $key_id; with wrong access_mask: " . $api_key_status['mask'] . "\n";
            $msg .= print_r($api_key_status, true);

            echo $msg;
            do_log($msg, 1);

            continue;
        }

        // key seems fine
        $globalDb->query("UPDATE corp_api_keys SET access_mask=$access_mask, state = 0 WHERE keyid = $key_id ");
    }
}




function update_corp_sheet()
{
	global $corp_time_diff, $globalDb, $SETTINGS;

    // get all corp keys with state = 0
	$sth = $globalDb->query("select a.corp_id as corp_id,a.keyid as keyid,a.vcode as vcode, " .
				"b.alliance_id as alliance_id from corp_api_keys a, corporations b WHERE " .
				"a.state = 0 AND a.corp_id=b.corp_id AND timestampdiff(minute,last_checked,now()) >= $corp_time_diff");



	while($result=$sth->fetch_array())
    {
		$alliance_id=intval($result['alliance_id']);
		$corp_id=intval($result['corp_id']);
		$key_id=$result['keyid'];
		$vcode=decrypt_vcode($result['vcode']);


		$corp_sheet_result = api_get_full_corp_sheet($key_id, $vcode);
		$corp_xml = simplexml_load_string($corp_sheet_result['data']);

		$ceo_id = intval($corp_xml->result->ceoID);

		$globalDb->query("UPDATE corporations SET ceo = $ceo_id WHERE corp_id = $corp_id ");



		// get asset and wallet divisions
		$asset_divisions=$corp_xml->result->rowset[0];
		$wallet_divisions=$corp_xml->result->rowset[1];

		foreach($asset_divisions as $asset_row)
		{
			$row = $asset_row->attributes();
			$name = $globalDb->real_escape_string($row['description']);
			$key  = intval($row['accountKey']);

			$sql = "INSERT INTO asset_division (corp_id, title, accountKey) VALUES
				($corp_id, '$name', $key) ON DUPLICATE KEY UPDATE title='$name'";

			$res = $globalDb->query($sql);

			if (!$res)
			{
				echo "Query failed: $sql\n";
			}
		}


		foreach($wallet_divisions as $wallet_row)
		{
			$row = $wallet_row->attributes();
			$name = $globalDb->real_escape_string($row['description']);
			$key  = intval($row['accountKey']);

			$sql = "INSERT INTO wallet_division (corp_id, title, accountKey) VALUES " .
				"($corp_id, '$name', $key) ON DUPLICATE KEY UPDATE
				title='$name' ";

			$res = $globalDb->query($sql);

			if (!$res)
			{
				echo "Query failed: $sql\n";
			}
		}

		$globalDb->query("UPDATE corp_api_keys SET state = 0, last_checked = now() WHERE keyid = $key_id ");

		//
	}
}



/** update the corp wallet - query Corp AccountBalance */
function update_corp_wallet()
{
	global $corp_time_diff, $globalDb, $SETTINGS;

	$sth = $globalDb->query("select a.corp_id as corp_id,a.keyid as keyid,a.vcode as vcode, " .
				"b.alliance_id as alliance_id from corp_api_keys a, corporations b WHERE " .
				"a.state = 0 AND a.corp_id=b.corp_id AND timestampdiff(minute,last_checked,now()) >= $corp_time_diff");



	while($result=$sth->fetch_array()) {
		$alliance_id=intval($result['alliance_id']);
		$corp_id=intval($result['corp_id']);
		$key_id=$result['keyid'];
		$vcode=decrypt_vcode($result['vcode']);


		$corp_wallet_result = api_get_corp_accountbalance($key_id, $vcode);

		if ($corp_wallet_result['status'] == 'OK')
		{
			$corp_xml = simplexml_load_file($corp_wallet_result['filename']);

			// result has a rowset with account ID, accountKey and balance
			$wallet_divisions=$corp_xml->result->rowset[0];
			$i = 1;
			foreach($wallet_divisions as $wallet_row)
			{
				$row = $wallet_row->attributes();
				$accountID = intval($row['accountID']);
				$accountKey = intval($row['accountKey']);
				$balance = floatval($row['balance']);
				$name = "Division $i";
				$i++;

				// update wallet_division
				$sql = "INSERT INTO wallet_division (corp_id, title, accountKey, accountID, balance) VALUES " .
					"($corp_id, '$name', $accountKey, $accountID, $balance) ON DUPLICATE KEY UPDATE
						accountID=$accountID, balance=$balance
						";

				$res = $globalDb->query($sql);

				if (!$res)
				{
					echo "Query failed: $sql\n";
				}

				// also write this into wallet division history
				$sql = "INSERT INTO wallet_division_history (corp_id, accountKey, balance) VALUES ($corp_id, $accountKey, $balance) ";

				$res = $globalDb->query($sql);

				if (!$res)
				{
					echo "Query failed: $sql\n";
				}
			}
		}
		else
		{
			// didnt work
			do_log("Error: couldnt query account balance for corp $corp_id", 1);
		}
		/*
		// get the corp WalletJournals
		$walletFields = array("date", "refID", "refTypeID", "ownerName1", "ownerID1", "ownerName2", "ownerID2", "argName1", "argID1", "amount", "balance", "reason", "taxReceiverID", "taxAmount");
		foreach (range(1000, 1006) as $accountKey) {
			$corp_walletJournal_result = api_get_corp_walletjournal($key_id, $vcode, $accountKey);

			if ($corp_walletJournal_result['status'] == 'OK') {
				$xml = simplexml_load_file($corp_walletJournal_result['filename']);
				foreach ($xml->result->rowset->row as $row) {
					$tmpArray = array();
					foreach ($walletFields as $field) {
						$tmpArray[] = $globalDb->real_escape_string((String) $row[$field]);
					}
					$qry = "INSERT IGNORE INTO `corp_walletJournal`
							(`corporationID`, `accountKey`, `" . implode("`, `", $walletFields) . "`)
							VALUES
							('$corp_id', '$accountKey', '" . implode("', '", $tmpArray) . "')";
					$globalDb->query($qry);
				}
			} else {
				// didnt work
				do_log("Error: couldnt query wallet journal $accountKey for corp $corp_id", 1);
			}
		}
		*/
	}
}




/**
 * Update Corporation Starbase List (not the fittings/assets, just the basic stats),
 * corp/StarbaseList.xml.aspx, cache timer: 6 hours
 */
function update_corp_starbases()
{
	global $corp_time_diff, $globalDb;

	$sth = $globalDb->query("select a.corp_id, a.keyid, a.vcode,
				b.alliance_id as alliance_id, a.last_sb_update
				FROM corp_api_keys a, corporations b WHERE
				a.state = 0 AND a.corp_id=b.corp_id");
	if (!$sth)
	{
		echo $globalDb->error;
		echo "\nFailed to query database...\n";
		return false;
	}

	// go over all corp api keys that we need to query
	while($result=$sth->fetch_array())
	{
		$alliance_id=intval($result['alliance_id']);
		$corp_id=intval($result['corp_id']);
		$key_id=$result['keyid'];
		$vcode=decrypt_vcode($result['vcode']);

		$starbase_result = get_starbase_list($key_id, $vcode);
		$starbase_xml = simplexml_load_string($starbase_result['data']);

		if ($starbase_xml)
		{
			echo "Updating starbases for corp $corp_id\n";
			$rows = $starbase_xml->result->rowset[0];

			$globalDb->query("update starbases SET state=1 WHERE corp_id = $corp_id ");

			$cnt = 0;

			foreach ($rows as $row)
			{
				$itemID = $row['itemID'];
				$typeID = $row['typeID'];
				$locationID = $row['locationID'];
				$moonID = $row['moonID'];
				$pos_state = $row['state'];
				$stateTimestamp = $row['stateTimestamp'];
				$onlineTimestamp = $row['onlineTimestamp'];
				$standingOwnerID = $row['standingOwnerID'];

				// also get fuel blocks from other API
				$block_status = getTowerFuelStatus($globalDb, $corp_id, $locationID, $itemID, 'Fuel Block')[0];
				$stront_status = getTowerFuelStatus($globalDb, $corp_id, $locationID, $itemID, 'Stront')[0];

				$cnt++;

				do_log("Starbase($cnt): itemID=$itemID, typeID=$typeID, pos_state=$pos_state, fuelblock=$block_status, stront=$stront_status", 1);

				$sql = "INSERT INTO starbases

					(corp_id, itemID, typeID, locationID, pos_state, stateTimestamp, onlineTimestamp,
					standingOwnerID, moonID, state, last_sb_update) VALUES " .
						"($corp_id, $itemID, $typeID, $locationID, $pos_state, '$stateTimestamp', '$onlineTimestamp', $standingOwnerID, $moonID, 0, now())

						ON DUPLICATE KEY UPDATE

						itemID = $itemID, pos_state = $pos_state,
						typeID = $typeID, stateTimestamp = '$stateTimestamp', onlineTimestamp = '$onlineTimestamp',
						standingOwnerID=$standingOwnerID, last_sb_update=now(),
						state=0
						";

				$insert_res = $globalDb->query($sql);
				if (!insert_res)
				{
					do_log("Error: Query failed: $sql", 0);
					echo "Error: Query failed: $sql\n";
				} else {
					// it worked, so add this into starbase log
					$sql2 = "INSERT INTO starbase_log
					(locationID, moonID, corp_id, state, stateTimestamp, onlineTimestamp, fuel_status, stront_status)
					VALUES
					($locationID, $moonID, $corp_id, $pos_state, '$stateTimestamp', '$onlineTimestamp', $block_status, $stront_status) ";

					if (!$globalDb->query($sql2))
					{
						do_log("Error: Query failed: $sql2", 0);
						echo "Error: Query failed: $sql2\n";
					}
				}

			}
			echo "Updated $cnt starbases\n";
			$globalDb->query("update starbases SET state=99 WHERE state=1 AND corp_id = $corp_id ");
			$globalDb->query("update corp_api_keys SET last_sb_update=now() WHERE corp_id = $corp_id ");

		}
		else
		{
			echo "Error, could not update starbases for corp $corp_id\n";
			do_log("Error, couldn't load starbase xml for corp_id $corp_id", 1);
		}
	}
}



/** import_corp_members() calls MemberTracking.xml.aspx via corp api key,
	and compares/updates the database table corp_members */
function import_corp_members()
{
	global $corp_time_diff;

	do_log("Entered import_corp_members",5);
	$db = connectToDB();


	// get all corp api keys
	$sth = $db->query("select a.corp_id as corp_id,a.keyid as keyid,a.vcode as vcode, " .
				"b.alliance_id as alliance_id from corp_api_keys a, corporations b WHERE " .
				"a.state = 0 AND a.corp_id=b.corp_id AND (last_checked is NULL OR timestampdiff(minute,last_checked,now()) >= $corp_time_diff) ");


	// iterate over corp api keys
	while ($result=$sth->fetch_array())
	{
		$alliance_id=intval($result['alliance_id']);
		$corp_id=intval($result['corp_id']);
		$key_id=$result['keyid'];
		$vcode=decrypt_vcode($result['vcode']);


		$db->query("update corporations set state=1 WHERE corp_id='$corp_id'");

		do_log("Quering Corp Api Key with ID $key_id", 5);
		$res2 = api_get_corp_members($corp_id, $key_id, $vcode);
		$membertrackingxml = simplexml_load_string($res2["data"]);

		// check if data is actually valid, if not, corp api key needs to be flagged
		if ($membertrackingxml && $membertrackingxml->result)
		{
			do_log("Extracting corp members from api and database...", 5);
			// extract the member list
			$api_members = get_all_members_from_apixml($membertrackingxml, $alliance_id, $corp_id);
			$db_members =  get_all_members_from_db($corp_id);

			do_log("api_members count=" . count($api_members), 5);
			do_log("db_members count=" . count($db_members), 5);

			// now find the differences
			// part 1: find members in $db_members, that are no longer in $api_members
			// part 2: find members in $api_members, that are no longer in $db_members

			do_log("Finding the difference...", 5);
			// using array_diff_assoc, see http://www.php.net/manual/en/function.array-diff-assoc.php
			// array_diff_assoc($arr1, $arr2) returns everything from $arr1 that was not in $arr2
			$diff1    = array_diff_assoc($db_members, $api_members);
			$diff2    = array_diff_assoc($api_members, $db_members);

			$diff1_str = print_r($diff1, true);
			$diff2_str = print_r($diff2, true);

			do_log("diff1 = members in db, but not in api", 5);
			do_log($diff1_str,5);
			do_log("diff2 = members in api, but not in db", 5);
			do_log($diff2_str,5);

			do_log("starting with part 1 now", 5);
			// part1 - members are in database (so only need to update), but no longer in api
			// means: they left the corp
			foreach ($diff1 as $id => $name)
			{
				// set state to 5 = recheck
				$sql = "UPDATE corp_members SET corp_id=0,alliance_id=0 WHERE character_id=$id ";
				$res = $db->query($sql);
				if (!$res)
					do_log("ERROR - query failed (qry1): $sql", 5);

				// insert into movement database
				$sql = "insert into corp_movement (character_id, action, current_corp) VALUES " .
						" ($id, 2, $corp_id)";
				$res = $db->query($sql);
				if (!$res)
					do_log("ERROR - query failed (qry2): $sql", 5);
			}


			do_log("starting with part 2 now", 5);
			// part2 - members are in api, but not in database or at least not affiliated with the corp
			// means: they joined the corp
			foreach ($diff2 as $id => $name)
			{
				// insert into movement database
				$sql = "insert into corp_movement (character_id, action, current_corp) VALUES " .
						" ($id, 1, $corp_id)";
				$res = $db->query($sql);
				if (!$res)
					do_log("ERROR - query failed (qry2): $sql", 1);
			}


			// set api key status to valid
			$db->query("update corporations set state=0 WHERE corp_id = '$corp_id'");
		}
		else
		{
			do_log("Corp Api Key with key_id $key_id is not valid any longer. ",9);
			// set api key status to invalid
			$db->query("update corporations set state=1 WHERE corp_id='$corp_id'");
		}
	}

}


?>
