<?php
/*************************************************
* SM3LL.net API HTML Code                        *
* Menu, Styles, ... - to be replaced by a        *
* Template Engine soon!                          *
*************************************************/





/** write HTML code for the basic layout - header
 * */
function base_page_header($custom_javascript,$page_head_title,$title, $right_top_menu_extra='')
{
	global $SETTINGS;

	$forum_url=CORP_FORUM_BASE;
	$kb_url=KB_BASE;

    print<<<EOF
<!DOCTYPE HTML>
<html class="no-js" lang="en">
    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <title>$SETTINGS[site_name] - $page_head_title</title>
        <link href="styles/foundation-6/css/foundation.min.css" rel="stylesheet">
        <script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
        <script src="styles/foundation-6/js/foundation.min.js"></script>
        <link rel="stylesheet" type="text/css" href="js/chosen/chosen.css">

        <link rel="shortcut icon" href="images/favicon.ico" />

			<style type="text/css">

			.dropdown li:not(.has-form):not(.active):hover > a:not(.skip_hover_color) {
			color: #000000;
			background-color: #D8D8D8 ;
			font-weight: bold;
			}
			.mydropdown li:not(.has-form):not(.active):hover > a:not(.skip_hover_color) {
			color: #000000;
			background-color: #aaaaaa ;
			font-weight: bold;
			}
			.mysubmenu li {
				margin-left: 25px;
			}
			.off-canvas, .title-bar {
			    background-color: #242323;
			    color: #ffffff;
			}
			tbody tr:nth-child(even)
			{
			    background-color: #efefef;
			}
			tbody tr:nth-child(odd)
			{
			   background-color: #cfcfcf;
			}
			th
			{
			    background-color: #818181;
			    color: #000000;
			}


			</style>
			<script src="js/tablesorter/jquery.tablesorter.min.js"></script>
			<script src="js/chosen/chosen.jquery.min.js"></script>
			<script src="js/utils.js"></script>
			<script>
			// render eve time
            function startTime() {
                var today = new Date();
                var h = today.getUTCHours();
                var m = today.getUTCMinutes();
                var s = today.getUTCSeconds();
                m = checkTime(m);
                s = checkTime(s);
                document.getElementById('curTime').innerHTML =
                h + ":" + m + ":" + s;
                var t = setTimeout(startTime, 500);
            }
            function checkTime(i) {
                if (i < 10) {i = "0" + i};  // add zero in front of numbers < 10
                return i;
            }
            </script>
			$custom_javascript			
		</head>
		<body onload="startTime()">
		    <div class="off-canvas-wrapper">
		        <div class="off-canvas-wrapper-inner" data-off-canvas-wrapper>
EOF;

    create_left_menu();

    //draw_actionbar();
    print<<<EOF
    <!-- content in the middle -->
				<div class="off-canvas-content" style="height: 100%" data-off-canvas-content>
					<!-- title bar -->
					<div class="title-bar">
						<div class="title-bar-left">
							<ul class="dropdown menu" data-dropdown-menu>
								<li>
									<button class="menu-icon hide-for-large" type="button" data-toggle="offCanvasLeft"></button>
								</li>
EOF;
    create_notifications();

    create_character_chooser();

    echo "<li>$right_top_menu_extra</li>";

    print<<<EOF
                            </ul>
						</div>
                        <div class="title-bar-right">

EOF;
	if (isset($_COOKIE['API_ASUSER'])) {
            echo "<a href=\"api.php?action=asume_user\">Reset Permissions?</a> ";
    }

    print<<<EOF
					    <h4> <a href="$forum_url">Forums</a></h4>
						</div>
					</div>
					<!-- title bar ended above -->

					<h3 style="margin: 15px">$title</h3>
					<hr style="margin: 15px" >
					<div class="row column" style="margin: 5px; overflow-x:scroll; " >
					<!-- CONTENT GOES AFTER THIS -->
EOF;
}


function create_character_chooser()
{
    global $SETTINGS, $registered_characters;

    if (sizeof($registered_characters) > 0)
    {
        echo '<li><a class="skip_hover_color" href="#">Your Characters</a>
         <ul class="menu" style="width: 300px;">';
        foreach ($registered_characters as $character_id => $character_name)
        {
            echo "<li><a href=\"api.php?action=char_sheet&character_id=" . $character_id . "\">
                    <img src=\"//imageserver.eveonline.com/Character/" . $character_id . "_32.jpg\"> " . $character_name . "</a>";
        }
		echo '</ul></li>';
    }
}


function create_notifications()
{
    global $SETTINGS;



    $db = connectToDB();

    $sql = "SELECT notification_id, datetime, message, responsible_user_Id FROM `player_notification` WHERE unread = 1 AND user_id = " . $GLOBALS['userid'] . " ORDER BY datetime DESC ";

    $res = $db->query($sql);


    echo '<li><img src="';

    if ($res->num_rows == 0)
    {
        echo 'images/notifications_0.png';
    } else {
        echo 'images/notifications_available.png';
    }

    echo '" width="32" height="32">
            <ul class="menu" style="width: 200px;">';

    if ($res->num_rows != 0) {
        while ($row = $res->fetch_array()) {
            $notification_id = $row['notification_id'];
            $msg = $row['message'];
            echo "<li><a href=\"api.php?action=show_notifications#" . $notification_id . "\">$msg</a>";
        }
    }
    else {
        echo "<li><a href=\"api.php?action=show_notifications\">No new notifications</a>";
    }


    echo '</ul></li>';
}



