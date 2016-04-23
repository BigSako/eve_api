<?php

base_page_header('', 'Shop Admin', 'Shop Admin');


function calculate_order_volume($order_id)
{
	global $db;
	$sql2 = "SELECT c.cart_id, i.typeName, i.typeID, i.mass, i.volume, i.published, 
	i.marketGroupID, c.amount, c.actual_price
	FROM eve_staticdata.invTypes i, shopping_order_cart c
	WHERE c.typeID = i.typeID AND c.order_id = $order_id";
	$res2 = $db->query($sql2);
	

	$total_volume = 0.0;
	
	while ($row = $res2->fetch_array())
	{
		$real_volume = getShipSize($row['typeID']);
		if ($real_volume == 0)			
			$volume = $row['volume'] * $row['amount'];
		else if ($real_volume > 0)
			$volume = $real_volume * $row['amount'];
		else
			$volume = $row['volume'] * $row['amount'];
		
		$total_volume += $volume;
	}
	
	return $total_volume;
}



$user_id = $GLOBALS['userid'];
echo "Welcome to the shop administration system.<br />";
if (getPageAccessForUser('shop_admin3', $GLOBALS['userid']) == true)
	echo "<a href=\"api.php?action=shop_admin3\">Go to shop superadmin</a><br />";


$db = connectToDB();

// display the orders that are currently open and assigned to this user
$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated
FROM shopping_order o, auth_users u
WHERE o.last_updated_by = $user_id AND o.user_id = u.user_id AND o.state <> 4
ORDER BY o.created ASC";

$res = $db->query($sql);


echo "<h3>Help</h3>";
echo "<ul><li>First, select orders from 'Unprocessed Orders' List (Show Details, verify it, accept it if you want to take it).</li> ";
echo "<li>Second, you can see the orders in the 'Accepted Orders' list.</li>";
echo "<li>Third, open your EvE Client on your Jita toon, and in the Ingame Browser click on '<a href=\"api.php?action=shop_admin_ingame\">Start Buying Items</a>' and buy the items.</li>";
echo "</ul>";


echo "<h3>Accepted Orders: " . $res->num_rows . "</h3>";
echo "<br /><a href=\"api.php?action=shop_admin_ingame\">Start Buying Items (Ingame Browser)</a><br /><br />";
echo "<table style=\"width: 100%\">
				<th class=\"table_header\">Order ID</th>
				<th class=\"table_header\">User Name</th>
				<th class=\"table_header\">Created</th>
				<th class=\"table_header\">State</th>
				<th class=\"table_header\">Options</th></tr>";

$total_volume = 0.0;

while ($row = $res->fetch_array())
{
	echo "<tr>";
	echo "<td>" . $row['order_id'] . "</td>";
	
	echo "<td>" . $row['user_name'] . "</td>";
	
	echo "<td>" . $row['created'] . "</td>";
	
	echo "<td>" . return_order_state($row['state']) . "</td>";
	
	// calculate volume
	$volume = calculate_order_volume($row['order_id']);
	
	$total_volume += $volume;
	
	echo "<td>
	<a href=\"api.php?action=shop_admin2&page=show_order&order_id=" . $row['order_id'] . "\">Show Details</a> ($volume m3)<br />
	Set State to:
	<a href=\"api.php?action=shop_admin2&page=start_deliver&order_id=" . $row['order_id'] . "\">Delivery</a>
	| <a href=\"api.php?action=shop_admin2&page=contract_order&order_id=" . $row['order_id'] . "\">Contracted</a>
	| <a href=\"api.php?action=shop_admin2&page=done_order&order_id=" . $row['order_id'] . "\">Paid</a>
	| <a href=\"api.php?action=shop_admin2&page=failed_order&order_id=" . $row['order_id'] . "\">Failed</a>

	</td>";
	
	echo "</tr>";
}
echo "<tr><td colspan=\"3\"><b>Total Volume:</b></td><td>$total_volume m3</td><td></td></tr>";
				
echo "</table>";

echo "<hr>";
echo "<h3>Unprocessed Orders</h3>";


// display the orders that are currently open and assigned to this user
$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated
FROM shopping_order o, auth_users u
WHERE o.user_id = u.user_id AND o.state = 0
ORDER BY o.created ASC";

$res = $db->query($sql);

