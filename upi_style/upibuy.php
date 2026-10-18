<?php
// upibuy.php - UPI Buy Offers List
require_once 'db.php';
requireLogin();

$error = '';
$success = '';

// Check if user has an active linked UPI account (specifically of type Buy)
$stmtBuyUpis = $pdo->prepare("
    SELECT u.* 
    FROM upi_accounts u 
    JOIN payment_partners p ON u.partner = p.name 
    WHERE u.user_id = ? AND u.status = 'Active' AND u.stopped = 0 AND (p.type = 'Buy' || p.type = 'Both')
");
$stmtBuyUpis->execute([$_SESSION['user_id']]);
$active_buy_upis = $stmtBuyUpis->fetchAll();
$active_buy_count = count($active_buy_upis);
$has_upi = ($active_buy_count > 0);

// Handle Buy Order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_buy') {
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $reward = isset($_POST['reward']) ? floatval($_POST['reward']) : 0;
    $itokens = isset($_POST['itokens']) ? floatval($_POST['itokens']) : 0;
    
    // Determine user_pay_upi (either selected from form or automatically selected if only 1 active)
    $user_pay_upi = isset($_POST['user_pay_upi']) ? trim($_POST['user_pay_upi']) : '';
    if (empty($user_pay_upi) && $active_buy_count === 1) {
        $user_pay_upi = $active_buy_upis[0]['upi_no'];
    }
    
    if (!$has_upi) {
        $error = 'Please link a UPI account first.';
    } elseif ($price <= 0 || $itokens <= 0) {
        $error = 'Invalid order details.';
    } else {
        // Generate random unique order no (16 digits)
        $order_no = '540' . str_pad(mt_rand(1, 9999999999999), 13, '0', STR_PAD_LEFT);
        
        // Fetch rotating UPI to assign to this order
        $upi_info = get_next_rotating_upi($pdo);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO buy_orders (order_no, user_id, type, assigned_upi_id, assigned_upi_name, user_pay_upi, price, reward, itokens, status) VALUES (?, ?, 'UPI', ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$order_no, $_SESSION['user_id'], $upi_info['upi_id'], $upi_info['name'], $user_pay_upi, $price, $reward, $itokens]);
            header("Location: checkout.php?order_no=" . urlencode($order_no));
            exit;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Define the static list of mock offers for UPI
$offers = [
    ['no' => '5406192572042245', 'price' => 2000, 'reward' => 65, 'itokens' => 2065.00, 'tag' => 'Reward 3%+5'],
    ['no' => '5406188382718981', 'price' => 3300, 'reward' => 104, 'itokens' => 3404.00, 'tag' => 'Reward 3%+5'],
    ['no' => '5406185242233861', 'price' => 4700, 'reward' => 146, 'itokens' => 4846.00, 'tag' => 'Reward 3%+5'],
    ['no' => '5405653342161925', 'price' => 5000, 'reward' => 155, 'itokens' => 5155.00, 'tag' => 'Reward 3%+5'],
    ['no' => '5406065432857605', 'price' => 6000, 'reward' => 185, 'itokens' => 6185.00, 'tag' => 'Reward 3%+5']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Buy (UPI)</title>
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
      --accent-red: #e74c3c;
      --accent-orange: #f39c12;
      --accent-blue: #1c82e6;
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
      padding-bottom: 80px; /* Space for fixed bottom nav */
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Page Header */
    .header {
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-bottom: 1px solid #f5f7fa;
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 10;
    }

    .header-title {
      font-size: 20px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0;
    }

    /* Tab navigation */
    .sub-tabs {
      display: flex;
      width: 100%;
      border-bottom: 1px solid #f0f3f6;
      background: #ffffff;
    }

    .tab-item {
      flex: 1;
      text-align: center;
      padding: 14px 0;
      text-decoration: none;
      color: #7f8c8d;
      font-size: 15px;
      font-weight: bold;
      position: relative;
      transition: color 0.2s;
    }

    .tab-item.active {
      color: var(--blue);
    }

    .tab-item.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 35%;
      width: 30%;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
    }

    /* Content Scroll Area */
    .content {
      flex: 1;
      overflow-y: auto;
      background: #ffffff;
    }

    /* Alerts */
    .alert {
      padding: 12px 18px;
      border-radius: 8px;
      margin: 12px 16px;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
    }
    .alert-error {
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      color: var(--accent-red);
    }
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #dcfce7;
      color: var(--success-green);
    }

    /* Filter Bar styling */
    .filter-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 18px;
      border-bottom: 1px solid #f0f3f6;
      background: #ffffff;
    }

    .filter-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .arrow-controls {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .arrow-controls svg {
      width: 16px;
      height: 16px;
      cursor: pointer;
    }

    .arrow-up {
      color: var(--blue);
    }

    .arrow-down {
      color: var(--muted);
    }

    .filter-limit {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      color: #7f8c8d;
    }

    .filter-limit span.value {
      color: #111111;
      font-weight: bold;
    }

    .filter-right {
      display: flex;
      align-items: center;
    }

    .search-btn {
      color: #f39c12;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .search-btn svg {
      width: 20px;
      height: 20px;
    }

    /* List of buy offers */
    .offers-list {
      display: flex;
      flex-direction: column;
    }

    .offer-item {
      padding: 16px;
      border-bottom: 1px solid #f2f5f8;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .offer-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: #7f8c8d;
    }

    .offer-no {
      font-family: Arial, sans-serif;
    }

    .offer-reward-tag {
      font-weight: bold;
    }

    .offer-body {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* Math layout columns */
    .offer-math {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .math-column {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .math-label {
      font-size: 11px;
      margin-bottom: 4px;
      font-weight: bold;
    }

    .math-value {
      font-size: 18px;
      font-weight: bold;
    }

    /* Colors for price/reward/itoken */
    .math-price .math-label { color: var(--accent-red); }
    .math-price .math-value { color: var(--accent-red); font-size: 20px; font-weight: 900;}

    .math-reward .math-label { color: #333333; }
    .math-reward .math-value { color: #333333; }

    .math-itoken .math-label { color: var(--accent-blue); }
    .math-itoken .math-value { color: var(--accent-blue); }

    .math-operator {
      font-size: 18px;
      font-weight: bold;
      color: #555555;
      padding-top: 15px; /* Offset to align with values */
    }

    /* Buy Action Button */
    .buy-btn-small {
      background: #4fa7ff;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 18px;
      font-size: 15px;
      font-weight: bold;
      cursor: pointer;
      font-family: inherit;
      box-shadow: 0 2px 6px rgba(79, 167, 255, 0.3);
      transition: background 0.2s, transform 0.1s;
    }

    .buy-btn-small:hover {
      background: #2b91ff;
    }

    .buy-btn-small:active {
      transform: scale(0.96);
    }

    /* Floating Chat Action Button */
    .fab {
      position: absolute;
      bottom: 84px;
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

    /* Bottom tab bar */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: min(100%, 574px);
      height: 64px;
      background: #ffffff;
      border-top: 1px solid var(--line);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 100;
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      text-decoration: none;
      color: var(--muted);
      font-size: 12px;
      font-weight: bold;
      width: 20%;
      height: 100%;
    }

    .nav-item.active {
      color: var(--blue);
    }

    .nav-item svg {
      width: 22px;
      height: 22px;
    }

    /* Bottom Drawer Styles */
    .drawer-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.4);
      z-index: 10000;
      display: none;
    }
    .bottom-drawer {
      position: fixed;
      bottom: 0; left: 50%;
      transform: translate(-50%, 100%);
      width: min(100%, 574px);
      background: #ffffff;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      padding: 24px;
      box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.15);
      z-index: 10001;
      transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
      box-sizing: border-box;
    }
    .drawer-title {
      font-size: 16px;
      font-weight: 800;
      color: #d35400;
      margin: 0;
    }
    .drawer-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 12px;
      padding: 8px 0 16px;
    }
    .drawer-error-text {
      font-size: 15px;
      font-weight: 700;
      color: #e74c3c;
      line-height: 1.5;
      margin: 0;
    }
    .drawer-footer-link {
      display: block;
      margin-top: 12px;
      font-size: 14px;
      font-weight: bold;
      color: #7f8c8d;
      text-decoration: none;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="screen">
    <!-- Header title -->
    <header class="header">
      <h1 class="header-title">Buy</h1>
    </header>

    <!-- Sub Navigation Tabs -->
    <nav class="sub-tabs">
      <a href="upibuyplus.php" class="tab-item">UPI Plus</a>
      <a href="upibuy.php" class="tab-item active">UPI</a>
      <a href="usdtbuy.php" class="tab-item">USDT</a>
    </nav>

    <!-- Scrollable content area -->
    <div class="content">

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
      <?php endif; ?>
      
      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-left">
          <div class="arrow-controls">
            <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="18 15 12 9 6 15"></polyline>
            </svg>
            <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="filter-limit">
            <span>Min</span>
            <span class="value">100</span>
            <span>Max</span>
            <span class="value">100000</span>
          </div>
        </div>
        <div class="filter-right">
          <div class="search-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>
        </div>
      </div>

      <!-- Offers List (UPI layout) -->
      <div class="offers-list">
        
        <?php foreach ($offers as $off): ?>
          <div class="offer-item">
            <div class="offer-header">
              <span class="offer-no">No:<?= sanitize($off['no']) ?></span>
              <span class="offer-reward-tag"><?= sanitize($off['tag']) ?></span>
            </div>
            <div class="offer-body">
              <div class="offer-math">
                <div class="math-column math-price">
                  <span class="math-label">Price</span>
                  <span class="math-value">₹ <?= intval($off['price']) ?></span>
                </div>
                <div class="math-operator">+</div>
                <div class="math-column math-reward">
                  <span class="math-label">Reward</span>
                  <span class="math-value"><?= floatval($off['reward']) ?></span>
                </div>
                <div class="math-operator">=</div>
                <div class="math-column math-itoken">
                  <span class="math-label">Itoken</span>
                  <span class="math-value"><?= number_format($off['itokens'], 2, '.', '') ?></span>
                </div>
              </div>
              <form method="POST" style="margin: 0;" onsubmit="return handleBuyClick(event, this)">
                <input type="hidden" name="action" value="place_buy">
                <input type="hidden" name="price" value="<?= $off['price'] ?>">
                <input type="hidden" name="reward" value="<?= $off['reward'] ?>">
                <input type="hidden" name="itokens" value="<?= $off['itokens'] ?>">
                <button type="submit" class="buy-btn-small">Buy</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
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

    <!-- Bottom tab bar navigation -->
    <nav class="bottom-nav">
      <a href="home.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Home
      </a>
      <a href="upibuyplus.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="2" y1="10" x2="22" y2="10"></line>
        </svg>
        Buy
      </a>
      <a href="kycpa.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        UPI
      </a>
      <a href="agent.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Team
      </a>
      <a href="mine.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        Mine
      </a>
    </nav>
  </div>

  <!-- Bottom Drawer Overlay -->
  <div id="upi-drawer-overlay" class="drawer-overlay" onclick="closeUpiDrawer()"></div>

  <!-- Bottom Drawer -->
  <div id="upi-drawer" class="bottom-drawer">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f3f6; padding-bottom: 12px; margin-bottom: 16px;">
      <h3 class="drawer-title" style="margin: 0; font-family: inherit; color: #c0392b;">Pay using the following payment UPIs:</h3>
      <button onclick="closeUpiDrawer()" style="background: transparent; border: none; font-size: 20px; color: #95a5a6; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    
    <div class="drawer-content">
      <p class="drawer-error-text" style="color: #c0392b; font-weight: bold;">No Payment UPI Available. Link a wallet that supports purchases (e.g., Freecharge, Mobikwik).</p>
      <a href="kycpa.php" class="buy-btn-small" style="margin-top: 12px; text-decoration: none; text-align: center; width: 100%; display: block; padding: 12px; font-weight: bold; background: var(--blue); color: white; border-radius: 8px;">
        Go to Link UPI
      </a>
      <a href="kycpa.php" class="drawer-footer-link">Manage UPI</a>
    </div>
  </div>

  <!-- Payer UPI Selector Drawer -->
  <div id="payer-upi-overlay" class="drawer-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;" onclick="closePayerUpiDrawer()"></div>
  <div id="payer-upi-drawer" class="bottom-drawer" style="position: fixed; bottom: 0; left: 50%; transform: translate(-50%, 100%); width: min(100%, 574px); background: #ffffff; border-radius: 20px 20px 0 0; padding: 20px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); z-index: 1001; transition: transform 0.2s ease-out; max-height: 80vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f3f6; padding-bottom: 12px; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #2c3e50;">Select Your Paying UPI ID</h3>
      <button onclick="closePayerUpiDrawer()" style="background: transparent; border: none; font-size: 20px; color: #95a5a6; cursor: pointer;">&times;</button>
    </div>
    <div style="display: flex; flex-direction: column; gap: 12px;">
      <?php foreach ($active_buy_upis as $uu): ?>
        <div class="payer-upi-card" onclick="selectPayerUpi('<?= sanitize($uu['upi_no']) ?>')" style="border: 2.2px solid #ebedf2; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; background: #ffffff;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 15px; font-weight: 800; color: #2c3e50; font-family: monospace;"><?= sanitize($uu['upi_no']) ?></span>
            <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; font-weight: bold;"><?= sanitize($uu['partner']) ?></span>
          </div>
          <span style="font-size: 12px; color: #7f8c8d; font-weight: bold;"><?= sanitize($uu['name']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <style>
    .payer-upi-card:hover {
      border-color: var(--blue) !important;
      background: #f0f8ff !important;
    }
  </style>

  <script>
    const hasUpiLinked = <?= $has_upi ? 'true' : 'false' ?>;
    let pendingForm = null;

    function handleBuyClick(event, formElement) {
      const activeBuyCount = <?= intval($active_buy_count) ?>;
      
      if (activeBuyCount === 0) {
        event.preventDefault();
        openUpiDrawer();
        return false;
      }
      
      if (activeBuyCount >= 2) {
        event.preventDefault();
        pendingForm = formElement;
        openPayerUpiDrawer();
        return false;
      }
      
      return true;
    }

    function openPayerUpiDrawer() {
      document.getElementById('payer-upi-drawer').style.transform = 'translate(-50%, 0)';
      document.getElementById('payer-upi-overlay').style.display = 'block';
    }

    function closePayerUpiDrawer() {
      document.getElementById('payer-upi-drawer').style.transform = 'translate(-50%, 100%)';
      document.getElementById('payer-upi-overlay').style.display = 'none';
    }

    function selectPayerUpi(upiNo) {
      if (pendingForm) {
        let input = pendingForm.querySelector('input[name="user_pay_upi"]');
        if (!input) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'user_pay_upi';
          pendingForm.appendChild(input);
        }
        input.value = upiNo;
        pendingForm.submit();
      }
      closePayerUpiDrawer();
    }

    function openUpiDrawer() {
      document.getElementById('upi-drawer').style.transform = 'translate(-50%, 0)';
      document.getElementById('upi-drawer-overlay').style.display = 'block';
    }

    function closeUpiDrawer() {
      document.getElementById('upi-drawer').style.transform = 'translate(-50%, 100%)';
      document.getElementById('upi-drawer-overlay').style.display = 'none';
    }
  </script>
</body>
</html>

