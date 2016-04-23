<?php

$superuser = false;
$admin = false;
$max_jumps = 10;

$corp_id = $SETTINGS['main_corp_id'];

$db = connectToDB();
$user_id = $GLOBALS['userid'];

$system_name = $db->real_escape_string($_REQUEST['system_name']);

// check if is allowed to do more (= superuser)
if (in_array(2, $group_membership))
{
	$superuser = true;
	$admin     = true;
	$max_jumps = 25;
} else {
	// check if group member of cyno locator
	$group = 18;

	//echo "SQL='SELECT COUNT(*) as cnt FROM group_membership WHERE group_id = 18 AND user_idd = $userid AND state=0'";
	
	$res = $db->query("SELECT COUNT(*) as cnt FROM group_membership WHERE group_id = 18 AND user_id = $userid AND state=0");
	$row = $res->fetch_array();
	if ($row['cnt'] == 1)
	{
		$superuser = true;
		$max_jumps = 25;
	}	
}


$action2 = $_REQUEST['action2'];

if ($action2 == "")
{
	$title = "Locator";
} else if ($action2 == "dotlan")
{
	$title = "Showing offices close to route from dotlan link";
} else if ($action2 == "find")
{
	$title = "Find Location";
}


//base_page_header('<script src="js/jquery-2.0.3.min.js"></script><script src="js/chosen.jquery.min.js"></script>',$title,$title);
base_page_header('',$title,$title);

echo "<ul>";
echo "<li><a href=\"api.php?action=your_characters&filter=cynos\">Show my cyno toons</a></li>";

if ($SETTINGS['spartan_corp_id'] != '')
{
	echo "<li><a href=\"api.php?action=map&corp_id=" . $SETTINGS['spartan_corp_id'] . "\">Show Spartan Offices Map</a></li>";
}
// for every corp that we are in, check if there is an api key in and then print the office map link
// http://sm3ll.net/api/api.php?action=map&corp_id=1070320653
$sql = "SELECT DISTINCT a.corp_id, c.corp_name 
FROM `api_characters` a, corporations c, corp_api_keys k
WHERE a.user_id = $user_id AND a.corp_id = c.corp_id AND c.corp_id = k.corp_id AND c.state <= 1";

$res = $db->query($sql);

while ($row = $res->fetch_array())
{
	echo "<li><a href=\"api.php?action=map&corp_id=" . $row['corp_id'] . "\">Show " . $row['corp_name'] . " Offices Map</a></li>";
}




echo "<li>";

echo "<form method=\"post\" action=\"api.php?action=locator\">";

	 echo<<<EOF
 Locate in system:
<!--  <select name="system_name" data-placeholder="Choose a System..." class="chosen-select" style="width:350px;" tabindex="3"> -->
<select name="system_name" style="width:350px;" tabindex="3"> 
EOF;
$res = $db->query("SELECT solarSystemName FROM eve_staticdata.mapSolarSystems ORDER BY solarSystemName ASC");
while ($row = $res->fetch_array())
{
	if ($row['solarSystemName'] == $system_name)
	{
		echo "<option value=\"". $row['solarSystemName'] . "\" selected>" . $row['solarSystemName'] . "</option>\n";
	} else {
		echo "<option value=\"". $row['solarSystemName'] . "\">" . $row['solarSystemName'] . "</option>\n";
	}
}
	

echo<<<EOF

 </select>
 <script type="text/javascript">
 var config = {
   '.chosen-select'           : {no_results_text: "Oops, nothing found!"},
   '.chosen-select-deselect'  : {allow_single_deselect:true},
   '.chosen-select-no-single' : {disable_search_threshold:10},
   '.chosen-select-no-results': {no_results_text:'Oops, nothing found!'},
   '.chosen-select-width'     : {width:"95%"}
 }
 for (var selector in config) {
   $(selector).chosen(config[selector]);
 }
</script>
EOF;


echo "<br />
<input type=\"checkbox\" name=\"cyno\" value=\"1\"> Cynos only<br />";
if ($superuser == true)
{
	echo "<input type=\"checkbox\" name=\"superuser_no\" value=\"1\"> My characters only<br />";
	echo "<input type=\"checkbox\" name=\"corp_only\" value=\"1\"> Corp only<br />";
}
echo "<input type=\"hidden\" name=\"action2\" value=\"find\" />";
echo "<br /><input type=\"submit\" value=\"Search...\" /></form>";

echo "</li>";

if ($superuser == true)
{
	echo "<li><h3>Dotlan Link - Find offices close to jump route</h3>This is highly experimental, do not overuse.<br />";
	echo "Link must start with <b><a target=\"_blank\" href=\"http://evemaps.dotlan.net/jump/\">http://evemaps.dotlan.net/jump/</a></b><br />";
	echo "<form method=\"post\" action=\"api.php?action=locator\"><input type=\"hidden\" name=\"action2\" value=\"dotlan\" />";
	echo "Post dotlan link: <input size=\"80\" type=\"text\" name=\"link\" value=\"http://evemaps.dotlan.net/jump/Anshar,544,S/Jita:Hasateem\" /> ";
	echo "<input type=\"submit\" value=\"Try ...\" /></form></li>";
}

