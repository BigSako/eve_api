<?php


// request price - default regionID = The Forge (= Jita)
function request_price($typeId, $regionID=10000002)
{
	$typeId = intval($typeId);
	$regionID = intval($regionID);
	
	$db = connectToDb();
	
	// http://api.eve-central.com/api/marketstat?typeid=34&regionlimit=10000002
	$sql = "SELECT buy, sell, last_update FROM prices WHERE type_id = $typeId AND region_id=$regionID";
	
	$res = $db->query($sql);
	
	if ($res->num_rows == 0)
	{
		// add request
		$sql = "INSERT INTO prices (type_id, region_id, buy, sell, last_update) VALUES ($typeId, $regionID, 0, 0, NULL) ";
		$db->query($sql);
		
		return array("last_update" => 0, "buy" => 0, "sell" => 0);
	} else {
		$row = $res->fetch_array();
		
		return array("last_update" => $row['last_update'], "buy" => $row['buy'], "sell" => $row['sell']);
	}
	
	
}


// request price - default regionID = The Forge (= Jita)
function request_price_from_api($typeId, $regionID=10000002)
{
	$typeId = intval($typeId);
	$regionID = intval($regionID);
	
	$db = connectToDb();
	
	
	
	$url = "http://api.eve-central.com/api/marketstat?typeid=$typeId&regionlimit=$regionID";
	$filename = TMPDIR . "price_region_" . $typeId . "_" . $regionID . ".xml";
	
	echo "... accessing $url";
	
	$result = getAPIUrl($url, $filename);
	
	if ($result['status'] == 'OK')
	{
		echo "... received data ...";
		$xml = simplexml_load_file($result['filename']);
		if ($xml && $xml->marketstat)
		{
			$buy = floatval($xml->marketstat->type->buy->max);
			$sell = floatval($xml->marketstat->type->sell->min);
			
			echo "buy = $buy, sell=$sell";
			
			$sql = "INSERT INTO prices (type_id, region_id, buy, sell, last_update) VALUES ($typeId, $regionID, $buy, $sell, NOW()) 
				on duplicate key update
					buy=$buy, sell=$sell, last_update = now()
			";
			$res = $db->query($sql);
			if (!$res)
			{
				echo "ERROR: $sql failed\n";
			}
		}
	}
	
	echo "\n";	
}


function bulk_request_price_from_api($typeIds, $regionID=10000002)
{
	
	$regionID = intval($regionID);
	
	$db = connectToDb();
	
	
	
	$url = "http://api.eve-central.com/api/marketstat?typeid=$typeIds&regionlimit=$regionID";
	$filename = TMPDIR . "price_region_" . md5($typeIds) . "_" . $regionID . ".xml";
	
	//echo "... accessing $url";
	
	$result = getAPIUrl($url, $filename);
	
	if ($result['status'] == 'OK')
	{
		$xml = simplexml_load_file($result['filename']);

		if ($xml && $xml->marketstat)
		{
			foreach ($xml->marketstat->type as $typeInfo)
			{
				$buy = floatval($typeInfo->buy->max);
				$sell = floatval($typeInfo->sell->min);
				$typeId = $typeInfo['id'];

				$sql = "INSERT INTO prices (type_id, region_id, buy, sell, last_update) VALUES ($typeId, $regionID, $buy, $sell, NOW()) 
					on duplicate key update
						buy=$buy, sell=$sell, last_update = now()

				";
				$res = $db->query($sql);
				if (!$res)
				{
					echo "typeid = $typeId, buy = $buy, sell=$sell\n";
					echo "ERROR: $sql failed\n";
				}
			}
		}
	}
	
	echo "\n";	
}




