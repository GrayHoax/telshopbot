<?php
date_default_timezone_set('Europe/Moscow');
//'389065268:AAGYZ38NojXe1yVUSeIwRVBI-GVKrOljcVU', 'bazovskaya_clerk_bot'
$BOT_API_KEY = "386254582:AAEDCiN0l6Ktj85fHocK8-6uMlFMrz0nuFM";
$BOT_LOGIN = "bazovskaya_shop_bot";

	$mcD_host = "127.0.0.1";
	$mcD_port = "11211";
        $mcD_pref = "shop_";
	
	define('BOTLOG', __DIR__ . '/botlog.actions');
	define('DEEPLOG', false);
	
	$db_host = "localhost";
	$db_user = "telegram_shop";
	$db_pass = "npUznvVQgZ0UJ10I";
	$db_base = "telegram_shop";
	
	define("MODERCHAT", -1001143921640);

	$options = [
		'parse_mode' => 'html',
		'reply_markup' => ['inline_keyboard' => []],
	];

?>