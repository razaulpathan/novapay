<?php
// masteragent/index.php - Head Agent Dashboard & Promoter Control
$current_page = 'index.php';
require_once 'header.php';

$success_msg = '';
$error_msg = '';

$master_id = $user['id'];

// Process Actions (Update Rate, Toggle Sub-Agent permission, Block/Unblock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    // Verify target belongs to this master agent's downline tree
    $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE id = ? AND referred_by = ?");
    $stmtCheck->execute([$target_id, $master_id]);
    $target = $stmtCheck->fetch();

    if (!$target) {
        $error_msg = "Promoter not found or doesn't belong to your downline network.";
    } else {
        try {
            if ($action === 'update_rate') {
                $new_rate = isset($_POST['reward_rate']) ? floatval($_POST['reward_rate']) : 0.0;
                if ($new_rate < 0 || $new_rate > 100) {
                    $error_msg = "Invalid reward rate. Must be between 0% and 100%.";
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE users SET reward_rate = ? WHERE id = ?");
                    $stmtUpdate->execute([$new_rate, $target_id]);
                    $success_msg = "Successfully updated reward rate for promoter " . sanitize($target['phone']) . " to {$new_rate}%.";
                }
            } elseif ($action === 'toggle_sub') {
                $new_val = ($target['allow_create_sub_agent'] == 1) ? 0 : 1;
                $stmtUpdate = $pdo->prepare("UPDATE users SET allow_create_sub_agent = ? WHERE id = ?");
                $stmtUpdate->execute([$new_val, $target_id]);
                $success_msg = "Toggled sub-agent creation permission for promoter " . sanitize($target['phone']) . ".";
            } elseif ($action === 'toggle_status') {
                $new_status = ($target['status'] === 'Active') ? 'Blocked' : 'Active';
                $stmtUpdate = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmtUpdate->execute([$new_status, $target_id]);
                $success_msg = "Promoter " . sanitize($target['phone']) . " status updated to {$new_status}.";
            }
            
            // Reload user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error_msg = "Database operation failed: " . $e->getMessage();
        }
    }
}

// Fetch stats
// 1. Total balance
$balance = floatval($user['itoken_balance']);

// 2. Count promoters
$stmtCountPromoters = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmtCountPromoters->execute([$master_id]);
$total_promoters = intval($stmtCountPromoters->fetchColumn());

