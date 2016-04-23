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
$shopping_cart = marketGetWalletItems($user_id);

		base_page_header('',"<div>
<div style=\"width: 100px; float: left; height: 128px;\">
<a href=\"api.php?action=shop\"><img src=\"images/best_panda_logo.png\" width=\"200\" height=\"95\" border=\"0\"></a>
</div>
<div style=\"width: 100px; float: left; height: 128px; margin-left: 500px;\">
<form method=\"post\" action=\"api.php?action=shop_search\">
<input type=\"text\" name=\"s\" style=\"height: 30px; width: 300px;\"/>
 <input type=\"submit\" value=\"\" style=\"background-image: url(images/search.png); background-repeat: no-repeat; border: none; width: 24px; height: 24px; background-size: 24px 24px; \" /></form>
</div>
<div style=\"width: 100px; float: left; margin-left: 600px; height: 128px;\">
 <a href=\"api.php?action=shop&page=cart\"><img src=\"images/shopping-cart.png\" width=\"32\" height=\"32\" title=\"You currently have $amount in your shopping cart\" /> $amount</a>
</div>
", 'Shop');


if (isset($_REQUEST['item_id']))
{
	$item_id = $db->real_escape_string($_REQUEST['item_id']);
	// find out everything of that item
	
	$sql = "SELECT typeName, typeID, mass, volume, published, marketGroupID, description FROM eve_staticdata.invTypes
		WHERE typeID = '" . $item_id . "' AND published=1 ";
	$res = $db->query($sql);
	
	if ($res->num_rows != 1)
	{
		echo "ERROR: Not found";
	} else {
		$row = $res->fetch_array();
		$item_name = $row['typeName'];
		$volume = $row['volume'];
		$marketGroupID = $row['marketGroupID'];
		$description = $row['description'];
		$real_volume = getShipSize($row['typeID']);
		if ($real_volume > 0)
			$volume = $real_volume;
		else
			$volume = $row['volume'];
		$price = request_price($row['typeID']);
		$price = $price['sell'] * $profit;
				
		$estimated_value_str = number_format($price, 2, '.', ',');
		
		echo "<table><tr><td rowspan=\"2\"><img src=\"//imageserver.eveonline.com/Type/" . $item_id . "_64.png\"/><br /><h3>$item_name</h3></td>";
		echo "<td><b>$item_name</b> - $volume m3</td></tr>";		
		echo "<tr><td>Price: $estimated_value_str ISK (estimated price - may change)<br />Volume: $volume m3<br /><br />";
		// check if this item is already in shopping cart
		
		foreach ($shopping_cart as $cartTypeId => $cartAmount)
		{
			if ($cartTypeId == $item_id)
			{
				echo "You already have $cartAmount items of this type in your <a href=\"api.php?action=shop&page=cart\">shopping cart</a>!<br />";
			}
		}		

		$max_amount_by_volume = intval(350000 / $volume)-1;
		
		
		echo "<form method=\"post\" action=\"api.php?action=shop&page=addToCart\">
				<input type=\"hidden\" name=\"typeID\" value=\"" . $row['typeID'] . "\" />
				<input type=\"number\" name=\"quantity\" value=\"1\" min=\"0\" max=\"$max_amount_by_volume\" width=\"10\"/> 
				<input type=\"submit\" value=\"Add\"></form>";
		
		
		echo "<br /><br /><b>Info:</b><br />" . $description . "</td></tr>";
		echo "<tr><td colspan=\"2\">Similar items:<br />";
		
		
		
		
		// print all similar items of that market group id
		// check if market group id has types
		$sql2 = "SELECT typeName, typeID, mass, volume, published, marketGroupID, description FROM eve_staticdata.invTypes
		WHERE marketGroupID = $marketGroupID ORDER BY typeName ASC";
		$res2 = $db->query($sql2);
		
		if ($res2->num_rows != 0)
		{
			echo "<table style=\"height: 128px; font-size: 8pt;\"><tr>";
			while ($row2 = $res2->fetch_array())
			{
				if ($row2['typeID'] != $item_id)
				{
					echo "<td><a href=\"api.php?action=shop_item_detail&item_id=" . $row2['typeID'] . "\">
						<img border=\"0\" src=\"//imageserver.eveonline.com/Type/" . $row2['typeID'] . "_64.png\"/><br />" . $row2['typeName'] . "</a><br />";

					$price = request_price($row2['typeID']);
					$price = $price['sell'] * $profit;
					
					$estimated_value_str = number_format($price, 2, '.', ',');
					
					echo $estimated_value_str . " ISK";
					echo "</td>";				
				}
			}			
		}
		echo "</tr></table>";
		
		echo "</td></tr>";
		echo "</table>";
	
	}
	
}
else
{
	echo "Error: Must select item!";
}



base_page_footer('', '');



?>
