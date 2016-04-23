<?php
	$db = connectToDB();
	$c_id = intval($_REQUEST['character_id']);
	$user_id = $GLOBALS['userid'];	

	// $c_id holds the character id of the character we are looking at	

	
	// before we show member information, we need to make sure that the user is allowed to look at it	
	$sth = $db->query("SELECT c.character_name, c.corp_name, c.user_id, c.state, skillpoints, 
				walletBalance, character_last_ship, character_location 
				FROM api_characters c 
				WHERE c.character_id = $c_id and c.user_id = $user_id");

	if ($sth->num_rows == 1)
	{	
		$row = $sth->fetch_array();
		$user_id = $row['user_id'];
		$name = $row['character_name'];
		$corp = $row['corp_name'];
		$skillpoints = $row['skillpoints'];
		$ship = $row['character_last_ship'];
		$balance = $row['walletBalance'];
		$location = $row['character_location'];
		
		base_page_header("","Wallet Data - $name", "Wallet Data - $name", "<a href=\"api.php?action=char_sheet&character_id=$c_id\">Back</a>");
	
			
		
		$balanceStr = number_format($balance, 2, '.', ',');
		

		$sql = "select refID, date, refTypeId, ownerName1, ownerID1, ownerName2, ownerID2, amount, reason from wallet_journal where character_id = $c_id 
AND ownerName2 <> 'Secure Commerce Commission'
ORDER BY DATE DESC";
		$res = $db->query($sql);
		echo "<table style=\"width: 95%\"><tr><th>Type</th><th>Date</th><th>From</th>
		<th>To</th><th>Amount</th><th>Reason</th></tr>";
		while ($row = $res->fetch_array())
		{
			echo "<tr><td title=\"" . return_reftypeid_longtext($row['refTypeId']). "\">" . return_reftypeid_text($row['refTypeId']) . "</td>";

			echo "<td>" . $row['date'] . "</td>";
			echo "<td>" . $row['ownerName1'] . "</td>";			
			echo "<td>" . $row['ownerName2'] . "</td>";
			

			$balanceStr = number_format($row['amount'], 2, '.', ',');

			echo "<td style=\"text-align: right\">" . $balanceStr . " ISK</td>";

			$reason = $row['reason'];

			if (strlen($reason) > 20)
			{
				$reason = substr($reason, 0, 17) . "...";
			}

			echo "<td title=\"" . $row['reason'] . "\">$reason</td>";


			echo "</tr>";
			//print_r($row);
		}
		echo "</table>";
		echo "Legend: <br /> " . return_reftypeid_text(-1);
	} else {
		echo "Not allowed";
	}


	base_page_footer('','');
?>


