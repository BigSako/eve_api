<?php


	do_log("Entered account_details",5);
	base_page_header('','Your Account','Your Account');
	$email=$GLOBALS["email"];
print<<<EOF
<form method=post action='api.php'>
<input type=hidden name=action value='update_account'>
<div><div class="account_details_1">Email Address:</div><div class="account_details_2"><input type=text name=email value='$email'></div><div class="account_details_3"/></div>
<div><div class="account_details_1">New Password:</div><div class="account_details_2"><input type=password name=password></div><div class="account_details_3">( Leave blank if you don't want to change password )</div></div>
<div><div class="account_details_1">Confirm New Password:</div><div class="account_details_2"><input type=password name=password_confirm></div><div class="account_details_3">( Leave blank if you don't want to change password )</div></div>
<div><input type=submit></div>
</form>
EOF;
	base_page_footer('1','');


?>