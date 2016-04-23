<?php

$profit = floatval($SETTINGS["shop_profit"]); // 10 %
$isk_per_m = 330;


if (!isset($_REQUEST['page']))
	exit;
if (!isset($_REQUEST['order_id']))
	exit;
$order_id = intval($_REQUEST['order_id']);
$page = $_REQUEST['page'];

$user_id = $GLOBALS['userid'];

$db = connectToDB();

$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated, a.character_id, a.character_name, o.last_updated_by
FROM shopping_order o, auth_users u, api_characters a
WHERE o.user_id = u.user_id AND o.order_id = $order_id AND o.character_id = a.character_id
ORDER BY o.created ASC";

$res = $db->query($sql);

if ($res->num_rows != 1)
	exit;

$order_row = $res->fetch_array();


switch ($page)
{
	case 'show_order':
		base_page_header('', "Showing order with id $order_id", "Showing order with id $order_id");
		echo "<a href=\"api.php?action=shop_admin\">Back</a>";
	
		if ($order_row['state'] == 0)
		{
			echo" | 
	<a href=\"api.php?action=shop_admin2&page=accept_order&order_id=$order_id\">Accept Order</a> | 
	<a href=\"api.php?action=shop_admin2&page=deny_order&order_id=$order_id\">Deny Order***</a>";
		} else {
			echo " | Set state to: 
			<a href=\"api.php?action=shop_admin2&page=start_deliver&order_id=$order_id\">Delivery</a>
	| <a href=\"api.php?action=shop_admin2&page=contract_order&order_id=$order_id\">Contracted</a>
	| <a href=\"api.php?action=shop_admin2&page=done_order&order_id=$order_id\">Paid</a>
	| <a href=\"api.php?action=shop_admin2&page=failed_order&order_id=$order_id\">Failed</a>";
		}


		
		echo "<br /> <br />";
		
		echo "ID: " . $order_id . "<br />State: "
			. return_order_state($order_row['state']);
		echo "<br />Created at: "
			. $order_row['created'];
		echo "<br />Assigned to: ";
		$last_updated_by = $order_row['last_updated_by'];
		if ($last_updated_by == 0)
			echo "None";
		else
		{
			$sql3 = "SELECT user_name FROM auth_users WHERE user_id = $last_updated_by";
			$res3 = $db->query($sql3);
			$row3 = $res3->fetch_array();
			echo $row3['user_name'];
		}
		echo "<br />Delivery planed to: ";

		if (EVE_IGB && EVE_TRUSTED)
		{
			echo "<a onclick=\"CCPEVE.showInfo(1377, " . $order_row['character_id'] . ")\">" .  $order_row['character_name']. "</a>";
		}
		else if (EVE_IGB) // not trusted
		{
			echo "<a onclick=\"CCPEVE.showInfo(1377, " . $order_row['character_id'] . ")\">" .  $order_row['character_name']. "</a>";
		}
		
		echo " <input type=\"text\" readonly=\"true\" value=\"" . $order_row['character_name'] . "\" />";

		echo "<br />Last Updated: " 
			. $order_row['last_updated'] . "<br />";
		
echo "<hr>";
			// display contents (same as cart)
			$sql2 = "SELECT c.cart_id, i.typeName, i.typeID, i.mass, i.volume, i.published, 
			i.marketGroupID, c.amount, c.actual_price
			FROM eve_staticdata.invTypes i, shopping_order_cart c
			WHERE c.typeID = i.typeID AND c.order_id = $order_id";
			$res2 = $db->query($sql2);
			
			echo "<table style=\"width: 100%\"><th class=\"table_header\">Icon</th>
				<th class=\"table_header\">Name</th>
				<th class=\"table_header\">Estimated Price</th>
				<th class=\"table_header\">Volume</th>
				<th class=\"table_header\"></th></tr>";
			
			$total_total = 0.0;
			$total_volume = 0.0;
			
			while ($row = $res2->fetch_array())
			{
				echo "<tr><td><img src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\" /></td>";
				echo "<td>
				<a href=\"https://wiki.eveonline.com/en/wiki/" . $row['typeName'] . "\">
					<img src=\"images/info_button.png\" /> </a> " . $row['amount'] . "x " . $row['typeName'] . " </td>";
				$price = request_price($row['typeID']);
				$price = $price['sell'] * $profit;
				
				$total = $price * $row['amount'];
				
				// check if item was already bought
				if (!is_null($row['actual_price']))
				{
					$price = $row['actual_price']  * $profit;
					$total = $price * $row['amount'];
				}
				
				$total_total += $total;
				$estimated_total_str = number_format($total, 2, '.', ',');
				
				echo "<td style=\"text-align: right\">
				$estimated_total_str ISK
				</td>";
				
				$real_volume = getShipSize($row['typeID']);
				if ($real_volume == 0)			
					$volume = $row['volume'] * $row['amount'];
				else if ($real_volume > 0)
					$volume = $real_volume * $row['amount'];
				else
					$volume = $row['volume'] * $row['amount'];
				
				$total_volume += $volume;
				
				echo "<td style=\"text-align: right\">$volume m3</td>";
				echo "<td></td>";
				echo "</tr>";
		
			}
			
			
			$estimated_total_str = number_format($total_total, 2, '.', ',');

			echo "<tr><td colspan=\"2\"><b>Sum</b>:</td><td style=\"text-align: right\">
					$estimated_total_str ISK*</td>
			<td style=\"text-align: right\">$total_volume m3</td><td>&nbsp;</td></tr>";
			
			$volume_price = $total_volume * $isk_per_m;
			$shipping_fee = number_format($volume_price, 2, '.', ',');

			echo "<tr><td colspan=\"2\"><b>Shipping</b>:</td><td style=\"text-align: right\">
					$shipping_fee ISK**</td>
			<td>&nbsp;</td><td>&nbsp;</td></tr>";
			
			$total_total += $volume_price;
			
			
			$estimated_total_str = number_format($total_total, 2, '.', ',');

			echo "<tr><td colspan=\"2\"><b>TOTAL</b>:</td><td style=\"text-align: right\">
					$estimated_total_str ISK*</td>
			<td>&nbsp;</td><td>&nbsp;</td></tr>";
				
			
			echo "</table>";





		
		base_page_footer('','');
		break;
	case 'accept_order':
		$sql = "UPDATE shopping_order 
		SET state=1, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state=0";
		
		$db->query($sql);
				
		// notify the user
		$sql2 = "SELECT c.user_id
			FROM shopping_order_cart c
			WHERE c.order_id = $order_id";
		$res2 = $db->query($sql2);
		$row2 = $res2->fetch_array();
		
		addTelegramNotificationForUserId($row2['user_id'], "Your order with ID $order_id has been accepted! We will notify you once it has been delivered!");
		
		header('Location: api.php?action=shop_admin');
		
		break;
	case 'deny_order':
		$sql = "UPDATE shopping_order 
		SET state=5, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state=0";
		
		$db->query($sql);
		
		// notify the user
		$sql2 = "SELECT c.user_id
			FROM shopping_order_cart c
			WHERE c.order_id = $order_id";
		$res2 = $db->query($sql2);
		$row2 = $res2->fetch_array();
		
		addTelegramNotificationForUserId($row2['user_id'], "Your order with ID $order_id has been denied!");		
		
		header('Location: api.php?action=shop_admin');
		break;
	case 'start_deliver':
		$sql = "UPDATE shopping_order 
		SET state=2, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state<>0 AND state <>4";
		
		$db->query($sql);
		header('Location: api.php?action=shop_admin');
		break;
	case 'contract_order':
		$sql = "UPDATE shopping_order 
		SET state=3, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state<>0 AND state <>4";
		
		$db->query($sql);
		
		// notify the user
		$sql2 = "SELECT c.user_id
			FROM shopping_order_cart c
			WHERE c.order_id = $order_id";
		$res2 = $db->query($sql2);
		$row2 = $res2->fetch_array();
		
		addTelegramNotificationForUserId($row2['user_id'], "Your order with ID $order_id has been contracted to you!");
			
		header('Location: api.php?action=shop_admin');
		break;
	case 'done_order':
		$sql = "UPDATE shopping_order 
		SET state=4, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state<>0 AND state <>4";
		
		$db->query($sql);
		header('Location: api.php?action=shop_admin');
		break;
	case 'failed_order':
		$sql = "UPDATE shopping_order 
		SET state=6, last_updated=NOW(), 
		last_updated_by=$user_id WHERE order_id = $order_id AND state<>0";
		
		$db->query($sql);
		header('Location: api.php?action=shop_admin');
		break;
	
}




?>