function get_avatar($character_id)
{
	// this is usually the image URL http://image.eveonline.com/Character/826032321_128.jpg

	$outfile=TMPDIR.$character_id."_256.jpg";
	$outfile2=TMPDIR.$character_id."_200.jpg";
	$outfile3=TMPDIR.$character_id."_100.jpg";
	if(file_exists($outfile)) {
		unlink($outfile);
	}
	if(file_exists($outfile2)) {
		unlink($outfile2);
	}
	if(file_exists($outfile3)) {
		unlink($outfile3);
	}
	$url="http://imageserver.eveonline.com/Character/".$character_id."_256.jpg";
	do_log("Getting $url",8);
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,10);
	file_put_contents($outfile,curl_exec($curl), LOCK_EX );
	curl_close($curl);
	
	resize_image($outfile,$outfile2,200,200);
	resize_image($outfile,$outfile3,100,100);
	
}


/* no longer needed


function import_corp_sheet($corp_id)
{
	api_get_corporation_sheet($corp_id);
	$corpxml = simplexml_load_file(TMPDIR.$corp_id."_CorporationSheet.xml.aspx");
	$corporation_name=preg_replace("/'/","\\'",$corpxml->result->corporationName);
	$corporation_ticker=preg_replace("/'/","\\'",$corpxml->result->ticker);
	$corporation_ceo=$corpxml->result->ceoID;
	do_log("Corp Name: $corporation_name ($corporation_ticker)",1);
	db_action("update corporations set corp_name='$corporation_name', corp_ticker='$corporation_ticker', ceo='$corporation_ceo' where corp_id='$corp_id'");
}
*/


// https://api.eveonline.com/eve/ConquerableStationList.xml.aspx
function api_get_conq_stations()
{
	$url="https://api.eveonline.com/eve/ConquerableStationList.xml.aspx";

	$result = getAPIUrlInMemory($url);

	return $result;
}


function api_get_alliance_list()
{
	$url="https://api.eveonline.com/eve/AllianceList.xml.aspx";
	$result=getAPIUrlInMemory($url);
	
	return $result;
}



/**
 * Gets the EXTENDED corp member tracking list https://api.eveonline.com/corp/MemberTracking.xml.aspx
*/
function api_get_corp_members($corp_id,$key_id,$vcode)
{
	do_log("Entered api_get_corp_members",6);

	$url="https://api.eveonline.com/corp/MemberTracking.xml.aspx?keyID=$key_id&vcode=$vcode&extended=1";
    $result=getAPIUrlInMemory($url);
	return $result;
}



/**
 * Gets the corp assets list  "https://api.eveonline.com/corp/AssetList.xml.aspx
 */
function api_get_corp_asset_list($corp_id,$key_id,$vcode)
{
	$url="https://api.eveonline.com/corp/AssetList.xml.aspx?keyID=$key_id&vcode=$vcode";
	$result=getAPIUrlInMemory($url);	
	return $result;
}


function api_get_player_asset_list($char_id,$key_id,$vcode)
{
	$url="https://api.eveonline.com/char/AssetList.xml.aspx?characterID=$char_id&keyID=$key_id&vcode=$vcode";
	$result=getAPIUrlInMemory($url);	
	return $result;
}



/**
 * gets corp assets locations (if in space) by using a POST request
 */
function api_get_corp_locations($corp_id,$key_id,$vcode,$itemIDs)
{
	$url="https://api.eveonline.com/corp/Locations.xml.aspx?keyID=$key_id&vcode=$vcode";
	
	$field = array("ids" => $itemIDs);

    $result = getAPIUrlInMemory($url,$field);

	return $result;
}



function api_get_player_asset_locations($character_id,$key_id,$vcode,$itemIDs)
{
	$url="https://api.eveonline.com/char/Locations.xml.aspx?characterID=$character_id&keyID=$key_id&vcode=$vcode&ids=$itemIDs";

    $result = getAPIUrlInMemory($url);

	return $result;
}


/** Downloads the public corporation sheet
 * @param $corp_id corporation id
 * @return array
 */
function api_get_corporation_sheet($corp_id)
{
	$url="https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationID=$corp_id";

	$result = getAPIUrlInMemory($url);

	return $result;
}


/** Downlolads the corporation sheet by using a director corp api key
 * @param $api_userid
 * @param $api_vcode
 * @return array
 */
