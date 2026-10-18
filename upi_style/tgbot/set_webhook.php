<?php
// tgbot/set_webhook.php - Telegram Webhook Registration and Management Utility
require_once '../db.php';

// Access restricted to administrators
if (!isAdmin()) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['ok' => false, 'description' => 'Unauthorized access']);
    exit;
}

$bot_token  = getSetting('telegram_bot_token');
$bot_secret = getSetting('telegram_bot_secret') ?: 'SECRET_BOT_TOKEN_HERE';

if (empty($bot_token)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'description' => 'Telegram Bot Token is not configured. Please enter it in Settings first.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'info';

// Auto-detect current host and protocol for Webhook registration URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$webhook_target = $protocol . $domain . "/tgbot/bot.php?token=" . urlencode($bot_secret);

$api_url = "";
if ($action === 'set') {
    // Register Webhook
    $api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_target);
} elseif ($action === 'delete') {
    // Delete Webhook
    $api_url = "https://api.telegram.org/bot{$bot_token}/deleteWebhook";
} else {
    // Fetch current webhook status
    $api_url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
if ($response === false) {
    echo json_encode(['ok' => false, 'description' => 'Connection to Telegram servers failed.']);
} else {
    // Append webhook url details to the response for display in admin panel
    $res_data = json_decode($response, true);
    $res_data['detected_webhook_url'] = $webhook_target;
    echo json_encode($res_data);
}
exit;
?>

