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
			base_page_header('',"Corp WalletJournal - Select Corporation","Corp WalletJournal - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=walletByRefType&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');		
			
			exit;
		}		
		else if (count($director_corp_ids) == 1) 
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=walletByRefType&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			// display corp selection page
			base_page_header('',"Corp WalletJournal - Select Corporation","Corp WalletJournal - Select Corporation");

			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=walletByRefType&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
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




base_page_header('',"Corp WalletJournal","Corp WalletJournal");
$db = connectToDB();

$refTypes = array(
	0 => "Undefined",
	1 => "Player Trading",
	2 => "Market Transaction",
	3 => "GM Cash Transfer",
	4 => "ATM Withdraw",
	5 => "ATM Deposit",
	6 => "Backward Compatible",
	7 => "Mission Reward",
	8 => "Clone Activation",
	9 => "Inheritance",
	10 => "Player Donation",
	11 => "Corporation Payment",
	12 => "Docking Fee",
	13 => "Office Rental Fee",
	14 => "Factory Slot Rental Fee",
	15 => "Repair Bill",
	16 => "Bounty",
	17 => "Bounty Prize",
	18 => "Agents_temporary",
	19 => "Insurance",
	20 => "Mission Expiration",
	21 => "Mission Completion",
	22 => "Shares",
	23 => "Courier Mission Escrow",
	24 => "Mission Cost",
	25 => "Agent Miscellaneous",
	26 => "LP Store",
	27 => "Agent Location Services",
	28 => "Agent Donation",
	29 => "Agent Security Services",
	30 => "Agent Mission Collateral Paid",
	31 => "Agent Mission Collateral Refunded",
	32 => "Agents_preward",
	33 => "Agent Mission Reward",
	34 => "Agent Mission Time Bonus Reward",
	35 => "CSPA",
	36 => "CSPAOfflineRefund",
	37 => "Corporation Account Withdrawal",
	38 => "Corporation Dividend Payment",
	39 => "Corporation Registration Fee",
	40 => "Corporation Logo Change Cost",
	41 => "Release Of Impounded Property",
	42 => "Market Escrow",
	43 => "Agent Services Rendered",
	44 => "Market Fine Paid",
	45 => "Corporation Liquidation",
	46 => "Brokers Fee",
	47 => "Corporation Bulk Payment",
	48 => "Alliance Registration Fee",
	49 => "War Fee",
	50 => "Alliance Maintainance Fee",
	51 => "Contraband Fine",
	52 => "Clone Transfer",
	53 => "Acceleration Gate Fee",
	54 => "Transaction Tax",
	55 => "Jump Clone Installation Fee",
	56 => "Manufacturing",
	57 => "Researching Technology",
	58 => "Researching Time Productivity",
	59 => "Researching Material Productivity",
	60 => "Copying",
	61 => "Duplicating",
	62 => "Reverse Engineering",
	63 => "Contract Auction Bid",
	64 => "Contract Auction Bid Refund",
	65 => "Contract Collateral",
	66 => "Contract Reward Refund",
	67 => "Contract Auction Sold",
	68 => "Contract Reward",
	69 => "Contract Collateral Refund",
	70 => "Contract Collateral Payout",
	71 => "Contract Price",
	72 => "Contract Brokers Fee",
	73 => "Contract Sales Tax",
	74 => "Contract Deposit",
	75 => "Contract Deposit Sales Tax",
	76 => "Secure EVE Time Code Exchange",
	77 => "Contract Auction Bid (corp)",
	78 => "Contract Collateral Deposited (corp)",
	79 => "Contract Price Payment (corp)",
	80 => "Contract Brokers Fee (corp)",
	81 => "Contract Deposit (corp)",
	82 => "Contract Deposit Refund",
	83 => "Contract Reward Deposited",
	84 => "Contract Reward Deposited (corp)",
	85 => "Bounty Prizes",
	86 => "Advertisement Listing Fee",
	87 => "Medal Creation",
	88 => "Medal Issued",
	89 => "Betting",
	90 => "DNA Modification Fee",
	91 => "Sovereignty bill",
	92 => "Bounty Prize Corporation Tax",
	93 => "Agent Mission Reward Corporation Tax",
	94 => "Agent Mission Time Bonus Reward Corporation Tax",
	95 => "Upkeep adjustment fee",
	96 => "Planetary Import Tax",
	97 => "Planetary Export Tax",
	98 => "Planetary Construction",
	99 => "Corporate Reward Payout",
	100 => "Minigame Betting",
	101 => "Bounty Surcharge",
	102 => "Contract Reversal",
	103 => "Corporate Reward Tax",
	104 => "Minigame Buy-In",
	105 => "Office Upgrade Fee",
	106 => "Store Purchase",
	107 => "Store Purchase Refund",
	108 => "PLEX sold for Aurum",
	109 => "Lottery Give Away",
	110 => "Minigame Betting House Cut",
	111 => "Aurum Token exchanged for Aur"
);
if (!isset($_REQUEST["refTypeID"])) {
	$qry = "SELECT `refTypeID`, SUM(`amount`) as `sum` FROM `corp_walletJournal` WHERE 1 GROUP BY `refTypeID` ORDER BY `sum` DESC";
	$res = $db->query($qry);
	echo "<table><tr><th>Type</th><th>Amount</th></tr>";
	if ($res && ($res->num_rows > 0)) {
		while (($row = $res->fetch_array()) != NULL) {
			echo "<tr>";
			echo "<td><a href=\"api.php?action=walletByRefType&corp_id=$corp_id&refTypeID=$row[refTypeID]\">" . $refTypes[$row["refTypeID"]] . "</a></td>";
			echo "<td style=\"text-align: right;\">" . number_format($row["sum"], 0, ",", ".") . "</td>";
			echo "</tr>";
		}
	}
	echo "</table>";
} else {
	$refTypeID = $db->real_escape_string($_REQUEST["refTypeID"]);
	echo "<h2> Showing Corp WalletJournal for Type " . $refTypes[$refTypeID] . " </h2>";
	$qry = "SELECT * FROM `corp_walletJournal` WHERE `refTypeID` = '$refTypeID' ORDER BY `date` DESC";
	$res = $db->query($qry);
	if ($res) {
		echo "<table>";
		$walletFields = array("date", "ownerName1", "ownerName2", "amount", "balance", "reason", "argName1");
		$decimalFields = array("amount", "balance");
		echo "<tr><th>" . implode("</th><th>" , $walletFields) . "</th></tr>";
		while (($row = $res->fetch_array()) != NULL) {
			echo "<tr>";
			foreach ($walletFields as $field) {
				if (in_array($field, $decimalFields)) {
					echo "<td style=\"text-align: right;\">" . number_format($row[$field], 0, ",", ".") . "</td>";
				} else {
					echo "<td>" . $row[$field] . "</td>";
				}
			}
			echo "</tr>";
		}
	}
	echo "<a href=\"api.php?action=walletByRefType\">back to overview</a>";
}
base_page_footer('','');
?>