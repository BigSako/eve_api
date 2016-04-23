<?php
	do_log("Entered corp_wallet_history",5);
	
	if (!isset($_REQUEST['corp_id']))
		exit;
	
	$corp_id = intval($_REQUEST['corp_id']);
	$accountKey = intval($_REQUEST['accountKey']);
	
	if ($corp_id < 1)
		exit;
		
	if (!in_array($corp_id, $director_corp_ids) && !$isAdmin)
	{
		echo "Not allowed";
		exit;
	}
	
	
	
	$db = connectToDB();
		
	$sql = "SELECT corp_name, ceo, corp_ticker FROM corporations WHERE corp_id = $corp_id";
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		echo "invalid corp";
		exit;
	}
	
	if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
	{
		echo "Not allowed";
		exit;
	}
	
	
	$corprow = $res->fetch_array();
	
	
	base_page_header('',"$corprow[corp_name] - Accounting History","$corprow[corp_name] - Accounting History");

	// display current wallet data + division names
	$sql = "SELECT title, accountKey, balance FROM wallet_division WHERE corp_id = $corp_id AND accountKey = $accountKey";
	
	$res = $db->query($sql);
	
	if ($res->num_rows == 0)
	{
		echo "No data available";
	}
	else
	{
		$row = $res->fetch_array();	
		$title = $row['title'];
		$accountKey = $row['accountKey'];
		$balance = $row['balance'];
		$balanceStr = number_format($balance, 2, '.', ',');
		echo "<b>Account Name:</b> $title<br />
		<b>Balance:</b> $balanceStr ISK</br />";
		
		echo "<h3>History</h3>";
		
		$sql = "SELECT balance, updated FROM wallet_division_history WHERE corp_id = $corp_id and accountKey = $accountKey ORDER BY updated DESC";
		
		$res = $db->query($sql);
		
		if ($res->num_rows == 0)
			echo "No data available";
		else
		{
					echo <<<EOF
			<table style="width: 100%"><tr><th class="table_header">Date</th><th class="table_header">Balance</th><th class="table_header">Difference</th></tr>
EOF;
			$last_balance = 0.0;
			$first = true;
			while ($row = $res->fetch_array())
			{
				$date = $row['updated'];
				$balance = $row['balance'];
				
				$balanceStr = number_format($balance, 2, '.', ',');
				
				
				$balanceDiff = -($balance - $last_balance);
				$balanceDiffStr = number_format($balanceDiff, 2, '.', ',');

				if ($balanceDiff > 0.0) {
					$balanceDiffStr = "+" . $balanceDiffStr;
					$color = "green";
				} else {
					$color = "red";
				}
				
				if ($first == true)
				{
					echo "<tr><td>Current ($date)</td><td align=\"right\">$balanceStr ISK</td><td align=\"right\">&nbsp;</td></tr>";
				}
				else
				{
					if (abs($balanceDiff) > 0.01) {
						echo "<tr style=\"color: $color\"><td>$date</td><td align=\"right\">$balanceStr ISK</td><td align=\"right\">$balanceDiffStr ISK</td></tr>";
					}
				}
				
				$first = false;
				$last_balance = $balance;
			}
			
			echo "</table>";
		}

		
	

	
	}
	
	
	base_page_footer('1','');
?>