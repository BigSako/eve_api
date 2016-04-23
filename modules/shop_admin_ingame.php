<?php
$user_id = $GLOBALS['userid'];

$db = connectToDB();

// list all items that we need to buy
// means: select all orders that are set to accepted (state = 1) by us, 
// then select all items in that order

if (isset($_REQUEST['do']))
{
	if ($_REQUEST['do'] == 'setPrice')
	{
		$typeID = intval($_REQUEST['typeID']);
		$price = floatval($_REQUEST['price']);
		$oldPrice = floatval($_REQUEST['oldPrice']);
		// set price of typeID to price for all open orders of user $user_id
		$sql = "UPDATE shopping_order_cart SET actual_price = '$price' WHERE typeID = $typeID  AND (actual_price is null or actual_price = '$oldPrice')";
		//echo "'$sql'";
		$db->query($sql);
		
	}
}



// display the orders that are currently open and assigned to this user
$sql = "SELECT t.typeName, t.typeID, SUM(c.amount) as sum, AVG(c.actual_price) as actual_price
FROM shopping_order o, auth_users u, shopping_order_cart c, eve_staticdata.invTypes t
WHERE o.last_updated_by = $user_id AND o.user_id = u.user_id AND o.state = 1 AND c.order_id = o.order_id
AND t.typeID = c.typeID
GROUP BY t.typeName, t.typeID
ORDER BY t.typeName ASC";

$res = $db->query($sql);

base_page_header('', 'Shop Admin - Buy Items', 'Shop Admin - Buy Items');


$item_list = "";

if ($res->num_rows == 0)
{
	echo "You dont need to buy any items. Go <a href=\"api.php?action=shop_admin\">back</a>.";
} else {	

	echo "<a href=\"api.php?action=shop_admin\">Back</a> | <a href=\"api.php?action=shop_admin_ingame\">Refresh</a><br /><br />"; 
	echo "Click on the icon of the item to open the market buy window (only if you are using the IGB).<br />";
	if (EVE_IGB)
	{
		echo "IGB mode activated.";
		if (EVE_IGB && !EVE_TRUSTED)
		{
			$api_url = $SETTINGS['api_url'] . '/';
			echo " You should trust this website for more features. <input type=\"submit\" value=\"Trust now!\" onclick=\"CCPEVE.requestTrust('$api_url');\" />.";
		}
		echo "<br />";
	}
	echo "<table style=\"width: 100%\"><th class=\"table_header\">Icon</th>
				<th class=\"table_header\">Name</th>
				<th class=\"table_header\">Amount</th>
				<th class=\"table_header\">Price*</th>
				<th class=\"table_header\">Options</th>
				</tr>";

	$cnt = 0;
				
	while ($row = $res->fetch_array())
	{
		$name = $row['typeName'];
		$amount = $row['sum'];
		echo "<tr id=\"row$cnt\"><td>";

		$item_list .= "$amount\t$name\r\n";

		if (EVE_IGB && EVE_TRUSTED)
		{
			echo "<img onclick=\"CCPEVE.showMarketDetails(" . $row['typeID'] . ")\" src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\" />";
		}
		else if (EVE_IGB) // not trusted
		{
			echo "<img onclick=\"CCPEVE.showMarketDetails(" . $row['typeID'] . ")\" src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\" />";
		}
		else
		{
			echo "<img src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\" />";
		}

		echo "</td>";
		echo "<td>$name</td>
		<td>$amount</td>";

		// display current price and possibility to update price
		$cur_item_price = $row['actual_price'];

		echo "<td>";
		if (is_null($cur_item_price))
			echo "<b>Item ISK Value is missing!!!</b>";
		else
			echo number_format($cur_item_price, 2, '.', ',');

		echo "<form method=\"post\" action=\"api.php?action=shop_admin_ingame&do=setPrice#row$cnt\">
		<input type=\"hidden\" name=\"typeID\" value=\"" . $row['typeID'] . "\" />
		<input type=\"hidden\" name=\"oldPrice\" value=\"$cur_item_price\" />
		<input type=\"number\" name=\"price\" value=\"$cur_item_price\" /> <input type=\"submit\" value=\"Set\" />
		</form></td>";

		echo "</tr>";

		$cnt++;
	}
	
	echo "</table>";

	echo "Or use multibuy (copy paste): <br /><textarea rows=\"20\">" . $item_list . "</textarea><br />";

	echo "<br /><b>*</b>Please manually calculate the average price for the items you bought OR just take the last price you bought the item for.<br />";
}


base_page_footer('', '');


?>
