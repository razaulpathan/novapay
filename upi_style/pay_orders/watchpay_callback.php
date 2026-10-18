<?php
// watchpay_callback.php - Asynchronous callback handler from WatchPay API
require_once __DIR__ . '/../db.php';
$config = include __DIR__ . '/watchpay_config.php';

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Log callback details
file_put_contents($config['LOG_FILE'], date('c') . " | CALLBACK " . $raw . PHP_EOL, FILE_APPEND);

$merchantOrder = $data['merchantOrder'] ?? '';
$orderNo = $data['orderNo'] ?? '';
$status = strtolower($data['status'] ?? '');

if (empty($merchantOrder)) {
    echo json_encode(['status' => 'error', 'message' => 'missing order number']);
    exit;
}

try {
    // 1. Fetch buy order details from NextPay database
    $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE order_no = ? LIMIT 1");
    $stmt->execute([$merchantOrder]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'order not found']);
        exit;
    }

    // 2. If already successful, stop
    if ($order['status'] === 'Success') {
        echo json_encode(['status' => 'ok', 'message' => 'already processed']);
        exit;
    }

    // 3. Process payment success
    if ($status === 'success') {
        $pdo->beginTransaction();

        // Update buy_orders status to Success and store WatchPay reference in utr
        $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Success', utr = ? WHERE id = ?");
        $updateStmt->execute([$orderNo, $order['id']]);

        // Add tokens and rewards to user account
        $userStmt = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, today_profit = today_profit + ? WHERE id = ?");
        $userStmt->execute([$order['itokens'], $order['reward'], $order['user_id']]);

        $pdo->commit();

        // Unconditionally trigger automatic token selling & Admin telegram alert
        triggerAutoSell($pdo, $order['id']);

        file_put_contents($config['LOG_FILE'], date('c') . " | CALLBACK SUCCESS: Order #{$merchantOrder} successfully credited.\n", FILE_APPEND);
        echo json_encode(['status' => 'ok', 'message' => 'success']);
        exit;
    }

    // Process other statuses (failed/cancelled)
    if ($status === 'failed' || $status === 'cancelled') {
        $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled', utr = ? WHERE id = ? AND status = 'Pending'");
        $updateStmt->execute([$orderNo, $order['id']]);
        echo json_encode(['status' => 'ok', 'message' => 'cancelled']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents($config['LOG_FILE'], date('c') . " | CALLBACK ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'ok']);
?>

