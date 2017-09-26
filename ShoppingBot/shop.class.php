<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of shop
 *
 * @author IAgienko
 */
class Shop {

    public $db;
    public $debug;
    public $debug_file;
    
    public $current_message;
    
    private $Memcached_link;
    private $user_id;
    private $useMemcached; // TODO
    
    private $Memcache_TTL_default = 3600;
    private $Memcache_TTL = [
        'USER' => 3600,
        'USERCART' => 3600,
        'STRING' => 3600 * 24
    ];
    /*
    function __construct() {
        $this->Logging('Class Cunstructor');
    }
    */
    function Shop($useMemcached = false, $MemcachedLink = null) {        
        if ($this->useMemcached) {
            $this->Logging('[!] Using Memcached');
            $this->useMemcached = $useMemcached;
            $this->Memcached_link = $MemcachedLink;
        }        
    }
    
    function SetUserID($user_id) {
        $this->Logging('Defined UserID: ' . $user_id);
        $this->user_id = $user_id;
    }
    
    /*
     * Функция для добавления товара в корзину
     * Принимает идентификатор товара в БД и работает с ним
     * Возвращает массив:
     * 
     * Array (
     *      [count] => Количество товара в корзине
     *      [alert] => Количество доступного товара всего или false если товар не ограничен
     * 
     * );
     */
    function PutInCart($product_id) {
        if (false == ($product = $this->hasProductInCart($product_id))) {
            $this->Logging('Put ProductID ' . $product_id);
            $this->db->insert('bot_shop_cart', ['count' => $product['oneoff'], 'user_id' => $this->user_id, 'product_id' => $product_id]);
        } else {
            $this->Logging('Update ProductID ' . $product_id . ' set: ' . $product['oneoff'] + $product['count']);
            $this->db->update('bot_shop_cart', ['count' => $product['oneoff'] + $product['count']], ['user_id' => $this->user_id, 'product_id' => $product_id]);
        }
        $this->LoadData2Memcached($this->user_id);
        if ($product['quantity'] == 0) {
            $alert = false;
        } else {
            $alert = $this->getAlertProductCount($product_id, $product['quantity']);
            $this->Logging('Alert ProductID ' . $product_id . ' defined: ' . $alert);
        }
        return ['count' => $product['oneoff'] + $product['count'], 'alert' => $alert];
    }
    
    /*
     * Функция удаления товара из корзины
     * Принимает идентификатор товара в БД и работает с ним
     * Возвращает массив:
     * Array(
     *      [count] => Количество товара в корзине
     * 
     * );
     */
    
    function RemoveFromCart($product_id) {
        $product = $this->hasProductInCart($product_id);
        if ($product['count'] == $product['oneoff']) {
            $this->Logging('Remove ProductID ' . $product_id);
            $this->db->delete('bot_shop_cart', ['user_id' => $this->user_id, 'product_id' => $product_id]);
        } else {
            $this->Logging('Set count ProductID ' . $product_id . ' to: ' . $product['count'] - $product['oneoff']);
            $this->db->update('bot_shop_cart', ['count' => $product['count'] - $product['oneoff']], ['user_id' => $this->user_id, 'product_id' => $product_id]);
        }
        $this->LoadData2Memcached($this->user_id);
        return ['count' => $product['count'] - $product['oneoff']];
    }
    
    /*
     * Функция для получения данных по корзине пользователя
     * озвращает массив с товарами, находящимися в корзине тпользователя
     */
    
    function GetCart() {
        $cart = $this->GetData('USERCART', $this->user_id);
        return $cart;
    }
    
    /*
     * Функция получения информации о товаре
     * Принимает идентификатор товара
     * Возвращает массив с параметрами товара(ов)
     */
    
    function GetProducts($product_id = '') {
        $return = $this->GetData('PRODUCT', $product_id);
        return $return;
    }
    
    function GetString($string_id = '') {
        $return = $this->GetData('STRING', $string_id);
        return $return;
    }
    
    function MessageWorker($json_result, $send_message = false) {
        $data = json_decode($json_result);
        if ($data->ok) {
            $this->Logging('Message OK: ' . $data->result->message_id, 'INFO');
            if ($send_message) $this->db->insert('bot_oldmessages', ['chat_id' => $data->result->chat->id, 'message_id' => $data->result->message_id]);
        } else {
            $this->Logging('Message ERROR: ' . $data->description, 'ERROR');
        }
    }
    
    /*
     * Функция для определения параметров продуква в корзине пользователя
     * Принимает идентификатор товара в БД и работает с ним
     * Возвращает массив:
     * Array(
     *      [count] => количество товара в корзине
     *      [oneoff] => Дробность покупки товара
     *      [quantity] => Количество на складе
     * );
     */
    
    private function hasProductInCart($product_id) {
        $return = false;
        $cart = $this->GetData('USERCART', $this->user_id);
        foreach ($cart as $value) {
            if ($value['id'] == $product_id) {
                $return['count'] = $value['count'];
                $return['oneoff'] = $value['oneoff'];
                $return['quantity'] = $value['quantity'];
            }
        }
        $this->Logging('ProductID ' . $product_id . ' - ' . $return);
        return $return;
    }
    
    /*
     * Функция получения пользователя
     */
    
    public function hasUser() {
        $users = $this->GetData('USER', $this->user_id);
        $return = false;
        if ($users[0]) $return = $users[0];
        return $return;
    }
    
