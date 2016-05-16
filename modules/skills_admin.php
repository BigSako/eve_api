<?php


if (!isset($_REQUEST['edit']))
{
	base_page_header('', 'Skills Admin', 'Skills Admin');


	// get a count of skills per filter type
	$sql = "SELECT 
	f.id, COUNT(s.typeId) as cnt 
	FROM skill_filter f, skill_filter_skills s 
	WHERE s.filter_id = f.id GROUP BY f.id";
	$res = $db->query($sql);
	$cnt_skills = array();

	while ($row = $res->fetch_array())
	{
		$cnt_skills[$row['id']] = $row['cnt'];
	}

	// get all filters
	$sql = "SELECT id, name, `desc` FROM skill_filter ORDER BY name ASC";
	$res = $db->query($sql);


	echo '<table>
	  <thead>
	    <tr>
	      <th>Name</th>
	      <th>Description</th>
	      <th>Contains Skills</th>
	      <th>Chars Can Fly?</th>
	    </tr>
	  </thead>
	  <tbody>';

	while ($row = $res->fetch_array())
	{
		echo '<tr>';

		echo '<td><a href="api.php?action=skills_admin&edit=' . $row['id'] . '">' . $row['name'] . '</a></td>';

		echo '<td>' . $row['desc'] . '</td>';

		if (array_key_exists($row['id'], $cnt_skills))
			echo '<td>' . $cnt_skills[$row['id']] . '</td>';
		else
			echo '<td>0</td>';

		echo '<td>Todo</td>';
		echo '</tr>';
	}


	echo '</tbody>
	</table>';
} else {
	// prepare a list of skillsheets for the secondary menu on top
	$list_of_skills = '<li><a class="skip_hover_color" href="#">List Skillsheets</a>
         <ul class="menu" style="width: 300px;">';

	$list_of_skills .= "<li><a href=\"api.php?action=skills_admin\">Go back</a></li>";
	
	// get all filters
	$sql = "SELECT id, name, `desc` FROM skill_filter ORDER BY name ASC";
	$res = $db->query($sql);

	while ($row = $res->fetch_array())
	{
		$list_of_skills .= "<li><a href=\"api.php?action=skills_admin&edit=" . $row['id'] . "\">" . $row['name'] . "</a>";
	}
	
	
	$list_of_skills .= '</ul></li>';



	// get the id
	$id = intval($_REQUEST['edit']);

	// check if something was changed
	if (isset($_REQUEST['typeID']))
	{
		$edit_typeID = intval($_REQUEST['typeID']);

		if (isset($_REQUEST['newLevel']))
		{
			$newLevel = intval($_REQUEST['newLevel']);

			$sql = "INSERT INTO skill_filter_skills (minLevel, typeID, filter_id) VALUES ($newLevel, $edit_typeID, $id) ON DUPLICATE KEY UPDATE  minLevel = $newLevel";
			if (!$db->query($sql))
			{
				echo $db->error;
				echo "Query was: '$sql'\n";
				exit();
			} 
		}
	} else {
		$edit_typeID = 0;
	}


	$sql = "SELECT id, name, `desc` FROM skill_filter WHERE id = $id";

	$res = $db->query($sql);
	if ($res->num_rows == 1)
	{
		$row = $res->fetch_array();

		base_page_header('', 'Skills Admin - ' . $row['name'], 'Skills Admin - ' . $row['name'], $list_of_skills);


		$sql = "SELECT k.groupID, k.groupName, k.typeName, k.typeID, k.rank, f.minLevel, k.published FROM invSkills k
		LEFT JOIN skill_filter_skills f ON f.filter_id=$id AND f.typeID = k.typeID
		WHERE k.published = 1
		ORDER BY k.groupName, k.typeName";
		$res = $db->query($sql);

		$lastGroupID = -1;


		echo "<table>
		<thead>
			<th>Skill name</th><th>Required Level</th><th>Options</th>
		</thead>
		<tbody>";

		while ($row = $res->fetch_array())
		{
			$groupID = $row['groupID'];
			$groupName = $row['groupName'];
			$typeID = $row['typeID'];
			$skill_name = $row['typeName'];		
			$level = $row['minLevel'];
			
			if ($level === NULL)
				$level = -1;

			if ($lastGroupID != $groupID && $groupName != 'Learning' && $groupName != 'Fake Skills')
			{
				echo "<tr><th colspan=\"3\"><a id=\"group$groupID\">&nbsp;</a>
					Group: $groupName
					</th></tr>\n";
			}
			
			$lastGroupID = $groupID;


			if ($typeID == $edit_typeID)
			{
				echo "<tr style=\"background-color: lightblue\">";
			} else {
				echo "<tr>";
			}


			echo "<td><a name=\"$typeID\" href=\"http://games.chruker.dk/eve_online/item.php?type_id=$typeID\" target=\"_blank\">$skill_name</a></td>";

			if ($level == -1 || $level == 0)
				echo "<td>Not req.</td>";
			else
				echo "<td>$level</td>";

			// print options
			echo "<td>";
			echo "New Level: 
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=0#group$groupID\">0</a>
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=1#group$groupID\">1</a>
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=2#group$groupID\">2</a>
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=3#group$groupID\">3</a>
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=4#group$groupID\">4</a>
				<a href=\"api.php?action=skills_admin&edit=$id&typeID=$typeID&newLevel=5#group$groupID\">5</a>
				";				


			echo "</td>";

			echo "</tr>";
		}



		echo "</tbody>
		</table>";


	} else {
		echo "Error: Skill Filter with id $id not founD! - please report this error to IT.";
	}


}


base_page_footer();

?>