<?php
// depo.php - Buy Order History
require_once 'db.php';
requireLogin();

$admin_bank_holder = getSetting('admin_bank_holder') ?: 'PRATHAMJOHRI';
$admin_bank_account = getSetting('admin_bank_account') ?: '8506943929';
$admin_bank_ifsc = getSetting('admin_bank_ifsc') ?: 'AIRP0000001';
$admin_upi = getSetting('admin_upi_id') ?: 'NextPayadmin@paytm';

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inr';
if (!in_array($current_tab, ['inr', 'cancel', 'usdt'])) {
    $current_tab = 'inr';
}

$user_id = $_SESSION['user_id'];

// Fetch orders depending on tab
if ($current_tab === 'inr') {
    // Show active or pending UPI / UPI Plus orders
    $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE user_id = ? AND type IN ('UPI', 'UPI_PLUS') AND status != 'Cancelled' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "Itoken has not been buy in this way";
} elseif ($current_tab === 'cancel') {
    // Show cancelled UPI / UPI Plus orders
    $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE user_id = ? AND type IN ('UPI', 'UPI_PLUS') AND status = 'Cancelled' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "No cancelled orders found";
} else {
    // Show USDT orders
    $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE user_id = ? AND type = 'USDT' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "USDT orders have not been buy in this way";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Buy History</title>
  <!-- Link to premium styles -->
  <link rel="stylesheet" href="website_style.css">
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --blue: #2395ee;
      --deep-blue: #07588f;
      --soft-blue: #f8fcff;
      --line: #e8f1f7;
      --muted: #b8c0c8;
      --bg-color: #f7f9fc;
      --text-dark: #333333;
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
      padding-bottom: 80px; /* Space for fixed bottom nav */
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Header styling */
    .header {
      height: 56px;
      display: flex;
      align-items: center;
      padding: 0 16px;
      border-bottom: 1px solid #f5f7fa;
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 10;
    }

    .header-back {
      color: #2c3e50;
      text-decoration: none;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .header-back svg {
      width: 24px;
      height: 24px;
    }

    .header-title {
      font-size: 18px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0 auto;
      transform: translateX(-12px); /* Centering offset */
    }

    .content {
      padding: 0;
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }

    /* Sub Tabs */
    .sub-tabs {
      display: flex;
      width: 100%;
      border-bottom: 1.5px solid #f0f3f6;
      background: #ffffff;
    }

    .tab-item {
      flex: 1;
      text-align: center;
      padding: 14px 0;
      cursor: pointer;
      color: #7f8c8d;
      font-size: 16px;
      font-weight: bold;
      position: relative;
      transition: color 0.2s;
      text-decoration: none;
    }

    .tab-item.active {
      color: var(--blue);
    }

    .tab-item.active::after {
      content: '';
      position: absolute;
      bottom: -1.5px;
      left: 42%;
      width: 16%;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
    }

    /* Order history items */
    .order-list {
      display: flex;
      flex-direction: column;
    }

    .order-item-box {
      padding: 16px;
      border-bottom: 1px solid #f2f5f8;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .order-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      font-weight: bold;
    }

    .order-no-text {
      color: #2c3e50;
      font-family: Arial, sans-serif;
    }

    .order-status {
      font-size: 13px;
      padding: 3px 8px;
      border-radius: 4px;
    }

    .order-status.Pending {
      background: #fff5ec;
      color: #e67e22;
    }

    .order-status.Success {
      background: #f0fdf4;
      color: #2ecc71;
    }

    .order-status.Cancelled {
      background: #fdf2f2;
      color: #e74c3c;
    }

    .order-body-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: #7f8c8d;
    }

    .order-details-col {
      display: flex;
      gap: 12px;
    }

    .order-detail-label {
      font-weight: bold;
    }

    .order-detail-val {
      color: #333333;
      font-family: Arial, sans-serif;
      font-weight: bold;
    }

    .order-date-text {
      font-size: 11px;
      color: #bdc3c7;
      font-family: Arial, sans-serif;
    }

    /* Empty state layout */
    .empty-state-view {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 64px 24px;
      text-align: center;
      gap: 16px;
      flex: 1;
    }

    .stats-icon-wrapper {
      width: 180px;
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #85c1e9;
      opacity: 0.85;
      margin-bottom: 8px;
    }

    .empty-text {
      font-size: 16px;
      font-weight: 800;
      color: #111111;
      margin: 0;
    }

    .action-btn {
      margin-top: 12px;
      width: 100%;
      max-width: 320px;
      height: 52px;
      border: none;
      background: #2395ee;
      color: #ffffff;
      border-radius: 26px;
      font: 800 16px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(35, 149, 238, 0.25);
      transition: background-color 0.2s, transform 0.1s;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .action-btn:hover {
      background: #0d7dd4;
    }

    .action-btn:active {
      transform: scale(0.98);
    }

    /* Floating Chat Button */
    .fab {
      position: absolute;
      bottom: 24px;
      right: 18px;
      width: 48px;
      height: 48px;
      background: #3a6bf6;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 4px 12px rgba(58, 107, 246, 0.4);
      cursor: pointer;
      z-index: 10;
    }

    .fab svg {
      width: 24px;
      height: 24px;
    }
    
    .status-dot-container {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13.5px;
      font-weight: 700;
    }
    .status-dot-container.Success { color: #2ecc71; }
    .status-dot-container.Cancelled { color: #95a5a6; }
    .status-dot-container.Pending { color: #e67e22; }
    
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
    .status-dot.Success { background-color: #2ecc71; }
    .status-dot.Cancelled { background-color: #95a5a6; }
    .status-dot.Pending { background-color: #e67e22; }
    
    .order-detail-link {
      color: var(--blue);
      text-decoration: underline;
      font-weight: bold;
      font-size: 13px;
    }
    .order-detail-link:hover {
      color: #1a7ecf;
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <a href="mine.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Buy History</h1>
    </header>

    <div class="content">
      
      <!-- Sub tabs -->
      <nav class="sub-tabs">
        <a href="depo.php?tab=inr" class="tab-item <?= $current_tab === 'inr' ? 'active' : '' ?>">INR</a>
        <a href="depo.php?tab=cancel" class="tab-item <?= $current_tab === 'cancel' ? 'active' : '' ?>">INR(Cancel)</a>
        <a href="depo.php?tab=usdt" class="tab-item <?= $current_tab === 'usdt' ? 'active' : '' ?>">USDT</a>
      </nav>

      <?php if (empty($orders)): ?>
        <!-- Empty state view -->
        <div class="empty-state-view">
          <div class="stats-icon-wrapper">
            <svg viewBox="0 0 160 120" style="width: 140px; height: 110px;">
              <rect x="10" y="20" width="140" height="80" rx="10" fill="none" stroke="#2395ee" stroke-width="6" opacity="0.8"/>
              <rect x="25" y="80" width="110" height="20" rx="3" fill="#cbd9e6" opacity="0.3"/>
              <path d="M25 75 L 55 50 L 75 60 L 105 35 L 135 55" fill="none" stroke="#2395ee" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M25 75 L 55 50 L 75 60 L 105 35 L 135 55 L 135 85 L 25 85 Z" fill="#2395ee" opacity="0.15"/>
            </svg>
          </div>
          <h4 class="empty-text"><?= sanitize($empty_msg) ?></h4>
          
          <a href="upibuyplus.php" class="action-btn">To buy</a>
        </div>
      <?php else: ?>
        <!-- Order List view -->
        <div class="order-list">
          <?php foreach ($orders as $order): ?>
            <?php 
              $display_status = $order['status'] === 'Pending' ? 'In Review' : ($order['status'] === 'Cancelled' ? 'Cancel' : 'Success');
              $status_color = $order['status'] === 'Success' ? '#2ecc71' : ($order['status'] === 'Cancelled' ? '#e74c3c' : '#f1c40f');
            ?>
            <div class="order-item-box" style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1.5px solid #f2f5f8; padding: 16px 20px;">
              
              <!-- Left side: details -->
              <div style="display: flex; flex-direction: column; gap: 4px; text-align: left;">
                <span style="font-size: 16px; font-weight: 800; color: #111111;">IToken</span>
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Reward: <?= number_format($order['reward'], 2, '.', '') ?></span>
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d; font-family: Arial, sans-serif;">OrderNo: <?= sanitize($order['order_no']) ?></span>
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d; font-family: Arial, sans-serif;"><?= sanitize($order['created_at']) ?></span>
              </div>
              
              <!-- Right side: prices & status (aligned right) -->
              <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px; text-align: right;">
                <span style="font-size: 16px; font-weight: 800; color: #111111; font-family: Arial, sans-serif;"><?= number_format($order['itokens'], 2, '.', '') ?></span>
                <span style="font-size: 14px; font-weight: 800; color: #333333; font-family: Arial, sans-serif;"><?= $order['type'] === 'USDT' ? '' : '₹ ' ?><?= intval($order['price']) ?><?= $order['type'] === 'USDT' ? ' USDT' : '' ?></span>
                
                <div style="display: flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: bold; color: <?= $status_color ?>;">
                  <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: <?= $status_color ?>;"></span>
                  <span><?= $display_status ?></span>
                </div>
                
                <a href="#" class="order-detail-link" onclick="openBuyDetailModal(<?= htmlspecialchars(json_encode($order)) ?>); return false;" style="color: var(--blue); text-decoration: none; font-weight: bold; font-size: 14px; margin-top: 4px;">Detail</a>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

    <!-- Floating Chat Button -->
    <div class="fab" onclick="location.href='customer.php'">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <circle cx="9" cy="10" r="1"></circle>
        <circle cx="13" cy="10" r="1"></circle>
        <circle cx="17" cy="10" r="1"></circle>
      </svg>
    </div>

  </div>

  <!-- TOAST NOTIFICATION FOR CANCEL SUCCESS -->
  <div class="toast" id="cancel-success-toast" style="position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(20px); background: rgba(44, 62, 80, 0.9); color: #ffffff; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: bold; z-index: 2000; display: flex; align-items: center; gap: 10px; opacity: 0; pointer-events: none; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);">
    <svg viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span>Cancel success</span>
  </div>

  <!-- Order Detail Modal matching screenshot -->
  <div id="detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; font-family: sans-serif; padding: 12px; box-sizing: border-box;">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 380px; padding: 24px 24px 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); box-sizing: border-box; color: #333; position: relative;">
      <!-- Order Number -->
      <h2 id="modal-order-no" style="font-size: 19px; font-weight: 800; color: #2c3e50; text-align: center; margin: 0 0 24px; font-family: monospace; letter-spacing: 0.5px;">-</h2>
      
      <!-- Table Grid Details -->
      <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13.5px; margin-bottom: 24px; font-weight: 700; color: #7f8c8d;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>UPI ID</span>
          <span id="modal-upi-id" style="color: #2c3e50;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>Kyc Partner</span>
          <span id="modal-partner" style="color: #2c3e50;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>Debit time</span>
          <span id="modal-debit-time" style="color: #2c3e50; font-family: monospace;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>Deal time</span>
          <span id="modal-deal-time" style="color: #2c3e50; font-family: monospace;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>Utr</span>
          <span id="modal-utr" style="color: #2c3e50; font-family: monospace;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px;">
          <span>Order status</span>
          <span id="modal-status" style="color: #2c3e50; font-weight: 800;">-</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 4px;">
          <span>Finish time</span>
          <span id="modal-finish-time" style="color: #2c3e50; font-family: monospace;">-</span>
        </div>
      </div>
      
      <!-- Footer links (Centered Close button) -->
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; margin-top: 20px;">
        <!-- Cancel Order Button Trigger -->
        <a href="#" id="modal-cancel-btn" onclick="triggerCancelFromModal(); return false;" style="color: #e74c3c; font-size: 16px; font-weight: 800; text-decoration: none; display: none; margin-bottom: 8px;">Cancel Order</a>
        <a href="#" onclick="closeDetailModal(); return false;" style="color: #2395ee; font-size: 18px; font-weight: 800; text-decoration: none; padding: 6px 0;">Close</a>
      </div>
    </div>
  </div>

  <!-- Hidden Cancellation Form Trigger -->
  <form id="modal-cancel-form" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="cancel_order">
    <input type="hidden" name="cancel_reason_option" value="User cancelled from history list">
  </form>

  <script>
    let currentSelectedOrderNo = '';
    const adminDetails = {
      upi: '<?= sanitize($admin_upi) ?>',
      holder: '<?= sanitize($admin_bank_holder) ?>',
      account: '<?= sanitize($admin_bank_account) ?>',
      ifsc: '<?= sanitize($admin_bank_ifsc) ?>'
    };

    function maskUpi(upi) {
      if (!upi) return 'N/A';
      const parts = upi.split('@');
      if (parts.length !== 2) return upi;
      const user = parts[0];
      const domain = parts[1];
      if (user.length <= 4) {
        return user.substring(0, 1) + '****' + '@' + domain;
      }
      return user.substring(0, 1) + '****' + user.substring(user.length - 2) + '@' + domain;
    }

    function openBuyDetailModal(order) {
      currentSelectedOrderNo = order.order_no;
      document.getElementById('modal-order-no').textContent = order.order_no;
      document.getElementById('modal-upi-id').textContent = maskUpi(order.user_pay_upi || adminDetails.upi);
      document.getElementById('modal-partner').textContent = (order.type === 'USDT' ? 'USDT Wallet' : 'paytm');
      document.getElementById('modal-debit-time').textContent = order.created_at;
      document.getElementById('modal-deal-time').textContent = order.created_at;
      document.getElementById('modal-utr').textContent = order.utr ? order.utr : 'NA';
      document.getElementById('modal-status').textContent = order.status === 'Pending' ? 'In Review' : order.status;
      document.getElementById('modal-finish-time').textContent = order.status === 'Success' ? order.created_at : 'NA';
      
      // Update status styling
      const statusSpan = document.getElementById('modal-status');
      if (order.status === 'Success') {
        statusSpan.style.color = '#2ecc71';
      } else if (order.status === 'Cancelled') {
        statusSpan.style.color = '#e74c3c';
      } else {
        statusSpan.style.color = '#f1c40f';
      }

      // Hide or show cancel button
      const cancelBtn = document.getElementById('modal-cancel-btn');
      if (order.status === 'Pending') {
        cancelBtn.style.display = 'inline-block';
      } else {
        cancelBtn.style.display = 'none';
      }

      document.getElementById('detail-modal').style.display = 'flex';
    }

    function closeDetailModal() {
      document.getElementById('detail-modal').style.display = 'none';
    }

    function triggerCancelFromModal() {
      if (confirm("Are you sure you want to cancel this order?")) {
        const form = document.getElementById('modal-cancel-form');
        form.action = 'checkout.php?order_no=' + encodeURIComponent(currentSelectedOrderNo);
        form.submit();
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      <?php if (isset($_GET['cancel_success']) && $_GET['cancel_success'] === '1'): ?>
        const toast = document.getElementById('cancel-success-toast');
        // Show toast
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
        
        setTimeout(() => {
          toast.style.opacity = '0';
          toast.style.transform = 'translateX(-50%) translateY(20px)';
        }, 2500);
      <?php endif; ?>
    });
  </script>
</body>
</html>

