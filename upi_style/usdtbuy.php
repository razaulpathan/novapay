<?php
// usdtbuy.php - Buy via USDT
require_once 'db.php';
requireLogin();

$error = '';
$success = '';

// Fetch exchange rate dynamically
$exchange_rate_usdt = getSetting('exchange_rate_usdt') ?: '106.7';
$exchange_rate_usdt = floatval($exchange_rate_usdt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usdt_quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    
    if ($usdt_quantity <= 0) {
        $error = 'Please enter a valid USDT quantity.';
    } else {
        $itokens = $usdt_quantity * $exchange_rate_usdt;
        // Generate random unique order no (16 digits)
        $order_no = '540' . str_pad(mt_rand(1, 9999999999999), 13, '0', STR_PAD_LEFT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO buy_orders (order_no, user_id, type, price, reward, itokens, status) VALUES (?, ?, 'USDT', ?, 0, ?, 'Pending')");
            $stmt->execute([$order_no, $_SESSION['user_id'], $usdt_quantity, $itokens]);
            header("Location: checkout.php?order_no=" . urlencode($order_no));
            exit;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Buy (USDT)</title>
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
      --border-color: #eef2f5;
      --tether-green: #26a17b;
      --warning-bg: #fff5ec;
      --warning-text: #e67e22;
      --accent-red: #e74c3c;
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
      background: var(--bg-color);
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

    /* Content Area */
    .content {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* Alerts */
    .alert {
      padding: 12px 18px;
      border-radius: 8px;
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

    /* Section Cards */
    .card {
      background: #ffffff;
      border-radius: 12px;
      padding: 16px;
      border: 1px solid #edf1f5;
      box-shadow: 0 2px 6px rgba(0,0,0,0.01);
    }

    /* Exchange Rate Row */
    .exchange-card {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .exchange-label {
      color: #7f8c8d;
      font-size: 14px;
      font-weight: bold;
    }

    .exchange-value {
      color: var(--blue);
      font-size: 15px;
      font-weight: 900;
    }

    /* Input Card Styles */
    .input-card {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .card-label {
      color: #7f8c8d;
      font-size: 13px;
      font-weight: bold;
    }

    .input-row {
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid #f0f3f6;
      padding-bottom: 8px;
    }

    .tether-icon {
      width: 24px;
      height: 24px;
      color: var(--tether-green);
      flex-shrink: 0;
    }

    .input-field {
      flex: 1;
      border: none;
      outline: none;
      font-size: 18px;
      font-family: inherit;
      color: #111;
      padding: 0;
    }

    .input-field::placeholder {
      color: #ccc;
    }

    .itoken-approx {
      color: #95a5a6;
      font-size: 13px;
      font-weight: bold;
      white-space: nowrap;
    }

    /* Warning alert banner */
    .warning-banner {
      background: var(--warning-bg);
      border-radius: 8px;
      padding: 10px 14px;
      color: var(--warning-text);
      font-size: 13px;
      font-weight: bold;
      line-height: 1.3;
    }

    /* Network Card */
    .network-card {
      display: flex;
      flex-direction: column;
      gap: 4px;
      cursor: pointer;
    }

    .network-select-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .network-name {
      font-size: 18px;
      font-weight: 900;
      color: #111111;
    }

    .network-arrow {
      color: #bdc3c7;
      display: flex;
      align-items: center;
    }

    .network-arrow svg {
      width: 20px;
      height: 20px;
    }

    .network-subtitle {
      font-size: 11px;
      color: #95a5a6;
      margin: 0;
    }

    /* Continue Button */
    .continue-btn {
      background: #1c82e6;
      color: white;
      border: none;
      border-radius: 12px;
      width: 100%;
      padding: 16px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      font-family: inherit;
      box-shadow: 0 4px 12px rgba(28, 130, 230, 0.25);
      transition: background-color 0.2s, transform 0.1s;
    }

    .continue-btn:hover {
      background: #0f6fcd;
    }

    .continue-btn:active {
      transform: scale(0.98);
    }

    /* Deposit guide styling */
    .deposit-guide-card {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .guide-title {
      font-size: 16px;
      font-weight: 800;
      color: #111111;
      margin: 0 0 4px 0;
    }

    .guide-steps {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .step-item {
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .step-badge {
      width: 26px;
      height: 26px;
      background: #eaeff2;
      border-radius: 50%;
      color: #7f8c8d;
      font-size: 13px;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .step-content {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .step-heading {
      font-size: 14px;
      font-weight: bold;
      color: #2c3e50;
    }

    .step-text {
      font-size: 11px;
      color: #95a5a6;
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
      <a href="upibuy.php" class="tab-item">UPI</a>
      <a href="usdtbuy.php" class="tab-item active">USDT</a>
    </nav>

    <!-- Scrollable content area -->
    <div class="content">

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
      <?php endif; ?>
      
      <!-- Exchange rate card -->
      <div class="card exchange-card">
        <span class="exchange-label">Exchange Rate</span>
        <span class="exchange-value">1 USDT &approx; <?= sanitize($exchange_rate_usdt) ?> IToken</span>
      </div>

      <!-- Amount input card -->
      <form class="card input-card" method="POST" action="usdtbuy.php">
        <span class="card-label">Amount</span>
        <div class="input-row">
          <!-- Tether Green Icon -->
          <svg class="tether-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="11" fill="#26a17b"/>
            <path d="M12 5.5c-2.9 0-5.3.6-5.3 1.3s2.4 1.3 5.3 1.3 5.3-.6 5.3-1.3-2.4-1.3-5.3-1.3zM12 9c-3.1 0-5.6.8-5.6 1.8 0 .8 1.6 1.4 3.9 1.7V17h3.4v-4.5c2.3-.3 3.9-.9 3.9-1.7C17.6 9.8 15.1 9 12 9z" fill="white"/>
          </svg>
          <input type="number" name="quantity" id="usdtInput" step="any" class="input-field" placeholder="Enter the quantity" required oninput="calculateIToken()">
          <span class="itoken-approx" id="approxText">&approx; 0.00 IToken</span>
        </div>
        <!-- Warning alert banner -->
        <div class="warning-banner">
          Please enter the value you want buy
        </div>

        <!-- Network configuration card -->
        <div class="card network-card" style="margin: 0; padding: 0; border: none; box-shadow: none;">
          <span class="card-label">Network</span>
          <div class="network-select-row" style="margin-top: 4px;">
            <span class="network-name">TRC-20</span>
            <span class="network-arrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </span>
          </div>
          <p class="network-subtitle" style="margin-top: 4px; margin-bottom: 0;">Protocol: TRC-20, Tron network, USDT</p>
        </div>

        <button type="submit" class="continue-btn" style="margin-top: 12px;">Continue</button>
      </form>

      <!-- Deposit Instructions Card -->
      <div class="card deposit-guide-card">
        <h4 class="guide-title">How to Deposit</h4>
        
        <div class="guide-steps">
          <!-- Step 1 -->
          <div class="step-item">
            <div class="step-badge">1</div>
            <div class="step-content">
              <span class="step-heading">Enter Amount</span>
              <span class="step-text">Input the amount you want to deposit</span>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="step-item">
            <div class="step-badge">2</div>
            <div class="step-content">
              <span class="step-heading">Select Network</span>
              <span class="step-text">Choose TRC-20, BEP-20 or other network</span>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="step-item">
            <div class="step-badge">3</div>
            <div class="step-content">
              <span class="step-heading">Scan QR or Copy Address</span>
              <span class="step-text">Complete the payment on your wallet app</span>
            </div>
          </div>
        </div>

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

  <script>
    const rate = <?= $exchange_rate_usdt ?>;
    function calculateIToken() {
      const quantity = parseFloat(document.getElementById('usdtInput').value);
      const approxText = document.getElementById('approxText');
      if (isNaN(quantity) || quantity <= 0) {
        approxText.innerText = "&approx; 0.00 IToken";
      } else {
        approxText.innerText = "≈ " + (quantity * rate).toFixed(2) + " IToken";
      }
    }
  </script>
</body>
</html>

