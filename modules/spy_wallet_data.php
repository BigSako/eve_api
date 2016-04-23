<?php
	$db = connectToDB();
	

	// $c_id holds the character id of the character we are looking at
	
	
		$left_menu =  "<br /><b>Filter:</b>
<ul>
		<li><a href=\"api.php?action=spy_wallet_data&min=1\">Min: 100 Mil</a></li>
		<li><a href=\"api.php?action=spy_wallet_data&min=5\">Min: 500 Mil</a></li>
		<li><a href=\"api.php?action=spy_wallet_data&min=10\">Donations</a></li>
		<li><a href=\"api.php?action=spy_wallet_data&min=13\">Donations Above 500 Mil</a></li>
		<li><a href=\"api.php?action=spy_wallet_data&min=12\">Donations and Trades</a></li>
		<li><a href=\"api.php?action=spy_wallet_data&min=11\">Bounty prices only</a></li>
</ul>
";
	
	
	base_page_header("",'Checking Spy Wallet Data','Checking Spy Wallet Data', $left_menu);


	

		echo "<h3>Wallet Journal</h3>";
		

		// get wallet data
		$min = intval($_REQUEST['min']);
		if ($min == 0)
			$min = 10;
		
		if ($min == 1)
			$where = "abs(amount) >= 100000000";
		else if ($min == 5)
			$where = "abs(amount) >= 500000000";
		else if ($min == 10)
			$where = "(refTypeId = 10 or refTypeId = 37)";
		else if ($min == 12)
			$where = "(refTypeId = 10 or refTypeId = 37 or refTypeId = 1)";
		else if ($min == 11)
			$where = "refTypeId = 17";
		else if ($min == 13)
			$where = "(refTypeId = 10 or refTypeId = 37) AND abs(amount) >= 500000000";

		$sql = "select refID, date, refTypeId, ownerName1, ownerID1, ownerName2, ownerID2, amount, reason from wallet_journal where $where ORDER BY DATE DESC";
		$res = $db->query($sql);
		echo "<table><tr><th class=\"table_header\">RefTypeID</th><th class=\"table_header\">Date</th><th class=\"table_header\">From</th>
		<th class=\"table_header\">To</th><th class=\"table_header\">Amount</th><th class=\"table_header\">Reason</th></tr>";
		while ($row = $res->fetch_array())
		{
			echo "<tr><td>" . return_reftypeid_text($row['refTypeId']) . "</td>";

			echo "<td>" . $row['date'] . "</td>";


			
			// check if ownerID1 is in our database
			$sql5 = "SELECT character_name, corp_id, corp_name, user_id FROM api_characters WHERE character_id = " . $row['ownerID1'];
			$res5 = $db->query($sql5);
			if ($res5->num_rows != 0)
			{
				$row5 = $res5->fetch_array();
				// check if ALT or registered
				echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID1'] . "\">" . $row['ownerName1'] . "</a> (REG)</td>";
				
			} else {
				if ($row['refTypeId'] == 1 || $row['refTypeId'] == 10) // donations and trade: show the other pilot involved - evewho link
					echo "<td>" . $row['ownerName1'] . " (<a href=\"http://evewho.com/pilot/" . $row['ownerName1'] . "\">EveWHO</a>)</td>";
				else 
					echo "<td>" . $row['ownerName1'] . "</td>";
			}

			// check if ownerID2 is in our database
			$sql5 = "SELECT character_name, corp_id, corp_name, user_id FROM api_characters WHERE character_id = " . $row['ownerID2'];
			$res5 = $db->query($sql5);
			if ($res5->num_rows != 0)
			{
				$row5 = $res5->fetch_array();
				// check if ALT or registered
				echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID2'] . "\">" . $row['ownerName2'] . "</a> (REG)</td>";
				
			} else {
				if ($row['refTypeId'] == 1 || $row['refTypeId'] == 10) // donations and trade: show the other pilot involved - evewho link
					echo "<td>" . $row['ownerName2'] . " (<a href=\"http://evewho.com/pilot/" . $row['ownerName2'] . "\">EveWHO</a>)</td>";
				else 
					echo "<td>" . $row['ownerName2'] . "</td>";
			}	
			

			$balanceStr = number_format($row['amount'], 2, '.', ',');

			echo "<td style=\"text-align: right\">" . $balanceStr . " ISK</td>";

			echo "<td>" . $row['reason'] . "</td>";


			echo "</tr>";
			//print_r($row);
		}
		echo "</table>";
		echo "Legend: <br /> " . return_reftypeid_text(-1);
	


	base_page_footer('','');
?>


