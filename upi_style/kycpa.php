<?php
// kycpa.php - Linked UPI accounts configuration
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

// Handle AJAX POST to toggle stopped status of UPI accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_stopped') {
    header('Content-Type: application/json');
    $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;

    $checkStmt = $pdo->prepare("SELECT stopped FROM upi_accounts WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$account_id, $_SESSION['user_id']]);
    $current = $checkStmt->fetchColumn();

    if ($current !== false) {
        $new_status = $current ? 0 : 1;
        $updateStmt = $pdo->prepare("UPDATE upi_accounts SET stopped = ? WHERE id = ?");
        $updateStmt->execute([$new_status, $account_id]);
        echo json_encode(['success' => true, 'stopped' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'UPI account not found.']);
    }
    exit;
}

// Fetch all UPI accounts for the current user (joining with payment_partners to load logos & type)
$stmtAccounts = $pdo->prepare("
    SELECT u.*, p.type AS partner_type, p.logo_url 
    FROM upi_accounts u 
    LEFT JOIN payment_partners p ON u.partner = p.name 
    WHERE u.user_id = ? 
    ORDER BY u.created_at DESC
");
$stmtAccounts->execute([$_SESSION['user_id']]);
$accounts = $stmtAccounts->fetchAll();

$buyAccounts = [];
$sellAccounts = [];
foreach ($accounts as $acc) {
    $pType = $acc['partner_type'] ?? 'Sell';
    if ($pType === 'Buy' || $pType === 'Both') {
        $buyAccounts[] = $acc;
    }
    if ($pType === 'Sell' || $pType === 'Both') {
        $sellAccounts[] = $acc;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - UPI Settings</title>
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
      --red-warning: #e74c3c;
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
      padding: 16px;
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* Red banner message */
    .warning-banner {
      color: var(--red-warning);
      font-size: 14px;
      font-weight: bold;
      text-align: center;
      margin: 4px 0 0 0;
    }

    /* Action buttons grid */
    .actions-grid {
      display: grid;
      grid-template-columns: 1.1fr 1.9fr;
      gap: 12px;
    }

    .action-btn {
      height: 74px;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      transition: transform 0.15s, opacity 0.2s;
    }

    .action-btn:active {
      transform: scale(0.97);
    }

    .action-btn.tutorial {
      background: #4fa7ff;
      color: #ffffff;
      box-shadow: 0 4px 10px rgba(79, 167, 255, 0.25);
    }

    .action-btn.link-new {
      background: #1c82e6;
      color: #ffffff;
      box-shadow: 0 4px 10px rgba(28, 130, 230, 0.25);
    }

    .action-btn svg {
      width: 22px;
      height: 22px;
    }

    /* Sub Tabs switching */
    .sub-tabs {
      display: flex;
      width: 100%;
      border-bottom: 1.5px solid #f0f3f6;
      background: #ffffff;
      margin-top: 4px;
    }

    .tab-item {
      flex: 1;
      text-align: center;
      padding: 12px 0;
      cursor: pointer;
      color: #7f8c8d;
      font-size: 16px;
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
      bottom: -1.5px;
      left: 40%;
      width: 20%;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
    }

    /* Sub tab content containers */
    .tab-view {
      display: none;
      flex-direction: column;
      gap: 16px;
      flex: 1;
    }

    .tab-view.active-view {
      display: flex;
    }

    /* Empty state layout (Buy sub-tab) */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 24px;
      text-align: center;
      gap: 14px;
      color: #7f8c8d;
    }

    .empty-icon {
      width: 80px;
      height: 80px;
      background: #eaf2f8;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blue);
      margin-bottom: 8px;
    }

    .empty-title {
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0;
    }

    .empty-desc {
      font-size: 14px;
      font-weight: bold;
      color: #95a5a6;
      margin: 0;
    }

    /* Active accounts list (Sell sub-tab) */
    .accounts-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .account-card {
      border: 1px solid #e1e8ed;
      border-radius: 12px;
      padding: 16px;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      gap: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.01);
    }

    .acc-header {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .logo-wrapper {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #f0f3f6;
      background: #fafbfc;
      flex-shrink: 0;
    }

    .logo-img-mock {
      font-size: 18px;
      font-weight: 900;
      color: #ffffff;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .acc-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
      flex: 1;
    }

    .acc-title {
      font-size: 15px;
      font-weight: 900;
      color: #2c3e50;
    }

    .acc-upi-id {
      font-size: 13px;
      color: #95a5a6;
      font-family: Arial, sans-serif;
    }

    .acc-status-row {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 2px;
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .status-dot.active { background: #2ecc71; }
    .status-dot.unlink { background: #95a5a6; }

    .status-text {
      font-size: 12px;
      font-weight: bold;
      color: #7f8c8d;
    }

    /* Switch stopped control */
    .acc-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 4px;
    }

    .stopped-toggle {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: bold;
      color: #2ecc71; /* Selling green color */
    }

    /* Switch track and thumb */
    .switch-track {
      width: 44px;
      height: 24px;
      background: #cbd5e0;
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      transition: background-color 0.2s;
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

    .action-sub-buttons {
      display: flex;
      gap: 8px;
    }

    .btn-outline {
      border: 1px solid #d4e1eb;
      background: #ffffff;
      color: #2c3e50;
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 12px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 4px;
      font-family: inherit;
      text-decoration: none;
    }

    .btn-outline svg {
      width: 14px;
      height: 14px;
      color: #7f8c8d;
    }

    /* Red validation alert at bottom of Paytm */
    .acc-warning-alert {
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 11px;
      font-weight: bold;
      color: #f05252;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 4px;
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

    /* Bottom navigation tabs */
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
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <h1 class="header-title">UPI</h1>
    </header>

    <div class="content">
      
      <!-- Warning text -->
      <p class="warning-banner">Once linked, your UPI ID cannot be changed or removed. Contact Support for changes.</p>

      <!-- Action buttons grid -->
      <div class="actions-grid">
        <a href="tutorial.php" class="action-btn tutorial">
          <!-- Video camera/play icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="23 7 16 12 23 17 23 7"></polygon>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
          </svg>
          <span>UPI Tutorial</span>
        </a>
        <a href="linkky.php" class="action-btn link-new">
          <!-- Link Chain icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
          </svg>
          <span>Link New UPI</span>
        </a>
      </div>

      <!-- Sub tabs -->
      <nav class="sub-tabs">
        <div class="tab-item" id="tabBuy" onclick="switchUpiTab('buy')">Buy</div>
        <div class="tab-item active" id="tabSell" onclick="switchUpiTab('sell')">Sell</div>
      </nav>

      <!-- Buy Tab Content (Active Accounts List) -->
      <div class="tab-view" id="viewBuy">
        <div class="accounts-list">
          <?php if (empty($buyAccounts)): ?>
            <div class="empty-state">
              <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 36px; height: 36px;">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </div>
              <h4 class="empty-title">No buying UPI accounts linked yet!</h4>
              <p class="empty-desc">Please Link New UPI to make payments.</p>
            </div>
          <?php else: ?>
            <?php foreach ($buyAccounts as $acc): 
              $partner = strtolower($acc['partner']);
              $bgColor = '#2395ee'; // Default blue color
              $shortLetter = strtoupper(substr($acc['partner'], 0, 2));
              
              if (strpos($partner, 'paytm') !== false) {
                  $bgColor = '#00baf2';
                  $shortLetter = 'Py';
              } elseif (strpos($partner, 'phonepe') !== false) {
                  $bgColor = '#5f259f';
                  $shortLetter = 'Ph';
              } elseif (strpos($partner, 'amazon') !== false) {
                  $bgColor = '#ff9900';
                  $shortLetter = 'Ap';
              } elseif (strpos($partner, 'freecharge') !== false) {
                  $bgColor = '#ff5c2c';
                  $shortLetter = 'Fc';
              } elseif (strpos($partner, 'airtel') !== false) {
                  $bgColor = '#ff0000';
                  $shortLetter = 'At';
              } elseif (strpos($partner, 'indus') !== false) {
                  $bgColor = '#800080';
                  $shortLetter = 'Ip';
              } elseif (strpos($partner, 'bharatpe') !== false) {
                  $bgColor = '#00bcd4';
                  $shortLetter = 'Bp';
              } elseif (strpos($partner, 'iob') !== false) {
                  $bgColor = '#0033a0';
                  $shortLetter = 'Ib';
              }
              
              $masked_name = sanitize($acc['name']);
              $masked_upi = sanitize($acc['upi_no']);
              if (strlen($masked_upi) > 6) {
                  $masked_upi = substr($masked_upi, 0, 3) . '****' . substr($masked_upi, -3);
              }
            ?>
              <!-- Dynamic Account Card -->
              <div class="account-card" id="card_buy_<?= $acc['id'] ?>">
                <div class="acc-header">
                  <div class="logo-wrapper">
                    <?php if (!empty($acc['logo_url']) && file_exists(__DIR__ . '/' . $acc['logo_url'])): ?>
                      <img src="<?= sanitize($acc['logo_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                      <div class="logo-img-mock" style="background: <?= $bgColor ?>; color: #fff;"><?= $shortLetter ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="acc-info">
                    <span class="acc-title"><?= sanitize($acc['partner']) ?>(<?= $masked_name ?>)</span>
                    <span class="acc-upi-id"><?= $masked_upi ?></span>
                    <div class="acc-status-row">
                      <div class="status-dot <?= strtolower($acc['status']) === 'active' ? 'active' : 'unlink' ?>"></div>
                      <span class="status-text"><?= sanitize($acc['status']) ?></span>
                    </div>
                  </div>
                </div>
                
                <div class="acc-controls">
                  <div class="stopped-toggle">
                    <span>Selling</span>
                    <div class="switch-track <?= !$acc['stopped'] ? 'on' : '' ?>" id="switch_buy_<?= $acc['id'] ?>" onclick="toggleSwitch(<?= $acc['id'] ?>, 'buy')">
                      <div class="switch-thumb"></div>
                    </div>
                  </div>
                  <div class="action-sub-buttons">
                    <a href="linkky.php?view=<?= $acc['id'] ?>" class="btn-outline">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                      </svg>
                      Details
                    </a>
                  </div>
                </div>

                <?php if (strtolower($acc['status']) === 'unlink'): ?>
                  <div class="acc-warning-alert" style="background: #fdf2f2; border: 1px solid #fde8e8; border-radius: 8px; padding: 12px; margin-top: 8px; display: flex; flex-direction: column; align-items: flex-start; gap: 8px;">
                    <span style="color: #e74c3c; font-weight: 800; font-size: 13px;">⚠️ UPI unlinked by admin - Please relink to resume buy & sell!</span>
                    <a href="linkky.php?relink=<?= $acc['id'] ?>" style="background: #e74c3c; color: white; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-block;">Relink UPI</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sell Tab Content (Active Accounts List) -->
      <div class="tab-view active-view" id="viewSell">
        <div class="accounts-list">
          
          <?php if (empty($sellAccounts)): ?>
            <div class="empty-state">
              <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 36px; height: 36px;">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </div>
              <h4 class="empty-title">No selling UPI accounts linked yet!</h4>
              <p class="empty-desc">Please Link New UPI to receive payments.</p>
            </div>
          <?php else: ?>
            <?php foreach ($sellAccounts as $acc): 
              $partner = strtolower($acc['partner']);
              $bgColor = '#2395ee'; // Default blue color
              $shortLetter = strtoupper(substr($acc['partner'], 0, 2));
              
              if (strpos($partner, 'paytm') !== false) {
                  $bgColor = '#00baf2';
                  $shortLetter = 'Py';
              } elseif (strpos($partner, 'phonepe') !== false) {
                  $bgColor = '#5f259f';
                  $shortLetter = 'Ph';
              } elseif (strpos($partner, 'amazon') !== false) {
                  $bgColor = '#ff9900';
                  $shortLetter = 'Ap';
              } elseif (strpos($partner, 'freecharge') !== false) {
                  $bgColor = '#ff5c2c';
                  $shortLetter = 'Fc';
              } elseif (strpos($partner, 'airtel') !== false) {
                  $bgColor = '#ff0000';
                  $shortLetter = 'At';
              } elseif (strpos($partner, 'indus') !== false) {
                  $bgColor = '#800080';
                  $shortLetter = 'Ip';
              } elseif (strpos($partner, 'bharatpe') !== false) {
                  $bgColor = '#00bcd4';
                  $shortLetter = 'Bp';
              } elseif (strpos($partner, 'iob') !== false) {
                  $bgColor = '#0033a0';
                  $shortLetter = 'Ib';
              }
              
              $masked_name = sanitize($acc['name']);
              $masked_upi = sanitize($acc['upi_no']);
              // Mask middle characters for security
              if (strlen($masked_upi) > 6) {
                  $masked_upi = substr($masked_upi, 0, 3) . '****' . substr($masked_upi, -3);
              }
            ?>
              <!-- Dynamic Account Card -->
              <div class="account-card" id="card_sell_<?= $acc['id'] ?>">
                <div class="acc-header">
                  <div class="logo-wrapper">
                    <?php if (!empty($acc['logo_url']) && file_exists(__DIR__ . '/' . $acc['logo_url'])): ?>
                      <img src="<?= sanitize($acc['logo_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                      <div class="logo-img-mock" style="background: <?= $bgColor ?>; color: #fff;"><?= $shortLetter ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="acc-info">
                    <span class="acc-title"><?= sanitize($acc['partner']) ?>(<?= $masked_name ?>)</span>
                    <span class="acc-upi-id"><?= $masked_upi ?></span>
                    <div class="acc-status-row">
                      <div class="status-dot <?= strtolower($acc['status']) === 'active' ? 'active' : 'unlink' ?>"></div>
                      <span class="status-text"><?= sanitize($acc['status']) ?></span>
                    </div>
                  </div>
                </div>
                
                <div class="acc-controls">
                  <div class="stopped-toggle">
                    <span>Selling</span>
                    <div class="switch-track <?= !$acc['stopped'] ? 'on' : '' ?>" id="switch_sell_<?= $acc['id'] ?>" onclick="toggleSwitch(<?= $acc['id'] ?>, 'sell')">
                      <div class="switch-thumb"></div>
                    </div>
                  </div>
                  <div class="action-sub-buttons">
                    <a href="linkky.php?view=<?= $acc['id'] ?>" class="btn-outline">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                      </svg>
                      Details
                    </a>
                  </div>
                </div>

                <?php if (strtolower($acc['status']) === 'unlink'): ?>
                  <!-- Unlinked validation warning alert -->
                  <div class="acc-warning-alert" style="background: #fdf2f2; border: 1px solid #fde8e8; border-radius: 8px; padding: 12px; margin-top: 8px; display: flex; flex-direction: column; align-items: flex-start; gap: 8px;">
                    <span style="color: #e74c3c; font-weight: 800; font-size: 13px;">⚠️ UPI unlinked by admin - Please relink to resume buy & sell!</span>
                    <a href="linkky.php?relink=<?= $acc['id'] ?>" style="background: #e74c3c; color: white; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-block;">Relink UPI</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

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
      <a href="kycpa.php" class="nav-item active">
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
    function switchUpiTab(tabType) {
      const tabBuy = document.getElementById('tabBuy');
      const tabSell = document.getElementById('tabSell');
      const viewBuy = document.getElementById('viewBuy');
      const viewSell = document.getElementById('viewSell');

      if (tabType === 'buy') {
        tabBuy.classList.add('active');
        tabSell.classList.remove('active');
        viewBuy.classList.add('active-view');
        viewSell.classList.remove('active-view');
      } else {
        tabSell.classList.add('active');
        tabBuy.classList.remove('active');
        viewSell.classList.add('active-view');
        viewBuy.classList.remove('active-view');
      }
    }

    function toggleSwitch(accountId, context) {
      const switchEl = document.getElementById('switch_' + (context ? context + '_' : '') + accountId);
      
      const formData = new FormData();
      formData.append('action', 'toggle_stopped');
      formData.append('account_id', accountId);

      fetch('kycpa.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // data.stopped = 0 means Selling is active (ON)
          // data.stopped = 1 means Selling is stopped (OFF)
          const isSelling = (data.stopped == 0);
          if (isSelling) {
            switchEl.classList.add('on');
            showSellingToast("start sell successfully");
          } else {
            switchEl.classList.remove('on');
            showSellingToast("stop sell successfully");
          }
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('Error toggling UPI stopped state:', error);
        alert('An error occurred. Please try again.');
      });
    }

    function showSellingToast(message) {
      const toast = document.getElementById('toast-selling');
      const text = document.getElementById('toast-selling-text');
      text.textContent = message;
      toast.style.display = 'block';
      setTimeout(() => {
        toast.style.display = 'none';
      }, 2000);
    }
  </script>

  <!-- Dynamic Success Toast -->
  <div id="toast-selling" class="toast-selling-container" style="display: none;">
    <div class="toast-selling-content">
      <span class="toast-selling-icon">✓</span>
      <span id="toast-selling-text">start sell successfully</span>
    </div>
  </div>

  <style>
    .toast-selling-container {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #484d5c;
      color: #ffffff;
      padding: 14px 24px;
      border-radius: 12px;
      z-index: 2000;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
      animation: toastFade 0.2s ease-out;
    }
    @keyframes toastFade {
      from { transform: translate(-50%, -50%) scale(0.9); opacity: 0; }
      to { transform: translate(-50%, -50%) scale(1); opacity: 1; }
    }
    .toast-selling-content {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .toast-selling-icon {
      background: #ffffff;
      color: #484d5c;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 14px;
    }
    #toast-selling-text {
      font-size: 16px;
      font-weight: 500;
    }
  </style>
</body>
</html>

