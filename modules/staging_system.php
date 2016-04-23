<?php
	

	if (isset($_REQUEST['changeSystem']) && isset($_REQUEST['new_system_id']))
	{
		$new_system_id = intval($_REQUEST['new_system_id']);
		$sql = "UPDATE settings SET svalue= '$new_system_id' WHERE name='staging_system_id'; ";
		
		$db->query($sql);
		
		header('Location: api.php?action=main');
	}
	
	base_page_header('',"Staging System","Staging System");
	
	
	$staging = getStagingSystem();
	
	$staging_id = $staging[0];
	$staging_Name = $staging[1];
	
	echo "Current Staging System: <b>$staging_Name</b><br />";
	
	echo "<form method=\"post\" action=\"api.php?action=staging_system\">";
	echo "<input type=\"hidden\" name=\"changeSystem\" value=\"1\" >";

	echo "Change To: ";
	
	
	 echo<<<EOF
<select name="new_system_id" style="width:350px;" tabindex="3" class="chosen-select">
EOF;
$res = $db->query("SELECT solarSystemName,solarSystemID FROM eve_staticdata.mapSolarSystems ORDER BY solarSystemName ASC");
while ($row = $res->fetch_array())
{
	if ($row['solarSystemName'] == $staging_Name)
	{
		echo "<option value=\"". $row['solarSystemID'] . "\" selected>" . $row['solarSystemName'] . "</option>\n";
	} else {
		echo "<option value=\"". $row['solarSystemID'] . "\">" . $row['solarSystemName'] . "</option>\n";
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
	
	echo "<input type=\"submit\" value=\"Change!\" /></form>";



	echo "<br /><br /><br /><br /><br /><br /><br />";


	base_page_footer('','');
?>