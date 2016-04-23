<?php
	$db = connectToDB();
	$c_id = intval($_REQUEST['character_id']);
	$user_id = $GLOBALS['userid'];
	

	// $c_id holds the character id of the character we are looking at
	
	$left_menu =  "<br /><b>Filter:</b>
<ul>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id\">All</a></li> 
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=1\">Min: 100 Mil</a></li>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=5\">Min: 500 Mil</a></li>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=10\">Donations</a></li>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=13\">Donations Above 500 Mil</a></li>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=12\">Donations and Trades</a></li>
		<li><a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=11\">Bounty prices only</a></li>
</ul>
";
	
	base_page_header("",'Showing Member Wallet Data','Showing Member Wallet Data', $left_menu);

	
	// before we show member information, we need to make sure that the user is allowed to look at it
	if ($isAdmin)
	{
		// ok
	} else
	{
		$my_user_id = $GLOBALS['userid'];
		$sql = "SELECT c.user_id FROM api_characters c WHERE c.character_id = $c_id AND c.corp_id in (SELECT a.corp_id FROM api_characters a WHERE a.user_id = $my_user_id)";
		$sth = $db->query($sql);
		if ($sth->num_rows == 0)
		{
			echo "Not allowed<br />";
			base_page_footer('','<a href="api.php?action=human_resources">Back</a>');
			exit;
		}
	}
	
	
	$sth = $db->query("SELECT c.character_name, c.corp_name, c.user_id, c.state, skillpoints, 
				walletBalance, character_last_ship, character_location 
				FROM api_characters c 
				WHERE c.character_id = $c_id");

	if ($sth->num_rows > 0)
	{	
		$row = $sth->fetch_array();
		$user_id = $row['user_id'];
		$name = $row['character_name'];
		$corp = $row['corp_name'];
		$skillpoints = $row['skillpoints'];
		$ship = $row['character_last_ship'];
		$balance = $row['walletBalance'];
		$location = $row['character_location'];
		
		$balanceStr = number_format($balance, 2, '.', ',');
		
		echo "<h3>Details</h3>";
		echo "<table><tr><td>";
		
		if ($c_id != -1)
			echo "<img src=\"//imageserver.eveonline.com/Character/" . $c_id . "_200.jpg\">";
		else
			echo "No characters available";
			
		echo "</td><td>";
		
		echo "<h3>$name</h3>";
		echo "<table width=\"100%\"><tr><td>User Id:</td><td>$user_id</td></tr>
			<tr><td>Name:</td><td>$name</td></tr>
			<tr><td>Corporation:</td><td>$corp</td></tr>
			<tr><td>Wallet Balance:</td><td>$balanceStr ISK</td></tr>";
		echo "</table><a href=\"api.php?action=show_member&character_id=$c_id\">Back to member details</a>
		</td></tr></table>";
		echo "<hr> ";

		echo "<h3>Wallet Journal</h3>";
		echo "Filter: <a href=\"api.php?action=player_wallet_data&character_id=$c_id\">All</a> |
			<a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=1\">&gt; 100M</a> |
			<a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=5\">&gt; 500M</a> |
			<a href=\"api.php?action=player_wallet_data&character_id=$c_id&min=12\">Donations</a>";


		echo "<br />";
		

		// get wallet data
		if (isset($_REQUEST['min']))
			$min = intval($_REQUEST['min']);
		else
			$min = 0;

		if ($min == 0)
			$where = "1=1";
		else if ($min == 1)
			$where = "abs(amount) >= 100000000";
		else if ($min == 5)
			$where = "abs(amount) >= 500000000";
		else if ($min == 10)
			$where = "(refTypeId = 10 or refTypeId = 37)"; // donations + caw
		else if ($min == 12)
			$where = "(refTypeId = 10 or refTypeId = 37 or refTypeId = 1)"; // donations + caw + trade
		else if ($min == 13)
			$where = "(refTypeId = 10 or refTypeId = 37) AND abs(amount) >= 500000000";

		$sql = "select refID, date, refTypeId, ownerName1, ownerID1, ownerName2, ownerID2, amount, reason from wallet_journal where character_id = $c_id AND $where 
AND ownerName2 <> 'Secure Commerce Commission'
ORDER BY DATE DESC";
		$res = $db->query($sql);
		echo "<table style=\"width: 95%\"><tr><th>Type</th><th>Date</th><th>From</th>
		<th>To</th><th>Amount</th><th>Reason</th></tr>";
		while ($row = $res->fetch_array())
		{
			echo "<tr><td title=\"" . return_reftypeid_longtext($row['refTypeId']). "\">" . return_reftypeid_text($row['refTypeId']) . "</td>";

			echo "<td>" . $row['date'] . "</td>";
			
			// check if ownerID1 is in our database
			$sql5 = "SELECT character_name, corp_id, corp_name, user_id FROM api_characters WHERE character_id = " . $row['ownerID1'];
			$res5 = $db->query($sql5);
			if ($res5->num_rows != 0)
			{
				$row5 = $res5->fetch_array();
				// check if ALT or registered
				if ($row5['user_id'] == $user_id)
				{
					echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID1'] . "\">" . $row['ownerName1'] . "</a> (ALT)</td>";
				} else {
					echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID1'] . "\">" . $row['ownerName1'] . "</a> (REG)</td>";
				}
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
				if ($row5['user_id'] == $user_id)
				{
					echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID2'] . "\">" . $row['ownerName2'] . "</a> (ALT)</td>";
				} else {
					echo "<td><a href=\"api.php?action=show_member&character_id=" . $row['ownerID2'] . "\">" . $row['ownerName2'] . "</a> (REG)</td>";
				}
			} else {
				if ($row['refTypeId'] == 1 || $row['refTypeId'] == 10) // donations and trade: show the other pilot involved - evewho link
					echo "<td>" . $row['ownerName2'] . " (<a href=\"http://evewho.com/pilot/" . $row['ownerName2'] . "\">EveWHO</a>)</td>";
				else 
					echo "<td>" . $row['ownerName2'] . "</td>";
			}	

			$balanceStr = number_format($row['amount'], 2, '.', ',');

			echo "<td style=\"text-align: right\">" . $balanceStr . " ISK</td>";

			$reason = $row['reason'];

			if (strlen($reason) > 15)
			{
				$reason = substr($reason, 0, 13) . "...";
			}

			echo "<td title=\"" . $row['reason'] . "\">$reason</td>";


			echo "</tr>";
			//print_r($row);
		}
		echo "</table>";
		echo "Legend: <br /> " . return_reftypeid_text(-1);
	}


	base_page_footer('','');
?>


