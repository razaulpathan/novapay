<?php
// mine.php - User Profile Settings & Order Configuration
require_once 'db.php';
requireLogin();

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch In Sell tokens
$stmtInSell = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sell_orders WHERE user_id = ? AND status = 'Paying'");
$stmtInSell->execute([$user['id']]);
$in_sell_tokens = floatval($stmtInSell->fetchColumn());

// Today's own trade profit
$stmtTradeProfit = $pdo->prepare("SELECT COALESCE(SUM(reward), 0) FROM buy_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) = CURRENT_DATE()");
$stmtTradeProfit->execute([$user['id']]);
$trade_profit_today = floatval($stmtTradeProfit->fetchColumn());

// Today's team commission profit
$l1_rate = floatval(getSetting('commission_l1_rate') ?: '0.003');
$l2_rate = floatval(getSetting('commission_l2_rate') ?: '0.002');

$stmtCommTodayL1 = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM buy_orders bo JOIN users u ON bo.user_id = u.id WHERE u.referred_by = ? AND bo.status = 'Success' AND DATE(bo.created_at) = CURRENT_DATE()");
$stmtCommTodayL1->execute([$user['id']]);
$comm_today_l1 = floatval($stmtCommTodayL1->fetchColumn()) * $l1_rate;

$stmtCommTodayL2 = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM buy_orders bo JOIN users u2 ON bo.user_id = u2.id JOIN users u1 ON u2.referred_by = u1.id WHERE u1.referred_by = ? AND bo.status = 'Success' AND DATE(bo.created_at) = CURRENT_DATE()");
$stmtCommTodayL2->execute([$user['id']]);
$comm_today_l2 = floatval($stmtCommTodayL2->fetchColumn()) * $l2_rate;

$team_profit_today = $comm_today_l1 + $comm_today_l2;

// Today's check-in / newbie reward event profit
$checkin_bonus = floatval(getSetting('daily_checkin_bonus') ?: '5');
$newbie_bonus = floatval(getSetting('newbie_reward_amount') ?: '50');

$stmtCheckin = $pdo->prepare("SELECT CASE WHEN DATE(last_checkin) = CURRENT_DATE() THEN 1 ELSE 0 END FROM users WHERE id = ?");
$stmtCheckin->execute([$user['id']]);
$has_checked_in = intval($stmtCheckin->fetchColumn()) === 1;

$stmtNewbie = $pdo->prepare("SELECT CASE WHEN DATE(created_at) = CURRENT_DATE() THEN 1 ELSE 0 END FROM users WHERE id = ?");
$stmtNewbie->execute([$user['id']]);
$is_newbie = intval($stmtNewbie->fetchColumn()) === 1;

$event_reward_today = 0.00;
if ($has_checked_in) {
    $event_reward_today += $checkin_bonus;
}
if ($is_newbie) {
    $event_reward_today += $newbie_bonus;
}

