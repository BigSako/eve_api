<?php

base_page_header('', 'Shop Superadmin', 'Shop Superadmin');

$user_id = $GLOBALS['userid'];
echo "Welcome to the shop super-administration system.<br />";

$db = connectToDB();


if (isset($_REQUEST['delete_order_id']))
{
	$del_order_id = intval($_REQUEST['delete_order_id']);
	// delete from shopping_order and shopping_order_cart
	$sql = "DELETE FROM shopping_order WHERE order_id = $del_order_id";
	$db->query($sql);
	$sql = "DELETE FROM shopping_order_cart WHERE order_id = $del_order_id";
	$db->query($sql);
}

// display the orders that are currently open and assigned to this user
$sql = "SELECT o.state, o.order_id, o.user_id, u.user_name, o.created, o.last_updated, o.last_updated_by 
FROM shopping_order o, auth_users u
WHERE o.user_id = u.user_id
ORDER BY o.created DESC";

$res = $db->query($sql);





echo "<h3>ALL Orders: " . $res->num_rows . "</h3>";

echo "Please do not delete orders unless absolutely necessary (e.g., bug). Canceled orders are deleted automatically after a day.<br />";

echo "<table style=\"width: 100%\">
				<th class=\"table_header\">Order ID</th>
				<th class=\"table_header\">User Name</th>
				<th class=\"table_header\">Created</th>
				<th class=\"table_header\">State</th>
				<th class=\"table_header\">Assigned To</th>
				<th class=\"table_header\">Options</th></tr>";

while ($row = $res->fetch_array())
{
	echo "<tr>";
	echo "<td>" . $row['order_id'] . "</td>";
	
	echo "<td><a href=\"api.php?action=show_member&user_id=" . $row['user_id'] . "\">" . $row['user_name'] . "</a></td>";
	
	echo "<td>" . $row['created'] . "</td>";
	
	echo "<td>" . return_order_state($row['state']) . "</td>";

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
	
	echo "<td>
	<a href=\"api.php?action=shop_admin2&page=show_order&order_id=" . $row['order_id'] . "\">Show Details</a> |
 <a href=\"api.php?action=shop_admin3&delete_order_id=" . $row['order_id'] . "\">DEL</a>
	

	</td>";
	
	echo "</tr>";
}
				
echo "</table>";







base_page_footer('', '');



?>