function create_left_menu_item_if_allowed($action_name, $url, $title, $allowed_pages)
{
    if (in_array($action_name, $allowed_pages)) {
        echo "<li><a href=\"$url\">$title</a></li>";
    }
}


function create_left_menu()
{
    global $SETTINGS, $registered_characters, $allowed_pages;

    $db = connectToDB();

    print <<<EOF
                <div class="off-canvas position-left reveal-for-large" id="offCanvasLeft" style="height: 100%"  data-off-canvas>
					<div style="text-align: center">
EOF;
    // handle main character / select main character

    if ($GLOBALS['existing_main'] > 1)
    {
        echo "<a href=\"api.php?action=main\"><img class=\"thumbnail\" src=\"//imageserver.eveonline.com/Character/" . $GLOBALS['existing_main'] ."_128.jpg\" /></a>";
        echo "<h5>Welcome, " . $GLOBALS["existing_main_name"] . "!</h5>";
    } else {
        if (sizeof($registered_characters) == 0)
        {
            // differentiate between users that have APIs registered and therefore can select a main character
            echo "<a href=\"api.php?action=main\"><img class=\"thumbnail\" src=\"images/aura.png\" width=\"128\" height=\"128\"/></a>";
            echo "<p>Please <a href=\"api.php?action=user_api_keys\">add a valid API Key</a>!</p>";

        } else {
            // differentiate between users that have APIs registered and therefore can select a main character
            echo "<a href=\"api.php?action=main\"><img class=\"thumbnail\" src=\"images/aura.png\" width=\"128\" height=\"128\"/></a>";
            echo "<p>Please <a href=\"api.php?action=select_main\">select your main character</a></p>";
        }


        // or users that do not have an API registered yet
    }

    // determine last API Pull time
    $sql = "SELECT max(curtime) as maxtime FROM `api_call_stats`";
    $res = $db->query($sql);
    $row = $res->fetch_array();

    $last_api_pull_time = $row['maxtime'];


    // print eve time
    echo "<p>EvE Time: <span id=\"curTime\">" . gmdate("H:i:s") . "</span> (UTC)<br />";
    echo "Last API Pull: $last_api_pull_time</p></div>";


    echo '<ul class="vertical menu mydropdown" data-accordion-menu>
			<!-- start of the menu -->
                <li>
                    <a><img src="images/api_keys.png" width="32" height="32">Account</a>
                    <ul class="vertical menu mysubmenu">';


	create_left_menu_item_if_allowed('user_settings', 'api.php?action=user_settings', 'Settings', $allowed_pages);
    create_left_menu_item_if_allowed('user_api_keys', 'api.php?action=user_api_keys', 'API Keys', $allowed_pages);
    create_left_menu_item_if_allowed('service_accounts', 'api.php?action=service_accounts', 'Service Accounts', $allowed_pages);
    create_left_menu_item_if_allowed('your_groups', 'api.php?action=your_groups', 'Groups', $allowed_pages);
    create_left_menu_item_if_allowed('user_settings', 'api.php?action=user_settings', 'Settings', $allowed_pages);
    create_left_menu_item_if_allowed('show_notifications', 'api.php?action=show_notifications', 'Notifications', $allowed_pages);

    echo '</ul>
       </li>';


    if (in_array("your_characters", $allowed_pages))
    {
        echo '<li><a><img src="images/your_characters.png" width="32" height="32">Member Functions</a>';
        echo '<ul class="vertical menu mysubmenu">';

        create_left_menu_item_if_allowed('your_characters', 'api.php?action=your_characters', 'Your Characters', $allowed_pages);
        // TODO: Assets
        create_left_menu_item_if_allowed('my_supers', 'api.php?action=my_supers', 'Your Capitals', $allowed_pages);
        create_left_menu_item_if_allowed('shop', 'api.php?action=shop', 'Shop', $allowed_pages);

        echo '</ul></li>';
    }


    if (in_array("corp_wallet", $allowed_pages) || in_array("starbases", $allowed_pages)
        || in_array("human_resources", $allowed_pages) || in_array("shop_admin", $allowed_pages) || in_array("group_admin", $allowed_pages))
    {
        echo '<li><a><img src="images/corp_keys.png" width="32" height="32">Management Area</a>';
        echo '<ul class="vertical menu mysubmenu">';

        create_left_menu_item_if_allowed('show_corp_keys', 'api.php?action=show_corp_keys', 'Corp API Keys', $allowed_pages);
        create_left_menu_item_if_allowed('human_resources', 'api.php?action=human_resources', 'Human Resources', $allowed_pages);
        create_left_menu_item_if_allowed('corp_wallet', 'api.php?action=corp_wallet', 'Accounting', $allowed_pages);
        create_left_menu_item_if_allowed('starbases', 'api.php?action=starbases', 'Starbases', $allowed_pages);
        create_left_menu_item_if_allowed('corp_office', 'api.php?action=corp_office', 'Corp Assets', $allowed_pages);

        // TODO: Group Admin
        create_left_menu_item_if_allowed('group_admin', 'api.php?action=group_admin', 'Group Admin', $allowed_pages);


        create_left_menu_item_if_allowed('shop_admin', 'api.php?action=shop_admin', 'Shop Admin', $allowed_pages);
        create_left_menu_item_if_allowed('admin_fleet_tracker', 'api.php?action=admin_fleet_tracker', 'Fleet Tracker', $allowed_pages);
        create_left_menu_item_if_allowed('timerboard', 'api.php?action=timerboard', 'Timerboard', $allowed_pages);
        create_left_menu_item_if_allowed('staging_system', 'api.php?action=staging_system', 'Staging System', $allowed_pages);

        create_left_menu_item_if_allowed('skills_admin', 'api.php?action=skills_admin', 'Skills Admin', $allowed_pages);

        echo "</ul></li>";
    }


    if (in_array("cron_jobs", $allowed_pages) || in_array("ts3_admin", $allowed_pages) || in_array("group_superadmin", $allowed_pages))
    {
        echo '<li><a><img src="images/dangerzone.png" width="32" height="32">Danger Zone (Admin)</a>';
        echo '<ul class="vertical menu mysubmenu">';

        create_left_menu_item_if_allowed('cron_jobs', 'api.php?action=cron_jobs', 'Cron Jobs', $allowed_pages);
        create_left_menu_item_if_allowed('group_superadmin', 'api.php?action=group_superadmin', 'Group Settings', $allowed_pages);
        create_left_menu_item_if_allowed('member_audit_forum', 'api.php?action=member_audit_forum', 'Forum Members', $allowed_pages);
        create_left_menu_item_if_allowed('ts3_admin', 'api.php?action=ts3_admin', 'TS3 Admin', $allowed_pages);
        create_left_menu_item_if_allowed('admin_entities', 'api.php?action=admin_entities', 'Allowed Entities', $allowed_pages);

        echo "</ul></li>";
    }

    /*



						<li>
							<a><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/User-admin-gear.svg/200px-User-admin-gear.svg.png" width="32" height="32">Admin Area</a>
							<ul class="vertical menu mysubmenu">
								<li><a href="#">Cron Jobs</a></li>
								<li><a href="#">Error Log</a></li>
								<li><a href="#">Group Admin</a></li>
								<li><a href="#">TS3</a></li>
								<li><a href="#">Allowed Entities</a></li>
							</ul>
						</li>



*/

    echo '</ul></div>';


}


				
function base_page_footer($show_home='',$alt_backlink='')
{
	global $isAdmin, $action, $start_mtime;

    $currentYear = date("Y");


    $debugInfos = "";



    if ($isAdmin == true) {
        // get all groups that have access to this page
        $sql = "SELECT a.group_id, g.group_name FROM api_page_access a, groups g WHERE a.page_name = '$action' AND a.group_id = g.group_id ";

        $sth = db_action($sql);
        $group_str = "";
        while ($result = $sth->fetch_array()) {
            $group_name = $result['group_name'];

            $group_str .= $group_name . ", ";
        }

        $debugInfos = "<br />Groups allowed: $group_str";




        $end_mtime = microtime(true);
        $diff = $end_mtime - $start_mtime;

        $debugInfos .= "<br />This page was created in $diff s.<br />";
    }



    print<<<EOF
               <br /> </div>
					<div class="top-bar bottom-bar">
						<div class="top-bar-right">(c) 2013 - $currentYear Burning Napalm IT Services $debugInfos</div>
					</div>
				</div>
			</div>
		</div>
		<script>
			$(document).foundation();
		</script>
	</body>
</html>


EOF;
}



function error_page($error_header,$error_text,$suppress_back=0)
{
	global $SETTINGS;
	
	base_page_header('',"API Services Portal Error","API Services Portal Error");
	print("<div id='error_header'>$error_header</div>");
	print("<div id='error_text'>$error_text</div>");
	if($suppress_back==0) {print("<span id='backlink' onClick='history.back();'>Back</span>");}
	base_page_footer('1','');
}

function draw_actionButton($text, $link, $image, $class) {
	global $headerStr, $action;
	echo $headerStr;
	$headerStr = "";
	if (strpos($link, $action) !== false)
		$text = "<i>$text</i>";
	echo("<li class='$class'><a href='$link' class='actionbar_href' style=\"font-weight: normal; \"><img class='actionbar_image' src='images/$image'/>$text</a></li>");
}


function draw_actionSubButton($text, $link, $image, $class) {
	global $headerStr, $action;
	echo $headerStr;
	$headerStr = "";
	if (strpos($link, $action) !== false)
		$text = "<i>$text</i>";
	echo("<li class='$class'><a href='$link' class='actionbar_href' style=\"font-weight: normal; \"><img class='actionsubbar_image' src='images/$image'/>$text</a></li>");
}






?>
