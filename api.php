<?php
/*************************************************
* SM3LL.net API Main File                        *
* Module Code, main Page ...                     *
*************************************************/

// "simple" check for CSRF
if (isset($_SERVER["HTTP_ORIGIN"]))
{
    $address = "http://".$_SERVER["SERVER_NAME"];
	$address_secure = "https://".$_SERVER["SERVER_NAME"];
    if (strpos($address, $_SERVER["HTTP_ORIGIN"]) !== 0 && strpos($address_secure, $_SERVER["HTTP_ORIGIN"]) !== 0) {
        exit("CSRF protection in POST request: detected invalid Origin header: ".$_SERVER["HTTP_ORIGIN"]);
    }
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

// another check for CSRF
if (isset($_SERVER["HTTP_REFERER"]))
{
	$address = "http://".$_SERVER["SERVER_NAME"];
	$address_secure = "https://".$_SERVER["SERVER_NAME"];
    if (!startsWith($_SERVER["HTTP_REFERER"], $address) && !startsWith($_SERVER["HTTP_REFERER"], $address_secure)) // strpos($address, $_SERVER["HTTP_REFERER"]) !== 0 && strpos($address_secure, $_SERVER["HTTP_REFERER"]) !== 0) {
    {
        exit("CSRF protection in POST request: detected invalid REFERER header: ".$_SERVER["HTTP_REFERER"]);
    }
}




require('config.php');

define('LOGFILE',LOGDIR ."web.log");
define('DEBUG',9);


$total_api_calls = 0;
$total_failed_api_calls = 0;


$start_mtime = microtime(true);


require('funcs/basics.php');
init(); // includes all the things

	// include forum stuff
	switch ($SETTINGS['forum_type'])
	{
		case 'vbulletin':
			include('funcs/basics_vbulletin.php');
			break;
		case 'phpBB':
			include('funcs/basics_phpbb.php');
			break;

	}





$db = connectToDB();





// Obtain user IP as global variable
$ip = $_SERVER['REMOTE_ADDR'];

if (isset($_REQUEST["action"])) {
	$action=$db->real_escape_string($_REQUEST["action"]);
} else {
	$action = "main";
}

if ($action == "")
	$action = "main";


$request_url = $_SERVER['REQUEST_URI'];

// check if user is  authorized
if (!get_user_details())
{
	// if user is not authorized, forward him to the forum and set a redirect cookie flag
	setcookie("redirectTo", $request_url, time()+600, "/api/", $SETTINGS['base_url']);

	header("Location: $SETTINGS[forum_auth_redirect]" . time());

	exit;
}

// check if user is actually authorized - shouldn't happen
if ($userid < 1)
{
	error_page("Not authorized","Please report this error to IT team.");
	exit;
}



// check if user needs to be redirected
if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] == 'true' && isset($_COOKIE['redirectTo']))
{
	$redir = $_COOKIE['redirectTo'];

	// prevent infinite redirect loop
	if (strpos($redir, 'redirect') > 0)
	{

	} else {

	unset($_COOKIE['redirectTo']);
	setcookie('redirectTo', FALSE, -1, "/api",  $settings['base_url']);

	header("Location: " . $redir);
	exit;
	}

}

// get all allowed pages for this user
$allowed_pages = getAllAllowedPagesForUser($userid);

IP_LOG();


// determine whether user is allowed to access this page or not


$allowed = getPageAccessForThisUser($action);


if (!$allowed)
{
    if (isset($_SERVER['HTTP_REFERER']))
        $referer = $_SERVER['HTTP_REFERER'];
    else
        $referer = "";
    $debugtext = "Action='$action',userid='$userid',FullURL='" . $_SERVER['REQUEST_URI'] . "',Referer='" . $referer . "'";


    $debugtext = base64_encode ($debugtext);

	error_page("Invalid action", "You do not have access to this area.
	Please notify the IT team if you believe that you should have access to this area and provide them with the information from the textbox below. Thank you.
	<textarea>$debugtext</textarea>
	",1);
	exit;
}

include("modules/" . $action . ".php");


?>
