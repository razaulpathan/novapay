<?php
// newbie_rewards.php - Dynamic Newbie Rewards Checklist & Claim
require_once 'db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if MobiKwik is linked
$stmtMk = $pdo->prepare("SELECT COUNT(*) FROM upi_accounts WHERE user_id = ? AND partner = 'MobiKwik' AND status = 'Active'");
$stmtMk->execute([$user_id]);
$has_mobikwik = ($stmtMk->fetchColumn() > 0);

// Check if user has purchased at least 5000 IToken in success buy orders
$stmtPur = $pdo->prepare("SELECT COUNT(*) FROM buy_orders WHERE user_id = ? AND status = 'Success' AND price >= 5000");
$stmtPur->execute([$user_id]);
$has_purchased_5000 = ($stmtPur->fetchColumn() > 0);

// Check if newbie reward is already claimed
$stmtClaimed = $pdo->prepare("SELECT COUNT(*) FROM newbie_rewards WHERE user_id = ?");
$stmtClaimed->execute([$user_id]);
$claimed_newbie = ($stmtClaimed->fetchColumn() > 0);

// Fetch links from settings
$tg_channel = getSetting('support_telegram_channel') ?: 'https://t.me/NextPay_channel';
$tg_vip = getSetting('support_telegram_vip') ?: 'https://t.me/NextPay_vip';

$reward_amount = floatval(getSetting('newbie_reward_amount') ?: 200.00);