echo "There are currently " . $res->num_rows . " unprocessed orders!<br />";
echo "<table style=\"width: 100%\">
				<th class=\"table_header\">Order ID</th>
				<th class=\"table_header\">User Name</th>
				<th class=\"table_header\">Volume</th>
				<th class=\"table_header\">Created</th>
				<th class=\"table_header\">Last Update</th>
				<th class=\"table_header\">Options</th></tr>";

while ($row = $res->fetch_array())
{
	echo "<tr>";
	echo "<td>" . $row['order_id'] . "</td>";
	
	echo "<td>" . $row['user_name'] . "</td>";
	
	$volume = calculate_order_volume($row['order_id']);
	
	echo "<td>" . $volume . " m3</td>";
	
	echo "<td>" . $row['created'] . "</td>";
	
	echo "<td>" . $row['last_updated'] . "</td>";
	
	echo "<td>
	<a href=\"api.php?action=shop_admin2&page=show_order&order_id=" . $row['order_id'] . "\">Show Details</a>
	</td>";
	
	echo "</tr>";
}
				
echo "</table>";

echo "<b>***</b> - If you deny orders, they will disappear for everybody.<br />";

echo "<hr>";

echo "<h3>Orders Accepted by Co-Workers</h3>";

// display 5 last orders that have been done by someone else 
$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated, o.last_updated_by 
FROM shopping_order o, auth_users u
WHERE o.user_id = u.user_id AND o.last_updated_by <> $user_id
ORDER BY o.created DESC LIMIT 5";

$res = $db->query($sql);

echo "<table style=\"width: 100%\">
				<th class=\"table_header\">Order ID</th>
				<th class=\"table_header\">User Name</th>
				<th class=\"table_header\">Created</th>
				<th class=\"table_header\">Last Update</th>
				<th class=\"table_header\">By</th></tr>";


while ($row = $res->fetch_array())
{
	echo "<tr>";
	echo "<td>" . $row['order_id'] . "</td>";
	
	echo "<td>" . $row['user_name'] . "</td>";
	
	echo "<td>" . $row['created'] . "</td>";
	
	echo "<td>" . $row['last_updated'] . " - " . return_order_state($row['state']) . "</td>";
	
	echo "<td>";
	$last_updated_by = $row['last_updated_by'];
	if ($last_updated_by == 0)
		echo "None";
	else
	{
		$sql3 = "SELECT user_name FROM auth_users WHERE user_id = $last_updated_by";
		$res3 = $db->query($sql3);
		$row3 = $res3->fetch_array();
		echo $row3['user_name'];
	}
	echo "</td>";
	
	echo "</tr>";
}
				
echo "</table>";

// figure out who has access to this
// get all users that are in group 73
$sql = "SELECT a.user_id, a.user_name FROM auth_users a, group_membership m WHERE a.user_id = m.user_id and m.group_id = 73";

$res = $db->query($sql);

echo "(Co-Workers: ";
while ($row = $res->fetch_array())
{
	$co_user_id = $row['user_id'];
	$sql2 = "SELECT COUNT(*) as cnt FROM shopping_order WHERE last_updated_by = $co_user_id";
	$res2 = $db->query($sql2);
	$row2 = $res2->fetch_array();
	echo $row['user_name'] . ' (' . $row2['cnt'] . '), ';
}
echo ")";

echo "<hr>";

// display the orders that are currently open and assigned to this user
$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated
FROM shopping_order o, auth_users u
WHERE o.last_updated_by = $user_id AND o.user_id = u.user_id AND (o.state = 4 or o.state = 6)
ORDER BY o.last_updated ASC";

$res = $db->query($sql);
echo "<h3>Your Done or Failed Orders</h3>";
echo "<table style=\"width: 100%\">
				<th class=\"table_header\">Order ID</th>
				<th class=\"table_header\">User Name</th>
				<th class=\"table_header\">Created</th>
				<th class=\"table_header\">Last Update</th>
				<th class=\"table_header\">Options</th></tr>";

while ($row = $res->fetch_array())
{
	echo "<tr>";
	echo "<td>" . $row['order_id'] . "</td>";
	
	echo "<td>" . $row['user_name'] . "</td>";
	
	echo "<td>" . $row['created'] . "</td>";
	
	echo "<td>" . $row['last_updated'] . " - " . return_order_state($row['state']) . "</td>";
	
	echo "<td>
	<a href=\"api.php?action=shop_admin2&page=show_order&order_id=" . $row['order_id'] . "\">Show Details</a>
	</td>";
	
	echo "</tr>";
}
				
echo "</table>";






base_page_footer('', '');



?>
