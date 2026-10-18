<?php
// sellhis.php - Sell Order History
require_once 'db.php';
requireLogin();

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'paying';
if (!in_array($current_tab, ['paying', 'success', 'all'])) {
    $current_tab = 'paying';
}

$user_id = $_SESSION['user_id'];

// Fetch the user's active payout account details
$stmtUpi = $pdo->prepare("SELECT * FROM upi_accounts WHERE user_id = ? AND status = 'Active' LIMIT 1");
$stmtUpi->execute([$user_id]);
$user_upi = $stmtUpi->fetch();

$user_upi_no = $user_upi ? $user_upi['upi_no'] : 'Not Linked';
$user_upi_name = $user_upi ? $user_upi['name'] : '-';
$user_upi_partner = $user_upi ? $user_upi['partner'] : 'NextPay';

// Query sell orders from DB
if ($current_tab === 'paying') {
    $stmt = $pdo->prepare("SELECT * FROM sell_orders WHERE user_id = ? AND status = 'Paying' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "There are no sales orders at the moment. If you have enabled consignment, please check the authorization of the key partner and whether the kyc partner can receive payment normally or contact customer service in time";
} elseif ($current_tab === 'success') {
    $stmt = $pdo->prepare("SELECT * FROM sell_orders WHERE user_id = ? AND status = 'Success' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "No successful sales history found";
} else {
    $stmt = $pdo->prepare("SELECT * FROM sell_orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    $empty_msg = "No sales orders found under any status";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Sell History</title>
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

    /* Order lists */
    .order-list {
      display: flex;
      flex-direction: column;
    }

    .order-item-box {
      padding: 16px;
      border-bottom: 1px solid #f2f5f8;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .order-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      font-weight: bold;
    }

    .order-id-text {
      color: #2c3e50;
      font-family: Arial, sans-serif;
    }

    .order-status {
      font-size: 12px;
      padding: 3px 8px;
      border-radius: 4px;
      font-weight: bold;
    }

    .order-status.Paying {
      background: #fff5ec;
      color: #e67e22;
    }

    .order-status.Success {
      background: #f0fdf4;
      color: #2ecc71;
    }

    .order-status.Failed {
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

    .order-amt-val {
      font-size: 16px;
      font-weight: bold;
      color: #333333;
      font-family: Arial, sans-serif;
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
      padding: 48px 20px;
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
      margin-bottom: 4px;
    }

    .empty-text {
      font-size: 13.5px;
      font-weight: bold;
      color: #2c3e50;
      line-height: 1.5;
      margin: 0;
      max-width: 460px;
    }

    .action-btn {
      margin-top: 14px;
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
      <h1 class="header-title">Sell history</h1>
    </header>

    <div class="content">
      
      <!-- Sub tabs -->
      <nav class="sub-tabs">
        <a href="sellhis.php?tab=paying" class="tab-item <?= $current_tab === 'paying' ? 'active' : '' ?>">Paying</a>
        <a href="sellhis.php?tab=success" class="tab-item <?= $current_tab === 'success' ? 'active' : '' ?>">Success</a>
        <a href="sellhis.php?tab=all" class="tab-item <?= $current_tab === 'all' ? 'active' : '' ?>">All</a>
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
          <p class="empty-text">
            <?= sanitize($empty_msg) ?>
          </p>
          
          <a href="sell.php" class="action-btn">Check Sell state</a>
        </div>
      <?php else: ?>
        <!-- Orders List view -->
        <div class="order-list">
          <?php foreach ($orders as $order): ?>
            <?php 
              // Generate custom 16-digit order number
              $order_no_display = '542' . str_pad($order['id'], 13, '0', STR_PAD_LEFT);
              $display_status = strtolower($order['status']);
              $status_color = $order['status'] === 'Success' ? '#2ecc71' : ($order['status'] === 'Failed' ? '#e74c3c' : '#f1c40f');
            ?>
            <div class="order-item-box" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f2f5f8; padding: 16px 20px;">
              
              <!-- Left Column: Details -->
              <div style="display: flex; flex-direction: column; gap: 4px; text-align: left; flex: 1;">
                
                <!-- Title Row: Icon and Order Number -->
                <div style="display: flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 800; color: #111111;">
                  <!-- Styled exchange icon -->
                  <div style="width: 20px; height: 20px; background: rgba(35,149,238,0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#2395ee" stroke-width="3" style="width: 12px; height: 12px;">
                      <path d="M17 3L21 7L17 11M7 21L3 17L7 13M3 17H21M21 7H3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                  <span><?= $order_no_display ?></span>
                </div>
                
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Selling price <span style="color: #111111;">₹ <?= number_format($order['amount'], 2) ?></span></span>
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Quantity <span style="color: #111111; font-family: Arial, sans-serif;"><?= number_format($order['amount'], 2) ?> IToken</span></span>
                <span style="font-size: 13px; font-weight: bold; color: #7f8c8d; font-family: Arial, sans-serif;">Deal Date <span style="color: #111111;"><?= sanitize($order['created_at']) ?></span></span>
                
                <!-- Status Row -->
                <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: bold; margin-top: 2px;">
                  <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: <?= $status_color ?>;"></span>
                  <span style="color: <?= $status_color ?>; font-weight: 800;"><?= $display_status ?></span>
                </div>
                
              </div>
              
              <!-- Right Column: Detail Link (Vertically Centered) -->
              <div style="margin-left: 16px;">
                <a href="#" class="order-detail-link" onclick="openSellDetailModal(<?= htmlspecialchars(json_encode($order)) ?>); return false;" style="color: var(--blue); text-decoration: none; font-weight: bold; font-size: 14px;">Detail</a>
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

  <!-- Sell Order Detail Modal matching screenshot -->
  <div id="sell-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; font-family: sans-serif; padding: 12px; box-sizing: border-box;">
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
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-top: 20px;">
        <a href="#" onclick="closeSellDetailModal(); return false;" style="color: #2395ee; font-size: 18px; font-weight: 800; text-decoration: none; padding: 6px 0;">Close</a>
      </div>
    </div>
  </div>

  <script>
    const userUpiDetails = {
      upi: '<?= sanitize($user_upi_no) ?>',
      name: '<?= sanitize($user_upi_name) ?>',
      partner: '<?= sanitize($user_upi_partner) ?>'
    };

    function maskUpi(upi) {
      if (!upi || upi === 'Not Linked') return 'N/A';
      const parts = upi.split('@');
      if (parts.length !== 2) return upi;
      const user = parts[0];
      const domain = parts[1];
      if (user.length <= 4) {
        return user.substring(0, 1) + '****' + '@' + domain;
      }
      return user.substring(0, 1) + '****' + user.substring(user.length - 2) + '@' + domain;
    }

    function openSellDetailModal(order) {
      const orderNoDisplay = '542' + String(order.id).padStart(13, '0');
      document.getElementById('modal-order-no').textContent = orderNoDisplay;
      document.getElementById('modal-upi-id').textContent = maskUpi(userUpiDetails.upi);
      document.getElementById('modal-partner').textContent = userUpiDetails.partner.toLowerCase();
      document.getElementById('modal-debit-time').textContent = order.created_at;
      document.getElementById('modal-deal-time').textContent = order.created_at;
      document.getElementById('modal-utr').textContent = order.status === 'Success' ? ('309' + String(order.id).padStart(9, '9')) : 'NA';
      document.getElementById('modal-status').textContent = order.status;
      
      // Calculate finish time = deal time + 1 min 9 sec as a simulation if success
      if (order.status === 'Success') {
        const d = new Date(order.created_at.replace(/-/g, "/"));
        d.setMinutes(d.getMinutes() + 1);
        d.setSeconds(d.getSeconds() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const hh = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        const sec = String(d.getSeconds()).padStart(2, '0');
        document.getElementById('modal-finish-time').textContent = `${yyyy}-${mm}-${dd} ${hh}:${min}:${sec}`;
      } else {
        document.getElementById('modal-finish-time').textContent = 'NA';
      }
      
      // Update status styling
      const statusSpan = document.getElementById('modal-status');
      if (order.status === 'Success') {
        statusSpan.style.color = '#2ecc71';
      } else if (order.status === 'Failed') {
        statusSpan.style.color = '#e74c3c';
      } else {
        statusSpan.style.color = '#f1c40f';
      }

      document.getElementById('sell-detail-modal').style.display = 'flex';
    }

    function closeSellDetailModal() {
      document.getElementById('sell-detail-modal').style.display = 'none';
    }
  </script>
</body>
</html>

