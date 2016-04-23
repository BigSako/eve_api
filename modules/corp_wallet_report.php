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
	
	$max_month = $month = date("n");
	$month_text = date("M");
	$max_year = $year = date("Y");
	
	if (isset($_REQUEST['month']) && isset($_REQUEST['year']))
	{
		$month = intval($_REQUEST['month']);
		$year = intval($_REQUEST['year']);
		$month_text = date("F", mktime(0,0,0, $month, 10));
	}
	
	
	
	$left_menu = "<a href=\"api.php?action=corp_wallet&corp_id=$corp_id\">Back</a>";
	
	if ($accountKey != 0)
	{
		$left_menu .= "<br /><a href=\"api.php?action=corp_wallet_report&corp_id=$corp_id&month=$month&year=$year\">Show all divisions</a>";
	}
	
	base_page_header('',"$corprow[corp_name] - Account Reporting","$corprow[corp_name] - Account Reporting", $left_menu);
	

	
	echo "<h3>Report for $year - $month_text</h3>";
	
	$prev_year = $year;
	$prev_month = $month-1;
	
	if ($prev_month <= 0)
	{
		$prev_month = 12;
		$prev_year--;
	}
	
	echo "<a href=\"api.php?action=corp_wallet_report&
		corp_id=$corp_id&accountKey=$accountKey&
		month=$prev_month&year=$prev_year\">Prev Month</a> ";
	
	// dont show next button if it's the current month/year
	if (! ($month == $max_month && $year == $max_year) )
	{
		$next_year = $year;
		$next_month = $month+1;
		if ($next_month > 12)
		{
			$next_month -= 12;
			$next_year += 1;
		}
		echo "| <a href=\"api.php?action=corp_wallet_report&
			corp_id=$corp_id&accountKey=$accountKey&
			month=$next_month&year=$next_year\">Next Month</a> ";
	}
	
	echo "<br />";

	
	$total_diff = 0.0;
	
	$show_total_diff = false;
	
	if ($accountKey != 0)
	{
		// display current wallet data + division names
		$sql = "SELECT title, accountKey, balance FROM wallet_division WHERE corp_id = $corp_id AND accountKey = $accountKey";
	} else
	{
		$show_total_diff = true;
		// select all accountKeys for that corp
		$sql = "SELECT title, accountKey, balance FROM wallet_division WHERE corp_id = $corp_id ORDER BY accountKey ASC";
	}
	
	$ares = $db->query($sql);
	
	if ($ares->num_rows == 0)
	{
		echo "No data available";
	}
	else
	{
		while ($row = $ares->fetch_array())
		{
			$title = $row['title'];
			$accountKey = $row['accountKey'];
			$balance = $row['balance'];
			$balanceStr = number_format($balance, 2, '.', ',');		
			
			
			// GET money for first day of month
			$sql = "SELECT balance, updated FROM 
				wallet_division_history WHERE corp_id = $corp_id and accountKey = $accountKey
				AND MONTH(updated) = $month 
				AND YEAR(updated) = $year
				ORDER BY updated ASC LIMIT 1";
			
			$res = $db->query($sql);
			
			if ($res->num_rows == 0)
			{
				echo "No data available <!--(sql='$sql')-->";
			}
			else
			{
				$row = $res->fetch_array();
				
				$start_balance = $row['balance'];
				$start_updated = $row['updated'];
				
				$start_balanceStr = number_format($start_balance, 2, '.', ',');
				
				// GET money for last day of month
				$sql = "SELECT balance, updated FROM 
					wallet_division_history WHERE corp_id = $corp_id and accountKey = $accountKey
					AND MONTH(updated) = $month 
					AND YEAR(updated) = $year
					ORDER BY updated DESC LIMIT 1";
				
				$res = $db->query($sql);
				
				if ($res->num_rows == 0)
				{
					echo "No data available <!--(sql='$sql')-->";
				} else {
					$row = $res->fetch_array();
					
					$end_balance = $row['balance'];
					$end_updated = $row['updated'];
				
					$end_balanceStr = number_format($end_balance, 2, '.', ',');
					
					
					$diff_balance = $end_balance - $start_balance;
					
					$total_diff += $diff_balance;
					
					$diff_balanceStr = number_format($diff_balance, 2, '.', ',');

					echo "
					<table>
					<tr><td><b>Account Name:</b></td><td>$title</td></tr>
					<tr><td><b>Beginning of month:</b></td><td style=\"text-align: right\"> $start_balanceStr ISK</td></tr>
					<tr><td><b>End of month:</b></td><td style=\"text-align: right\"> $end_balanceStr ISK</td></tr>
					<tr><td><b>Difference:</b></td><td style=\"text-align: right\"> $diff_balanceStr ISK</td></tr>
					</table>";
					
				}
				
			

			}
		}	
	}
	
	if ($show_total_diff == true)
	{
		$diff_balanceStr = number_format($total_diff, 2, '.', ',');
		echo "<b>Total Difference:</b> $diff_balanceStr ISK<br />";
	}
	
	
	echo "<br /><a href=\"api.php?action=corp_wallet&corp_id=$corp_id\">Back</a>";
	
	base_page_footer('','');
?>