    public function CreateUser($chat_id, $username, $firstname, $lastname) {
        $this->db->insert('bot_profiles', ['chat_id' => $chat_id, 'username' => $username, 'first_name' => $firstname, 'last_name' => $lastname]);
        $this->LoadData2Memcached('USERS');
        return $this->db->id();
    }
    
    /*
     * Функция загрузки данных в Memcached
     * Принимает:
     *      $item => Параметр, который надо загрузить
     *      $parameter => Значение, которое должно учавствовать в выборке
     *      $forcedata => Признак, если не false - запишет значение в Memcached
     * Возвращает массив с данными, загруженными в Memcached
     */
    
    private function LoadData2Memcached($item, $parameter = '', $forcedata = false) {
        $return = '';
        
        if ($forcedata != '') {
            $this->Logging('Force loading ' . $item . ' to Memcached');
            if ($this->useMemcached) $this->Memcached_link->set($item . '_' . $parameter, $forcedata, (isset($this->Memcache_TTL[$item])) ? $this->Memcache_TTL[$item] : $this->Memcache_TTL_default);
            return;
        }
        
        switch($item) {
            case "USERCART":
                $this->Logging('Loading CART data from [!] DATABASE to Memcached');
                $return = $this->db->select('bot_shop_cart',
                    ['[>]bot_shop_products' => ['product_id' => 'id']],
                    '*',
                    ['user_id' => $parameter]
                );
                if ($this->useMemcached) $this->Memcached_link->set('USERCART_' . $parameter, $return, (isset($this->Memcache_TTL[$item])) ? $this->Memcache_TTL[$item] : $this->Memcache_TTL_default);
                break;
            case "USER":
                $this->Logging('Loading USERS data from [!] DATABASE to Memcached');
                $where = [];
                if ($parameter != '') $where = ['chat_id' => $parameter];
                $return = $this->db->select('bot_profiles', '*', $where);
                if ($this->useMemcached) $this->Memcached_link->set('USER', $return, (isset($this->Memcache_TTL[$item])) ? $this->Memcache_TTL[$item] : $this->Memcache_TTL_default);
                break;
            case "STRING":
                $this->Logging('Loading STRING data from [!] DATABASE to Memcached');
                $return = $this->db->select('bot_strings', '*');
                break;
        }
        return $return;
    }
    
    /*
     * Функция для получения данных из БД и загрузку их в Memecached
     * Принимает:
     *      $item => Параметр, который надо загрузить
     *      $parameter => Значение, которое должно учавствовать в выборке
     * Возвращает массив с данными, загруженными из БД
     */
    
    private function GetData($item, $parameter = '') {
        $return = '';
        switch($item) {
            case "USERCART":
                if (!$this->useMemcached or false == ($return = $this->Memcached_link->get($item . '_' . $parameter))) {
                    $this->Logging('Loading CART data from [!] DATABASE');
                    $return = $this->db->select('bot_shop_cart',
                        ['[>]bot_shop_products' => ['product_id' => 'id']],
                        '*',
                        ['user_id' => $parameter]
                    );
                    $this->LoadData2Memcached($item, $parameter, $return);
                }
                break;
            case "PRODUCT":
                $this->Logging('Loading PRODUCT data');
                $where = [];
                if ($parameter != '') $where = ['id' => $parameter];
                $return = $this->db->select('bot_shop_products', ['[>]bot_shop_owners' => ['chat_id' => 'chat_id']], '*', $where);
                break;
            case "USER":
                if (!$this->useMemcached or false == ($return = $this->Memcached_link->get($item))) {
                    $this->Logging('Loading USERS data from [!] DATABASE');
                    $where = [];
                    if ($parameter != '') $where = ['chat_id' => $parameter];
                    $return = $this->db->select('bot_profiles', '*', $where);
                    $this->LoadData2Memcached($item, $parameter, $return);
                }
                break;
            case "STRING":
                if (!$this->useMemcached or false == ($return = $this->Memcached_link->get($item))) {
                    $this->Logging('Loading STRINGS data from [!] DATABASE');
                    $where = [];
                    if ($parameter != '') $where = ['id' => $parameter];
                    $return = $this->db->select('bot_strings', '*', $where);
                    $this->LoadData2Memcached($item, $parameter, $return);
                }
                break;
        }
        return $return;
    }
    
    /*
     * Функция для получения Алерта на количество товара
     * Принимает:
     *      $product_id => Идентификатор товара в БД
     *      $parameter => Количество товара на складе
     * Возвращает True/False
     */
    
    private function getAlertProductCount($product_id, $parameter) {
        $cart_count = $this->db->sum('bot_shop_cart', 'count', ['product_id' => $product_id]);
        //$cart_count = $this->db->sum('bot_shop_cart', 'count', ['product_id' => $product_id]);// TODO - SUMM ORDERED PRODUCTS
        if ($parameter < $cart_count) {
            return true;
        } else {
            return false;
        }
    }
    
    /*
     * Функция для логирования
     */
    private function Logging($text, $severity = 'INFO') {
        //$this->debug .= date("d.m.Y H:i:s") . ' - [' . $severity . '] - UID: ' . $this->user_id . ' - ' . $text . "\n";
        file_put_contents($this->debug_file, date("d.m.Y H:i:s") . ' - [' . $severity . '] - UID: ' . $this->user_id . ' - ' . $text . "\n", FILE_APPEND);
    }
    
    function __destruct() {
        $this->Logging('Class DEstructor');
        $this->Logging('DATABASE TRACE: ' . print_r($this->db->log(), true));
    }
}