echo "</ul>";
echo "<hr />";



	
if ($action2 == "dotlan" && $superuser == true)
{
	echo "<h3>Showing offices close to route from dotlan link</h3>";

	$origLink = $_REQUEST['link'];	
	include("funcs/extras.php");
	
	if (! startsWith($origLink, "http://evemaps.dotlan.net/jump/"))
	{
		echo "Invalid link, needs to start with http://evemaps.dotlan.net/jump/";
		exit;
	} else {
		echo "<a href=\"$origLink\" target=\"_blank\">Dotlan Link</a><br />";
		
		
		include("funcs/simple_html_dom.php");
		
		$html = file_get_html($origLink);
		
		// find all system names
		$system_names = array();
		$cnt = 0;
		foreach($html->find('tr[class]') as $element) 
		{
			
			if ($element->class == "tlr0")
			{
				
				foreach ($element->find("a[href]") as $link)
				{
					if (startsWith($link->href, "/system/") && !endsWith($link->href, "/kills"))
					{
						$ll = str_replace("/system/", "", $link->href);
						$system_names[$cnt] = $ll;
						$cnt++;
						//echo $ll . "<br />\n";
					}
				}
				
			}
		}
		
		
		
		$spartan_corp_id = 1022115221;
		echo "<table id='your_api_keys' style=\"width: 100%\">";
		echo "<tr><th class=\"table_header\">Jump Point</th><th class=\"table_header\">Location</th><th class=\"table_header\">Jumps</th></tr>";
		
		
		for ($i = 0; $i < $cnt; $i++)
		{
			$system_name = $db->real_escape_string($system_names[$i]);
			$dotlan_system_name = str_replace(' ', '_', $system_name);
			// query this office
			$corp_offices_res = $db->query(get_offices_close_to($system_name, $spartan_corp_id, $max_jumps));
				
			if ($corp_offices_res->num_rows > 0)
			{
				$last_system_name = '';
				
				while ($office_row = $corp_offices_res->fetch_array())
				{
					$loc = $office_row['location'];
					$jumps = $office_row['jumps'];
					$solarSysName = $office_row['solarSystemName'];
					$dotlan_solarSysName = str_replace(' ', '_', $solarSysName);
					
					if ($last_system_name != $system_name) {
						echo "<tr><td><b><a href=\"api.php?action=locator&action2=find&system_name=$system_name\">$system_name</a></b></td>";
					} else {
						echo "<tr><td>&nbsp;</td>";
					}
					echo "<td>$loc";
					
					// let's see if we can edit the link so it includes the system
					if ($solarSysName != $system_name) {
						// if system name is already in, then it's easy, just need to replace it
						if (strstr($origLink, $dotlan_system_name)) {
							$new_link = str_replace($dotlan_system_name, $dotlan_solarSysName, $origLink);
							echo " [<a target=\"_blank\" href=\"$new_link\">Use this system</a>]";
						} else {
							// though if it is not in yet, it's more complicated...
						
						}
					}
					
					echo "</td><td>$jumps";

					if ($solarSysName != $system_name) {
						echo " [<a target=\"_blank\" href=\"http://evemaps.dotlan.net/route/$dotlan_solarSysName:$dotlan_system_name\">Show route</a>]";
					}
					
					echo "</td></tr>";
					
					$last_system_name = $system_name;
					
				}
				echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
			}
			else
			{
				echo "<td>$system_name</td><td colspan=\"2\">No Spartan offices within $max_jumps jumps.</td></tr>";
			}
			
		
			
		}
		
		echo "</table>";
		
		
	}
	
	base_page_footer('1','');
	
}
else if ($action2 == "find")
{
	
	if (strlen($system_name) < 3)
	{
		echo "System name too short.";
	} else {
		
	
		$cyno = intval($_REQUEST['cyno']);
		$superuser_no = intval($_REQUEST['superuser_no']);
		$corp_only = intval($_REQUEST['corp_only']);
		
		$title = "Locator - Find";
		
		$where = "1=1";
		
		if ($superuser == false || $superuser_no == 1)
		{
			$where = "u.user_id = $user_id";
			$title .= " my";
		} else {
			$title .= " all";
		}
		
		if ($cyno == 1)
		{
			$where .= " AND cyno_skill > 0 AND character_last_ship NOT IN ('Nyx', 'Hel', 'Aeon', 'Wyvern', 'Revenant', 'Avatar', 'Ragnarok', 'Erebus', 'Leviathan') ";
			$title .= " cynos ";
		} else {
			$title .= " characters ";
		}
		
		if ($corp_only == 1)
		{
			$where .= " AND a.corp_id = $corp_id ";
		}
		
		$title .= "close to $system_name";
		
		
		echo "<h3>$title</h3>";
		
		// check if we are supposed to show spartan offices
		if ($SETTINGS['spartan_corp_id'] != '')
		{
			// before we do anything, find offices for Spartan first
			$spartan_corp_id = $SETTINGS['spartan_corp_id'];
			
			$corp_offices_res = $db->query(get_offices_close_to($system_name, $spartan_corp_id, $max_jumps));
		
			if ($corp_offices_res->num_rows > 0)
			{
				echo "Showing Spartan offices close to $system_name (<a href=\"api.php?action=map&corp_id=" . $SETTINGS['spartan_corp_id'] . "\">show Spartan Offices Map</a>)<br />";
				echo "<table id='your_api_keys' style=\"width: 100%\">";
				echo "<tr><th class=\"table_header\">Location</th><th class=\"table_header\">Jumps</th></tr>";

				while ($office_row = $corp_offices_res->fetch_array())
				{
					$loc = $office_row['location'];
					$jumps = $office_row['jumps'];
					$solarSysName = $office_row['solarSystemName'];
					echo "<tr>";
					echo "<td>$loc</td><td>$jumps</td></tr>";
				}
				echo "</table><br />";
			}
			else
			{
				echo "No Spartan offices within $max_jumps jumps.<br />";
			}
		}
			
		
		// before we do anything, find offices for the main corp first
		$corp_id = $SETTINGS['main_corp_id'];
		
		$corp_offices_res = $db->query(get_offices_close_to($system_name, $corp_id, $max_jumps));
	
		if ($corp_offices_res->num_rows > 0)
		{
			echo "Showing Burning Napalm offices close to $system_name (<a href=\"api.php?action=map&corp_id=" . $corp_id . "\">Show Corp Offices Map</a>)<br />";
			echo "<table id='your_api_keys' style=\"width: 100%\">";
			echo "<tr><th class=\"table_header\">Location</th><th class=\"table_header\">Jumps</th></tr>";

			while ($office_row = $corp_offices_res->fetch_array())
			{
				$loc = $office_row['location'];
				$jumps = $office_row['jumps'];
				$solarSysName = $office_row['solarSystemName'];
				echo "<tr>";
				echo "<td>$loc</td><td>$jumps</td></tr>";
			}
			echo "</table><br />";
		}
		else
		{
			echo "No Corp offices within $max_jumps jumps.<br />";
		}
		
		
		
		

		
		$sql = "SELECT a.character_id, a.character_name, a.corp_name, a.user_id, a.character_location, a.character_last_ship, a.cyno_skill, s.jumps, u.user_name, u.has_regged_main " .
			"FROM api_characters a, eve_routing.sys_to_sys s, auth_users u " .
			"WHERE a.state<=10 AND character_location <> '' AND $where AND u.user_id = a.user_id AND s.jumps < $max_jumps AND " .
				"(  (character_location = s.a_name AND s.b_name LIKE '$system_name') OR (character_location = s.b_name AND s.a_name LIKE '$system_name') ) ORDER BY s.jumps LIMIT 20";
		
		//echo "SQL = '$sql' ";
		
		$res = $db->query($sql);
		
		echo "<table id='your_api_keys' style=\"width: 100%\">";
		echo "<tr><th class=\"table_header\">Character Name</th><th class=\"table_header\">Corp Name</th><th class=\"table_header\">Location</th><th class=\"table_header\">Ship</th><th class=\"table_header\">Cyno Skill</th></tr>";
			
		while ($row = $res->fetch_array())
		{
			$character_id = $row['character_id'];
			$character_name = $row['character_name'];
			$corp_name = $row['corp_name'];
			$user_id = $row['user_id'];
			$location = $row['character_location'];
			$cyno_skill = $row['cyno_skill'];
			$jumps = $row['jumps'];
			
			if ($admin == true) {
			$ship = $row['character_last_ship'];
			$forum_name = $row['user_name'];
			} else {
				$ship = "hidden";
				$forum_name = "hidden";
			}
			
			
			
			echo "<tr><td>";
			
			if ($admin == true)
			{
				echo "<a href=\"api.php?action=show_member&character_id=$character_id\">$character_name</a>";
			}
			else
			{
				echo "<a href=\"api.php?action=char_sheet&character_id=$character_id\">$character_name</a>";
			}
			if ($superuser == true && $superuser_no != 1)
			{
				echo "<br />Forum Name: $forum_name";
			}
			
			echo "</td><td>$corp_name</td><td>$location - $jumps jumps</td><td>$ship</td><td>$cyno_skill</td></tr>";
		}
		
		echo "</table>";
	
	}
	
	base_page_footer('','<br/><br/><a href=""api.php?action=locator">Back</a>');
	
}









?>
