<?php
// masteragent/transfer_token.php - Head Agent Token Distribution
$current_page = 'transfer_token.php';
require_once 'header.php';

$success_msg = '';
$error_msg = '';

$master_id = $user['id'];

// Handle Transfer Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_token') {
    $target_uid = isset($_POST['target_uid']) ? intval($_POST['target_uid']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;

    if ($target_uid <= 0 || $amount <= 0) {
        $error_msg = "Please select a valid promoter and enter a transfer amount.";
    } elseif ($amount > $user['itoken_balance']) {
        $error_msg = "Insufficient balance. Your current balance is ₹" . number_format($user['itoken_balance'], 2);
    } else {
        // Verify target belongs to this master agent
        $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE id = ? AND referred_by = ?");
        $stmtCheck->execute([$target_uid, $master_id]);
        $promoter = $stmtCheck->fetch();

        if (!$promoter) {
            $error_msg = "Selected user is not a direct promoter under your downline.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Deduct from Master Agent
                $stmtDeduct = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance - ? WHERE id = ?");
                $stmtDeduct->execute([$amount, $master_id]);

                // 2. Add to Promoter Agent
                $stmtAdd = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ? WHERE id = ?");
                $stmtAdd->execute([$amount, $target_uid]);

                // 3. Log into token_transfers as instant 'Success'
                $stmtInsert = $pdo->prepare("
                    INSERT INTO token_transfers (agent_id, target_phone, target_uid, amount, status) 
                    VALUES (?, ?, ?, ?, 'Success')
                ");
                $stmtInsert->execute([$master_id, $promoter['phone'], $target_uid, $amount]);

                $pdo->commit();

                // Refresh local user variables
                $user['itoken_balance'] -= $amount;
                $success_msg = "Successfully transferred ₹" . number_format($amount, 2) . " instantly to promoter " . sanitize($promoter['phone']) . ".";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Transfer transaction failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch Master's direct promoters
$stmtPList = $pdo->prepare("SELECT id, phone, itoken_balance FROM users WHERE referred_by = ? ORDER BY phone ASC");
$stmtPList->execute([$master_id]);
$promoters = $stmtPList->fetchAll();

// Fetch Token transfers history logged by this master agent
$stmtLogs = $pdo->prepare("
    SELECT t.*, u.phone as sender_phone 
    FROM token_transfers t 
    JOIN users u ON t.agent_id = u.id 
    WHERE t.agent_id = ? 
    ORDER BY t.created_at DESC
");
$stmtLogs->execute([$master_id]);
$transfers = $stmtLogs->fetchAll();
?>

<!-- Alerts messaging -->
<?php if ($success_msg !== ''): ?>
    <div class="alert alert-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <?= $success_msg ?>
    </div>
<?php endif; ?>
<?php if ($error_msg !== ''): ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <?= $error_msg ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
    
    <!-- Left form panel -->
    <div class="card-element">
        <div class="card-header-element">
            <span class="card-title-element">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Distribute Tokens to Promoters
            </span>
        </div>
        <div class="card-body-element">
            <form method="POST">
                <input type="hidden" name="action" value="transfer_token">
                
                <div class="form-group">
                    <label class="form-label" for="target_uid">Select Direct Downline Promoter</label>
                    <select name="target_uid" id="target_uid" class="form-control" required style="padding: 0 10px;">
                        <option value="">-- Choose Promoter Agent --</option>
                        <?php foreach ($promoters as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= sanitize($p['phone']) ?> (UID: <?= $p['id'] ?>) - Balance: ₹<?= number_format($p['itoken_balance'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text">Choose the downstream promoter whose wallet balance you want to fund.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="amount">Amount to Transfer (₹)</label>
                    <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control" placeholder="Enter transfer amount in ₹" required>
                    <span class="form-text">This balance will be instantly credited to the promoter agent's wallet.</span>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 8px;">Confirm &amp; Instant Transfer</button>
            </form>
        </div>
    </div>

    <!-- Right balance summary card -->
    <div class="card-element">
        <div class="card-header-element">
            <span class="card-title-element">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Wallet Overview
            </span>
        </div>
        <div class="card-body-element" style="display: flex; flex-direction: column; gap: 16px; align-items: center; text-align: center;">
            <div style="background: var(--table-header-bg); border: 1px solid var(--border-color); width: 100%; padding: 20px; border-radius: 8px;">
                <span style="font-size: 12px; font-weight: bold; color: var(--text-secondary); text-transform: uppercase; display: block; margin-bottom: 6px;">Available Balance</span>
                <span style="font-size: 26px; font-weight: 800; color: var(--primary);">₹ <?= number_format($user['itoken_balance'], 2) ?></span>
            </div>
            <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; text-align: justify;">
                Ensure that you verify the promoter before performing the transfer. Since transfers from Master Agents to direct promoters are instant, they cannot be reversed once processed.
            </p>
        </div>
    </div>

</div>

<!-- Transfers History List -->
<div class="card-element" style="margin-top: 24px;">
    <div class="card-header-element">
        <span class="card-title-element">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Tokens Transfer History Logs
        </span>
    </div>
    <div class="card-body-element">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>Target Phone</th>
                        <th>Target UID</th>
                        <th>Transferred Amount (₹)</th>
                        <th>Status</th>
                        <th>Transfer Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px 0; color: var(--text-secondary);">No token transfer transaction logs recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td>#<?= $t['id'] ?></td>
                                <td style="font-weight: 700;"><?= sanitize($t['target_phone']) ?></td>
                                <td>UID: <?= $t['target_uid'] ?></td>
                                <td style="font-weight: 700; color: var(--primary);">₹<?= number_format($t['amount'], 2) ?></td>
                                <td>
                                    <span class="badge badge-success">Instant Success</span>
                                </td>
                                <td style="font-family: Arial, sans-serif; font-size: 13px; color: var(--text-secondary);"><?= $t['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once '../footer.php';
?>
