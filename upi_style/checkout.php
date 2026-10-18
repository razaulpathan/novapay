<?php
// checkout.php - NextPay Payment Gateway Checkout Screen (Redesigned)
require_once 'db.php';
requireLogin();

$error = '';
$success = '';

$order_no = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';

if (empty($order_no)) {
    header("Location: home.php");
    exit;
}

// Fetch order details
$stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE order_no = ? AND user_id = ?");
$stmt->execute([$order_no, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: home.php");
    exit;
}

// Handle Order Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $reason = isset($_POST['cancel_reason_option']) ? trim($_POST['cancel_reason_option']) : 'Not specified';
    if ($reason === 'Other' && !empty($_POST['cancel_reason_text'])) {
        $reason = trim($_POST['cancel_reason_text']);
    }
    
    try {
        $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled', cancel_reason = ? WHERE id = ?");
        $updateStmt->execute([$reason, $order['id']]);
        header("Location: depo.php?tab=" . ($order['type'] === 'USDT' ? 'usdt' : 'cancel') . "&cancel_success=1");
        exit;
    } catch (PDOException $e) {
        // Fallback if cancel_reason column doesn't exist
        try {
            $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled' WHERE id = ?");
            $updateStmt->execute([$order['id']]);
            header("Location: depo.php?tab=" . ($order['type'] === 'USDT' ? 'usdt' : 'cancel') . "&cancel_success=1");
            exit;
        } catch (PDOException $e2) {
            $error = 'Database error: ' . $e2->getMessage();
        }
    }
}

