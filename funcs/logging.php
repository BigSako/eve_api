<?php
/*************************************************
* SM3LL.net API Logging                          *
*                                                *
*************************************************/


function do_log($loglines,$debug_level)
{
	if($debug_level <= DEBUG){
	#Log Levels:
	# 0 = minimal logging
	# 8 = Logging of all DB queries that are performed
	# 9 = Maximum logging, and write to stdout as well
	if(isset($_SERVER['REMOTE_ADDR'])) {
		$remip=$_SERVER['REMOTE_ADDR'];
	} else {
		$remip = "local"; //$_SERVER['REMOTE_ADDR'];
	}

	file_put_contents(LOGFILE, date('Y/m/d H:i:s')." -$remip $loglines\n", FILE_APPEND | LOCK_EX);
	}
	
	if(DEBUG==10)
	{
		echo(date('Y/m/d H:i:s')." - $loglines\n");
	}
}



function IP_LOG()
{
	global $action;
	
	do_log("in IP_LOG()", 9);
	
	$db = connectToDB();
	
	$action = $db->real_escape_string($action);
	if (isset($_SERVER['HTTP_REFERER']))
		$referer = $db->real_escape_string($_SERVER['HTTP_REFERER']);
	else 
		$referer = '';
		
	$ipAddress = $db->real_escape_string($_SERVER['REMOTE_ADDR']);
	$user_id = $GLOBALS['userid'];
	$request_url = $db->real_escape_string($_SERVER['REQUEST_URI']);
	
	$db->query("INSERT INTO log (user_id, action, request_url, IP, httpReferer) " .
			"VALUES ($user_id, '$action', '$request_url', '$ipAddress', '$referer') ");
			
	/*
	do_log("INSERT INTO log (user_id, action, request_url, IP, httpReferer) " .
			"VALUES ($user_id, '$action', '$request_url', '$ipAddress', '$referer') ", 9); */
	
	
}





?>
