<?php
// watchpay_config.php - Dynamically configured settings from database
require_once __DIR__ . '/../db.php';

// Detect protocol and host domain dynamically
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$current_domain = $proto . '://' . $host;

// Detect current path to pay_orders directory
$dir_path = dirname($_SERVER['SCRIPT_NAME']);
$dir_path = str_replace('\\', '/', $dir_path);
if (substr($dir_path, -1) !== '/') {
    $dir_path .= '/';
}

return [
    'WATCH_BASE_URL'    => getSetting('watchpay_base_url') ?: 'https://api.watchpays.com',
    'WATCH_CREATE_PATH' => getSetting('watchpay_create_path') ?: '/v1/create',

    'WATCH_MERCHANT_ID' => getSetting('watchpay_merchant_id') ?: '100555004',
    'WATCH_API_KEY'     => getSetting('watchpay_api_key') ?: 'b4051de2ecea122385551d9b28c78787',

    'MY_HOST'    => $current_domain,
    'RETURN_URL' => $current_domain . $dir_path . '../depo.php',

    // Notify URL points to the watchpay_callback.php in this directory
    'NOTIFY_URL' => $current_domain . $dir_path . 'watchpay_callback.php',

    'LOG_FILE' => __DIR__ . '/watchpay.log'
];
?>

