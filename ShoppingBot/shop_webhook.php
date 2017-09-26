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
    $database = new Medoo([
        'database_type' => 'mysql',
        'database_name' => $db_base,
        'server' => $db_host,
        'username' => $db_user,
        'password' => $db_pass,
        'charset' => 'utf8mb4',
        "logging" => (defined('DEEPLOG')) ? DEEPLOG : false,
    ]);
    
    $memcacheD = new Memcached;
    $memcacheD->addServer($mcD_host, $mcD_port);
    $memcacheD->setOption(Memcached::OPT_PREFIX_KEY, $mcD_pref);
    
    $shop = new Shop(true, $memcacheD);
    
$bot->cmd('/start', function() {
    global $memcacheD, $shop;
    $m = Bot::message();
    $shop->SetUserID($m['message']['from']['id']);
    if (!$shop->hasUser()) {
        $shop->CreateUser(
            $m['message']['from']['id'],
            $m['message']['from']['username'],
            $m['message']['from']['firstname'],
            $m['message']['from']['lastname']
        );
    }
    
    $products = $shop->GetCart();
    if (sizeof($products) > 0) {
        foreach ($products as $product) {
            TemplatingProduct($product);
        }
    } else {
        
    }
});

function TemplatingProduct($product) {
    global $shop;
    $options = [];
    
    $cart_button = round($product['oneoff'], 3) . ' ' . $product['units'];
    $description_button = ($product['url'] == '') ? ['text' => 'Описание', 'callback_data' => 'INFO_' . $product['id']] : ['text' => 'Описание', 'url' => $product['url']];
    
    //$options['chat_id'] = $product['chat_id'];
    $options['disable_notification'] = true;
    $options['reply_markup'] = ['inline_keyboard' => []];
    $options['reply_markup']['inline_keyboard'][] = [
        ['text' => json_decode('"\u2795"') . ' ' . $cart_button, 'callback_data' => 'BUY_' . $product['id']],
        ['text' => json_decode('"\u2796"') . ' ' . $cart_button, 'callback_data' => 'UNBUY_' . $product['id']]
    ];
    
    if ($product['image'] == '') {
        $i_res = Bot::sendMessage(sprintf($shop->GetString(1), $product['title'], $product['count'], $product['units']), $options);
    } else {
        $options['reply_markup']['inline_keyboard'][] = [
            ['text' => json_decode('"\u2753"'), 'callback_data' => 'CART_' . $product['id']],
            $description_button
        ];
        $options['caption'] = sprintf($shop->GetString(1), $product['title'], $product['count'], $product['units']);
        $i_res = Bot::sendPhoto($product['image'], $options);
    }
}

$bot->run();