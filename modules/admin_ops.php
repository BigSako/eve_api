<?php
	if (isset($_REQUEST['m']))
		$m = $_REQUEST['m'];
	else 
		$m = "";
		
		
	
	$db = connectToDB();
	$title = "Ops Admin";
		
	switch ($m)
	{
		case '':
			$title = "Ops Admin - List all Ops";
			break;
		case 'add':
			$title = "Ops Admin - Add Op";
			break;
		case 'add2':
			$title = "Ops Admin - Adding Op...";
			break;
		case 'del':
			$title = "Delete Op?";
			break;
		case 'del2':
			$title = "Deleting Op";
			break;
		case 'edit':
			$title = "Edit Op";
			break;			
	}
	
	base_page_header('',$title, $title);
	
	
?>
<ul>
	<li><a href="api.php?action=admin_ops">List all Ops</a></li>
</ul>
<hr />
<?php

	echo "<b>Work in progress</b>";

	
	switch ($m)
	{
		case '':
			$sql = "SELECT op_id, name, created_time, created_user_id, op_time, allowed_parties, ping_jabber, ships FROM ops ORDER BY op_time DESC";
			$res = $db->query($sql);
			echo "<table>
				<tr><th>Op Name</th><th>Op Created</th><th>Start Time</th><th>Options</th></tr>
				";
			while ($row = $res->fetch_array())
			{
				$op_id = $row['op_id'];
				$name  = $row['name'];
				$created_time = $row['created_time'];
				$created_user_id = $row['created_user_id'];
				$op_time = $row['op_time'];
				$user_name = getUsername($created_user_id);
				
				
				echo "<tr><td><a href=\"api.php?action=admin_ops&op_id=$op_id&m=edit\">$name</a></td><td>$created_time by $user_name</td><td>$op_time</td>
					<td><a href=\"api.php?action=admin_ops&op_id=$op_id&m=edit\">Edit</a> | <a href=\"api.php?action=admin_ops&op_id=$op_id&m=del\">$name</a></td>
				</tr>";
			}
			
			echo "</table>";
			
			break;
		case 'add':
			echo "Please be aware that this functionality is still in beta test. Bugs might appear etc...<br />";
			
			echo "
				<form method=\"post\" action=\"api.php?page=admin_ops\">
				<table>
				<tr>
					<td>Name:</td><td> <input type=\"text\" name=\"op_name\" /> </td>
				</tr>
				
				<tr>
					<td>Op Time:</td><td> <input type=\"text\" name=\"op_time\" />  </td>
				</tr>
				<!--
				<tr>
					<td>Allowed Parties:</td><td> </td>
				</tr>
				-->
				<tr>
					<td>Ping Jabber:</td><td> <input type=\"checkbox\" name=\"ping_jabber\" value=\"1\" /> </td>
				</tr>
				
				<tr>
					<td>&nbsp;</td><td><input type=\"hidden\" name=\"action\" value=\"add2\">
					<input type=\"submit\" value=\"Add\"></td>
				</tr>
				
				</table>
				</form>
			";
			break;
		case 'add2':
			$title = "Ops Admin - Adding Op...";
			break;
		case 'del':
			$op_id = intval($_REQUEST['op_id']);
			$title = "Delete Op?";
			break;
		case 'del2':
			$op_id = intval($_REQUEST['op_id']);
			$title = "Deleting Op";
			break;
		case 'edit':
			$op_id = intval($_REQUEST['op_id']);
			$title = "Edit Op";
			break;			
	}
	

	base_page_footer('1','');
?>