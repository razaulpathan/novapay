<?php
// watchpay.php - Secure checkout initiator for WatchPay
@ob_start();
require_once __DIR__ . '/../db.php';
requireLogin();

$config = include __DIR__ . '/watchpay_config.php';

function logit($m){
    global $config;
    file_put_contents($config['LOG_FILE'], date('c')." | ".$m.PHP_EOL, FILE_APPEND);
}

function normalize_amount($a){
    return number_format((float)$a, 2, '.', '');
}

$order_no = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';

if (empty($order_no)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'missing order number']);
    exit;
}

// Fetch buy order details from database
$stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE order_no = ? AND user_id = ?");
$stmt->execute([$order_no, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'order not found']);
    exit;
}

$amount = normalize_amount($order['price']);
$uid = $order['user_id'];

// Get user phone/mobile
$stmtUser = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$uid]);
$phone = $stmtUser->fetchColumn();

if (!$phone) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'user not found']);
    exit;
}

$merchant_order_no = $order['order_no'];

/* SIGNATURE */
$params = [
    "amount" => $amount,
    "callback_url" => $config['NOTIFY_URL'],
    "merchant_id" => $config['WATCH_MERCHANT_ID'],
    "merchant_order_no" => $merchant_order_no
];

ksort($params);

$signStr = "";
foreach($params as $k => $v){
    $signStr .= $k."=".$v."&";
}
$signStr .= "key=".$config['WATCH_API_KEY'];

$signature = md5($signStr);

/* payload */
$payload = [
    "merchant_id" => $config['WATCH_MERCHANT_ID'],
    "api_key" => $config['WATCH_API_KEY'],
    "amount" => $amount,
    "merchant_order_no" => $merchant_order_no,
    "callback_url" => $config['NOTIFY_URL'],
    "extra" => $uid,
    "signature" => $signature
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config['WATCH_BASE_URL'].$config['WATCH_CREATE_PATH']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);
logit("CREATE order $merchant_order_no: " . $response);

if (!empty($res['success']) && !empty($res['payment_url'])) {
    header("Location: " . $res['payment_url']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => false, 'message' => 'gateway error', 'res' => $res]);
?>