// 3. Count total network size (users referred by their promoters)
$stmtCountNetwork = $pdo->prepare("
    SELECT COUNT(u2.id) 
    FROM users u2 
    JOIN users u1 ON u2.referred_by = u1.id 
    WHERE u1.referred_by = ?
");
$stmtCountNetwork->execute([$master_id]);
$network_size = intval($stmtCountNetwork->fetchColumn());

// 4. Today successful deals inside network
$stmtTodaySuccessDeals = $pdo->prepare("
    SELECT COALESCE(SUM(bo.price), 0) 
    FROM buy_orders bo 
    JOIN users u ON bo.user_id = u.id 
    JOIN users u1 ON u.referred_by = u1.id
    WHERE u1.referred_by = ? AND bo.status = 'Success' AND DATE(bo.created_at) = CURRENT_DATE()
");
$stmtTodaySuccessDeals->execute([$master_id]);
$today_deals_volume = floatval($stmtTodaySuccessDeals->fetchColumn());

// Fetch Promoters List
$stmtPromoters = $pdo->prepare("
    SELECT p.id, p.phone, p.itoken_balance, p.reward_rate, p.allow_create_sub_agent, p.status, p.created_at, COUNT(u.id) as team_count
    FROM users p
    LEFT JOIN users u ON u.referred_by = p.id
    WHERE p.referred_by = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmtPromoters->execute([$master_id]);
$promoters = $stmtPromoters->fetchAll();
?>

<!-- Alert messaging -->
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

<!-- Stats Grid Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px;">
    <div class="card-element" style="background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; border: 0;">
        <div class="card-body-element" style="display: flex; flex-direction: column; gap: 8px;">
            <span style="font-size: 13px; font-weight: 600; opacity: 0.85; text-transform: uppercase;">Head Wallet Balance</span>
            <span style="font-size: 28px; font-weight: 800;">₹ <?= number_format($balance, 2) ?></span>
            <span style="font-size: 12px; opacity: 0.75;">Available tokens for distribution</span>
        </div>
    </div>
    
    <div class="card-element">
        <div class="card-body-element" style="display: flex; flex-direction: column; gap: 8px;">
            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Promoters</span>
            <span style="font-size: 28px; font-weight: 800; color: var(--primary);"><?= $total_promoters ?></span>
            <span style="font-size: 12px; color: var(--text-secondary);">Direct agents in network</span>
        </div>
    </div>

    <div class="card-element">
        <div class="card-body-element" style="display: flex; flex-direction: column; gap: 8px;">
            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Downline Users</span>
            <span style="font-size: 28px; font-weight: 800; color: var(--success);"><?= $network_size ?></span>
            <span style="font-size: 12px; color: var(--text-secondary);">Sub-referrals network pool</span>
        </div>
    </div>

    <div class="card-element">
        <div class="card-body-element" style="display: flex; flex-direction: column; gap: 8px;">
            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Today Success deals</span>
            <span style="font-size: 28px; font-weight: 800; color: var(--warning);">₹ <?= number_format($today_deals_volume, 2) ?></span>
            <span style="font-size: 12px; color: var(--text-secondary);">Trading volumes today</span>
        </div>
    </div>
</div>

<!-- Promoter Management Table -->
<div class="card-element">
    <div class="card-header-element">
        <span class="card-title-element">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Promoters Management Control
        </span>
    </div>
    
    <div class="card-body-element">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Promoter Phone</th>
                        <th>Token Balance (₹)</th>
                        <th>Reward Rate (%)</th>
                        <th>Sub-Agents Count</th>
                        <th>Allow Sub</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 320px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promoters)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 32px 0; color: var(--text-secondary);">No promoters registered under your hierarchy.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($promoters as $p): ?>
                            <tr>
                                <td>#<?= $p['id'] ?></td>
                                <td style="font-weight: 700;"><?= sanitize($p['phone']) ?></td>
                                <td style="font-weight: 600; font-family: monospace;">₹ <?= number_format($p['itoken_balance'], 2) ?></td>
                                <td style="font-weight: 700; color: var(--primary);"><?= number_format($p['reward_rate'], 2) ?>%</td>
                                <td>
                                    <span class="badge" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);"><?= $p['team_count'] ?> Sub-agents</span>
                                </td>
                                <td>
                                    <span class="badge <?= $p['allow_create_sub_agent'] == 1 ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $p['allow_create_sub_agent'] == 1 ? 'Allowed' : 'Blocked' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $p['status'] === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $p['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                        <!-- Update Rate Form -->
                                        <form method="POST" style="display: flex; gap: 4px; margin: 0;">
                                            <input type="hidden" name="action" value="update_rate">
                                            <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                            <input type="number" step="0.01" name="reward_rate" placeholder="%" required 
                                                   style="width: 55px; height: 32px; border: 1px solid var(--border-color); border-radius: 6px; padding: 0 6px; font-size: 12px; outline: 0;"
                                                   value="<?= $p['reward_rate'] ?>">
                                            <button type="submit" class="btn btn-primary" style="height: 32px; padding: 0 10px; font-size: 12px;">Rate</button>
                                        </form>

                                        <!-- Toggle Sub Permission -->
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="toggle_sub">
                                            <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="height: 32px; padding: 0 10px; font-size: 11px; font-weight: bold;">Toggle Sub</button>
                                        </form>

                                        <!-- Block / Unblock -->
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Change status for promoter <?= sanitize($p['phone']) ?>?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn <?= $p['status'] === 'Active' ? 'btn-secondary' : 'btn-primary' ?>" 
                                                    style="height: 32px; padding: 0 10px; font-size: 11px; <?= $p['status'] === 'Active' ? 'background: #fecaca; color: #991b1b;' : '' ?>">
                                                <?= $p['status'] === 'Active' ? 'Block' : 'Unblock' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
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