/* probably not needed?
function api_get_real_corporation_sheet($api_userid, $api_vcode)
{
	do_log("Entered api_get_corporation_sheet",5);
	$filename=TMPDIR.$api_userid."_CorporationSheet.xml.aspx";
	if(file_exists($filename)) {
		unlink($filename);
	}
	$url="https://api.eveonline.com/corp/CorporationSheet.xml.aspx?keyID=$api_userid&vcode=$api_vcode";

	$result = getAPIUrlInMemory($url);

	return $result;
}
*/


/** downloads the corporation sheet by using a director corp api key
 *
 */
function api_get_full_corp_sheet($corp_keyid, $vcode)
{
	$url="https://api.eveonline.com/corp/CorporationSheet.xml.aspx?keyID=$corp_keyid&vcode=$vcode";
	$result=getAPIUrlInmemory($url);
	return $result;
}


/** downloads the corporation starbase list (corp/StarbaseList.xml.aspx) using a director corp api key
 * 
 */
function get_starbase_list($api_userid,$api_vcode)
{
	$url="https://api.eveonline.com/corp/StarbaseList.xml.aspx?keyID=$api_userid&vcode=$api_vcode";
    $result=getAPIUrlInMemory($url);
    return $result;
}



function get_server_status()
{
	
	do_log("Entered get_server_status",5);
	$filename=TMPDIR."ServerStatus.xml.aspx";
	if(file_exists($filename)) {
		unlink($filename);
	}
	$url="https://api.eveonline.com/server/ServerStatus.xml.aspx";
	do_log("Getting $url",9);
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,10);
	file_put_contents($filename,curl_exec($curl), LOCK_EX );
	if(!curl_errno($curl))
	{
		$result['filename']=$filename;
	} else {
		$result['filename']="error";
	}
	curl_close($curl);

	
	return $result;
}


function api_get_corp_accountbalance($corp_api_id,$api_vcode)
{
	do_log("Entered api_get_corp_accountbalance",5);
	$filename=TMPDIR."$corp_api_id.AccountBalance.xml.aspx";
	if(file_exists($filename)) {
		unlink($filename);
	}
	$url="https://api.eveonline.com/corp/AccountBalance.xml.aspx?keyID=$corp_api_id&vcode=$api_vcode";
	
	$result=getAPIUrl($url, $filename);
	return $result;
}

function api_get_corp_walletjournal($corp_api_id,$api_vcode,$accountKey)
{
	do_log("Entered api_get_walletjournal",5);
	$filename=TMPDIR."$corp_api_id.WalletJournal.xml.aspx";
	if(file_exists($filename)) {
		unlink($filename);
	}
	$url="https://api.eveonline.com/corp/WalletJournal.xml.aspx?keyID=$corp_api_id&vcode=$api_vcode&rowCount=2560&accountKey=$accountKey";
	
	$result=getAPIUrl($url, $filename);
	return $result;
}



function api_get_account_status($api_userid,$api_vcode)
{
	do_log("Entered api_get_character_info",5);

	$url="https://api.eveonline.com/account/AccountStatus.xml.aspx?keyID=$api_userid&vcode=$api_vcode";
	
	$result=getAPIUrlInMemory($url);
	return $result;
}


/** Fetches the sovereignty list from https://api.eveonline.com/map/Sovereignty.xml.aspx
 * @return array
 */
function api_get_sovereignty()
{
	$url="https://api.eveonline.com/map/Sovereignty.xml.aspx";
	
	$result=getAPIUrlInMemory($url);
	return $result;

}


/** Fetches the skill tree from https://api.eveonline.com/eve/SkillTree.xml.aspx
 * @return array
 */
function api_get_skilltree()
{
	$url = "https://api.eveonline.com/eve/SkillTree.xml.aspx";
	
	$result = getAPIUrlInMemory($url);
	return $result;
}



