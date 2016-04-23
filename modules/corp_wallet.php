<?php
	do_log("Entered human_resources",5);
	
	$corp_id = -1;
	
	// check for corp id
	if (!isset($_REQUEST['corp_id']))
	{
		// check if is admin
		if ($isAdmin == true && !isset($_REQUEST['ignore_main_corp_id']))
		{
			$corp_id = $SETTINGS['main_corp_id'];
		}  else if ($isAdmin == true && isset($_REQUEST['ignore_main_corp_id']))
		{
			// display corp selection page
			base_page_header('',"Accounting - Select Corporation","Human Resources - Select Corporation");

			
			$sql = "SELECT c.corp_name, c.corp_id FROM corporations c, corp_api_keys k WHERE c.corp_id = k.corp_id";
			$res = $db->query($sql);
				
			while ($row = $res->fetch_array())
			{				
				$corp_id = $row['corp_id'];
				echo "<li><a href=\"api.php?action=corp_wallet&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
			}
			
			echo "</ul>";
			
			
			
			base_page_footer('','');		
			
			exit;
		} else if (count($director_corp_ids) == 1) 
		{
			// only one, so we can automatically redirect
			header('Location: api.php?action=corp_wallet&corp_id=' . $director_corp_ids[0]);
			exit;
		} else if (count($director_corp_ids) > 0) // display possible corps to look at
		{
			$corp_ids = implode(',', $director_corp_ids);
			$sql = "SELECT corp_name, corp_id FROM corporations WHERE corp_id in ($corp_ids)";
			$res = $db->query($sql);

			// char is director in several corps, but only one of them is also available on services
			if ($res->num_rows == 1)
			{
				$row = $res->fetch_array();
				$corp_id = $row['corp_id'];
				header("Location: api.php?action=corp_wallet&corp_id=$corp_id");
			} else {
				// list all corps where this toon is director

				// display corp selection page
				base_page_header('',"Accounting - Select Corporation","Accounting - Select Corporation");

				echo "<ul>";

				while ($row = $res->fetch_array()) {
					$corp_id = $row['corp_id'];
					echo "<li><a href=\"api.php?action=corp_wallet&corp_id=$corp_id\">" . $row['corp_name'] . "</a></li>";
				}

				echo "</ul>";

				base_page_footer('','');
			}
			
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
	
	
	base_page_header('',"$corprow[corp_name] - Accounting","$corprow[corp_name] - Accounting");
	
	if ($isAdmin == true)
	{
		echo "<a href=\"api.php?action=corp_wallet&ignore_main_corp_id=true\">Show all corps</a><br /><br />";
	}
	

	// display current wallet data + division names
	$sql = "SELECT title, accountKey, balance FROM wallet_division WHERE corp_id = $corp_id";
	
	$res = $db->query($sql);
	
	if ($res->num_rows == 0)
	{
		echo "No data available";
	}
	else
	{
		echo <<<EOF
			<table style="width: 100%"><tr><th class="table_header">Wallet Division</th><th class="table_header">Balance</th><th class="table_header">Options</th></tr>
		
EOF;
		$total_balance = 0.0;

		while ($row = $res->fetch_array())
		{
			$title = $row['title'];
			$accountKey = $row['accountKey'];
			$balance = $row['balance'];
			$balanceStr = number_format($balance, 2, '.', ',');
			echo "<tr><td>$title</td><td align=\"right\">$balanceStr ISK</td><td>
				<a href=\"api.php?action=corp_wallet_report&corp_id=$corp_id&accountKey=$accountKey\"><img src=\"images/reporting_small.png\"></a> 
				<a href=\"api.php?action=corp_wallet_history&corp_id=$corp_id&accountKey=$accountKey\"><img src=\"images/details_small.png\"></a> W.I.P.
			</td></tr>";
			
			$total_balance += $balance;
		}
		
		$total_balance_str = number_format($total_balance, 2, '.', ',');
		
		echo "<tr><td><b>Total Balance</b></td><td align=\"right\">$total_balance_str ISK</td><td></td></tr>";
		
		echo "</table>";
		
		

	
	}
	
	
	base_page_footer('1','');
?>