// Handle UTR/TXID Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_proof') {
    $proof = isset($_POST['proof']) ? trim($_POST['proof']) : '';
    
    if (empty($proof)) {
        $error = 'Please enter the transaction reference proof.';
    } else {
        if ($order['type'] === 'USDT') {
            // Validate TXID
            if (strlen($proof) < 10) {
                $error = 'Invalid USDT Transaction ID (TXID). Please try again.';
            }
        } else {
            // Validate UPI UTR: 12-digit numeric
            if (!preg_match('/^[0-9]{12}$/', $proof)) {
                $error = 'Invalid UTR! UPI Ref No (UTR) must be exactly 12 digits.';
            }
        }
        
        // Handle payment proof screenshot upload
        $proof_img_path = null;
        if (empty($error)) {
            if (isset($_FILES['proof_img']) && $_FILES['proof_img']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['proof_img']['tmp_name'];
                $fileName = $_FILES['proof_img']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $error = 'Invalid file extension! Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.';
                } else {
                    $uploadDir = 'uploads/proofs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $newFileName = 'proof_' . $order['order_no'] . '_' . time() . '.' . $fileExtension;
                    $dest_path = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $proof_img_path = $dest_path;
                    } else {
                        $error = 'Failed to save payment proof image. Please check directory permissions.';
                    }
                }
            } else {
                if ($order['type'] !== 'USDT') {
                    $error = 'Please upload a screenshot of your payment proof.';
                }
            }
        }
        
        if (empty($error)) {
            try {
                // Check if UTR is duplicate to prevent double spending / fraud
                $chkDup = $pdo->prepare("SELECT COUNT(*) FROM buy_orders WHERE utr = ? AND id != ?");
                $chkDup->execute([$proof, $order['id']]);
                
                if ($chkDup->fetchColumn() > 0) {
                    // Increment failed UTR attempts on user profile
                    $pdo->prepare("UPDATE users SET failed_utr_attempts = failed_utr_attempts + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                    
                    // Check threshold for blocking
                    $userQ = $pdo->prepare("SELECT failed_utr_attempts, phone FROM users WHERE id = ?");
                    $userQ->execute([$_SESSION['user_id']]);
                    $usr = $userQ->fetch();
                    
                    if ($usr && intval($usr['failed_utr_attempts']) >= 3) {
                        $pdo->prepare("UPDATE users SET status = 'Blocked' WHERE id = ?")->execute([$_SESSION['user_id']]);
                        $error = 'Your account has been automatically BLOCKED due to multiple fake UTR submissions.';
                    } else {
                        $error = 'This UTR has already been registered in the system. Repeated fake attempts will result in account blocking!';
                    }
                } else {
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET utr = ?, payment_proof_img = ? WHERE id = ?");
                    $updateStmt->execute([$proof, $proof_img_path, $order['id']]);
                    
                    // Send instant approval Telegram alert to Administrator
                    $admin_chat_id = getSetting('telegram_admin_chat_id');
                    $bot_token = getSetting('telegram_bot_token');
                    $bot_status = getSetting('telegram_bot_status');
                    
                    if ($bot_status === '1' && !empty($bot_token) && !empty($admin_chat_id)) {
                        // Mask helper function inline
                        $maskedPhone = substr($_SESSION['phone'], 0, 4) . '••••' . substr($_SESSION['phone'], -2);
                        
                        $msg = "📥 <b>New Pending Buy Order Alert!</b>\n";
                        $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
                        $msg .= "👤 <b>User:</b> <code>{$maskedPhone}</code> (ID: {$order['user_id']})\n";
                        $msg .= "💵 <b>Amount:</b> <code>₹ {$order['price']}</code>\n";
                        if (!empty($order['user_pay_upi'])) {
                            $msg .= "💳 <b>Payer UPI:</b> <code>{$order['user_pay_upi']}</code>\n";
                        }
                        $msg .= "🧾 <b>UTR:</b> <code>{$proof}</code>\n";
                        $msg .= "🏷️ <b>Order ID:</b> <code>#{$order['id']}</code>\n";
                        $msg .= "🕒 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
                        
                        $inline_keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Approve ✅', 'callback_data' => 'approve_buy_' . $order['id']],
                                    ['text' => 'Reject ❌', 'callback_data' => 'reject_buy_' . $order['id']]
                                ]
                            ]
                        ];
                        
                        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
                        $post_fields = [
                            'chat_id' => $admin_chat_id,
                            'text' => $msg,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($inline_keyboard)
                        ];
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                    
                    header("Location: checkout.php?order_no=" . urlencode($order_no) . "&success=submitted");
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Admin Payment Configurations
$admin_upi = getSetting('admin_upi_id') ?: 'NextPayadmin@paytm';
$admin_usdt = getSetting('admin_usdt_address') ?: 'TY3q4UoE9sHh1eA7N2G1sW8yU7xP4mQ9zT';
$admin_bank_name = getSetting('admin_bank_name') ?: 'AIRP0000001';
$admin_bank_account = getSetting('admin_bank_account') ?: '8506943929';
$admin_bank_ifsc = getSetting('admin_bank_ifsc') ?: 'AIRP0000001';
$admin_bank_holder = getSetting('admin_bank_holder') ?: 'PRATHAMJOHRI';
// Fetch user's own active linked UPI account to display as dynamic pay channel
$stmtUserUpi = $pdo->prepare("SELECT * FROM upi_accounts WHERE user_id = ? AND status = 'Active' LIMIT 1");
$stmtUserUpi->execute([$_SESSION['user_id']]);
$userUpi = $stmtUserUpi->fetch();

if ($userUpi) {
    $admin_pay_channel = $userUpi['partner'] . '(' . $userUpi['upi_no'] . ')';
} else {
    $admin_pay_channel = getSetting('admin_pay_channel') ?: 'freecharge(nirmal577@freecharge)';
}

// Dynamic QR generation for back-up / Go pay flow
$upi_intent = '';
if ($order['type'] !== 'USDT') {
    $amount = number_format($order['price'], 2, '.', '');
    $upi_to_use = !empty($order['assigned_upi_id']) ? $order['assigned_upi_id'] : $admin_upi;
    $upi_intent = "upi://pay?pa={$upi_to_use}&pn=NextPay&am={$amount}&cu=INR&tn={$order_no}";
} else {
    $upi_intent = $admin_usdt;
}

// Calculate Remaining Time from Database Timestamp to prevent timezone offset errors
$dbTimeStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(NOW()) as now, UNIX_TIMESTAMP(created_at) as created FROM buy_orders WHERE id = ?");
$dbTimeStmt->execute([$order['id']]);
$dbTime = $dbTimeStmt->fetch();
$elapsed = $dbTime['now'] - $dbTime['created'];
$time_remaining = 1800 - $elapsed; // 30 minutes (1800s)
if ($time_remaining < 0) {
    $time_remaining = 0;
}

// Check initial tab status based on database state
$initial_tab = 1;
if (!empty($order['utr'])) {
    $initial_tab = 3;
}
if (isset($_GET['success']) && $_GET['success'] === 'submitted') {
    $initial_tab = 3;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buy Itoken details</title>
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --blue: #2395ee;
      --deep-blue: #07588f;
      --light-bg: #f5f7fa;
      --line-gray: #e2e8f0;
      --text-dark: #2c3e50;
      --text-muted: #7f8c8d;
      --danger-red: #e74c3c;
      --alert-bg: #fff2f2;
      --success-green: #2ecc71;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #eef2f5;
      color: var(--text-dark);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .screen {
      width: min(100%, 574px);
      min-height: 100vh;
      background: #ffffff;
      position: relative;
      padding-bottom: 24px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Page Header */
    .header {
      height: 56px;
      display: flex;
      align-items: center;
      padding: 0 16px;
      border-bottom: 1px solid #f5f7fa;
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 100;
    }

    .header-back {
      color: var(--text-muted);
      text-decoration: none;
      display: flex;
      align-items: center;
      cursor: pointer;
      width: 40px;
    }

    .header-back svg {
      width: 24px;
      height: 24px;
    }

    .header-title {
      font-size: 19px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0 auto;
      text-align: center;
      flex: 1;
    }

    .header-history {
      color: var(--text-muted);
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      width: 40px;
    }

    .header-history svg {
      width: 24px;
      height: 24px;
    }

    .content {
      padding: 0 0 16px 0;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    /* Error Alert */
    .error-alert {
      margin: 12px 16px 0 16px;
      background: var(--alert-bg);
      border: 1px solid rgba(231, 76, 60, 0.1);
      color: var(--danger-red);
      padding: 10px 16px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 14px;
      text-align: center;
    }

    /* Red Countdown Banner */
    .timer-banner {
      background: var(--alert-bg);
      color: var(--danger-red);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: bold;
      gap: 8px;
    }

    .timer-banner.audit-mode {
      background: #fff9e6;
      color: #d35400;
    }

    .timer-icon {
      font-size: 18px;
    }

    .timer-val {
      font-family: monospace, sans-serif;
      font-size: 18px;
    }

    /* Stepper/Progress Line */
    .stepper {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 32px;
      position: relative;
    }

    .stepper-line {
      position: absolute;
      top: 38px;
      left: 60px;
      right: 60px;
      height: 2px;
      background: var(--line-gray);
      z-index: 1;
    }

    .stepper-line-fill {
      position: absolute;
      top: 38px;
      left: 60px;
      height: 2px;
      width: 0%;
      background: var(--blue);
      z-index: 2;
      transition: width 0.3s ease;
    }

    .step-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      z-index: 3;
      flex: 1;
    }

    .step-circle {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #ffffff;
      border: 2px solid var(--line-gray);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: bold;
      color: var(--text-muted);
      transition: all 0.3s;
    }

    .step-item.active .step-circle {
      border-color: var(--blue);
      color: var(--blue);
    }

    .step-item.completed .step-circle {
      background: var(--blue);
      border-color: var(--blue);
      color: #ffffff;
    }

    .step-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted);
      transition: color 0.3s;
    }

    .step-item.active .step-label,
    .step-item.completed .step-label {
      color: var(--text-dark);
    }

    /* Form and Tab Content Containers */
    .tab-content {
      display: none;
      padding: 0 20px;
    }

    .tab-content.active {
      display: block;
    }

    /* Red instruction text */
    .instruction-text {
      color: var(--danger-red);
      font-size: 16px;
      font-weight: 800;
      line-height: 1.4;
      margin-bottom: 24px;
      text-align: center;
    }

    /* Details Rows Layout */
    .details-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 24px;
    }

    .detail-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #f0f3f6;
    }

    .detail-label {
      font-size: 15px;
      color: var(--text-muted);
      font-weight: 600;
      width: 100px;
    }

    .detail-value {
      font-size: 16px;
      font-weight: 800;
      color: var(--text-dark);
      flex: 1;
      text-align: left;
      padding-left: 12px;
      word-break: break-all;
    }

    .btn-copy {
      background: transparent;
      border: 1px solid var(--blue);
      color: var(--blue);
      font-size: 12px;
      font-weight: bold;
      padding: 4px 12px;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s;
      outline: none;
    }

    .btn-copy:hover {
      background: var(--blue);
      color: #ffffff;
    }

    /* notices notices */
    .notices-block {
      background: #fdfaf6;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 20px;
      border-left: 4px solid #f39c12;
    }

    .notice-item {
      color: var(--danger-red);
      font-size: 13.5px;
      font-weight: 800;
      line-height: 1.5;
      margin-bottom: 10px;
    }

    .notice-item:last-child {
      margin-bottom: 0;
    }

    .cancel-section {
      text-align: center;
      margin: 16px 0 24px;
    }

    .cancel-link-btn {
      background: transparent;
      border: none;
      color: var(--text-muted);
      font-size: 14.5px;
      font-weight: 600;
      cursor: pointer;
      outline: none;
    }

    .cancel-link-btn strong {
      color: var(--danger-red);
      text-decoration: underline;
    }

    /* Form controls inside prove tab */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 24px;
    }

    .form-label {
      font-size: 14px;
      font-weight: bold;
      color: var(--text-dark);
    }

    .form-control {
      width: 100%;
      height: 50px;
      border: 1px solid var(--line-gray);
      border-radius: 10px;
      padding: 0 16px;
      font-size: 16px;
      outline: none;
      font-family: inherit;
    }

    .form-control:focus {
      border-color: var(--blue);
    }

    /* Buttons Footer bar */
    .button-footer {
      display: flex;
      gap: 16px;
      margin-top: auto;
      padding: 16px 20px;
      background: #ffffff;
      border-top: 1px solid #f5f7fa;
    }

    .btn-action {
      flex: 1;
      height: 48px;
      border-radius: 10px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      transition: all 0.2s;
      font-family: inherit;
    }

    .btn-outline {
      background: #ffffff;
      border: 1.5px solid var(--blue);
      color: var(--blue);
    }

    .btn-outline:hover {
      background: rgba(35, 149, 238, 0.05);
    }

    .btn-solid {
      background: var(--blue);
      border: none;
      color: #ffffff;
      box-shadow: 0 4px 10px rgba(35, 149, 238, 0.2);
    }

    .btn-solid:hover {
      background: #1980d4;
    }

    /* Confirmation Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-card {
      background: #ffffff;
      border-radius: 16px;
      width: 100%;
      max-width: 400px;
      padding: 24px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      text-align: center;
      animation: zoomIn 0.25s ease;
    }

    .modal-title {
      font-size: 19px;
      font-weight: 800;
      color: var(--text-dark);
      margin: 0 0 14px 0;
    }

    .modal-body {
      font-size: 14.5px;
      color: #555555;
      line-height: 1.6;
      margin-bottom: 24px;
    }

    .modal-buttons {
      display: flex;
      border-top: 1px solid #f0f3f6;
      margin: 0 -24px -24px -24px;
    }

    .modal-btn {
      flex: 1;
      padding: 16px 0;
      border: none;
      background: transparent;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      outline: none;
      font-family: inherit;
    }

    .modal-btn-cancel {
      color: var(--text-muted);
      border-right: 1px solid #f0f3f6;
    }

    .modal-btn-confirm {
      color: var(--blue);
    }

    /* Floating Support Button */
    .fab-support {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: #ffffff;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blue);
      cursor: pointer;
      z-index: 99;
      text-decoration: none;
    }

    .fab-support svg {
      width: 24px;
      height: 24px;
    }

    /* Toast Notification styles */
    .toast {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(44, 62, 80, 0.9);
      color: #ffffff;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
      z-index: 2000;
      display: flex;
      align-items: center;
      gap: 10px;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
    }

    .toast.show {
      opacity: 1;
    }

    /* Audit Page Styles */
    .audit-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 30px 20px;
    }

    .audit-image-wrapper {
      width: 140px;
      height: 140px;
      margin-bottom: 24px;
      color: var(--blue);
    }

    .audit-status-text {
      font-size: 18px;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 12px;
    }

    .audit-link {
      color: var(--blue);
      font-size: 15px;
      font-weight: bold;
      text-decoration: underline;
      cursor: pointer;
      margin-top: 10px;
    }

    @keyframes zoomIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .close-modal-btn {
      position: absolute;
      top: 14px;
      right: 18px;
      background: transparent;
      border: none;
      font-size: 26px;
      color: #bbb;
      cursor: pointer;
      line-height: 1;
      padding: 0;
    }
    .close-modal-btn:hover {
      color: #666;
    }
    .cancel-options-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .cancel-option-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 15px;
      font-weight: 600;
      color: var(--text-dark);
      cursor: pointer;
    }
    .cancel-option-item input[type="radio"] {
      width: 18px;
      height: 18px;
      accent-color: var(--blue);
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header -->
    <header class="header">
      <a href="upibuyplus.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Buy Itoken details</h1>
      <a href="depo.php" class="header-history">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      </a>
    </header>

    <div class="content">

      <?php if (!empty($error)): ?>
        <div class="error-alert"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <!-- 1. Countdown Timer Banner -->
      <div class="timer-banner" id="timer-banner-container">
        <span class="timer-icon">🕒</span>
        <span id="timer-text" class="timer-val">00:30:00</span>
        <span id="timer-label-text">Please pay in time</span>
      </div>

      <!-- 2. Stepper progress bar -->
      <div class="stepper">
        <div class="stepper-line"></div>
        <div class="stepper-line-fill" id="stepper-fill"></div>
        
        <div class="step-item" id="step-1">
          <div class="step-circle">1</div>
          <span class="step-label">Payment info</span>
        </div>
        
        <div class="step-item" id="step-2">
          <div class="step-circle">2</div>
          <span class="step-label">Payment prove</span>
        </div>
        
        <div class="step-item" id="step-3">
          <div class="step-circle">3</div>
          <span class="step-label">Audit</span>
        </div>
      </div>

      <!-- TAB 1: Payment Info -->
      <div class="tab-content" id="tab-1-content">
        <div class="instruction-text">
          <?php if ($order['type'] === 'USDT'): ?>
            Please transfer the USDT amount to the TRC-20 address below
          <?php elseif ($userUpi): ?>
            Please use the <?= sanitize($admin_pay_channel) ?> of your choice to pay
          <?php else: ?>
            <span style="color: var(--danger-red); font-weight: 800;">Please link your UPI ID first to complete the payment!</span>
          <?php endif; ?>
        </div>

        <div class="details-list">
          <?php if ($order['type'] === 'USDT'): ?>
            <div class="detail-row">
              <span class="detail-label">Network</span>
              <span class="detail-value" id="val-network">TRC-20 (Tether)</span>
              <button class="btn-copy" onclick="copyText('val-network', 'Network copied!')">Copy</button>
            </div>
            <div class="detail-row">
              <span class="detail-label">Address</span>
              <span class="detail-value" id="val-address"><?= sanitize($admin_usdt) ?></span>
              <button class="btn-copy" onclick="copyText('val-address', 'Address copied!')">Copy</button>
            </div>
            <div class="detail-row">
              <span class="detail-label">Amount</span>
              <span class="detail-value" id="val-amount"><?= intval($order['price']) ?></span>
              <button class="btn-copy" onclick="copyText('val-amount', 'Amount copied!')">Copy</button>
            </div>
          <?php else: ?>
            <div class="detail-row">
              <span class="detail-label">Name</span>
              <span class="detail-value" id="val-name"><?= sanitize(!empty($order['assigned_upi_name']) ? $order['assigned_upi_name'] : $admin_bank_holder) ?></span>
              <button class="btn-copy" onclick="copyText('val-name', 'Name copied!')">Copy</button>
            </div>
            <div class="detail-row">
              <span class="detail-label">UPI ID</span>
              <span class="detail-value" id="val-upi"><?= sanitize(!empty($order['assigned_upi_id']) ? $order['assigned_upi_id'] : $admin_upi) ?></span>
              <button class="btn-copy" onclick="copyText('val-upi', 'UPI ID copied!')">Copy</button>
            </div>
            <div class="detail-row">
              <span class="detail-label">Amount</span>
              <span class="detail-value" id="val-amount-inr"><?= intval($order['price']) ?></span>
              <button class="btn-copy" onclick="copyText('val-amount-inr', 'Amount copied!')">Copy</button>
            </div>
            <div class="detail-row">
              <span class="detail-label">Status</span>
              <span class="detail-value" style="color: var(--success-green); font-weight: bold;">Active</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="notices-block">
          <div class="notice-item">Notice: The remittance amount must be consistent, otherwise the transaction will not be completed.</div>
          <div class="notice-item">Notice: If you have already paid, please patiently for the transaction review, please do not cancel the order</div>
        </div>

        <div class="cancel-section">
          <button type="button" class="cancel-link-btn" onclick="openCancelModal()">
            Unable to complete payment <strong>Cancel</strong> my order.
          </button>
        </div>

        <div class="button-footer">
          <?php if ($order['type'] === 'USDT'): ?>
            <button type="button" class="btn-action btn-solid" onclick="openConfirmationModal()">Finish payment</button>
          <?php elseif ($userUpi): ?>
            <a href="<?= !empty($upi_intent) ? $upi_intent : '#' ?>" class="btn-action btn-outline" target="_blank">Go pay</a>
            <button type="button" class="btn-action btn-solid" onclick="openConfirmationModal()">Finish payment</button>
          <?php else: ?>
            <a href="kycpa.php" class="btn-action btn-solid" style="background: var(--danger-red); box-shadow: none;">Link UPI ID Now</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- TAB 2: Payment Prove -->
      <div class="tab-content" id="tab-2-content">
        <div class="instruction-text" style="margin-bottom: 12px;">
          <?php if ($order['type'] === 'USDT'): ?>
            Please submit the Transaction Hash (TXID) as proof of payment
          <?php elseif ($userUpi): ?>
            Please use the <?= sanitize($admin_pay_channel) ?> of your choice to pay
          <?php else: ?>
            <span style="color: var(--danger-red); font-weight: 800;">Please link your UPI ID first to complete the payment!</span>
          <?php endif; ?>
        </div>

        <div class="notices-block" style="background: #fafbfc; border-left: none; margin-bottom: 24px; padding: 12px 16px;">
          <div class="notice-item" style="color: #666; font-size: 13px; font-weight: normal; margin-bottom: 8px;">
            1. Copy the payment address and complete the transfer in your transaction app.
          </div>
          <div class="notice-item" style="color: #666; font-size: 13px; font-weight: normal; margin-bottom: 8px;">
            2. Enter the transaction reference code (12-digit UTR/Ref No or TXID) below as proof of payment.
          </div>
          <div class="notice-item" style="color: var(--danger-red); font-size: 13px; font-weight: bold; direction: ltr;">
            3.टोकन 30 मिनट के भीतर प्राप्त न होने पर भुगतान समय पर पूरा किया जाना चाहिए कृपया अपने विशिष्ट वीआईपी समूह में ग्राहक सेवा से संपर्क करें
          </div>
        </div>

        <form method="POST" action="checkout.php?order_no=<?= urlencode($order_no) ?>" id="proof-form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="submit_proof">
          
          <div class="form-group">
            <label class="form-label" for="proof-input">
              <?= $order['type'] === 'USDT' ? 'Transaction Hash (TXID)' : 'UPI Reference Number (12-digit UTR)' ?>
            </label>
            <input 
              type="text" 
              name="proof" 
              id="proof-input" 
              class="form-control" 
              placeholder="<?= $order['type'] === 'USDT' ? 'Enter TXID' : 'e.g. 850694392912' ?>" 
              required
              <?php if ($order['type'] !== 'USDT'): ?>
                pattern="[0-9]{12}"
                inputmode="numeric"
                maxlength="12"
              <?php endif; ?>
            >
          </div>

          <div class="form-group">
            <label class="form-label" for="proof-img-input">
              Upload Payment Proof Screenshot (JPEG/PNG/WEBP)
            </label>
            <input 
              type="file" 
              name="proof_img" 
              id="proof-img-input" 
              class="form-control" 
              accept="image/*"
              style="padding-top: 12px;"
              <?= ($order['type'] !== 'USDT') ? 'required' : '' ?>
            >
          </div>

          <div class="cancel-section" style="margin-top: 10px;">
            <button type="button" class="cancel-link-btn" onclick="openCancelModal()">
              I'm having trouble paying and want to <strong>Cancel</strong> my order.
            </button>
          </div>

          <div class="button-footer" style="padding: 16px 0;">
            <button type="button" class="btn-action btn-outline" onclick="switchTab(1)">Previous</button>
            <?php if ($order['type'] === 'USDT'): ?>
              <button type="submit" class="btn-action btn-solid">Confirm payment</button>
            <?php elseif ($userUpi): ?>
              <button type="submit" class="btn-action btn-solid">Confirm payment</button>
            <?php else: ?>
              <a href="kycpa.php" class="btn-action btn-solid" style="background: var(--danger-red); box-shadow: none;">Link UPI ID Now</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- TAB 3: Audit (Review Screen) -->
      <div class="tab-content" id="tab-3-content">
        
        <div class="audit-container">
          <div class="notices-block" style="text-align: left; background: #fffcf4; border-left: 4px solid #f39c12; margin-bottom: 24px;">
            <div style="font-size: 18px; font-weight: 800; color: var(--danger-red); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
              Notice! सूचना
            </div>
            <div class="notice-item" style="color: var(--danger-red); font-size: 13.5px; font-weight: 800;">
              Please carefully check the amount you have paid. If the amount in rupees is insufficient, make sure to complete the remaining payment to the same person. If you overpaid, unfortunately, you will need to contact the recipient directly for refunds. We will track the action of malicious payments and limit accounts for assistance. Keep your payment accurate to avoid any unnecessary issues!
            </div>
          </div>

          <div class="cancel-section" style="margin-bottom: 30px;">
            <button type="button" class="cancel-link-btn" onclick="openCancelModal()">
              I'm having trouble paying and want to <strong>Cancel</strong> my order.
            </button>
          </div>

          <div class="audit-image-wrapper">
            <!-- SVG Review Illustration -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="width: 100%; height: 100%;">
              <circle cx="12" cy="12" r="10" stroke="var(--blue)" stroke-width="1.5"></circle>
              <line x1="12" y1="16" x2="12" y2="12" stroke="var(--blue)" stroke-width="2"></line>
              <line x1="12" y1="8" x2="12.01" y2="8" stroke="var(--blue)" stroke-width="2.5"></line>
              <path d="M7 12h10" opacity="0.3"></path>
            </svg>
          </div>

          <div class="audit-status-text">Waiting for review</div>
          
          <a href="customer.php" class="audit-link">Contact Customer Service</a>
        </div>
      </div>

    </div>
  </div>

  <!-- FLOATING CUSTOMER SUPPORT FAB -->
  <a href="customer.php" class="fab-support">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      <circle cx="9" cy="10" r="1"></circle>
      <circle cx="13" cy="10" r="1"></circle>
      <circle cx="17" cy="10" r="1"></circle>
    </svg>
  </a>

  <!-- WHY CANCEL MODAL DIALOG -->
  <div class="modal-overlay" id="cancel-reason-modal">
    <div class="modal-card" style="text-align: left; max-width: 440px; position: relative;">
      <button type="button" class="close-modal-btn" onclick="closeCancelModal()">&times;</button>
      
      <h3 class="modal-title" style="text-align: center; margin-bottom: 20px;">Why cancel -> Are you sure?</h3>
      
      <form id="cancel-order-form" method="POST" action="checkout.php?order_no=<?= urlencode($order_no) ?>">
        <input type="hidden" name="action" value="cancel_order">
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 14.5px; border-bottom: 1.5px solid #f0f3f6; padding-bottom: 12px;">
          <span style="color: var(--text-muted); font-weight: 600;">Order No</span>
          <span style="font-weight: 800; font-family: monospace; color: var(--text-dark);"><?= sanitize($order['order_no']) ?></span>
        </div>

        <div class="cancel-options-list">
          <label class="cancel-option-item">
            <input type="radio" name="cancel_reason_option" value="Incorrect bank account information" checked onclick="toggleCancelOtherInput(false)">
            <span>Incorrect bank account information</span>
          </label>
          
          <label class="cancel-option-item">
            <input type="radio" name="cancel_reason_option" value="Don't want to buy" onclick="toggleCancelOtherInput(false)">
            <span>Don't want to buy</span>
          </label>
          
          <label class="cancel-option-item">
            <input type="radio" name="cancel_reason_option" value="Payment Fail" onclick="toggleCancelOtherInput(false)">
            <span>Payment Fail</span>
          </label>
          
          <label class="cancel-option-item" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <input type="radio" name="cancel_reason_option" value="Other" id="radio-other" onclick="toggleCancelOtherInput(true)">
              <span>Other</span>
            </div>
            <input type="text" name="cancel_reason_text" id="cancel-other-text" class="form-control" style="flex: 1; min-width: 150px; height: 36px; padding: 0 10px; font-size: 13.5px; border-radius: 6px;" placeholder="Please enter a reason" disabled>
          </label>
        </div>

        <div style="color: var(--danger-red); font-size: 13.5px; font-weight: 700; line-height: 1.5; margin: 18px 0; background: #fff2f2; padding: 10px 12px; border-radius: 8px;">
          Warning: if you try to cancel and money debit from your bank, we can't give it back.
        </div>

        <div class="modal-buttons" style="border-top: 1px solid #f0f3f6; margin: 0 -24px -24px -24px;">
          <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCancelModal()" style="border-right: 1px solid #f0f3f6; font-weight: bold; color: #7f8c8d;">Cancel</button>
          <button type="submit" class="modal-btn modal-btn-confirm" style="font-weight: bold; color: var(--blue);">Confirm</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CONFIRMATION MODAL DIALOG -->
  <div class="modal-overlay" id="confirm-payment-modal">
    <div class="modal-card">
      <h3 class="modal-title">Confirm payment</h3>
      <div class="modal-body">
        <?php if ($order['type'] === 'USDT'): ?>
          Please confirm that you have completed the USDT payment to the address shown. If you do not complete the payment, your credit will be affected and your transactions will be limited!
        <?php else: ?>
          Please confirm that you have completed the payment<?php if ($userUpi): ?> with the <strong><?= sanitize($admin_pay_channel) ?></strong> account<?php endif; ?>. If you do not complete the payment, your credit will be affected and your transactions will be limited!
        <?php endif; ?>
      </div>
      <div class="modal-buttons">
        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeConfirmationModal()">Continue to pay</button>
        <button type="button" class="modal-btn modal-btn-confirm" onclick="confirmPaymentAction()">Got it</button>
      </div>
    </div>
  </div>

  <!-- COPY TOAST NOTIFICATION -->
  <div class="toast" id="copy-toast">
    <svg viewBox="0 0 24 24" fill="none" stroke="var(--success-green)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span id="toast-msg">Copied!</span>
  </div>

  <!-- GLOBAL TABS STATE & TIMER JAVASCRIPT -->
  <script>
    let currentTab = <?= $initial_tab ?>;
    let timeRemaining = <?= $time_remaining ?>;
    let timerInterval = null;

    function switchTab(tabNum) {
      currentTab = tabNum;
      
      // Update Tab Contents
      document.getElementById('tab-1-content').classList.remove('active');
      document.getElementById('tab-2-content').classList.remove('active');
      document.getElementById('tab-3-content').classList.remove('active');
      
      document.getElementById(`tab-${tabNum}-content`).classList.add('active');
      
      // Update Stepper CSS
      const step1 = document.getElementById('step-1');
      const step2 = document.getElementById('step-2');
      const step3 = document.getElementById('step-3');
      const fillLine = document.getElementById('stepper-fill');
      
      step1.className = 'step-item';
      step2.className = 'step-item';
      step3.className = 'step-item';
      
      if (tabNum === 1) {
        step1.classList.add('active');
        fillLine.style.width = '0%';
        updateTimerLabel(false);
      } else if (tabNum === 2) {
        step1.classList.add('completed');
        step2.classList.add('active');
        fillLine.style.width = '50%';
        updateTimerLabel(false);
      } else if (tabNum === 3) {
        step1.classList.add('completed');
        step2.classList.add('completed');
        step3.classList.add('completed');
        fillLine.style.width = '100%';
        updateTimerLabel(true);
      }
    }

    function updateTimerLabel(isAudit) {
      const banner = document.getElementById('timer-banner-container');
      const labelText = document.getElementById('timer-label-text');
      
      if (isAudit) {
        banner.classList.add('audit-mode');
        labelText.textContent = 'Under review, Please wait';
      } else {
        banner.classList.remove('audit-mode');
        labelText.textContent = 'Please pay in time';
      }
    }

    // Timer Countdown logic
    function startTimer() {
      const timerText = document.getElementById('timer-text');
      
      function updateDisplay() {
        if (timeRemaining <= 0) {
          timerText.textContent = "00:00:00";
          clearInterval(timerInterval);
          return;
        }
        
        let hrs = Math.floor(timeRemaining / 3600);
        let mins = Math.floor((timeRemaining % 3600) / 60);
        let secs = timeRemaining % 60;
        
        let hrsStr = hrs < 10 ? "0" + hrs : hrs;
        let minsStr = mins < 10 ? "0" + mins : mins;
        let secsStr = secs < 10 ? "0" + secs : secs;
        
        timerText.textContent = `${hrsStr} : ${minsStr} : ${secsStr}`;
        timeRemaining--;
      }
      
      updateDisplay();
      timerInterval = setInterval(updateDisplay, 1000);
    }

    // Modal popup triggers
    function openConfirmationModal() {
      document.getElementById('confirm-payment-modal').classList.add('active');
    }

    function closeConfirmationModal() {
      document.getElementById('confirm-payment-modal').classList.remove('active');
    }

    function confirmPaymentAction() {
      closeConfirmationModal();
      switchTab(2);
    }

    function openCancelModal() {
      document.getElementById('cancel-reason-modal').classList.add('active');
    }

    function closeCancelModal() {
      document.getElementById('cancel-reason-modal').classList.remove('active');
    }

    function toggleCancelOtherInput(enable) {
      const otherInput = document.getElementById('cancel-other-text');
      otherInput.disabled = !enable;
      if (enable) {
        otherInput.required = true;
        otherInput.focus();
      } else {
        otherInput.required = false;
        otherInput.value = '';
      }
    }

    // Copy function
    function copyText(elementId, message) {
      const text = document.getElementById(elementId).innerText.trim();
      navigator.clipboard.writeText(text).then(() => {
        showToast(message);
      }).catch(err => {
        console.error('Copy failed: ', err);
      });
    }

    function showToast(message) {
      const toast = document.getElementById('copy-toast');
      const msgSpan = document.getElementById('toast-msg');
      msgSpan.innerText = message;
      toast.classList.add('show');
      
      setTimeout(() => {
        toast.classList.remove('show');
      }, 2000);
    }

    // Initialize state
    document.addEventListener("DOMContentLoaded", () => {
      switchTab(currentTab);
      startTimer();
      
      // If we just submitted the UTR successfully, trigger the audit toast message once
      <?php if (isset($_GET['success']) && $_GET['success'] === 'submitted'): ?>
        showToast("Submission successful, waiting review");
      <?php endif; ?>
    });
  </script>
</body>
</html>

