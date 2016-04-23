<?php
define('ADMIN_GROUP_ID', 2); // the admin group id (can do almost everything)
define('SUPERADMIN_GROUP_ID', 1042); // the superadmin group id (can do everything)

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'your_api');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'your_api');

// the forum db host
define('FORUM_DB_HOST', 'localhost');
define('FORUM_DB_PORT', 3306);
define('FORUM_DB_USER', 'your_forum');
define('FORUM_DB_PASSWORD', 'your_password');
define('FORUM_DB_NAME', 'your_forum');

// eve_staticdata (SDE dump) - needs write access
define('STATIC_DB_HOST', 'localhost');
define('STATIC_DB_PORT', 3306);
define('STATIC_DB_USER', 'eve_staticdata');
define('STATIC_DB_PASSWORD', '');
define('STATIC_DB_NAME', 'eve_staticdata');


define('DOMAIN', 'localhost');
define('CORP_FORUM_BASE', 'http://localhost/forum/');
define('KB_BASE', 'http://ENTERCORPNAME.eve-kill.net/');
define('BASE_PATH', '/var/www/vhosts/localhost/api/');
define('EXTERNAL_PATH', '/tmp/tmp_folder/');
define('BASE_URL', '/api/');
define('LOGDIR', EXTERNAL_PATH.'logs/');
define('TMPDIR', EXTERNAL_PATH.'temp/');

define('PHPBB_ROOT_PATH', '/var/www/forum/');
$phpbb_root_path = '/var/www/forum/';


define('SECRET_PHRASE', 'enter a secret phrase here and keep it secret! for ever!');

?>
