<?php

if (!isset($_REQUEST['s']))
{
	header('Location: api.php?action=shop');
	exit;
}

$db = connectToDB();

$s = $db->real_escape_string($_REQUEST['s']);

// search for s in item database
$sql = "SELECT typeID, groupID, typeName, description, volume, marketGroupID FROM eve_staticdata.invTypes WHERE marketGroupID is not null AND typeName LIKE '%$s%' LIMIT 20";

$res = $db->query($sql);

if ($res->num_rows == 0)
{
	base_page_header('',"Shop &nbsp; &nbsp; <form style=\"display:inline; margin:0px; padding:0px;\" method=\"post\" action=\"api.php?action=shop_search\">
<input type=\"text\" name=\"s\" style=\"height: 24px;\"/>
 <input type=\"submit\" value=\"\" style=\"background-image: url(images/search.png); background-repeat: no-repeat; border: none; width: 24px; height: 24px; background-size: 24px 24px; \" /></form>", 'Shop');

	echo "We couldn't find the item you were looking for.<br />Please try again or <a href=\"api.php?action=shop\">browse through the shop categories</a>";

	base_page_footer('', '');
} else if ($res->num_rows == 1)
{
	// forward to the item
	$row = $res->fetch_array();
	$marketGroupID = $row['marketGroupID'];
	$item_id = $row['typeID'];

	header("Location: api.php?action=shop_item_detail&item_id=$item_id");
} else
{
	// diplay items
	base_page_header('',"Shop &nbsp; &nbsp; <form style=\"display:inline; margin:0px; padding:0px;\" method=\"post\" action=\"api.php?action=shop_search\">
<input type=\"text\" name=\"s\" style=\"height: 24px;\"/>
 <input type=\"submit\" value=\"\" style=\"background-image: url(images/search.png); background-repeat: no-repeat; border: none; width: 24px; height: 24px; background-size: 24px 24px; \" /></form>", 'Shop');

	if ($res->num_rows == 20)
	{
		echo "Too many entries found, displaying the first 20 items...<br />";
	}

	echo "<ul>";
	while ($row = $res->fetch_array())
	{
		$marketGroupID = $row['marketGroupID'];
		$name = $row['typeName'];
		$typeID = $row['typeID'];
		$amount = 1;
		echo "<li><a href=\"api.php?action=shop_item_detail&item_id=$typeID\">$name</a> - <a href=\"api.php?action=shop&page=addToCart&typeID=$typeID&quantity=1\">Add to cart</a></li>";
	}
	echo "</ul>";

	base_page_footer('', '');
}


?>
