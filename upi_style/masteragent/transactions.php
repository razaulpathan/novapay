<?php
// masteragent/transactions.php - Team Status, UPIs, and Buy Order Disputes
$current_page = 'transactions.php';
require_once 'header.php';

$success_msg = '';
$error_msg = '';

$master_id = $user['id'];

// Handle Actions (Approve Buy Order, Cancel Buy Order, Toggle Auto Selling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'approve_buy' || $action === 'cancel_buy') {
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

            // Verify order belongs to this master agent's downline network
            $stmtCheck = $pdo->prepare("
                SELECT bo.* 
                FROM buy_orders bo 
                JOIN users u ON bo.user_id = u.id 
                JOIN users p ON u.referred_by = p.id
                WHERE bo.id = ? AND p.referred_by = ?
            ");
            $stmtCheck->execute([$order_id, $master_id]);
            $order = $stmtCheck->fetch();

            if (!$order) {
                $error_msg = "Order not found or doesn't belong to your downline network.";
            } elseif ($order['status'] !== 'Pending') {
                $error_msg = "This order has already been processed (Status: {$order['status']}).";
            } else {
                if ($action === 'approve_buy') {
                    $pdo->beginTransaction();

                    // Update order status to Success
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Success' WHERE id = ?");
                    $updateStmt->execute([$order_id]);

                    // Add itokens to user balance, and reward to user profit
                    $userStmt = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, today_profit = today_profit + ? WHERE id = ?");
                    $userStmt->execute([$order['itokens'], $order['reward'], $order['user_id']]);

                    // Unconditionally trigger automatic token selling & Admin telegram alert
                    triggerAutoSell($pdo, $order_id);

                    // Distribute multi-level commission to referrer chain
                    distributeMultiLevelCommission($order['user_id'], $order['price']);

                    $pdo->commit();
                    $success_msg = "Buy order #{$order['order_no']} approved successfully. Balance credited.";
                } else {
                    // Update order status to Cancelled
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled' WHERE id = ?");
                    $updateStmt->execute([$order_id]);
                    $success_msg = "Buy order #{$order['order_no']} marked as Cancelled.";
                }
            }
        } elseif ($action === 'toggle_auto_sell') {
            $target_uid = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            // Verify user belongs to direct promoter's downline network
            $stmtCheck = $pdo->prepare("
                SELECT u.* 
                FROM users u 
                JOIN users p ON u.referred_by = p.id
                WHERE u.id = ? AND p.referred_by = ?
            ");
            $stmtCheck->execute([$target_uid, $master_id]);
            $target_user = $stmtCheck->fetch();

            if (!$target_user) {
                $error_msg = "User not found or doesn't belong to your downline network.";
            } else {
                $new_val = ($target_user['auto_selling'] == 1) ? 0 : 1;
                $stmtUpdate = $pdo->prepare("UPDATE users SET auto_selling = ? WHERE id = ?");
                $stmtUpdate->execute([$new_val, $target_uid]);
                $success_msg = "Toggled Auto Selling status for user " . sanitize($target_user['phone']) . " to " . ($new_val == 1 ? 'ON' : 'OFF') . ".";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Operation failed: " . $e->getMessage();
    }
}

// Fetch 1. Downline Users for Auto Selling Status Check
$stmtUsers = $pdo->prepare("
    SELECT u.id, u.phone, u.itoken_balance, u.auto_selling, p.phone as promoter_phone 
    FROM users u 
    JOIN users p ON u.referred_by = p.id
    WHERE p.referred_by = ?
    ORDER BY u.created_at DESC
");
$stmtUsers->execute([$master_id]);
$network_users = $stmtUsers->fetchAll();

// Fetch 2. Downline Buy Orders
$buy_status_filter = isset($_GET['buy_status']) ? trim($_GET['buy_status']) : 'Pending';
if (!in_array($buy_status_filter, ['All', 'Pending', 'Success', 'Cancelled'])) {
    $buy_status_filter = 'Pending';
}
$where_buy = '';
$params_buy = [$master_id];
if ($buy_status_filter !== 'All') {
    $where_buy = "AND bo.status = ?";
    $params_buy[] = $buy_status_filter;
}
$stmtBuy = $pdo->prepare("
    SELECT bo.*, u.phone as user_phone, p.phone as promoter_phone 
    FROM buy_orders bo 
    JOIN users u ON bo.user_id = u.id 
    JOIN users p ON u.referred_by = p.id
    WHERE p.referred_by = ? $where_buy
    ORDER BY bo.created_at DESC
");
$stmtBuy->execute($params_buy);
$buy_orders = $stmtBuy->fetchAll();

// Fetch 3. Downline UPI Accounts
$stmtUpis = $pdo->prepare("
    SELECT ua.*, u.phone as user_phone, p.phone as promoter_phone 
    FROM upi_accounts ua 
    JOIN users u ON ua.user_id = u.id 
    JOIN users p ON u.referred_by = p.id
    WHERE p.referred_by = ?
    ORDER BY ua.created_at DESC
");
$stmtUpis->execute([$master_id]);
$upi_accounts = $stmtUpis->fetchAll();
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

<!-- Section Navigation Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
    <button onclick="switchTab('tab-users')" id="btn-tab-users" class="btn btn-primary" style="height: 36px; padding: 0 16px; font-size: 13px;">👥 Downline Users</button>
    <button onclick="switchTab('tab-buy-orders')" id="btn-tab-buy-orders" class="btn btn-secondary" style="height: 36px; padding: 0 16px; font-size: 13px;">🛒 Buy Order Disputes</button>
    <button onclick="switchTab('tab-upis')" id="btn-tab-upis" class="btn btn-secondary" style="height: 36px; padding: 0 16px; font-size: 13px;">💳 UPI Accounts Directory</button>
</div>

<!-- Tab 1: Downline Users -->
<div id="tab-users" class="tab-content card-element">
    <div class="card-header-element">
        <span class="card-title-element">👥 Downline Network Users</span>
    </div>
    <div class="card-body-element">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>User Phone</th>
                        <th>Parent Promoter</th>
                        <th>Token Balance (₹)</th>
                        <th>Auto Selling State</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($network_users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px 0; color: var(--text-secondary);">No network users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($network_users as $nu): ?>
                            <tr>
                                <td>#<?= $nu['id'] ?></td>
                                <td style="font-weight: 700;"><?= sanitize($nu['phone']) ?></td>
                                <td><?= sanitize($nu['promoter_phone']) ?></td>
                                <td style="font-weight: 600;">₹<?= number_format($nu['itoken_balance'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $nu['auto_selling'] == 1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $nu['auto_selling'] == 1 ? 'Active (IN SELL)' : 'OFF' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Toggle Auto Selling state for user <?= sanitize($nu['phone']) ?>?')">
                                        <input type="hidden" name="action" value="toggle_auto_sell">
                                        <input type="hidden" name="user_id" value="<?= $nu['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="height: 32px; padding: 0 12px; font-size: 11px; font-weight: bold;">
                                            <?= $nu['auto_selling'] == 1 ? 'Turn OFF' : 'Turn ON' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab 2: Downline Buy Orders -->
<div id="tab-buy-orders" class="tab-content card-element" style="display: none;">
    <div class="card-header-element" style="flex-wrap: wrap; gap: 12px;">
        <span class="card-title-element">🛒 Buy Orders &amp; Disputes review</span>
        <div style="display: flex; gap: 8px;">
            <a href="transactions.php?buy_status=Pending&tab=tab-buy-orders" class="btn <?= $buy_status_filter === 'Pending' ? 'btn-primary' : 'btn-secondary' ?>" style="height: 32px; padding: 0 12px; font-size: 12px;">Pending</a>
            <a href="transactions.php?buy_status=Success&tab=tab-buy-orders" class="btn <?= $buy_status_filter === 'Success' ? 'btn-primary' : 'btn-secondary' ?>" style="height: 32px; padding: 0 12px; font-size: 12px;">Success</a>
            <a href="transactions.php?buy_status=Cancelled&tab=tab-buy-orders" class="btn <?= $buy_status_filter === 'Cancelled' ? 'btn-primary' : 'btn-secondary' ?>" style="height: 32px; padding: 0 12px; font-size: 12px;">Cancelled</a>
            <a href="transactions.php?buy_status=All&tab=tab-buy-orders" class="btn <?= $buy_status_filter === 'All' ? 'btn-primary' : 'btn-secondary' ?>" style="height: 32px; padding: 0 12px; font-size: 12px;">All</a>
        </div>
    </div>
    <div class="card-body-element">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>User</th>
                        <th>Promoter</th>
                        <th>Price (₹)</th>
                        <th>I-Tokens</th>
                        <th>UTR Code / Proof</th>
                        <th>Status</th>
                        <th>Requested Date</th>
                        <th style="text-align: right; width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($buy_orders)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 24px 0; color: var(--text-secondary);">No buy orders found under status "<?= $buy_status_filter ?>".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($buy_orders as $bo): ?>
                            <tr>
                                <td>#<?= sanitize($bo['order_no']) ?></td>
                                <td style="font-weight: 700;"><?= sanitize($bo['user_phone']) ?></td>
                                <td><?= sanitize($bo['promoter_phone']) ?></td>
                                <td style="font-weight: 700;">₹<?= number_format($bo['price'], 2) ?></td>
                                <td style="font-weight: 700; color: var(--success);">₹<?= number_format($bo['itokens'], 2) ?></td>
                                <td>
                                    <?php if (!empty($bo['utr'])): ?>
                                        <span style="background: rgba(79, 70, 229, 0.1); color: var(--primary); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(79, 70, 229, 0.2); font-weight: bold; font-family: monospace;">
                                            <?= sanitize($bo['utr']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-style: italic; font-size: 12px; color: var(--text-secondary);">No UTR submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $bo['status'] === 'Success' ? 'badge-success' : ($bo['status'] === 'Pending' ? 'badge-warning' : 'badge-danger') ?>">
                                        <?= $bo['status'] ?>
                                    </span>
                                </td>
                                <td style="font-family: Arial, sans-serif; font-size: 12px; color: var(--text-secondary);"><?= $bo['created_at'] ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <?php if ($bo['status'] === 'Pending'): ?>
                                            <!-- Approve form -->
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Approve order #<?= sanitize($bo['order_no']) ?>?')">
                                                <input type="hidden" name="action" value="approve_buy">
                                                <input type="hidden" name="order_id" value="<?= $bo['id'] ?>">
                                                <button type="submit" class="btn btn-primary" style="height: 30px; padding: 0 10px; font-size: 11px; background: var(--success);">Approve</button>
                                            </form>
                                            <!-- Cancel form -->
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Cancel order #<?= sanitize($bo['order_no']) ?>?')">
                                                <input type="hidden" name="action" value="cancel_buy">
                                                <input type="hidden" name="order_id" value="<?= $bo['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="height: 30px; padding: 0 10px; font-size: 11px; background: #fee2e2; color: #991b1b; border: 0;">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-style: italic; font-size: 12px; color: var(--text-secondary);">Completed</span>
                                        <?php endif; ?>
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

<!-- Tab 3: UPI Accounts -->
<div id="tab-upis" class="tab-content card-element" style="display: none;">
    <div class="card-header-element">
        <span class="card-title-element">💳 Team linked UPI Accounts Directory</span>
    </div>
    <div class="card-body-element">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>User Phone</th>
                        <th>Parent Promoter</th>
                        <th>UPI Address</th>
                        <th>Partner Channel</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Linked Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upi_accounts)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px 0; color: var(--text-secondary);">No UPI accounts linked by your network yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upi_accounts as $ua): ?>
                            <tr>
                                <td style="font-weight: 700;"><?= sanitize($ua['user_phone']) ?></td>
                                <td><?= sanitize($ua['promoter_phone']) ?></td>
                                <td style="font-family: monospace; font-size: 13.5px; font-weight: bold; color: var(--primary);"><?= sanitize($ua['upi_id']) ?></td>
                                <td><?= sanitize($ua['partner_name']) ?></td>
                                <td>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: bold;">
                                        <?= sanitize($ua['tab_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $ua['status'] === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $ua['status'] ?>
                                    </span>
                                </td>
                                <td style="font-family: Arial, sans-serif; font-size: 12.5px; color: var(--text-secondary);"><?= $ua['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.style.display = 'none';
        });
        
        // Show active tab
        document.getElementById(tabId).style.display = 'block';

        // Toggle buttons style
        document.getElementById('btn-tab-users').className = 'btn btn-secondary';
        document.getElementById('btn-tab-buy-orders').className = 'btn btn-secondary';
        document.getElementById('btn-tab-upis').className = 'btn btn-secondary';

        document.getElementById('btn-' + tabId).className = 'btn btn-primary';
    }

    // Auto-active tab based on query param on load
    window.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab) {
            switchTab(activeTab);
        }
    });
</script>

<?php
require_once '../footer.php';
?>
