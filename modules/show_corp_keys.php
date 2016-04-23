<?php
	do_log("Entered show_corp_keys",5);
	
	base_page_header('','Corporation Key Status','Corporation Key Status');
	
	$where = "1=1";
	
	// let's see what corp_ids we have access to
	if ($isAdmin == true)
	{
		$where = "1=1";
	} else {
		if (count($director_corp_ids) > 0)
		{
			$where = "corp_id IN (" . implode(',', $director_corp_ids) . " ) ";
		}
		else 
		{
			echo "Access denied - You need to be a director of a corp.";
			base_page_footer('1','');
			exit;
		}
	}
	
	$db = connectToDB();
	
	// did we dis-allow a corp to be on our services?
	if (isset($_REQUEST['disallow_key_id']) && isset($_REQUEST['corp_id']))
	{
		$key_id = intval($_REQUEST['disallow_key_id']);
		$corp_id = intval($_REQUEST['corp_id']);
		
		if ($isAdmin == false)
		{
			echo "Access denied";
			base_page_footer('1', '');
			exit;
		} else 
		{
			$sql = "UPDATE corporations SET is_allowed_to_reg = 0 WHERE corp_id = $corp_id";
			$db->query($sql);
		}
	}


	$res = $db->query("select c.alliance_id, a.alliance_name, c.corp_name, c.corp_id, c.state,
		c.is_allowed_to_reg as corp_allowed, a.is_allowed_to_reg as all_allowed,
		c.is_allied as allied_corp, a.is_allied as allied_all
	from corporations c, alliances a 
				WHERE (c.alliance_id = a.alliance_id) AND 
						(c.is_allowed_to_reg=1 OR a.is_allowed_to_reg=1) AND
						$where 
						ORDER BY a.is_allowed_to_reg DESC, c.is_allowed_to_reg DESC,
						a.alliance_name, c.corp_name ");

	$last_alliance_id = -1;

	echo "The following list contains all corporations that are allowed to access the services in one way or another.<br />
	If you are a CEO, please make sure you enter a full corp API Key (Access Mask 67108863 - everything except bookmarks) for your corporation.
	The API Key is also automatically used for pulling killmails, member data, starbases, etc....<br /><br />";
	
	echo "<table style=\"width: 100%\"><tr>
		<th class=\"table_header\">Alliance</th>
		<th class=\"table_header\">Corp</th>
		<th class=\"table_header\">Key ID</th>
		<th class=\"table_header\">Options</th>
		</tr>";
	
	$i = 0;
	
	while($row=$res->fetch_array()) 
	{
		$alliance_name = $row['alliance_name'];		
		$alliance_Id = $row['alliance_id'];
		$corp_name=$row['corp_name'];
		$corp_id = $row['corp_id'];	
		$corp_allowed = $row['corp_allowed'];
		$all_allowed = $row['all_allowed'];
		$allied_corp = $row['allied_corp'];
		$allied_all  = $row['allied_all'];
		
		$bgclass = "bg" . ($i % 2);
		$i++;
		
		if ($corp_name == '')
			$corp_name = "Corp ID $corp_id";
		
	
		$sql = "SELECT keyid, state, last_checked, access_mask FROM corp_api_keys WHERE corp_id = $corp_id";
		
		$res2 = $db->query($sql);
		$rows = $res2->num_rows;
		if ($rows == 0)
			echo "<tr class=\"$bgclass\"><td>$alliance_name</td><td>$corp_name</td><td><b>None/Missing</b></td>
				<td><b>Add API Key (Access Mask=" . $SETTINGS['corp_api_key_accessmask'] . ")</b>:<br />
					<form method=\"post\" action=\"api.php?action=add_corp_key\">
						<input type=\"hidden\" name=\"corp_id\" value=\"$corp_id\" />
						KeyID: <input type=\"text\" name=\"corp_keyid\" /><br />
						VCode: <input type=\"text\" name=\"corp_vcode\" /><br />
						&nbsp; <input type=\"submit\" value=\"Add\" />
					</form>				
				</td></tr>";
		else 
		{
			// get corp members
			$sql3 = "SELECT COUNT(*) as cnt FROM corp_members WHERE corp_id = $corp_id";
			$res3 = $db->query($sql3);
			$row3 = $res3->fetch_array();
			$amount_members = $row3['cnt'];

			echo "<tr class=\"$bgclass\"><td rowspan=\"$rows\">$alliance_name</td><td rowspan=\"$rows\">$corp_name ($amount_members <a href=\"api.php?action=member_audit&members=all&corp_id=$corp_id\">Members</a>)
        [<a href=\"api.php?action=human_resources&corp_id=$corp_id\">HR</a>] [<a href=\"api.php?action=starbases&corp_id=$corp_id\">POS</a>]


        </td>";
			echo "<td><table width=\"100%\">";

			$row2 = $res2->fetch_array();
			
			$key_id = $row2['keyid'];
			$state = $row2['state'];
			$last_checked = $row2['last_checked'];
			$key_access_mask = $row2['access_mask'];

			$state_text=return_state_text($state);
			$state_class=return_state_class($state);

			$extra = "";
			
			if ($state == 0 || $state == 1)
			{
				$extra = "";
			} else {
				// check if api file exists and display output
				$filename = $filename=TMPDIR."$key_id.APIKeyInfo.xml.aspx";

				if (file_exists($filename))
				{	
					$api_xml_data = file_get_contents ($filename);
					$api_xml_data = htmlspecialchars ($api_xml_data);

					$extra = "<br /><textarea rows=\"10\" cols=\"30\">$api_xml_data</textarea>";
				}
			}

			echo "<tr><td class=\"$state_class\">$key_id<br />$state_text<br />Mask: $key_access_mask</td></tr>";
				
			
			echo "</td></tr>";
			
			echo "</table></td>";
			
			echo "<td><a href=\"api.php?action=delete_corp_api_key&corp_id=$corp_id&key_id=$key_id\">Delete Api KEY</a><br />
			<a href=\"api.php?action=refresh_corp_api_key&corp_id=$corp_id&key_id=$key_id\">Refresh Api KEY</a>$extra</td>";
			
			echo "</tr>";
	
			
		}
		
	}
	
	echo "</table>";
	
	base_page_footer('1','');

?>
