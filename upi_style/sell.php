<?php
// sell.php - Sell / Consignment State Page (Cloned from user screenshot)
require_once 'db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle auto_selling state update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_in_sell') {
    $new_state = isset($_POST['state']) ? intval($_POST['state']) : 0;
    $stmtUpdate = $pdo->prepare("UPDATE users SET auto_selling = ? WHERE id = ?");
    $stmtUpdate->execute([$new_state, $user_id]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'new_state' => $new_state]);
    exit;
}

// Fetch user profile details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: login.php");
    exit;
}

// Calculate dynamic stats
// 1. In transaction: active/pending sales orders where status is 'Paying'
$stmtTx = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND status = 'Paying'");
$stmtTx->execute([$user_id]);
$in_transaction = $stmtTx->fetchColumn();

// 2. Today Deal: count of all deal attempts today
$stmtTodayDeal = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmtTodayDeal->execute([$user_id]);
$today_deal = $stmtTodayDeal->fetchColumn();

// 3. Today times: count of successful deal orders today
$stmtTodayTimes = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) = CURDATE()");
$stmtTodayTimes->execute([$user_id]);
$today_times = $stmtTodayTimes->fetchColumn();

// 4. Today Success: sum of successful deal amounts today
$stmtTodaySuccess = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sell_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) = CURDATE()");
$stmtTodaySuccess->execute([$user_id]);
$today_success = $stmtTodaySuccess->fetchColumn();

