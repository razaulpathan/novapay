<?php
// agent/transfer_token.php - Token Transfer Settlement page
require_once '../db.php';

// Auth check
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Fetch agent details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'Blocked') {
    session_destroy();
    header("Location: login.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle token transfer request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_token') {
    $target_uid = isset($_POST['target_uid']) ? intval(trim($_POST['target_uid'])) : 0;
    $target_phone = isset($_POST['target_phone']) ? trim($_POST['target_phone']) : '';
    $amount = isset($_POST['amount']) ? floatval(trim($_POST['amount'])) : 0.00;

    if ($target_uid <= 0 || empty($target_phone) || $amount <= 0) {
        $error_msg = "Please fill in all fields with valid information.";
    } elseif ($amount > $user['itoken_balance']) {
        $error_msg = "Insufficient Itoken balance. Your current balance is ₹" . number_format($user['itoken_balance'], 2);
    } else {
        // Verify target customer exists in the users table
        $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE id = ? AND phone = ?");
        $stmtCheck->execute([$target_uid, $target_phone]);
        $target_user = $stmtCheck->fetch();

        if (!$target_user) {
            $error_msg = "Target customer mismatch. User ID and Username/Phone do not match any NextPay user.";
        } elseif ($target_user['id'] === $user['id']) {
            $error_msg = "You cannot transfer tokens to yourself.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Deduct/Block balance from agent immediately
                $stmtDeduct = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance - ? WHERE id = ?");
                $stmtDeduct->execute([$amount, $user['id']]);

                // Insert into token_transfers
                $stmtInsert = $pdo->prepare("INSERT INTO token_transfers (agent_id, target_phone, target_uid, amount, status) VALUES (?, ?, ?, ?, 'Pending')");
                $stmtInsert->execute([$user['id'], $target_phone, $target_uid, $amount]);

                $pdo->commit();
                
                // Refresh local user data
                $user['itoken_balance'] -= $amount;
                $success_msg = "Transfer request of ₹" . number_format($amount, 2) . " submitted! Awaiting administrator approval.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch all transfers logged by the agent
$stmtTrans = $pdo->prepare("SELECT * FROM token_transfers WHERE agent_id = ? ORDER BY created_at DESC");
$stmtTrans->execute([$user['id']]);
$transfers = $stmtTrans->fetchAll();

// Include Layout Header
$current_page = 'transfer_token.php';
require_once 'header.php';
?>

<!-- Info box and transfer form -->
<div class="transfer-grid-container">
  <!-- Card 1: Transfer Form -->
  <div class="card-element flex-form-card">
    <div class="card-header-element">
      <span class="card-title-element">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        Transfer Tokens to NextPay Account
      </span>
    </div>
    <div class="card-body-element">
      <?php if (!empty($error_msg)): ?>
        <div class="transfer-alert alert-danger"><?= sanitize($error_msg) ?></div>
      <?php endif; ?>
      <?php if (!empty($success_msg)): ?>
        <div class="transfer-alert alert-success"><?= sanitize($success_msg) ?></div>
      <?php endif; ?>

      <form method="POST" class="transfer-input-form">
        <input type="hidden" name="action" value="transfer_token">
        
        <div class="form-input-group">
          <label class="form-input-label" for="target_uid">NextPay ID (UID)</label>
          <input type="number" name="target_uid" id="target_uid" class="form-text-input" placeholder="Enter target UID" required>
        </div>
        <div class="form-input-group">
          <label class="form-input-label" for="target_phone">NextPay Username (Phone)</label>
          <input type="tel" name="target_phone" id="target_phone" class="form-text-input" placeholder="Enter target phone" required>
        </div>
        <div class="form-input-group">
          <label class="form-input-label" for="amount">Amount to Transfer (₹)</label>
          <input type="number" name="amount" id="amount" class="form-text-input" placeholder="Enter amount" step="0.01" min="1" required>
        </div>
        <button type="submit" class="submit-action-btn">Submit Settlement Request</button>
      </form>
    </div>
  </div>

  <!-- Card 2: Balance Summary -->
  <div class="card-element balance-summary-card">
    <div class="card-header-element">
      <span class="card-title-element">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
          <line x1="12" y1="4" x2="12" y2="20"></line>
        </svg>
        Balance Overview
      </span>
    </div>
    <div class="card-body-element balance-summary-body">
      <div class="balance-sub-box">
        <span class="bal-label">Available Balance</span>
        <span class="bal-value">₹<?= number_format($user['itoken_balance'], 2) ?></span>
      </div>
      <div class="balance-alert-tip">
        Please verify the NextPay ID (UID) and username of the recipient carefully. Blocked tokens are immediately debited from your balance and are held securely until approved or rejected by the administrator.
      </div>
    </div>
  </div>
</div>

<!-- Token Transfer history list -->
<div class="card-element" style="margin-top: 20px;">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
      Transfer Transaction History
    </span>
  </div>
  <div class="card-body-element">
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Target UID</th>
            <th>Target Phone</th>
            <th>Amount (₹)</th>
            <th>Status</th>
            <th>Requested Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transfers)): ?>
            <tr>
              <td colspan="6" class="empty-table-row">No transfer history records registered yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($transfers as $tr): ?>
              <tr>
                <td>#<?= intval($tr['id']) ?></td>
                <td><?= intval($tr['target_uid']) ?></td>
                <td style="font-weight: 700;"><?= sanitize($tr['target_phone']) ?></td>
                <td style="font-weight: 700; color: var(--primary);">₹<?= number_format($tr['amount'], 2) ?></td>
                <td>
                  <?php if ($tr['status'] === 'Pending'): ?>
                    <span class="badge-status status-warning">Pending</span>
                  <?php elseif ($tr['status'] === 'Success'): ?>
                    <span class="badge-status status-success">Approved</span>
                  <?php else: ?>
                    <span class="badge-status status-danger">Rejected</span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 12px; color: var(--text-secondary);"><?= $tr['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  .transfer-grid-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
  }

  .flex-form-card {
    display: flex;
    flex-direction: column;
  }

  .balance-summary-card {
    display: flex;
    flex-direction: column;
  }

  .balance-summary-body {
    display: flex;
    flex-direction: column;
    gap: 20px;
    justify-content: center;
  }

  .balance-sub-box {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: var(--table-header-bg);
    border: 1px solid var(--border-color);
    padding: 16px;
    border-radius: 8px;
    text-align: center;
  }

  .bal-label {
    font-size: 12px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
  }

  .bal-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--primary);
  }

  .balance-alert-tip {
    font-size: 12.5px;
    color: var(--text-secondary);
    line-height: 1.4;
  }

  .transfer-alert {
    padding: 11px 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 600;
  }

  .alert-danger {
    background: #fdf2f2;
    border: 1px solid #fde8e8;
    color: #e74c3c;
  }

  .alert-success {
    background: #f3faf6;
    border: 1px solid #def7ec;
    color: #0e6251;
  }

  .transfer-input-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .form-input-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .form-input-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
  }

  .form-text-input {
    height: 42px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    border-radius: 8px;
    padding: 0 14px;
    font-size: 13.5px;
    color: var(--text-main);
    outline: 0;
    transition: border-color 0.2s;
    font-family: inherit;
    width: 100%;
  }

  .form-text-input:focus {
    border-color: var(--primary);
  }

  .submit-action-btn {
    height: 44px;
    background: var(--primary);
    color: white;
    font-weight: 700;
    border: 0;
    border-radius: 8px;
    font-size: 13.5px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.2);
    transition: opacity 0.2s;
    margin-top: 10px;
  }

  .submit-action-btn:hover {
    opacity: 0.95;
  }

  /* Data Table Layouts */
  .grid-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
  }

  .grid-data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
  }

  .grid-data-table th {
    background: var(--table-header-bg);
    color: var(--text-main);
    font-weight: 700;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
  }

  .grid-data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-main);
    background: var(--bg-card);
  }

  .grid-data-table tr:hover td {
    background: var(--table-hover-bg);
  }

  .grid-data-table tr:last-child td {
    border-bottom: 0;
  }

  .empty-table-row {
    text-align: center;
    color: var(--text-secondary) !important;
    padding: 40px 0 !important;
    font-weight: 500;
  }

  /* Badge status chips */
  .badge-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
  }

  .status-warning {
    background: rgba(243, 156, 18, 0.08);
    color: var(--warning);
  }

  .status-success {
    background: rgba(46, 204, 113, 0.08);
    color: var(--success);
  }

  .status-danger {
    background: rgba(231, 76, 60, 0.08);
    color: var(--danger);
  }

  @media (max-width: 900px) {
    .transfer-grid-container {
      grid-template-columns: 1fr;
    }
  }
</style>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

