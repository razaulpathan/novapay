<?php
// old_rf.php - Dynamic Invite Friends Referral Rewards
require_once 'db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle claim request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_reward') {
    $referred_id = isset($_POST['referred_id']) ? intval($_POST['referred_id']) : 0;
    
    // Verify relationship
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND referred_by = ?");
    $stmt->execute([$referred_id, $user_id]);
    $ref_user = $stmt->fetch();
    
    if (!$ref_user) {
        $error = "Invalid referred user details.";
    } else {
        // Calculate their success buy sum
        $stmtBuy = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM buy_orders WHERE user_id = ? AND status = 'Success'");
        $stmtBuy->execute([$referred_id]);
        $total_buy = floatval($stmtBuy->fetchColumn());
        
        if ($total_buy < 5000) {
            $error = "Referred friend has not completed the required ₹5000 transaction.";
        } else {
            // Check if already claimed
            $stmtClaim = $pdo->prepare("SELECT * FROM referral_rewards WHERE referred_id = ? AND status = 'Claimed'");
            $stmtClaim->execute([$referred_id]);
            $already_claimed = $stmtClaim->fetch();
            
            if ($already_claimed) {
                $error = "Reward has already been claimed for this user.";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Insert or update referral_rewards
                    $stmtIns = $pdo->prepare("INSERT INTO referral_rewards (referrer_id, referred_id, amount, status, claimed_at) VALUES (?, ?, 300.00, 'Claimed', NOW()) ON DUPLICATE KEY UPDATE status = 'Claimed', claimed_at = NOW()");
                    $stmtIns->execute([$user_id, $referred_id]);
                    
                    // Add 300 to referrer's balance & profit
                    $stmtUser = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + 300.00, today_profit = today_profit + 300.00 WHERE id = ?");
                    $stmtUser->execute([$user_id]);
                    
                    $pdo->commit();
                    $success = "Successfully claimed ₹300 referral reward!";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Transaction error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch referred friends along with buy sums and claim status
$stmt = $pdo->prepare("
    SELECT u.id, u.phone, 
           (SELECT COALESCE(SUM(price), 0) FROM buy_orders b WHERE b.user_id = u.id AND b.status = 'Success') as total_buy,
           (SELECT status FROM referral_rewards r WHERE r.referred_id = u.id) as claim_status
    FROM users u 
    WHERE u.referred_by = ?
    ORDER BY u.id DESC
");
$stmt->execute([$user_id]);
$referred_users = $stmt->fetchAll();

// Calculate Done / All / Received details
$all_friends_count = count($referred_users);
$done_friends_count = 0;
foreach ($referred_users as $ru) {
    if (floatval($ru['total_buy']) >= 5000) {
        $done_friends_count++;
    }
}

// Total potential bonus (all rewards including what can be claimed)
$total_potential_bonus = $all_friends_count * 300;

// Total received bonus from DB
$stmtClaimedSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_rewards WHERE referrer_id = ? AND status = 'Claimed'");
$stmtClaimedSum->execute([$user_id]);
$received_bonus = floatval($stmtClaimedSum->fetchColumn());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Invite Friends Rewards</title>
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
      --red-alert: #e74c3c;
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

    /* Blue statistics summary card */
    .summary-card {
      background: linear-gradient(135deg, #1fa2ff 0%, #12d6df 100%);
      border-radius: 16px;
      padding: 18px 14px;
      color: #ffffff;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      text-align: center;
      box-shadow: 0 4px 12px rgba(31, 162, 255, 0.25);
    }

    .stat-box {
      display: flex;
      flex-direction: column;
      gap: 6px;
      align-items: center;
    }

    .stat-label {
      font-size: 12px;
      font-weight: bold;
      opacity: 0.9;
    }

    .stat-val-wrapper {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 16px;
      font-weight: 800;
    }

    .coin-icon {
      width: 20px;
      height: 20px;
      background: radial-gradient(circle, #ffe066 30%, #f5b041 90%);
      border: 1px solid #d35400;
      color: #d35400;
      border-radius: 50%;
      font-size: 10px;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }

    .stat-number {
      font-family: Arial, sans-serif;
    }

    /* Red description text */
    .reminder-text {
      color: var(--red-alert);
      font-size: 12.5px;
      font-weight: bold;
      line-height: 1.45;
      margin: 0;
    }

    /* Accordion header */
    .accordion-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 4px;
      border-bottom: 1.5px solid #2c3e50;
      cursor: pointer;
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
    }

    .accordion-header svg {
      width: 20px;
      height: 20px;
      transition: transform 0.2s;
    }

    .accordion-header.collapsed svg {
      transform: rotate(-90deg);
    }

    /* Friends list container */
    .friends-list {
      display: flex;
      flex-direction: column;
      transition: max-height 0.3s ease-out;
      overflow: hidden;
    }

    .friend-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 4px;
      border-bottom: 1px solid #f2f5f8;
    }

    .friend-left {
      display: flex;
      align-items: center;
      gap: 12px;
      width: 40%;
    }

    .gift-icon {
      width: 32px;
      height: 32px;
      background: #fdf2e9;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .gift-icon svg {
      width: 16px;
      height: 16px;
      color: #e67e22;
    }

    .reward-value-row {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 14px;
      font-weight: bold;
      color: #2c3e50;
    }

    .friend-phone {
      font-size: 15px;
      font-weight: bold;
      color: #2c3e50;
      font-family: Arial, sans-serif;
      flex: 1;
      text-align: center;
    }

    /* Status badges */
    .status-badge {
      border: none;
      border-radius: 12px;
      padding: 6px 14px;
      font-size: 11px;
      font-weight: 800;
      font-family: inherit;
      width: 84px;
      text-align: center;
    }

    .status-badge.received {
      background: #fef9e7;
      color: #f1c40f;
      cursor: not-allowed;
    }

    .status-badge.undone {
      background: #7f8c8d;
      color: #ffffff;
      cursor: not-allowed;
    }

    .status-badge.claim-btn {
      background: var(--success-green);
      color: #ffffff;
      cursor: pointer;
      transition: all 0.2s;
    }

    .status-badge.claim-btn:hover {
      background: #27ae60;
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
      color: var(--success-green);
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
      <a href="agent.php" class="header-back">
        <!-- Back chevron SVG -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Invite Friends Rewards</h1>
    </header>

    <div class="content">

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
      <?php endif; ?>
      
      <!-- Stats summary card -->
      <div class="summary-card">
        <div class="stat-box">
          <span class="stat-label">Total bonus</span>
          <div class="stat-val-wrapper">
            <div class="coin-icon">₹</div>
            <span class="stat-number"><?= intval($total_potential_bonus) ?></span>
          </div>
        </div>
        <div class="stat-box" style="border-left: 1px solid rgba(255,255,255,0.25); border-right: 1px solid rgba(255,255,255,0.25);">
          <span class="stat-label">Done / All Friends</span>
          <div class="stat-val-wrapper">
            <span class="stat-number"><?= $done_friends_count ?>/<?= $all_friends_count ?></span>
          </div>
        </div>
        <div class="stat-box">
          <span class="stat-label">Received bonus</span>
          <div class="stat-val-wrapper">
            <div class="coin-icon">₹</div>
            <span class="stat-number"><?= intval($received_bonus) ?></span>
          </div>
        </div>
      </div>

      <!-- Warning reminder -->
      <p class="reminder-text">
        Warning: You need to remind your friends to complete the newbie tasks (₹5000 minimum transaction) and receive rewards.
      </p>

      <!-- Accordion Header list -->
      <div class="accordion-header" id="listToggle" onclick="toggleFriendsList()">
        <span>Show Your Friends</span>
        <!-- Down chevron SVG -->
        <svg id="chevronIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </div>

      <!-- Scrollable list of invited friends -->
      <div class="friends-list" id="friendsList">
        
        <?php if (empty($referred_users)): ?>
          <div style="text-align: center; color: var(--muted); padding: 24px 0; font-size: 14px; font-weight: bold;">
            You have not invited any friends yet.
          </div>
        <?php else: ?>
          <?php foreach ($referred_users as $ru): 
            $masked_phone = substr($ru['phone'], 0, 3) . '****' . substr($ru['phone'], -3);
            $total_buy = floatval($ru['total_buy']);
          ?>
            <div class="friend-row">
              <div class="friend-left">
                <div class="gift-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="8" width="18" height="14" rx="2" ry="2"></rect>
                    <line x1="12" y1="22" x2="12" y2="8"></line>
                    <path d="M12 8H7.5a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8zM12 8h4.5a2.5 2.5 0 0 0 0-5C13 3 12 8 12 8z"></path>
                  </svg>
                </div>
                <div class="reward-value-row">
                  <div class="coin-icon" style="width: 14px; height: 14px; font-size: 8px;">₹</div>
                  <span>300</span>
                </div>
              </div>
              <span class="friend-phone"><?= sanitize($masked_phone) ?></span>
              
              <?php if ($ru['claim_status'] === 'Claimed'): ?>
                <button class="status-badge received" disabled>Received</button>
              <?php elseif ($total_buy >= 5000): ?>
                <form method="POST" style="margin: 0;">
                  <input type="hidden" name="action" value="claim_reward">
                  <input type="hidden" name="referred_id" value="<?= $ru['id'] ?>">
                  <button type="submit" class="status-badge claim-btn">Receive</button>
                </form>
              <?php else: ?>
                <button class="status-badge undone" disabled>Undone</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

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

  <script>
    let isListCollapsed = false;

    function toggleFriendsList() {
      const list = document.getElementById('friendsList');
      const header = document.getElementById('listToggle');
      const chevron = document.getElementById('chevronIcon');

      if (isListCollapsed) {
        list.style.display = 'flex';
        header.classList.remove('collapsed');
        chevron.style.transform = 'rotate(0deg)';
        isListCollapsed = false;
      } else {
        list.style.display = 'none';
        header.classList.add('collapsed');
        chevron.style.transform = 'rotate(-90deg)';
        isListCollapsed = true;
      }
    }
  </script>
</body>
</html>
