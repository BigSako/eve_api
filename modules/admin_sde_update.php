<?php
	$title = "Update EvE Static Data (SDE)";


	$filename = "mysql-latest.tar.bz2";
	$mysql_dump_url = "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2";
	$path = TMPDIR . "/sde/" . $filename;

	if (!isset($_REQUEST['sqlfile']))
	{
		echo "param not set";
		//header('Location: api.php?action=admin_sde_status');
		exit();
	}

	$sqlfile = $_REQUEST['sqlfile'];
	if (strpos($sqlfile, "..") !== false)
	{
		// potential break in attempt cancel this
		header('Location: api.php');
		exit();
	}

	$path = TMPDIR ."/sde/" .  $sqlfile;

	if (file_exists($path))
	{
		$testpath = dirname($path);

		$position = strpos($testpath, TMPDIR);
		if ($position != 0)
		{
			// potential break in attempt - cancel this
			header('Location: api.php');
			exit();
		}
		base_page_header('',$title,$title, "");

		echo "Importing SQL file...<br />";
		// open connection to eve_staticdata database
		$static_db = connectToStaticDataDB();

		// Temporary variable, used to store current query
		$templine = '';
		// Read in entire file
		$handle = fopen($path, "r");

		// Loop through each line
		while (($line = fgets($handle)) !== false)
		{
			// Skip it if it's a comment
			if (substr($line, 0, 2) == '--' || $line == '')
			{
				continue;
			}

			// Add this line to the current segment
			$templine .= $line;
			// If it has a semicolon at the end, it's the end of the query
			if (substr(trim($line), -1, 1) == ';')
			{
				// Perform the query
				$res = $static_db->query($templine);
				if (!$res)
				{
					echo '<b>Error performing query</b> \'' . $templine . '\'<br/><b>Error was:</b> ' . $static_db->error . '<br />';
					exit();
				}
				set_time_limit(30);
				// Reset temp variable to empty
				$templine = '';
			}
		}
		fclose($handle);
		echo "Tables imported successfully<br />";

		
		
		base_page_footer();
	} else
	{
		echo "Path $path not found"; exit();
		header('Location: api.php?action=admin_sde_status');
		exit();
	}


	

?>