// Active linked UPI accounts count
$stmtActiveUpi = $pdo->prepare("SELECT COUNT(*) FROM upi_accounts WHERE user_id = ? AND status = 'Active'");
$stmtActiveUpi->execute([$user_id]);
$active_upi_count = $stmtActiveUpi->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Sell</title>
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

    /* Switch styling */
    .switch-track {
      width: 44px;
      height: 24px;
      background: #e2e8f0;
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      transition: background 0.2s;
    }

    .switch-track.on {
      background: var(--blue);
    }

    .switch-thumb {
      width: 20px;
      height: 20px;
      background: #ffffff;
      border-radius: 50%;
      position: absolute;
      top: 2px;
      left: 2px;
      transition: transform 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }

    .switch-track.on .switch-thumb {
      transform: translateX(20px);
    }

    /* India flag styling */
    .india-flag {
      width: 20px;
      height: 14px;
      border-radius: 2px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
    }
    .flag-orange { background: #ff9933; height: 33.3%; }
    .flag-white { background: #ffffff; height: 33.3%; position: relative; display: flex; align-items: center; justify-content: center; }
    .flag-blue-dot { width: 3px; height: 3px; border-radius: 50%; background: #000080; }
    .flag-green { background: #138808; height: 33.3%; }

    /* Menu Rows Section */
    .menu-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 20px;
      border-bottom: 1px solid #f2f5f8;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      transition: background-color 0.2s;
    }

    .menu-row:active {
      background-color: #fafbfc;
    }

    /* Floating Support Button */
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

    /* Bottom Tab Navigation Bar */
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
    
    <!-- Header -->
    <header class="header">
      <a href="mine.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Sell</h1>
    </header>

    <div class="content">
      
      <!-- Token Balance Card -->
      <div style="background: #ffffff; border-radius: 12px; padding: 18px 20px; border: 1px solid #ebedf2; display: flex; justify-content: space-between; align-items: center; margin: 16px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="india-flag" style="width: 24px; height: 18px; border-radius: 3px;">
            <div class="flag-orange"></div>
            <div class="flag-white"><div class="flag-blue-dot"></div></div>
            <div class="flag-green"></div>
          </div>
          <span style="font-size: 15px; font-weight: bold; color: #2c3e50;">I Token</span>
        </div>
        <span style="font-size: 18px; font-weight: 800; color: #2c3e50; font-family: Arial, sans-serif;"><?= number_format($user['itoken_balance'], 2, '.', '') ?></span>
      </div>

      <!-- Grid Cards -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 0 20px 24px;">
        <!-- In transaction -->
        <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #ebedf2; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(35, 149, 238, 0.08); display: flex; align-items: center; justify-content: center; color: #2395ee;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 22px; height: 22px;">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">In transaction</span>
          <span style="font-size: 18px; font-weight: 800; color: #2c3e50; font-family: Arial, sans-serif;"><?= intval($in_transaction) ?></span>
        </div>
        <!-- Today Deal -->
        <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #ebedf2; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(243, 156, 18, 0.08); display: flex; align-items: center; justify-content: center; color: #f39c12;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 22px; height: 22px;">
              <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
          </div>
          <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Today Deal</span>
          <span style="font-size: 18px; font-weight: 800; color: #2c3e50; font-family: Arial, sans-serif;"><?= intval($today_deal) ?></span>
        </div>
        <!-- Today times -->
        <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #ebedf2; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(35, 149, 238, 0.08); display: flex; align-items: center; justify-content: center; color: #2395ee;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 22px; height: 22px;">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
          </div>
          <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Today times</span>
          <span style="font-size: 18px; font-weight: 800; color: #2c3e50; font-family: Arial, sans-serif;"><?= intval($today_times) ?></span>
        </div>
        <!-- Today Success -->
        <div style="background: #ffffff; border-radius: 12px; padding: 18px; border: 1px solid #ebedf2; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(243, 156, 18, 0.08); display: flex; align-items: center; justify-content: center; color: #f39c12;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 22px; height: 22px;">
              <circle cx="12" cy="12" r="10"></circle>
              <circle cx="12" cy="12" r="6"></circle>
              <circle cx="12" cy="12" r="2"></circle>
            </svg>
          </div>
          <span style="font-size: 13px; font-weight: bold; color: #7f8c8d;">Today Success</span>
          <span style="font-size: 18px; font-weight: 800; color: #2c3e50; font-family: Arial, sans-serif;"><?= number_format($today_success, 2, '.', '') ?></span>
        </div>
      </div>

      <!-- In Sell toggle Row -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1.5px solid #f2f5f8; margin: 0 10px;">
        <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">In Sell</span>
        <div class="switch-track <?= $user['auto_selling'] ? 'on' : '' ?>" id="sell-toggle-switch" onclick="toggleSellState()">
          <div class="switch-thumb"></div>
        </div>
      </div>

      <!-- Active UPIs Row -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1.5px solid #f2f5f8; margin: 0 10px;">
        <div>
          <div style="font-size: 15px; font-weight: bold; color: #2c3e50;">In Active: <?= intval($active_upi_count) ?></div>
          <div style="font-size: 12px; color: #bdc3c7; margin-top: 4px;">Active UPI</div>
        </div>
        <a href="kycpa.php" style="color: #7f8c8d; font-size: 14px; font-weight: bold; text-decoration: none;">Manage UPI &rsaquo;</a>
      </div>

      <!-- Menu Navigation Links -->
      <div style="margin-top: 16px; display: flex; flex-direction: column;">
        <!-- Buy IToken -->
        <a href="upibuyplus.php" class="menu-row">
          <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;">
              <div class="india-flag" style="width: 20px; height: 14px; border-radius: 2px;">
                <div class="flag-orange"></div>
                <div class="flag-white"><div class="flag-blue-dot"></div></div>
                <div class="flag-green"></div>
              </div>
            </div>
            <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">Buy IToken</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 18px; height: 18px; color: var(--muted);">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </a>
        
        <!-- Sell History -->
        <a href="sellhis.php" class="menu-row">
          <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; color: #2395ee;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
            </div>
            <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">Sell History</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 18px; height: 18px; color: var(--muted);">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </a>

        <!-- Official Service -->
        <a href="customer.php" class="menu-row">
          <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; color: #9b59b6;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;">
                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
              </svg>
            </div>
            <span style="font-size: 16px; font-weight: bold; color: #2c3e50;">Official Service</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 18px; height: 18px; color: var(--muted);">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </a>
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

    <!-- Bottom navigation tabs -->
    <nav class="bottom-nav">
      <a href="home.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Home
      </a>
      <a href="upibuyplus.php" class="nav-item">
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
    function toggleSellState() {
      const switchEl = document.getElementById('sell-toggle-switch');
      const isCurrentlyOn = switchEl.classList.contains('on');
      const newState = isCurrentlyOn ? 0 : 1;
      
      const formData = new FormData();
      formData.append('action', 'toggle_in_sell');
      formData.append('state', newState);

      fetch('sell.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.new_state) {
            switchEl.classList.add('on');
          } else {
            switchEl.classList.remove('on');
          }
        } else {
          alert('Failed to update sell state. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
      });
    }
  </script>
</body>
</html>
