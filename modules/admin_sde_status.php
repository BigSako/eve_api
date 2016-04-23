<?php
ini_set('memory_limit', '-1');
	$title = "Update EvE Static Data (SDE)";
	base_page_header('',$title,$title, "");


	$filename = "mysql-latest.tar.bz2";
	$mysql_dump_url = "https://www.fuzzwork.co.uk/dump/mysql-latest.tar.bz2";
	$path = TMPDIR . "/" . $filename;

	if (isset($_REQUEST['delete']))
	{
		unlink($path);
	}


	// step 1: check if there is a downloaded file for this
	// step 1a: if yes, tell the user that there is, print a date, ask if he wants to delete it
	if (file_exists($path) && !isset($_REQUEST['continue']))
	{
		echo "There is an update file already in place, it was created at " . date("F d Y H:i:s", filemtime($path)) . "<br />";
		echo 'Click <a href="api.php?action=admin_sde_status&continue=1">here</a> if you want to continue with this file, or click <a href="api.php?action=admin_sde_status&delete=1">delete</a>  to delete that file!<br />';
	} else {
		if (!isset($_REQUEST['continue']))
		{
			// step 2: if no, download that file
			echo 'Relying on <a href="' . $mysql_dump_url . '">fuzzwork link</a> to be up, check manually if unsure... ';
			echo "Downloading file to $path... Please wait...<br />";
			flush();

			$ch = curl_init($mysql_dump_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$data = curl_exec($ch);

			curl_close($ch);

			file_put_contents($path, $data);

			echo "Done !<br />";
			flush();
		} else {
			echo "Using already existing file $path<br />";
		}

		$sde_dir = TMPDIR . "/sde";

		if (!file_exists($sde_dir)) {
			echo "ERROR: $sde_dir does not exist, trying to create it...<br/>";
			mkdir($sde_dir);
		}

		echo "Trying to open with bzip2<br />";
		echo shell_exec("tar -xjf $path -C " . $sde_dir);

		echo "The following SDE files are available now at $sde_dir:<br />";

		// step 3: once finished, check for a directory starting with sde
		foreach (glob($sde_dir . "/*") as $filename) 
		{
			$last_slash = strrpos($filename, '/')+1;
			$new_filename = substr($filename, $last_slash);
			echo $new_filename . "<br />";
			foreach (glob($sde_dir . "/"  . $new_filename . "/*.sql") as $sqlfile) 
			{
				$last_str = strpos($sqlfile, $new_filename);
				$new_sql_file = substr($sqlfile, $last_str);
				echo "&nbsp; &gt; <a href=\"api.php?action=admin_sde_update&sqlfile=$new_sql_file\">$new_sql_file - Update now</a> (" . date("F d Y H:i:s", filemtime($sqlfile)) . ")<br />";
			}
		}
	
	}


	

	base_page_footer('', '');

?>