// Handle claim request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_newbie') {
    if (!$has_mobikwik || !$has_purchased_5000) {
        $error = "Please complete all newbie tasks first (Link MobiKwik & purchase at least ₹5000 IToken).";
    } else {
        if ($claimed_newbie) {
            $error = "You have already claimed your newbie reward.";
        } else {
            try {
                $pdo->beginTransaction();

                // Save claim in newbie_rewards table
                $stmtIns = $pdo->prepare("INSERT INTO newbie_rewards (user_id) VALUES (?)");
                $stmtIns->execute([$user_id]);

                // Add reward to balance & profit
                $stmtUser = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, today_profit = today_profit + ? WHERE id = ?");
                $stmtUser->execute([$reward_amount, $reward_amount, $user_id]);

                $pdo->commit();
                $success = "Successfully claimed ₹" . $reward_amount . " newbie reward!";
                $claimed_newbie = true; // Update state for rendering
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Transaction error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Newbie Rewards</title>
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
      --green-done: #2ecc71;
      --red-alert: #e74c3c;
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
      padding: 16px;
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* Summary Card */
    .bonus-card {
      background: linear-gradient(135deg, #2196f3 0%, #1e88e5 100%);
      border-radius: 16px;
      padding: 18px 20px;
      color: #ffffff;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(33, 150, 243, 0.25);
    }

    .card-left {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .card-label {
      font-size: 14px;
      font-weight: bold;
      opacity: 0.95;
    }

    .card-amount-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 28px;
      font-weight: 900;
    }

    .coin-icon {
      width: 32px;
      height: 32px;
      background: radial-gradient(circle, #ffe066 30%, #f5b041 90%);
      border: 1.5px solid #d35400;
      color: #d35400;
      border-radius: 50%;
      font-size: 18px;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }

    .card-amount {
      font-family: Arial, sans-serif;
    }

    /* Claim Button styling */
    .claim-btn {
      background: var(--green-done);
      color: #ffffff;
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-size: 15px;
      font-weight: bold;
      font-family: inherit;
      cursor: pointer;
      box-shadow: 0 3px 8px rgba(46, 204, 113, 0.3);
      transition: all 0.2s;
    }

    .claim-btn:hover {
      background: #27ae60;
    }

    .received-btn {
      background: #5d6d7e;
      color: #cbd5e0;
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-size: 15px;
      font-weight: bold;
      font-family: inherit;
      cursor: not-allowed;
      box-shadow: none;
      opacity: 0.85;
    }

    .undone-btn {
      background: #7f8c8d;
      color: #ffffff;
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-size: 15px;
      font-weight: bold;
      font-family: inherit;
      cursor: not-allowed;
      box-shadow: none;
      opacity: 0.7;
    }

    /* Tasks list styling */
    .tasks-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-top: 8px;
    }

    .task-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 4px;
      border-bottom: 1px solid #f2f5f8;
    }

    .task-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .task-icon-wrapper {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .task-icon-wrapper.tg {
      background: #2ea6de;
      color: #ffffff;
    }

    .task-icon-wrapper.video {
      background: #eef5fc;
      color: #2395ee;
      border: 1px solid #d4e6f1;
    }

    .task-icon-wrapper.partner {
      background: #fcf3cf;
      color: #f39c12;
      border: 1px solid #f9e79f;
    }

    .task-icon-wrapper.token {
      background: #fef5e7;
      color: #e67e22;
      border: 1px solid #fdebd0;
    }

    .task-icon-wrapper svg {
      width: 18px;
      height: 18px;
    }

    .task-name {
      font-size: 15px;
      font-weight: bold;
      color: #2c3e50;
    }

    /* Done/Go tags */
    .done-tag {
      background: var(--green-done);
      color: #ffffff;
      border: none;
      border-radius: 14px;
      padding: 6px 18px;
      font-size: 12px;
      font-weight: 800;
      font-family: inherit;
      cursor: not-allowed;
    }

    .go-btn {
      background: var(--blue);
      color: #ffffff;
      border: none;
      border-radius: 14px;
      padding: 6px 18px;
      font-size: 12px;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      transition: background-color 0.2s;
    }
    .go-btn:hover {
      background: #0d7dd4;
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

    /* Alert Styling */
    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
    }
    .alert-success {
      background-color: rgba(46, 204, 113, 0.1);
      color: var(--green-done);
      border: 1px solid rgba(46, 204, 113, 0.2);
    }
    .alert-error {
      background-color: rgba(231, 76, 60, 0.1);
      color: var(--red-alert);
      border: 1px solid rgba(231, 76, 60, 0.2);
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <a href="mine.php" class="header-back">
        <!-- Back chevron SVG -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Newbie Rewards</h1>
    </header>

    <div class="content">

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
      <?php endif; ?>
      
      <!-- Total Bonus card -->
      <div class="bonus-card">
        <div class="card-left">
          <span class="card-label">Total bonus</span>
          <div class="card-amount-wrapper">
            <div class="coin-icon">₹</div>
            <span class="card-amount"><?= intval($reward_amount) ?></span>
          </div>
        </div>
        
        <?php if ($claimed_newbie): ?>
          <button class="received-btn" disabled>Received</button>
        <?php elseif ($has_mobikwik && $has_purchased_5000): ?>
          <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="claim_newbie">
            <button type="submit" class="claim-btn">Receive</button>
          </form>
        <?php else: ?>
          <button class="undone-btn" disabled>Undone</button>
        <?php endif; ?>
      </div>

      <!-- Tasks Checklist -->
      <div class="tasks-list">
        
        <!-- Task 1: Subscribe -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper tg">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
              </svg>
            </div>
            <span class="task-name">Subscribe to Official Channel</span>
          </div>
          <a href="<?= sanitize($tg_channel) ?>" target="_blank" class="go-btn">Go</a>
        </div>

        <!-- Task 2: Join VIP -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper tg">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
              </svg>
            </div>
            <span class="task-name">Join VIP Group</span>
          </div>
          <a href="<?= sanitize($tg_vip) ?>" target="_blank" class="go-btn">Go</a>
        </div>

        <!-- Task 3: Watch tutorial -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper video">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2.5" ry="2.5"></rect>
                <polygon points="10 8 14 10 10 12 10 8"></polygon>
                <line x1="6" y1="21" x2="18" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
            <span class="task-name">Watch Beginner Tutorial</span>
          </div>
          <a href="tutorial.php" class="go-btn">Go</a>
        </div>

        <!-- Task 4: Link Mobikwik -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper partner">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <polygon points="12 3 21 19 3 19 12 3" />
              </svg>
            </div>
            <span class="task-name">Link Mobikwik</span>
          </div>
          <?php if ($has_mobikwik): ?>
            <button class="done-tag" disabled>Done</button>
          <?php else: ?>
            <a href="linkky.php" class="go-btn">Go Link</a>
          <?php endif; ?>
        </div>

        <!-- Task 5: Purchase IToken -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper token">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10" fill="#f1c40f"/>
                <text x="12" y="16" font-family="Arial" font-size="12" font-weight="900" fill="#d35400" text-anchor="middle">₹</text>
              </svg>
            </div>
            <span class="task-name">Purchase 5000 IToken</span>
          </div>
          <?php if ($has_purchased_5000): ?>
            <button class="done-tag" disabled>Done</button>
          <?php else: ?>
            <a href="upibuyplus.php" class="go-btn">Go Buy</a>
          <?php endif; ?>
        </div>

        <!-- Task 6: Bind TG -->
        <div class="task-item">
          <div class="task-left">
            <div class="task-icon-wrapper tg">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
              </svg>
            </div>
            <span class="task-name">Bind TG Account</span>
          </div>
          <button class="done-tag" disabled>Done</button>
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

  </div>
</body>
</html>