function api_get_character_affiliation($character_ids)
{
	$filename=TMPDIR."CharacterAffiliation.xml.aspx";
	
	if (file_exists($filename)) {
		unlink($filename);
	}
	$url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx";
	
	$field = array("ids" => $character_ids);
    // use a post request here as it allows us to use more character ids
	
	$result = getAPIUrlPOST($url, $filename, $field);
	return $result;
}



function getAPIUrlPOST($url, $filename, $dataArray)
{
	do_log("Getting API $url",9);
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,30);
	curl_setopt($curl,CURLOPT_ENCODING , "gzip");
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dataArray));
	
	file_put_contents($filename,curl_exec($curl), LOCK_EX );
	
	$result = array();
	
	$result['unauthorized'] = true;
	
	// check for error
	if(!curl_errno($curl))
	{
		$result['status'] = "OK";
		$result['filename']=$filename;
		$result['unauthorized'] = false;
		
	} else // an error occured, check for it
	{
		// get info
		$info = curl_getinfo($curl);
		
		if (empty($info['http_code'])) 
		{
			$result['status'] = "Unknown Error";
			$result['errorcode'] = 404;
		} else 
		{
			$code = $info['http_code'];
			$result['errorcode'] = $code;
			switch ($code)
			{
				case 200:
					break;
				case 400:
					$result['status'] = "Bad request";
					break;
				case 404:
					$result['status'] = "Error - Not found";
					$result['unauthorized'] = false;
					break;
				case 401:
					$result['status'] = "Unauthorized";
					$result['unauthorized'] = true;
					break;
				case 403:
					$result['status'] = "Forbidden";
					$result['unauthorized'] = true;
					break;
				case 404:
					$result['status'] = "Not Found";
					$result['unauthorized'] = false;
					break;
				case 500:
					$result['status'] = "Internal Error";
					$result['unauthorized'] = false;
					break;
				case 503:
					$result['status'] = "Service unavailable";
					$result['unauthorized'] = false;
					break;
				case 520:
					$result['status'] = "Unknown error/Service unavailable";
					$result['unauthorized'] = false;
					break;
				default:
					$result['status'] = "Unknown error";
					break;
			}
		}
		
		$result['filename']="";
	}
	curl_close($curl);	
	
	
	// LOG result
	if ($result['status'] != 'OK')
	{
		echo "CRON: Error accessing API data ($filename) - " . $result['status'] . "\n";
		do_log("CRON: Error accessing API data ($filename) - " . $result['status'], 1);
	}
	
	return $result;
}



/** performs an API Call (e.g., CCP EvE API) via CURL and stores the result into a file
 * @param $url the url to call
 * @param $filename the filename where the result should be stored in
 * @return array an array containing the following fields: unauthorized (boolean), status (e.g., OK), errorcode (int)
 */
function getAPIUrl($url, $filename)
{
	global $total_api_calls, $total_failed_api_calls;

	$total_api_calls += 1;

	do_log("Getting API $url",9);
	$curl = curl_init($url); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl, CURLOPT_TIMEOUT,30);
	curl_setopt($curl, CURLOPT_ENCODING , "gzip");
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Hosted by s4ko88 (+s4ko88 AT gmail DOT com');

	file_put_contents($filename,curl_exec($curl), LOCK_EX );
	
	$result = array();
	
	$result['unauthorized'] = true;
	
	// check for error
	if(!curl_errno($curl))
	{
		$result['status'] = "OK";
		$result['filename']=$filename;
		$result['unauthorized'] = false;
		
	} else // an error occured, check for it
	{
		// get info
		$info = curl_getinfo($curl);
		
		if (empty($info['http_code'])) 
		{
			$result['status'] = "Unknown Error";
			$result['errorcode'] = 404;
		} else 
		{
			$code = $info['http_code'];
			$result['errorcode'] = $code;
			switch ($code)
			{
				case 200:
					break;
				case 400:
					$result['status'] = "Bad request";
					break;
				case 404:
					$result['status'] = "Error - Not found";
					$result['unauthorized'] = false;
					break;
				case 401:
					$result['status'] = "Unauthorized";
					$result['unauthorized'] = true;
					break;
				case 403:
					$result['status'] = "Forbidden";
					$result['unauthorized'] = true;
					break;
				case 404:
					$result['status'] = "Not Found";
					$result['unauthorized'] = false;
					break;
				case 500:
					$result['status'] = "Internal Error";
					$result['unauthorized'] = false;
					break;
				case 503:
					$result['status'] = "Service unavailable";
					$result['unauthorized'] = false;
					break;
				case 520:
					$result['status'] = "Unknown error/Service unavailable";
					$result['unauthorized'] = false;
					break;
				default:
					$result['status'] = "Unknown error";
					break;
			}
		}
		
		$result['filename']="";
	}
	curl_close($curl);	
	
	
	// LOG result
	if ($result['status'] != 'OK')
	{
		echo "CRON: Error accessing API data ($filename) - " . $result['status'] . "\n";
		do_log("CRON: Error accessing API data ($filename) - " . $result['status'], 1);
		$total_failed_api_calls += 1;
	}
	
	return $result;
}


