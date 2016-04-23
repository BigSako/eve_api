<?php
	$corp_id = $SETTINGS['main_corp_id'];
	
	if ($_REQUEST['corp_id'])
	{
		$corp_id = intval($_REQUEST['corp_id']);
	}
	
	if ($corp_id == 0)
	{
		exit;
	}
	
	if (!in_array($corp_id, $director_corp_ids) && !in_array(2, $group_membership))
	{
		echo "Not allowed";
		exit;
	}
	
	$locationID = intval($_REQUEST['locationID']);
	$moonID     = intval($_REQUEST['moonID']);
	$itemID     = intval($_REQUEST['itemID']);
	
	if ($moonID == 0 || $locationID == 0)
	{
		exit;
	}	
	
	
	$db = connectToDB();
	
	$comment = $db->real_escape_string($_REQUEST['comment']);
	$comment = str_replace("<", "&lt;", $comment);
	$comment = str_replace(">", "&gt;", $comment);
	
	

	$sql = "UPDATE starbases SET sbComment='$comment' WHERE itemID = $itemID AND locationID = $locationID AND corp_id = $corp_id AND moonID = $moonID";
	$db->query($sql);
	
	my_meta_refresh("api.php?action=starbase_detail&itemID=$itemID&locationID=$locationID&moonID=$moonID&corp_id=$corp_id", 0);
	
?>