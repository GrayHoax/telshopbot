<?php
date_default_timezone_set('Europe/Moscow');
//'389065268:AAGYZ38NojXe1yVUSeIwRVBI-GVKrOljcVU', 'bazovskaya_clerk_bot'
$BOT_API_KEY = "389065268:AAGYZ38NojXe1yVUSeIwRVBI-GVKrOljcVU";
$BOT_LOGIN = "bazovskaya_clerk_bot";

	$mcD_host = "127.0.0.1";
	$mcD_port = "11211";
	
	define('BOTLOG', __DIR__ . '/botlog.txt');
	define('DEEPLOG', false);
	
	$db_host = "localhost";
	$db_user = "clerk_bot";
	$db_pass = "npUznvVQgZ0UJ10I";
	$db_base = "clerk_bot";
	
	define("MODERCHAT", -1001143921640);

	$options = [
		'parse_mode' => 'html',
		'reply_markup' => ['inline_keyboard' => []],
	];

?>