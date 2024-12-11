<?php
// Configuration
if (is_file(dirname(__FILE__)."/../admin/config_local.php")) {
    require_once(dirname(__FILE__)."/../admin/config_local.php");
} elseif (is_file(dirname(__FILE__)."/../admin/config.php")) {
    require_once(dirname(__FILE__)."/../admin/config.php");
} else {
    write_to_log("Отсутствует файл конфигурации");
    exit();
}
// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Request
$request = new Request();
$registry->set('request', $request);

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$registry->set('response', $response);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);


// Store
// todo: сделать параметром
$config->set('config_store_id', 0);

// Settings
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' OR store_id = '" . (int)$config->get('config_store_id') . "' ORDER BY store_id ASC");
foreach($query->rows as $result) {
    if (!$result['serialized']) {
        $config->set($result['key'], $result['value']);
    } else {
        $config->set($result['key'], json_decode($result['value'], true));
    }
}

$config->set('config_url', HTTP_SERVER);
$config->set('config_ssl', HTTPS_SERVER);

// Cache
$cache = new Cache('file');
$registry->set('cache', $cache);

// Session
//$session = new Session();
//$registry->set('session', $session);

// Language Detection
$languages = array();

$query = $db->query("SELECT * FROM " . DB_PREFIX . "language WHERE status = '1'");
foreach($query->rows as $result) {
    $languages[$result['code']] = $result;
}

$code = $config->get('config_language');
$config->set('config_language_id', $languages[$code]['language_id']);
$config->set('config_language', $languages[$code]['code']);

// Language
$language = new Language($languages[$code]['directory']);
$language->load($languages[$code]['directory']);
$registry->set('language', $language);

// Url
$url = new Url($config->get('config_url'), $config->get('config_secure') ? $config->get('config_ssl') : $config->get('config_url'));
$registry->set('url', $url);

// Log
$log = new Log($config->get('config_error_filename'));
$registry->set('log', $log);

// Currency
$registry->set('currency', new Currency($registry));

// Tax
$registry->set('tax', new Tax($registry));

// Weight
$registry->set('weight', new Weight($registry));

// Length
$registry->set('length', new Length($registry));

if($config->get('isklad_status')){
    $loader->model('tool/isklad');
    $model_tool_isklad = $registry->get('model_tool_isklad');
    write_to_log("Sync orders - начало");
    $model_tool_isklad->exportOrders();
    write_to_log("Sync orders - завершено");
}

function write_to_log( $message ){
    $logile = DIR_LOGS . "isklad.log";
    if (file_exists($logile) && filesize($logile) >= 10 * 1024 * 1024 /* Erase after 10 mb */) {
        unlink($logile);
        file_put_contents( $logile, date("Y-m-d H:i:s - ") . " Файл логов очищен и начат заново" . "\r\n", FILE_APPEND );
    }

    file_put_contents( $logile, date("Y-m-d H:i:s - ") . $message . "\r\n", FILE_APPEND );
}
