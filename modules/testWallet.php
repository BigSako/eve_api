<?php
// check for corp_id
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
			base_page_header('',"Activity By Kills - Select Corporation","Activity By Kills - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=activityByKills&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');		
			
			exit;
		}		
		else if (count($director_corp_ids) == 1) 
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=activityByKills&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Activity By Kills - Select Corporation","Activity By Kills - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=activityByKills&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');			
			
			exit;
		} 
		else // if 0
		{
			echo "Not allowed.";
			exit;			
		}		
	}
	
	
	if (isset($_REQUEST['corp_id']))
		$corp_id = intval($_REQUEST['corp_id']);
	
	if ($corp_id < 1)
		$corp_id = $SETTINGS['main_corp_id'];
		
	$killboard_db = $SETTINGS['killboard_db'];




base_page_header('',"Activity by Kills","Activity by Kills");



$db = connectToDB();
$vcode = "hYw2C3Ou64BHswhCccBmQxRJeRwGdhzKkgVLrRcSHcHZhuOGNHcAn5QjVJSeSOSj";
$key_id = "2686887";
$walletFields = array("date", "refID", "refTypeID", "ownerName1", "ownerID1", "ownerName2", "ownerID2", "argName1", "argID1", "amount", "balance", "reason", "taxReceiverID", "taxAmount");
foreach (range(1000, 1006) as $accountKey) {
	// |||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||
	$fromID = 0;
	do {
		$url="https://api.eveonline.com/corp/WalletJournal.xml.aspx?keyID=$key_id&vcode=$vcode&rowCount=2560&accountKey=$accountKey";
		if ($fromID > 0) { $url .= "&fromID=$fromID"; }
		$corp_xml = getStuff($url);
		foreach ($corp_xml->result->rowset->row as $row) {
			$tmpArray = array();
			foreach ($walletFields as $field) {
				$tmpArray[] = $db->real_escape_string((String) $row[$field]);
			}
			$qry = "INSERT IGNORE INTO `corp_walletJournal` 
					(`corporationID`, `accountKey`, `" . implode("`, `", $walletFields) . "`) 
					VALUES 
					('$corp_id', '$accountKey', '" . implode("', '", $tmpArray) . "')";
			$res = $db->query($qry);
			if (!$res) {
				echo "<p> error! " . $db->errno . ": " . $db->error . "</p>";
				echo "<p> $qry </p>";
				die();
			}
		}
		$res = $db->query("SELECT MIN(`refID`) FROM `corp_walletJournal` WHERE `corporationID` = '$corp_id' and `accountKey` = '$accountKey'");
			if (!$res) {
				echo "<p> error! " . $db->errno . ": " . $db->error . "</p>";
				echo "<p> $qry </p>";
				die();
			}
		$row = $res->fetch_array();
		$fromID = $row[0];
	} while (count($corp_xml->result->rowset->row) == 2560);
}

base_page_footer('1','');

function getStuff($url) {
	$curl = curl_init($url); 
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($curl,CURLOPT_TIMEOUT,10);
	curl_setopt($curl,CURLOPT_ENCODING , "gzip");
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	$retval =  simplexml_load_string(curl_exec($curl));
	curl_close($curl);
	return $retval;
}


?>