$curl_global = 0;


/** performs an API Call (e.g., CCP EvE API) via CURL and stores the result in memory
 * @param $url the url to call
 * @return array an array containing the following fields: unauthorized (boolean), status (e.g., OK), errorcode (int), data (the result)
 */
function getAPIUrlInMemory($url,$postData="")
{
    global $total_api_calls, $total_failed_api_calls, $curl_global;

    $total_api_calls += 1;

    do_log("Getting API $url",9);
    // reuse the handle if possible
    if ($curl_global == 0) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_ENCODING, "gzip");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Hosted by s4ko88 (+s4ko88 AT gmail DOT com');
        $curl_global = $curl;
    } else {
        $curl = $curl_global;
        curl_setopt($curl, CURLOPT_URL, $url);
    }
	
	if ($postData != "")
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
	else
		curl_setopt($curl, CURLOPT_POSTFIELDS, "");
	

    $data = curl_exec($curl);

    $result = array();

    $result['filename'] = 'getAPIUrlInMemory Legacy Filename, not use';

    $result['unauthorized'] = true;
    $result['data'] = $data;

    // check for error
    if(!curl_errno($curl))
    {
        $result['status'] = "OK";
        $result['unauthorized'] = false;

    } else // an error occured, check for it
    {
        // get info
        $info = curl_getinfo($curl);

        if (empty($info['http_code']))
        {
            $result['status'] = "Unknown Error";
            $result['errorcode'] = 404;
        } else
        {
            $code = $info['http_code'];
            $result['errorcode'] = $code;
            switch ($code)
            {
                case 200:
                    break;
                case 400:
                    $result['status'] = "Bad request";
                    break;
                case 404:
                    $result['status'] = "Error - Not found";
                    $result['unauthorized'] = false;
                    break;
                case 401:
                    $result['status'] = "Unauthorized";
                    $result['unauthorized'] = true;
                    break;
                case 403:
                    $result['status'] = "Forbidden";
                    $result['unauthorized'] = true;
                    break;
                case 404:
                    $result['status'] = "Not Found";
                    $result['unauthorized'] = false;
                    break;
                case 500:
                    $result['status'] = "Internal Error";
                    $result['unauthorized'] = false;
                    break;
                case 503:
                    $result['status'] = "Service unavailable";
                    $result['unauthorized'] = false;
                    break;
                case 520:
                    $result['status'] = "Unknown error/Service unavailable";
                    $result['unauthorized'] = false;
                    break;
                default:
                    $result['status'] = "Unknown error";
                    break;
            }
        }
    }
    //curl_close($curl);


    // LOG result
    if ($result['status'] != 'OK')
    {
        echo "CRON: Error accessing API data $url - " . $result['status'] . "\n";
        do_log("CRON: Error accessing API data $url - " . $result['status'], 1);
        $total_failed_api_calls += 1;
    }

    return $result;
}