// Handle Order size setting update via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_size') {
    header('Content-Type: application/json');
    $min = isset($_POST['min']) ? intval($_POST['min']) : 0;
    $max = isset($_POST['max']) ? intval($_POST['max']) : 0;

    if ($min < 100 || $min > 10000) {
        echo json_encode(['success' => false, 'message' => 'Min must be between 100 and 10,000.']);
        exit;
    }
    if ($max < 2000 || $max > 100000) {
        echo json_encode(['success' => false, 'message' => 'Max must be between 2,000 and 100,000.']);
        exit;
    }
    if (($max - $min) < 1000) {
        echo json_encode(['success' => false, 'message' => 'Max must exceed Min by at least 1,000.']);
        exit;
    }

    try {
        $updateStmt = $pdo->prepare("UPDATE users SET order_min = ?, order_max = ? WHERE id = ?");
        $updateStmt->execute([$min, $max, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Order size updated successfully!', 'min' => $min, 'max' => $max]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Mine</title>
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
      --warning-red: #d35400;
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

    .content {
      padding: 16px 0;
      flex: 1;
      overflow-y: auto;
    }

    /* Profile Header Section */
    .profile-section {
      display: flex;
      align-items: center;
      padding: 16px 20px;
      gap: 16px;
      border-bottom: 8px solid #f7f9fc;
    }

    .avatar-wrapper {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #eef5fc;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #d4e6f1;
      overflow: hidden;
    }

    .profile-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
    }

    .reward-badge {
      color: #7f8c8d;
      font-size: 13px;
      font-weight: bold;
    }

    .user-id-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
    }

    .user-id {
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
      font-family: Arial, sans-serif;
    }

    .chevron-right {
      color: var(--muted);
      width: 18px;
      height: 18px;
    }

    /* List Rows Section */
    .menu-list {
      display: flex;
      flex-direction: column;
    }

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

    .menu-left {
      display: flex;
      align-items: center;
      gap: 14px;
      font-size: 16px;
      font-weight: bold;
      color: #2c3e50;
    }

    .menu-icon {
      width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .menu-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .menu-val {
      font-size: 15px;
      font-weight: 800;
      color: #f39c12;
      font-family: Arial, sans-serif;
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

    /* Sign out button area */
    .signout-area {
      padding: 24px 20px;
    }

    .signout-btn {
      width: 100%;
      height: 52px;
      border-radius: 12px;
      background: #fafbfc;
      border: 1px solid #e1e8ed;
      color: #7f8c8d;
      font: 800 16px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background-color 0.2s;
    }

    .signout-btn:active {
      background: #f0f3f5;
    }

    /* Floating Chat Button */
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
      transition: color 0.2s;
    }

    .nav-item.active {
      color: var(--blue);
    }

    .nav-item svg {
      width: 22px;
      height: 22px;
    }

    /* MODAL OVERLAYS */
    .modal-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 200;
      display: none;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    /* Event List Modal Content */
    .event-modal-content {
      background: #ffffff;
      width: 100%;
      max-width: 380px;
      border-radius: 18px;
      padding: 24px 20px 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      animation: modalAppear 0.25s ease forwards;
      position: relative;
    }

    .modal-close-x {
      position: absolute;
      top: 14px;
      right: 14px;
      color: #95a5a6;
      cursor: pointer;
    }
    .modal-close-x svg {
      width: 20px;
      height: 20px;
    }

    .modal-title {
      font-size: 18px;
      font-weight: 900;
      color: #111111;
      margin: 4px 0 8px;
    }

    .modal-btn {
      width: 100%;
      height: 52px;
      border-radius: 12px;
      border: 1px solid #e1e8ed;
      background: #ffffff;
      color: #2c3e50;
      font: 800 15px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background-color 0.2s, box-shadow 0.2s;
    }

    .modal-btn:hover {
      background: #fafbfc;
      box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }

    .modal-cancel-btn {
      background: transparent;
      border: none;
      color: #2395ee;
      font-size: 16px;
      font-weight: 800;
      cursor: pointer;
      padding: 8px 20px;
      margin-top: 4px;
      font-family: inherit;
    }

    /* Order Sell Size Setting Modal Content */
    .setting-modal-content {
      background: #ffffff;
      width: 100%;
      max-width: 380px;
      border-radius: 18px;
      padding: 24px 20px 0;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      display: flex;
      flex-direction: column;
      gap: 14px;
      animation: modalAppear 0.25s ease forwards;
    }

    .warning-desc {
      color: var(--warning-red);
      font-size: 12px;
      font-weight: bold;
      line-height: 1.4;
      margin: 0;
    }

    .setting-input-group {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: bold;
      color: #7f8c8d;
    }

    .setting-input-group span {
      width: 40px;
    }

    .setting-input {
      flex: 1;
      height: 48px;
      border: 1.5px solid #d4e1eb;
      border-radius: 8px;
      padding: 0 14px;
      outline: none;
      font-family: Arial, sans-serif;
      font-weight: bold;
      font-size: 16px;
      color: #2c3e50;
    }

    .setting-input:focus {
      border-color: var(--blue);
    }

    .setting-actions {
      display: flex;
      border-top: 1px solid #f2f5f8;
      margin-top: 12px;
    }

    .setting-act-btn {
      flex: 1;
      height: 52px;
      border: none;
      background: transparent;
      font: 800 16px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      cursor: pointer;
      color: #7f8c8d;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .setting-act-btn.confirm {
      color: #2395ee;
      border-left: 1px solid #f2f5f8;
    }

    @keyframes modalAppear {
      from { transform: scale(0.92); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <h1 class="header-title">Mine</h1>
    </header>

    <div class="content">
      
      <!-- User Profile Header -->
      <div class="profile-section" onclick="location.href='account_info.php'" style="cursor: pointer;">
        <div class="avatar-wrapper">
          <!-- Avatar SVG -->
          <svg viewBox="0 0 120 120" style="width: 100%; height: 100%;">
            <circle cx="60" cy="60" r="60" fill="#e2eefc"/>
            <path d="M60 70 A 24 24 0 0 0 36 94 A 8 8 0 0 0 44 102 L 76 102 A 8 8 0 0 0 84 94 A 24 24 0 0 0 60 70 Z" fill="#07588f"/>
            <circle cx="60" cy="46" r="16" fill="#fbb0ac" />
            <path d="M44 46 Q60 54 76 46 Z" fill="#07588f" opacity="0.3"/>
            <rect x="58" y="58" width="4" height="14" rx="2" fill="#fbb0ac"/>
            <path d="M44 38 Q60 30 76 38" fill="none" stroke="#07588f" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="profile-info">
          <span class="reward-badge">Reward:<?= number_format($user['reward_rate'], 2, '.', '') ?>%</span>
          <div class="user-id-row">
            <span class="user-id">ID:<?= sanitize($user['id']) ?></span>
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </div>
      </div>

      <!-- Settings Menu Rows -->
      <div class="menu-list">
        
        <!-- IToken Row -->
        <div class="menu-row" onclick="openITokenModal()" style="cursor: pointer;">
          <div class="menu-left">
            <div class="menu-icon">
              <div class="india-flag">
                <div class="flag-orange"></div>
                <div class="flag-white"><div class="flag-blue-dot"></div></div>
                <div class="flag-green"></div>
              </div>
            </div>
            <span>IToken</span>
          </div>
          <div class="menu-right">
            <span class="menu-val"><?= number_format($user['itoken_balance'], 2, '.', '') ?></span>
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </div>

        <!-- Today Profit Row -->
        <div class="menu-row" onclick="openTodayProfitModal()" style="cursor: pointer;">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Gift Icon SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
                <polyline points="20 12 20 22 4 22 4 12"></polyline>
                <rect x="2" y="7" width="20" height="5"></rect>
                <path d="M12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
              </svg>
            </div>
            <span>Today Profit</span>
          </div>
          <div class="menu-right">
            <span class="menu-val" style="color: #ccc;"><?= number_format($user['today_profit'], 2, '.', '') ?></span>
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </div>

        <!-- Set Order Size Row -->
        <div class="menu-row" onclick="openSettingModal()">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Notepad SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#1abc9c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
            </div>
            <span>Set order size</span>
          </div>
          <div class="menu-right">
            <span class="menu-val" id="orderSizeText" style="color: #7f8c8d;"><?= intval($user['order_min']) ?>~<?= intval($user['order_max']) ?></span>
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </div>

        <!-- UPI Sell History Row -->
        <a href="sellhis.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Card/Exchange SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#3498db" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 21px; height: 21px;">
                <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="2" y1="10" x2="22" y2="10"></line>
              </svg>
            </div>
            <span>UPI Sell History</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

        <!-- Buy History Row -->
        <a href="depo.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Blue Clipboard SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#2980b9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
              </svg>
            </div>
            <span>Buy History</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

        <!-- Transfer IToken History Row -->
        <a href="transfer_history.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Blue Clipboard SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#2980b9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
              </svg>
            </div>
            <span>Transfer IToken History</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

        <!-- Event Center Row -->
        <div class="menu-row" onclick="openEventModal()">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Yellow Ticket SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#f1c40f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 21px; height: 21px;">
                <path d="M22 18.5V17a3 3 0 0 0-3-3 3 3 0 0 0 3-3V5.5A2.5 2.5 0 0 0 19.5 3h-15A2.5 2.5 0 0 0 2 5.5V11a3 3 0 0 0 3 3 3 3 0 0 0-3 3v1.5A2.5 2.5 0 0 0 4.5 21h15a2.5 2.5 0 0 0 2.5-2.5z"></path>
              </svg>
            </div>
            <span>Event Center</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </div>

        <!-- Tutorial Row -->
        <a href="tutorial.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Play Icon SVG -->
              <svg viewBox="0 0 24 24" fill="#2c3e50" style="width: 21px; height: 21px;">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
              </svg>
            </div>
            <span>Tutorial</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

        <!-- Official Service Row -->
        <a href="customer.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Headset SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 21px; height: 21px;">
                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
              </svg>
            </div>
            <span>Official Service</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

        <!-- Modify Password Row -->
        <a href="editpass.php" class="menu-row">
          <div class="menu-left">
            <div class="menu-icon">
              <!-- Blue Lock SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="#2980b9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 21px; height: 21px;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </div>
            <span>Modify Password</span>
          </div>
          <div class="menu-right">
            <svg class="chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </div>
        </a>

      </div>

      <!-- Sign out area -->
      <div class="signout-area">
        <button class="signout-btn" onclick="location.href='logout.php'">Sign Out</button>
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
      <a href="mine.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        Mine
      </a>
    </nav>

    <!-- EVENT LIST MODAL OVERLAY -->
    <div class="modal-overlay" id="eventModal">
      <div class="event-modal-content">
        <div class="modal-close-x" onclick="closeEventModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </div>
        <h3 class="modal-title">Event List</h3>
        <button class="modal-btn" onclick="location.href='newbie_rewards.php'">Newbie Reward</button>
        <button class="modal-btn" onclick="location.href='today_rewards.php'">Buy Inr Amount Reward</button>
        <button class="modal-cancel-btn" onclick="closeEventModal()">cancel</button>
      </div>
    </div>

    <!-- ORDER SELL SIZE SETTING MODAL OVERLAY -->
    <div class="modal-overlay" id="settingModal">
      <div class="setting-modal-content">
        <h3 class="modal-title" style="text-align: center; margin-bottom: 2px;">Order Sell Size Setting</h3>
        <p class="warning-desc">
          You should make the right sold size for you token, otherwise the token will not be sold
        </p>
        <p class="warning-desc" style="margin-bottom: 6px;">
          notice:min is between 100 and 10,000, and max is between 2000 and 100,000,The maximum value must exceed the minimum value by at least 1000
        </p>
        
        <label class="setting-input-group">
          <span>Min:</span>
          <input type="number" class="setting-input" value="<?= intval($user['order_min']) ?>" id="minInput">
        </label>

        <label class="setting-input-group">
          <span>Max:</span>
          <input type="number" class="setting-input" value="<?= intval($user['order_max']) ?>" id="maxInput">
        </label>

        <div class="setting-actions">
          <button class="setting-act-btn" onclick="closeSettingModal()">Cancel</button>
          <button class="setting-act-btn confirm" onclick="confirmSettingModal()">Confirm</button>
        </div>
      </div>
    </div>

  </div>

  <script>
    // Event List Modal Handlers
    function openEventModal() {
      const modal = document.getElementById('eventModal');
      modal.style.display = 'flex';
    }
    function closeEventModal() {
      const modal = document.getElementById('eventModal');
      modal.style.display = 'none';
    }

    // Setting Modal Handlers
    function openSettingModal() {
      const modal = document.getElementById('settingModal');
      modal.style.display = 'flex';
    }
    function closeSettingModal() {
      const modal = document.getElementById('settingModal');
      modal.style.display = 'none';
    }
    function confirmSettingModal() {
      const minVal = parseInt(document.getElementById('minInput').value);
      const maxVal = parseInt(document.getElementById('maxInput').value);

      if (isNaN(minVal) || minVal < 100 || minVal > 10000) {
        alert("Min must be between 100 and 10,000.");
        return;
      }
      if (isNaN(maxVal) || maxVal < 2000 || maxVal > 100000) {
        alert("Max must be between 2,000 and 100,000.");
        return;
      }
      if ((maxVal - minVal) < 1000) {
        alert("The maximum value must exceed the minimum value by at least 1000.");
        return;
      }

      // Submit update request using fetch
      const formData = new FormData();
      formData.append('action', 'update_order_size');
      formData.append('min', minVal);
      formData.append('max', maxVal);

      fetch('mine.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('orderSizeText').innerText = data.min + '~' + data.max;
          closeSettingModal();
          alert(data.message);
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('Error updating order size:', error);
        alert('An error occurred. Please try again.');
      });
    }

    function openITokenModal() {
      document.getElementById('itoken-modal').style.display = 'flex';
    }

    function closeITokenModal() {
      document.getElementById('itoken-modal').style.display = 'none';
    }

    function openTodayProfitModal() {
      document.getElementById('today-profit-modal').style.display = 'flex';
    }

    function closeTodayProfitModal() {
      document.getElementById('today-profit-modal').style.display = 'none';
    }
  </script>

  <!-- Today Profit Detail Modal -->
  <div id="today-profit-modal" class="custom-detail-modal-overlay" style="display: none;">
    <div class="custom-detail-modal-card">
      <h2 class="modal-title">Today Profit Detail</h2>
      <div class="modal-body">
        <p class="modal-text font-orange">Trade Profit (INR): <?= number_format($trade_profit_today, 2, '.', '') ?></p>
        <p class="modal-text font-orange">Team Profit: <?= number_format($team_profit_today, 2, '.', '') ?></p>
        <p class="modal-text font-orange">Event Reward: <?= number_format($event_reward_today, 2, '.', '') ?></p>
      </div>
      <button class="modal-confirm-btn" onclick="closeTodayProfitModal()">Confirm</button>
    </div>
  </div>

  <!-- IToken Detail Modal -->
  <div id="itoken-modal" class="custom-detail-modal-overlay" style="display: none;">
    <div class="custom-detail-modal-card">
      <h2 class="modal-title">IToken Detail</h2>
      <div class="modal-body">
        <p class="modal-text font-orange">Available: <?= number_format($user['itoken_balance'], 2, '.', '') ?></p>
        <p class="modal-text font-orange">In Sell: <?= number_format($in_sell_tokens, 2, '.', '') ?></p>
      </div>
      <button class="modal-confirm-btn" onclick="closeITokenModal()">Confirm</button>
    </div>
  </div>

  <style>
    /* Custom Modal Overlay styling to avoid namespace conflict */
    .custom-detail-modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 20px;
    }
    .custom-detail-modal-card {
      background: #ffffff;
      border-radius: 16px;
      width: 100%;
      max-width: 320px;
      padding: 30px 24px 20px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      animation: modalScale 0.25s ease-out;
    }
    @keyframes modalScale {
      from { transform: scale(0.9); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    .modal-title {
      font-size: 23px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 24px;
      font-family: inherit;
    }
    .modal-body {
      margin-bottom: 30px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .modal-text {
      font-size: 19px;
      font-weight: 700;
      margin: 0;
    }
    .font-orange {
      color: #f39c12;
    }
    .modal-confirm-btn {
      background: transparent;
      border: 0;
      border-top: 1px solid #f1f5f9;
      color: #3a6bf6;
      font-size: 19px;
      font-weight: 700;
      width: 100%;
      padding-top: 15px;
      cursor: pointer;
      transition: opacity 0.2s;
      outline: 0;
      font-family: inherit;
    }
    .modal-confirm-btn:hover {
      opacity: 0.8;
    }
  </style>
</body>
</html>

