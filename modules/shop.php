<?php

$profit = floatval($SETTINGS["shop_profit"]); // 10 %
$isk_per_m = 330;

if (isset($_REQUEST['page']))
	$page = $_REQUEST['page'];
else
	$page = 'main';

$user_id = $GLOBALS['userid'];
$db = connectToDB();





$amount = marketGetAmountWalletItems($user_id);



if ($page != "EFT2" && $page != "clearCart") // show header
{
	base_page_header('',"Shop", "Shop",
	"<form method=\"post\" action=\"api.php?action=shop_search\"><input placeholder=\"Search for an item by name\" type=\"text\" name=\"s\" /> </form>");
	echo "<ul class=\"vertical medium-horizontal menu\">
            <li><a href=\"api.php?action=shop\">Shop Main Page</a></li>
            <li><a href=\"api.php?action=shop&page=orders\">Your Orders</a></li>
            <li><a href=\"api.php?action=shop&page=cart\">Your Cart</a></li>
            <li><a href=\"api.php?action=shop&page=EFT\">Buy EFT Fit</a></li>
    </ul>";
}

switch ($page)
{
	case 'main':
		// display all items


		if (!isset($_REQUEST['marketGroupID']))
		{
			echo "Welcome to the shop! We have listed all items that available ingame to buy on the market. The site works similar as the ingame market.<br />";
		}


		$parentGroupID = -1;
		if (!isset($_REQUEST['marketGroupID']))
		{
			$marketGroupID = 0;
			$title = "Please select a category";
			$sql = "SELECT marketGroupID, marketGroupName, iconID, hasTypes FROM eve_staticdata.invMarketGroups
				WHERE parentGroupID is null";
		} else {
			$marketGroupID = intval($_REQUEST['marketGroupID']);
			$sql = "SELECT marketGroupID, marketGroupName, iconID, hasTypes FROM eve_staticdata.invMarketGroups
				WHERE parentGroupID = $marketGroupID";

			$res = $db->query("SELECT marketGroupName, parentGroupID FROM eve_staticdata.invMarketGroups WHERE
				marketGroupID = $marketGroupID");
			if ($res->num_rows == 0)
			{
				$title = "Error";
			} else {
				$row = $res->fetch_array();
				$title = $row['marketGroupName'];
				$parentGroupID = $row['parentGroupID'];
			}
		}


		if ($parentGroupID == -1 || is_null($parentGroupID))
		{
            // list most popular items
			if (!isset($marketGroupID) || $marketGroupID == 0) {
				echo "<br /><b>Most Popular Items</b>";
				$sqlmost = "select t.typeName, c.typeID, t.marketGroupID, t.description FROM
					(SELECT typeID,Count(*) as cnt FROM shopping_order_cart  group by typeID ORDER BY count(*) DESC) AS c,
					eve_staticdata.invTypes t WHERE c.typeID = t.typeID LIMIT 4";
				$resmost = $db->query($sqlmost);

				if (!$resmost)
				{
					echo "Failed to query '$sqlmost'<br />";
					echo $db->error . "<br />";
				}

				echo "<table><tr>";
				echo "<td style=\"text-align: center\">";
                echo "<a href=\"api.php?action=shop&page=EFT\"><img src=\"images/eft.png\"><br />Buy fit via EFT</a>";
				echo "</td>";

				while ($rowmost = $resmost->fetch_array())
				{
					$mGroId = $rowmost['marketGroupID'];
					$itemName = $rowmost['typeName'];
					$itemId = $rowmost['typeID'];

					echo "<td style=\"text-align: center\">
						<a href=\"api.php?action=shop_item_detail&item_id=$itemId\"><img src=\"//imageserver.eveonline.com/Type/".$itemId."_64.png\" title=\"" . $rowmost['description'] . "\" /><br />$itemName</a></td> ";
				}

				echo "</tr></table><br /><br />";
			}
		}

        echo "<h3>Browse Category: $title</h3><hr>";

        // build breadcrumbs
        if (isset($_REQUEST['marketGroupID'])) {
            echo '<nav aria-label="You are here:" role="navigation"><ul class="breadcrumbs">';
            // main link
            echo ' <li><a href="api.php?action=shop">Shop</a></li>';

            $list_output = "";

            if ($parentGroupID != -1 && $parentGroupID != "") {
                // iterate over all parents
                while ($parentGroupID != -1 && $parentGroupID != 0 && !is_null($parentGroupID)) {
                    $sql5 = "SELECT marketGroupID, marketGroupName, iconID, hasTypes, parentGroupID FROM eve_staticdata.invMarketGroups
				WHERE marketGroupID = $parentGroupID";

                    $res4 = $db->query($sql5);
                    $row4 = $res4->fetch_array();


                    $list_output = ' <li><a href="api.php?action=shop&page=main&marketGroupID=' . $parentGroupID . '">' . $row4['marketGroupName'] . '</a></li>' . $list_output;

                    $parentGroupID = $row4['parentGroupID'];
                }
            }
            echo "$list_output";
            // current
            echo " <li>$title</li>";
            echo '</ul></nav>';
        }

        echo '<p>All item prices on this site are estimates based on eve-central and not guaranteed. Items with price 0 have not been queried yet.</p>';




		echo "<br />";

		// check if market group id has types
		$sql2 = "SELECT typeName, typeID, mass, volume, published, marketGroupID, description FROM eve_staticdata.invTypes
		WHERE marketGroupID = $marketGroupID ORDER BY typeName ASC";
		$res2 = $db->query($sql2);

		if ($res2->num_rows != 0)
		{
			echo "<table style=\"width: 100%\"><th class=\"table_header\">Icon</th>
			<th class=\"table_header\">Name</th>
			<th class=\"table_header\">Estimated Price</th>
			<th class=\"table_header\">Order?</th></tr>";
			// print types/item in this group
			while ($row = $res2->fetch_array())
			{
				if (isset($_REQUEST['highlightItemID']) && $row['typeID'] == $_REQUEST['highlightItemID'])
				{
					$class = "highlighted";
				}
				else
				{
					$class = "";
				}
				$item_desc = $row['description'];

				echo "<tr class=\"$class\"><td><a id=\"item". $row['typeID'] . "\"><img src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\"/></a></td>";
				$real_volume = getShipSize($row['typeID']);
				if ($real_volume > 0)
					$volume = $real_volume;
				else
					$volume = $row['volume'];

				echo "<td>
				<a href=\"https://wiki.eveonline.com/en/wiki/" . $row['typeName'] . "\">
					<img src=\"images/info_button.png\" /> </a> <a href=\"api.php?action=shop_item_detail&item_id=" . $row['typeID'] . "\">" . $row['typeName'] . "</a>
					(" . $volume . " m3)</td>";
				$price = request_price($row['typeID']);
				$price = $price['sell'] * $profit;

				$estimated_value_str = number_format($price, 2, '.', ',');

				$max_amount_by_volume = intval(350000 / $volume)-1;

				echo "<td style=\"text-align: right\">$estimated_value_str ISK*</td>";
				echo "<td><form method=\"post\" action=\"api.php?action=shop&page=addToCart\">
				<input type=\"hidden\" name=\"typeID\" value=\"" . $row['typeID'] . "\" />
				<input type=\"number\" name=\"quantity\" value=\"1\" min=\"0\" max=\"$max_amount_by_volume\" />
				<input type=\"submit\" value=\"Add\"></form></td>";
				echo "</tr>";
			}

			echo "</table>";
		} else {
			// print all subgroups


			echo "<ul>";
			$res = $db->query($sql);
			while ($row = $res->fetch_array())
			{
				echo "<li><a href=\"api.php?action=shop&page=main&marketGroupID=" . $row['marketGroupID'] . "\">" .
				$row['marketGroupName'] . "</a></li>";
			}

			echo "</ul>";

		}


		base_page_footer('', '');

		break;
	case 'addToCart':
		if (!isset($_REQUEST['typeID']))
			break;
		if (!isset($_REQUEST['quantity']))
			break;

		$typeID = intval($_REQUEST['typeID']);
		$amount = intval($_REQUEST['quantity']);

		if ($amount < 1)
			break;

		$sql2 = "SELECT typeName, typeID, mass, volume, published, marketGroupID FROM eve_staticdata.invTypes
		WHERE typeID = $typeID";
		$res2 = $db->query($sql2);

		if ($res2->num_rows != 0)
		{
			$row = $res2->fetch_array();
			$typeTitle = "Adding " . $row['typeName'] . " to cart";


			marketAddToCart($user_id, $typeID, $amount);

			echo "Added " . $amount . "x " . $row['typeName'] . " to your cart<br /><br />";

			echo "Do you want to <a href=\"api.php?action=shop&page=cart\">show your cart</a>?<br />";
			echo "Go back to <a href=\"api.php?action=shop\">market</a>.<br /><br />";
			echo "Search for another item: ";
			echo "<form method=\"post\" action=\"api.php?action=shop_search\"><input type=\"text\" name=\"s\" /> <input type=\"submit\" value=\"Search\" /></form>";


			base_page_footer('', '');
		}
		else
		{
			echo "invalid type id";
		}
		break;
	case 'changeCart':
		if (!isset($_REQUEST['typeID']))
			break;
		if (!isset($_REQUEST['quantity']))
			break;
		if (!isset($_REQUEST['cart_id']))
			break;

		$typeID = intval($_REQUEST['typeID']);
		$amount = intval($_REQUEST['quantity']);
		$cart_id = intval($_REQUEST['cart_id']);

		if ($amount < 1)
		{
			// delete
			$sql = "DELETE FROM shopping_cart WHERE cart_id = $cart_id AND user_id = $user_id";
		} else {
			// update
			$sql = "UPDATE shopping_cart SET amount = $amount WHERE cart_id = $cart_id AND user_id = $user_id";
		}
		$db->query($sql);
		// do not break here, show cart!
	case 'cart':
		// show cart

        echo "<h3>Your Cart</h3><hr>";




		$coupon_modifier = 1.0;
		/*
		if (isset($_REQUEST['coupon_code']))
		{
			$coupon_code = $db->real_escape_string($_REQUEST['coupon_code']);
			// get coupon modifier
			$coupon_modifier = 0.95;
		} else {

			// check of order has a coupon code
			$coupon_code = "";
		} */


		echo "<form method=\"post\" action=\"api.php?action=shop&page=orderCart\">
			<input type=\"submit\" value=\"Order now!\" />
		</form><br />";

		$sql2 = "SELECT c.cart_id, i.typeName, i.typeID, i.mass, i.volume, i.published, i.marketGroupID, c.amount
		FROM eve_staticdata.invTypes i, shopping_cart c
		WHERE c.typeID = i.typeID AND c.user_id = $user_id";
		$res2 = $db->query($sql2);

		echo "<table style=\"width: 100%\"><th class=\"table_header\">Icon</th>
			<th class=\"table_header\">Name</th>
			<th class=\"table_header\">Estimated Price</th>
			<th class=\"table_header\">Volume</th>
			<th class=\"table_header\">Order?</th></tr>";

		$total_total = 0.0;
		$total_volume = 0.0;

		while ($row = $res2->fetch_array())
		{
			echo "<tr><td><img src=\"//imageserver.eveonline.com/Type/" . $row['typeID'] . "_64.png\" /></td>";
			echo "<td>
			<a href=\"https://wiki.eveonline.com/en/wiki/" . $row['typeName'] . "\">
				<img src=\"images/info_button.png\" /> </a> " . $row['amount']  . "x <a href=\"api.php?action=shop_item_detail&item_id=" . $row['typeID']. "\">" . $row['typeName'] . "</a></td>";
			$price = request_price($row['typeID']);
			$price = $price['sell'] * $profit;

			$total = $price * $row['amount'];
			$total_total += $total;
			$estimated_value_str = number_format($price, 2, '.', ',');
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

			$max_amount_by_volume = intval(350000 / $row['volume'])-1;

			echo "<td style=\"text-align: right\">$volume m3</td>";
			echo "<td><form method=\"post\" action=\"api.php?action=shop&page=changeCart\">
			<input type=\"hidden\" name=\"typeID\" value=\"" . $row['typeID'] . "\" />
			<input type=\"hidden\" name=\"cart_id\" value=\"" . $row['cart_id'] . "\" />
			<input type=\"number\" name=\"quantity\" value=\"" . $row['amount'] . "\" min=\"0\" max=\"$max_amount_by_volume\" />
			<input type=\"submit\" value=\"Change\"></form>
			<a href=\"api.php?action=shop&page=changeCart&typeID=" . $row['typeID'] . "&cart_id=" . $row['cart_id'] . "&quantity=0\">Remove from cart</a>

</td>";
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

		if ($coupon_modifier != 1.0)
		{
			$total_total = $total_total * $coupon_modifier;
			$estimated_total_str = number_format($total_total, 2, '.', ',');
			echo "<tr><td colspan=\"1\"><b>Coupon:</b></td><td>$coupon_text - <b>New TOTAL:</b></td>
			<td style=\"text-align: right\">
				$estimated_total_str ISK*</td>
		<td>&nbsp;</td><td>&nbsp;</td></tr>";
		}


		echo "</table>";


/*
		echo "<form method=\"post\" action=\"api.php?action=shop&page=cart\">";
		echo "<b>Coupon Code (NEW):</b>";
		echo "<input type=\"text\" name=\"coupon_code\" value=\"$coupon_code\"/> <input type=\"submit\" value=\"Add Coupon\" /><br /><br />";
		echo "</form><br />";
*/



		echo "<form method=\"post\" action=\"api.php?action=shop&page=orderCart\">
			<input type=\"submit\" value=\"Order now!\" />
		</form>
		<form method=\"post\" action=\"api.php?action=shop&page=clearCart\">
			<input type=\"submit\" value=\"Clear cart\" />
		</form>";

		echo "*Please be aware that all prices are estimated via eve-central and the actual prices may differ. Items with 0 ISK might not have a price value in our database, therefore the actual price will depend on what price we can buy it at.<br />";
		echo "**Shipping Fee: $shipping_fee ISK ($isk_per_m ISK per m3)<br />";

		base_page_footer('', '');
		break;
	case 'clearCart':
		$sql = "DELETE FROM shopping_cart WHERE user_id = $user_id";
		$db->query($sql);
		header('Location: api.php?action=shop&page=cart');
		break;
	case 'cancelOrder':
		if (isset($_REQUEST['order_id']))
		{
			$order_id = intval($_REQUEST['order_id']);

			$sql = "UPDATE shopping_order SET state=9
			WHERE order_id = $order_id AND user_id = $user_id AND state = 0";
			$db->query($sql);

			addFleetbotPingForGroup("BC/MOVEITMOVEIT", "Unfortunately the order with id $order_id has been canceled by $username.");
		}
	case 'orders':
		echo "<h3>Your Orders</h3><hr>";



		$sql = "SELECT order_id, character_id, state, created FROM shopping_order WHERE user_id = $user_id ORDER BY created DESC";

		echo "<table style=\"width: 100%\"><tr>
		<th class=\"table_header\">ID</th>
		<th class=\"table_header\">State</th>
		<th class=\"table_header\">Date</th>
		<th class=\"table_header\">Options</th></tr>";
		$res = $db->query($sql);

		while ($row = $res->fetch_array())
		{
			echo "<tr><td><a href=\"api.php?action=shop&page=showOrder&order_id=" . $row['order_id'] . "\">
			" . $row['order_id'] . "</a>
			</td>
			<td>" . return_order_state($row['state']) . "</td>
			<td>" . $row['created'] . "</td>";

			echo "<td>";

			if ($row['state'] == 0)
			{
				echo "<a href=\"api.php?action=shop&page=cancelOrder&order_id=" . $row['order_id'] . "\">
				Cancel order</a><br />";
			} else
			{
				echo "&nbsp;";
			}
			echo "<a href=\"api.php?action=shop&page=showOrder&order_id=" . $row['order_id'] . "\">Show Order</a>";

			echo "</td>";


			echo "</tr>";
		}

		echo "</table>";

		// check how many unprocessed orders there are
		$sql = "SELECT COUNT(*) as numOrders
		FROM shopping_order o, auth_users u
		WHERE o.user_id = u.user_id AND o.state = 0";

		$res = $db->query($sql);
		$row = $res->fetch_array();

		echo "Shop Delivery Status: There are currently " . $row['numOrders'] . " orders waiting to be processed, yours is one of them! Please be patient...<br />";

		base_page_footer('', '');
		break;
	case 'showOrder': // same as show shopping cart, but with the order
		// first, select order details
		if (!isset($_REQUEST['order_id']))
			break;
		$order_id = intval($_REQUEST['order_id']);

		$sql = "SELECT o.state, o.character_id, a.character_name,
		o.last_updated, o.last_updated_by, o.comment, o.created, o.last_updated_by
		FROM shopping_order o, api_characters a
		WHERE o.order_id = $order_id AND o.user_id = $user_id AND a.character_id = o.character_id";

		$res = $db->query($sql);

		if ($res->num_rows != 0)
		{
			echo "<h3>Showing Details for Order $order_id</h3>";
			echo "<a href=\"api.php?action=shop\">Shop</a> |
			<a href=\"api.php?action=shop&page=orders\">Orders</a> |
			<a href=\"api.php?action=shop&page=showOrder&order_id=$order_id\">Refresh</a><br /><br /> ";


			$row = $res->fetch_array();

			echo "ID: " . $order_id . "<br />State: "
			. return_order_state($row['state']);
			if ($row['state'] == 0)
			{
				echo " (<a href=\"api.php?action=shop&page=cancelOrder&order_id=$order_id\">Cancel</a>)";
			}

			echo "<br />Created at: "
			. $row['created'] . "<br />Delivery planed to: "
			. $row['character_name'] . "<br />Last Updated: "
			. $row['last_updated'] . "<br />";

			$last_updated_by = $row['last_updated_by'];

			echo "Assigned to: ";

			if ($last_updated_by == 0)
				echo "None";
			else
			{
				$sql3 = "SELECT user_name FROM auth_users WHERE user_id = $last_updated_by";
				$res3 = $db->query($sql3);
				$row3 = $res3->fetch_array();
				echo $row3['user_name'];
			}


			if ($row['comment'] != "")
			{
				echo "Comment: " . $row['comment'];
			}

			echo "<hr>";
			// display contents (same as cart)
			$sql2 = "SELECT c.cart_id, i.typeName, i.typeID, i.mass, i.volume, i.published,
			i.marketGroupID, c.amount, c.actual_price
			FROM eve_staticdata.invTypes i, shopping_order_cart c
			WHERE c.typeID = i.typeID AND c.user_id = $user_id AND c.order_id = $order_id";
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

			if ($row['state'] <= 1)
			{
				echo "*Please be aware that all prices are estimated via eve-central
				and the actual prices may differ. Items with 0 ISK might not have a price
				value in our database, therefore the actual price will depend on what price
				we can buy it at.<br />";
			} else
			{
				echo "*Price is final<br />";
			}
			echo "**Shipping Fee: $shipping_fee ISK ($isk_per_m ISK per m3)<br />";



			base_page_footer('', '');
		}

		break;
	case 'orderCart':
		echo "Before ordering, we want you to understand, that the <b>estimated price can change</b> depending on availability of the item and other market changes.<br />
		Also, by clicking the <b>Order Button</b> you will confirm that you have the ISK in your wallet to order. Failing to do so or declining the order will get you banned from this service.<br />";
		echo "<b>DO NOT SEND ANY MONEY, A CONTRACT WILL BE PUT UP TO YOU!!!</b><br /><br />";


		echo '<form method="post" action="api.php?action=shop&page=orderCart2">';



		echo "Please select the toon you want the contract to be set up to (must be a valid character registered on this API site under your account): <br />";

		// get main character of this user
		$sql = "SELECT has_regged_main FROM auth_users WHERE user_id = $user_id";
		$res = $db->query($sql);
		$row = $res->fetch_array();
		$main_char_id = $row['has_regged_main'];

		// get all characters of this user
		$sql = "SELECT a.character_id, a.character_name
		FROM api_characters a, corporations c, alliances l
		WHERE a.state <= 10 AND c.corp_id = a.corp_id AND c.alliance_id = l.alliance_id AND
		(l.is_allowed_to_reg = 1 OR c.is_allowed_to_reg = 1 OR l.is_allied = 1 OR c.is_allied = 1)
		AND user_id = $user_id ORDER BY character_name ASC";
		$res = $db->query($sql);

		echo '<select name="shop_char_id">';
		while ($row = $res->fetch_array())
		{
			if ($row['character_id'] == $main_char_id)
			{
				echo '<option value="' . $row['character_id'] . '" selected>' . $row['character_name'] . ' (Your main character)</option>';
			} else {
				echo '<option value="' . $row['character_id'] . '">' . $row['character_name'] . '</option>';
			}
		}

		echo '</select><br />';

		echo '<input type="submit" value="Order" /></form><br />';

		echo "Or go <a href=\"api.php?action=shop\">back to the shop</a> WITHOUT ordering.<br />";

		base_page_footer('', '');
		break;
	case 'orderCart2':
		// which character should this be shipped to?
		if (!isset($_REQUEST['shop_char_id']))
			break;

		echo "<h3>Order has been added!</h3>";
		$char_id = intval($_REQUEST['shop_char_id']);

		$coupon_code = "";

		// create a new order with user_id, character_id in shopping_order
		// move all entries from shopping_cart into shopping_order_cart
		$sql = "INSERT into shopping_order
			(user_id, state, created, character_id, last_updated, last_updated_by, `comment`, coupon_code)
			VALUES
			($user_id, 0, NOW(), $char_id, NOW(), 0, '', '$coupon_code')
			";

		$res = $db->query($sql);

		$order_id = $db->insert_id;

		addFleetbotPingForGroup("BC/MOVEITMOVEIT", "A new order with id $order_id has been created by $username.");

		// move cart over to order_cart (use group by to group multiple items with the same typeid)
		$sql = "INSERT INTO shopping_order_cart
SELECT NULL as cart_id,
min(user_id) as user_id, typeID, sum(amount) as amount,
MIN(date_added) as date_added, -1 as order_id, null as actual_price from shopping_cart WHERE user_id = $user_id GROUP BY typeID ";
		$db->query($sql);

		// delete cart entries
		$sql = "DELETE FROM shopping_cart WHERE user_id = $user_id";
		$db->query($sql);

		// set order id
		$sql = "UPDATE shopping_order_cart SET order_id = $order_id WHERE user_id = $user_id AND order_id = -1";
		$db->query($sql);

		echo "Your order with id $order_id was successfully created!<br />
		Visit <a href=\"api.php?action=shop&page=orders\">the orders page</a> to look at your current orders or
		go to the <a href=\"api.php?action=shop&page=showOrder&order_id=$order_id\">order detail page</a>.<br />";
		echo "You may also <a href=\"api.php?action=shop\">continue shopping</a>.<br />";
		base_page_footer('', '');

		break;
	case 'EFT2':
		if (isset($_REQUEST['eft_text']) && strlen($_REQUEST['eft_text']) > 3)
		{
			$db = connectToDB();
			// remove all occurences of \r
			$text = str_replace("\r", "", $_REQUEST['eft_text']);
			$data = explode("\n", $text); // split the text by line

			$ship_type = "";
			$items_added_to_cart = 0;

			for ($i = 0; $i < count($data); $i++)
			{
				$text = $db->real_escape_string($data[$i]);
				// check for ship type
				if ($ship_type == "" && $i < 5) // should be within the first 5 lines
				{
					if (strpos($text, '[') == 0)
					{
						// extract ship type:
						$items = explode(',', $text);
						$ship_type = substr($items[0], 1);
						//echo "Ship Type: $ship_type<br />";
						// add shitype
						$typeID = marketGetTypeID($ship_type);
						if ($typeID != -1)
						{
							marketAddToCart($user_id, $typeID, 1);
							$items_added_to_cart++;
						}

						continue;
					}
				}

				// if there is a comma, (e.g., ammo)
				$items = explode(',', $text);
				for ($j = 0; $j < count($items); $j++)
				{
					$item = trim($items[$j]);

					// item most not contain "empty" (e.g., [empty high slot]) and must be longer than 2 characters
					if (strlen($item) > 2 && ! (strpos($item, "empty") > 0))
					{
						// check for x + number at the end = x amount of item (however, it must not be at the end of the item)
						if (strrpos($item, 'x') >= strlen($item)-5 && strrpos($item, 'x') != strlen($item)-1)
						{
							// add $amount of item
							$new_item = substr($item, 0, strrpos($item, 'x'));
							$amount = substr($item, strrpos($item,'x')+1);
							//echo "$new_item, amount: $amount<br />";
							$typeID = marketGetTypeID($new_item);
							if ($typeID != -1)
							{
								marketAddToCart($user_id, $typeID, $amount);
								$items_added_to_cart++;
							}
							else
								echo "Failed to add $new_item<br />";
						} else {
							// only add x1 of the item
							echo "Item Name: " . $item . "<br />";
							$typeID = marketGetTypeID($item);


							if ($typeID != -1)
							{
								marketAddToCart($user_id, $typeID, 1);
								$items_added_to_cart++;
							}
							else
								echo "Failed to add '$item'<br />";
						}


					}
				}

				// forward to cart
				header('Location: api.php?action=shop&page=cart');

			}
		} else {
            header('Location: api.php?action=shop&page=EFT');
        }
    break;
	case 'EFT':
        echo "<h3>Buy EFT Fit</h3><hr>";
		echo "Copy and paste an item list or a fitting from EFT into the textbox below.<br />";

		echo "<form method=\"post\" action=\"api.php?action=shop&page=EFT2\">
		<textarea rows=\"20\" cols=\"40\" name=\"eft_text\"></textarea>
		<br />
		<input type=\"submit\" value=\"Buy\" />
		</form>";

		base_page_footer('', '');
		break;

}




?>