/***************************************************************
** API CALL: https://api.eveonline.com/account/APIKeyInfo.xml.aspx?keyID=$api_userid&vcode=$api_vcode
** returns array $result with fields status, context, mask and the actual xml 
*/
function api_get_key_permissions($api_userid,$api_vcode)
{
	do_log("Entered api_get_key_permissions",8);
	$filename=TMPDIR."$api_userid.APIKeyInfo.xml.aspx";
	if(file_exists($filename)) {
		unlink($filename);
	}
	$url="https://api.eveonline.com/account/APIKeyInfo.xml.aspx?keyID=$api_userid&vcode=$api_vcode";
	
	$result = getAPIUrl($url, $filename);
	if ($result['status'] == 'OK')
	{
		$apikeyxml = simplexml_load_file($result['filename']);
		
		if ($apikeyxml->error)
		{
			do_log("ERROR: api_get_key_permissions returned an error-code for $filename: " . $apikeyxml->error['code'] . ";", 1);
			
			if ( $apikeyxml->error['code'] == 222)
			{
				// key has expired 
				$result['context']="";
				$result['mask']="";
				$result['filename']="";
				$result['status'] = "Expired";
				$result['unauthorized'] = true;
				$result['errorcode'] = 222;
			} else if ( $apikeyxml->error['code'] == 203)
			{
				// key is invalid 
				$result['context']="";
				$result['mask']="";
				$result['filename']="";
				$result['status'] = "Authentication Failure";
				$result['unauthorized'] = true;
				$result['errorcode'] = 203;
			} else if ( $apikeyxml->error['code'] == 221)
			{
				// temporary error - ccp is bad at this, they say this is something with wrong permissions, but this should never happen at this call
				$result['context']="";
				$result['mask']="";
				$result['filename']="";
				$result['status'] = "Temporary Problem";
				$result['unauthorized'] = false;
				$result['errorcode'] = 221;
				
			} else {			
				$result['status'] = 'Error: ' . $apikeyxml->error;
				$result['context']="";
				$result['mask']="";
				$result['errorcode'] = $apikeyxml->error['code'];
			}
		} else {			
			$result['context']=$apikeyxml->result->key['type'];
			$result['mask']=$apikeyxml->result->key['accessMask'];
		}
		$result['xml'] = $apikeyxml;
	} else 
	{
		$result['context']="";
		$result['mask']="";
		$result['filename']="";
	}	
	
	return $result;
}


function api_get_character_sheet($api_userid,$api_vcode,$api_character_id)
{
	do_log("Entered api_get_character_sheet",8);
	
	$url="https://api.eveonline.com/char/CharacterSheet.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}



function api_get_walletjournal($api_userid,$api_vcode,$api_character_id, $fromid)
{
	do_log("Entered api_get_walletjournal",8);
	
	// either get the entrances below fromid, or just get the main entrance
	if (isset($fromid) && $fromid != -1)
		$url="https://api.eveonline.com/char/WalletJournal.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id&fromid=$fromid";
	else
		$url="https://api.eveonline.com/char/WalletJournal.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}





function api_get_skill_in_training($api_userid,$api_vcode,$api_character_id)
{
	do_log("Entered api_get_skill_in_training",8);
	
	$url="https://api.eveonline.com/char/SkillInTraining.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}




function api_get_skill_queue($api_userid,$api_vcode,$api_character_id)
{
	do_log("Entered api_get_skill_queue",8);

	$url="https://api.eveonline.com/char/SkillQueue.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}





function api_get_character_info($api_userid,$api_vcode,$api_character_id)
{
	do_log("Entered api_get_character_info",8);

	$url="https://api.eveonline.com/eve/CharacterInfo.xml.aspx?keyID=$api_userid&vcode=$api_vcode&characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}



function api_get_public_character_info($api_character_id)
{
	do_log("Entered api_get_public_character_info",5);

	$url="https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID=$api_character_id";
	
	// call api
	$result = getAPIUrlInMemory($url);
	
	return $result;
}


?>
