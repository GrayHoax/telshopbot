<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

    require_once 'config.php';
    require_once 'shop.class.php';
    require_once 'vendor/autoload.php';
	
    use Medoo\Medoo;

    $bot = new PHPTelebot($BOT_API_KEY, $BOT_LOGIN);
    
    $memcacheD = new Memcached;
    $memcacheD->addServer($mcD_host, $mcD_port);
    $memcacheD->setOption(Memcached::OPT_PREFIX_KEY, $mcD_pref);
    
    $shop = new Shop(true, $memcacheD);
    $shop->debug_file = BOTLOG;
    $shop->db = new Medoo([
        'database_type' => 'mysql',
        'database_name' => $db_base,
        'server' => $db_host,
        'username' => $db_user,
        'password' => $db_pass,
        'charset' => 'utf8mb4',
        "logging" => (defined('DEEPLOG')) ? DEEPLOG : false,
    ]);

    if (php_sapi_name() === "cli") {
        $products = $shop->GetProducts();
        foreach($products as $product) {
            if ($product['message_id'] == '') {
                TemplatingProduct($product); // TODO Get product template id from owner table
            }
        }
    }
    
$bot->cmd('/start', function() {
    global $memcacheD, $shop;
    $m = Bot::message();
    $shop->SetUserID($m['from']['id']);
    if (!$shop->hasUser()) {
        $shop->CreateUser(
            $m['from']['id'],
            $m['from']['username'],
            $m['from']['first_name'],
            $m['from']['last_name']
        );
    }
    
    $products = $shop->GetCart();
    if (sizeof($products) > 0) {
        foreach ($products as $product) {
            TemplatingProduct($product, 3, true);
        }
    } else {
        $text = $shop->GetString(2);
        $i_res = Bot::sendMessage($text[0]['spf_ru'], $options);
        $shop->MessageWorker($i_res, true);
    }
});

$bot->cmd('/create', function() {
    global $memcacheD, $shop;
    $m = Bot::message();
    $shop->SetUserID($m['from']['id']);
    /*
     * TODO Creating data
     */
});

$bot->on('callback', function($data) {
    $m = Bot::message();
    $shop->SetUserID($m['from']['id']);
    if (!$shop->hasUser()) {
        $shop->CreateUser(
            $m['from']['id'],
            $m['from']['username'],
            $m['from']['first_name'],
            $m['from']['last_name']
        );
    }
    list($command, $parameter) = explode('_', $data);
    switch ($command) {
        case "INFO":
            break;
        case "BUY":
            break;
        case "UNBUY":
            break;
        case "CART":
            break;
        case "DEL":
            break;
    }
});

function TemplatingProduct($product, $template = 1, $is_cart_mode = false) {
    global $shop;
    $options = [];
    
    $cart_button = round($product['oneoff'], 3) . ' ' . $product['units'];
    $inline_cart_button = ($is_cart_mode) ? ['text' => json_decode('"\u274c"'), 'callback_data' => 'DEL_' . $product['id']] : ['text' => json_decode('"\u2753"'), 'callback_data' => 'CART_' . $product['id']];
    $description_button = ($product['url'] == '') ? ['text' => 'Описание', 'callback_data' => 'INFO_' . $product['id']] : ['text' => 'Описание', 'url' => $product['url']];
    
    if (!$is_cart_mode) $options['chat_id'] = $product['chat_id'];
    $options['disable_notification'] = true;
    $options['reply_markup'] = ['inline_keyboard' => []];
    $options['reply_markup']['inline_keyboard'][] = [
        ['text' => json_decode('"\u2795"') . ' ' . $cart_button, 'callback_data' => 'BUY_' . $product['id']],
        ['text' => json_decode('"\u2796"') . ' ' . $cart_button, 'callback_data' => 'UNBUY_' . $product['id']]
    ];
    error_log(print_r($product, true));
    error_log(print_r($options, true));
    $text = $shop->GetString($template);
    if ($product['image'] == '') {
        $i_res = Bot::sendMessage(sprintf($text[0]['spf_ru'], $product['title'], $product['count'], $product['units']), $options);
        $shop->MessageWorker($i_res, true);
    } else {
        $options['reply_markup']['inline_keyboard'][] = [
            $inline_cart_button,
            $description_button
        ];
        $options['caption'] = sprintf($text[0]['spf_ru'], $product['title'], $product['count'], $product['units']);
        $i_res = Bot::sendPhoto($product['image'], $options);
        $shop->MessageWorker($i_res, true);
    }
}
file_put_contents(BOTLOG, $shop->debug, FILE_APPEND);
$bot->run();