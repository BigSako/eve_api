<?php
	do_log("Entered asume_user",1);

	if (isset($_REQUEST['forum_id']) && $isSuperAdmin == true)
	{
		$forum_id = intval($_REQUEST['forum_id']);
		
		setcookie('API_ASUSER', $forum_id, time()+60*60*2, '/');
		//$_COOKIE['API_ASUSER'] = $forum_id;
		header('Location: api.php');
	} else
	{
		// revert to normal, delete cookie
		unset($_COOKIE['API_ASUSER']);
		setcookie('API_ASUSER', null, time()-3600, '/');
		
		header('Location: api.php');
	}


?>