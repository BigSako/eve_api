<?php

$allowed = false;
$admin = false;

$db = connectToDB();
$user_id = $GLOBALS['userid'];
$character_id = intval($_REQUEST['character_id']);
if (isset($_REQUEST['filter']))
	$filter = intval($_REQUEST['filter']);
else
	$filter = 0;


// check if user is allowed to edit skillsheets
$sql = "SELECT page_name, m.group_id FROM api_page_access a, group_membership m
WHERE a.page_name='skillsheet_admin' AND (a.group_id = m.group_id OR m.group_id = 2) AND m.user_id = $userid  AND m.state=0 ";
$res = $db->query($sql);

if ($res->num_rows >= 1)
{
	$row = $res->fetch_array();
	if ($row['page_name'] == 'skillsheet_admin')
	{
		$admin = true;
		$allowed = true;
	}
} else {
	
	$res = $db->query("SELECT character_id FROM api_characters WHERE character_id = $character_id AND user_id = $user_id");
	$row = $res->fetch_array();
	if ($row['character_id'] == $character_id)
	{
		$allowed = true;
	}	
}


if ($allowed == false)
{
	exit;
}






$res = $db->query("SELECT character_name, skillpoints FROM api_characters WHERE character_id = $character_id ");
if ($res->num_rows == 1) 
{
	$row = $res->fetch_array();
	$character_name = $row['character_name'];
	$title = "Displaying skills for $character_name";
	
	base_page_header('',$title,$title, "<a href=\"api.php?action=char_sheet&character_id=$character_id\">Back</a>");

if ($filter == 0)
{
	$sql = "
SELECT k.groupID, k.groupName, k.typeName, k.typeID, k.rank, s.level, s.skillpoints, s.character_id, 0 as minLevel FROM invSkills k
LEFT JOIN char_skillsheet s ON s.character_id = $character_id AND s.typeID = k.typeID

ORDER BY k.groupName, k.typeName ASC
";

} else
{
	$sql = "SELECT k.groupID, k.groupName, k.typeName, k.typeID, k.rank, s.level, s.skillpoints, s.character_id, f.minLevel
FROM invSkills k
LEFT JOIN skill_filter_skills f ON f.filter_id = $filter
AND f.typeID = k.typeID
LEFT JOIN char_skillsheet s ON s.character_id = $character_id
AND s.typeID = k.typeID
ORDER BY k.groupName, k.typeName ASC 
";
}

	echo "<!-- SQL = '$sql' -->";

	
	
	echo "Filters: <a href=\"api.php?action=skillsheet&character_id=$character_id\">None</a> | " .
				"<a href=\"api.php?action=skillsheet&character_id=$character_id&filter=1\">Nyx/Aeon</a> | " .
				"<a href=\"api.php?action=skillsheet&character_id=$character_id&filter=3\">Avatar/Revelation</a> | " .
				"<a href=\"api.php?action=skillsheet&character_id=$character_id&filter=2\">Erebus/Moros</a> | " .
				"<a href=\"api.php?action=skillsheet&character_id=$character_id&filter=4\">Ragnarok/Naglfar</a> | " .
				"<a href=\"api.php?action=skillsheet&character_id=$character_id&filter=5\">Leviathan/Phoenix</a> | " .
				"<br /><br />";
	
	if ($filter != 0)
	{
		$res = $db->query("SELECT name FROM skill_filter WHERE id=$filter");
		if ($res->num_rows == 1)
		{
		 	$row = $res->fetch_array();
			echo "<b>ACTIVE FILTER: " . $row['name'] . "</b> - <a href=\"api.php?action=skillsheet&requirements=1&filter=$filter&character_id=$character_id\">Show requirements for this filter</a><br />";
		} else 
		{
			$filter = 0;
		}

	}
	echo "<br />";
	
	
	if (isset($_REQUEST['requirements']) && $_REQUEST['requirements'] == 1 && $filter != 0)
	{
		$sql = "SELECT k.groupID, k.groupName, k.typeName, k.typeID, k.rank, f.minLevel, k.published
FROM invSkills k
LEFT JOIN skill_filter_skills f ON f.filter_id=$filter AND f.typeID = k.typeID

ORDER BY k.groupName, k.typeName";
		$res = $db->query($sql);
		
		echo "<table id='your_api_keys' style=\"width: 100%\">";
		echo "<tr><td colspan=\"3\" class=\"long_table_header\">Requirements</td></tr>";
		echo "<tr><th class=\"table_header\">Skill Name</th>";
		
		if ($admin == true)
		{
			echo "<th class=\"table_header\">Admin</th>";
		}
		
		echo "<th class=\"table_header\"></th></tr>";
		
		$lastGroupID = -1;
		
		$show_group_id = intval($_REQUEST['show_group_id']);
		
		while ($row = $res->fetch_array())
		{
			$groupID = $row['groupID'];
			$groupName = $row['groupName'];
			$sp = $row['skillpoints'];
			$typeID = $row['typeID'];
			$skill_name = $row['typeName'];		
			$level = $row['minLevel'];
			$published = $row['published'];
			
			if ($published == 0)
				continue;
			
			if ($level === NULL)
				$level = -1;
			
			if ($lastGroupID != $groupID && $groupName != 'Learning' && $groupName != 'Fake Skills')
			{
				echo "<tr><td colspan=\"3\" class=\"long_table_header\">
					<a name=\"gr$groupID\" href=\"api.php?action=skillsheet&requirements=1&filter=$filter&character_id=$character_id&show_group_id=$groupID#gr$groupID\">$groupName</a>
					
					</td></tr>\n";
			}
			
			$lastGroupID = $groupID;
			
			if ($groupID != $show_group_id)
				continue;
			
			
			

			
			echo "<tr><td><a name=\"$typeID\">$skill_name</a> / Required Level: $level ";

			echo "/ ID: $typeID";
			
			if ($admin == true)
			{
				echo "</td><td>Level: 
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=0\">0</a>
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=1\">1</a> 
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=2\">2</a>
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=3\">3</a>
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=4\">4</a>
				<a href=\"api.php?action=skillsheet_admin&show_group_id=$groupID&character_id=$character_id&filter=$filter&typeID=$typeID&newLevel=5\">5</a>
				";
			}
			
			
			echo "</td><td>";
			
			if ($level != -1)
			{
				echo "<img src=\"images/skill_level$level.png\" />";
			} else
			{
				echo "Not required";
			}
			
			echo "</td></tr>\n";
		}
		
		echo "</table>";

	}
	else // display normal skillqueue
	{
		$res = $db->query($sql);
	
		echo "<table id='your_api_keys' style=\"width: 100%\">";
		echo "<tr><td colspan=\"2\" class=\"long_table_header\">Skills</td></tr>";
		echo "<tr><th class=\"table_header\">Skill Name</th><th class=\"table_header\"></th></tr>";
			
		$lastGroupID = -1;
		$total_group_sp = 0;
		$total_group_trained = 0;
		$total_group_untrained = 0;
		while ($row = $res->fetch_array())
		{
			$groupID = $row['groupID'];
			$groupName = $row['groupName'];
			$sp = $row['skillpoints'];
			$typeID = $row['typeID'];
			$skill_name = $row['typeName'];		
			$level = $row['level'];
			$minLevel = $row['minLevel'];
			
			
			
			if ($lastGroupID != $groupID && $groupName != 'Learning' && $groupName != 'Fake Skills') 
			{
				if ($total_group_sp != 0)
				{
					echo "<tr><td colspan=\"2\" style=\"text-align: right\">$total_group_trained out of " . ($total_group_trained+$total_group_untrained) . " skills trained, for a total of $total_group_sp skillpoints</td></tr>";
				}
				$total_group_sp = 0;
				$total_group_trained = 0;
				$total_group_untrained = 0;
				echo "<tr><td colspan=\"2\" class=\"long_table_header\">$groupName</td></tr>\n";
			}
			
			$lastGroupID = $groupID;
			
			if ($level === NULL && $filter == 0)
			{
				echo "<!-- skipping $groupName $skill_name because of null value -->";
				$total_group_untrained++;
				continue;
			}
			
			if ($filter != 0 && $minLevel === NULL)
			{
				echo "<!-- skipping $groupName $skill_name also, because of null value at minLevel -->";
				continue;
			}
	

			if ($level === NULL)
			{
				$level = -1;
				$sp = 0;
				$total_group_untrained++;
			}
		

		
			
			if ($minLevel === NULL)
				$minLevel = -1;
				
			if ($minLevel > $level)
			{
				if ($level == -1)
				{
					echo "<tr><td style=\"color: red\">$skill_name / <b>untrained</b> / SP: $sp";
				} 
				else
				{
					echo "<tr><td style=\"color: red\">$skill_name / <b>Level: $level / $minLevel</b> / SP: $sp";
				}
			}
			else
			{
				echo "<tr><td>$skill_name / Level: $level / SP: $sp";
			}
			

			
			if ($level != -1) {
				echo "</td><td><img src=\"images/skill_level$level.png\" /></td></tr>\n";
			} else {
				echo "</td><td><b>not trained</b></td></tr>\n";
			}
			
			
			if ($level != -1) {			
				$total_group_sp += $sp;
				$total_group_trained += 1;
			}
			
		}
		
		echo "<tr><td colspan=\"2\" style=\"text-align: right\">$total_group_trained out of " . ($total_group_trained+$total_group_untrained) . " skills trained, for a total of $total_group_sp skillpoints</td></tr>";

		echo "</table>";	

	}


	base_page_footer('1','');

}









?>