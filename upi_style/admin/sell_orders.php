<?php
// admin/sell_orders.php - Admin Sell Orders Payout Control
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Process Status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($order_id <= 0) {
        $error_msg = "Invalid order ID.";
    } else {
        try {
            // Fetch sell order details
            $stmt = $pdo->prepare("SELECT * FROM sell_orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                $error_msg = "Order not found.";
            } elseif ($order['status'] !== 'Paying') {
                $error_msg = "This order has already been processed (Status: " . $order['status'] . ").";
            } else {
                if ($action === 'success') {
                    // Check if the user has at least one Active linked UPI account
                    $stmtUpiCheck = $pdo->prepare("SELECT COUNT(*) FROM upi_accounts WHERE user_id = ? AND status = 'Active'");
                    $stmtUpiCheck->execute([$order['user_id']]);
                    $activeUpiCount = $stmtUpiCheck->fetchColumn();
                    
                    if ($activeUpiCount == 0) {
                        $error_msg = "Cannot complete payout! The user has unlinked their UPI account. Payout is suspended until they relink their UPI.";
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE sell_orders SET status = 'Success' WHERE id = ?");
                        $updateStmt->execute([$order_id]);
                        
                        // Dispatch Telegram group payout announcement
                        $group_chat_id = getSetting('telegram_group_chat_id');
                        $bot_token = getSetting('telegram_bot_token');
                        $bot_status = getSetting('telegram_bot_status');
                        
                        if ($bot_status === '1' && !empty($bot_token) && !empty($group_chat_id)) {
                            $uStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                            $uStmt->execute([$order['user_id']]);
                            $usr = $uStmt->fetch();
                            
                            if ($usr) {
                                $masked = substr($usr['phone'], 0, 4) . '••••' . substr($usr['phone'], -2);
                                $payout_amount = number_format($order['amount'], 2);
                                
                                $msg = "🎉 <b>Automatic Payout Success Notification!</b>\n";
                                $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $msg .= "👤 <b>User:</b> <code>{$masked}</code> (ID: {$order['user_id']})\n";
                                $msg .= "💸 <b>Paid Amount:</b> <code>₹ {$payout_amount}</code>\n";
                                $msg .= "💎 <b>Status:</b> Completed successfully!\n\n";
                                $msg .= "📈 Join trading and link your UPI account to receive payouts instantly!";
                                
                                $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
                                $post_fields = [
                                    'chat_id' => $group_chat_id,
                                    'text' => $msg,
                                    'parse_mode' => 'HTML'
                                ];
                                
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_POST, true);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                curl_exec($ch);
                                curl_close($ch);
                            }
                        }
                        
                        $success_msg = "Sell order #{$order_id} marked as Success. (Payout proof posted)";
                    }
                } elseif ($action === 'failed') {
                    $updateStmt = $pdo->prepare("UPDATE sell_orders SET status = 'Failed' WHERE id = ?");
                    $updateStmt->execute([$order_id]);
                    $success_msg = "Sell order #{$order_id} marked as Failed.";
                }
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Filters setup
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'Paying';
if (!in_array($status_filter, ['All', 'Paying', 'Success', 'Failed'])) {
    $status_filter = 'Paying';
}

$where_clause = '';
$params = [];
if ($status_filter !== 'All') {
    $where_clause = "WHERE s.status = ?";
    $params[] = $status_filter;
}

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    if ($where_clause === '') {
        $where_clause = "WHERE u.phone LIKE ? OR s.id = ?";
    } else {
        $where_clause .= " AND (u.phone LIKE ? OR s.id = ?)";
    }
    $params[] = "%$search%";
    $params[] = intval($search);
}

// Fetch sell orders
$query = "SELECT s.*, u.phone FROM sell_orders s JOIN users u ON s.user_id = u.id $where_clause ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Sell Orders</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Debit Control (Sell Orders)</h1>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body">
            <!-- Alert Display -->
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

            <!-- Panel table -->
            <div class="panel">
                <div class="panel-header">
                    <!-- Nav Tabs for Status filtering -->
                    <div style="display: flex; gap: 8px;">
                        <a href="sell_orders.php?status=Paying&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Paying' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Paying</a>
                        <a href="sell_orders.php?status=Success&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Success' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Success</a>
                        <a href="sell_orders.php?status=Failed&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Failed' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Failed</a>
                        <a href="sell_orders.php?status=All&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'All' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">All</a>
                    </div>

                    <form method="GET" class="table-filters" action="sell_orders.php">
                        <input type="hidden" name="status" value="<?= sanitize($status_filter) ?>">
                        <div class="search-input-wrapper">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" class="search-input" placeholder="Search ID or Phone..." value="<?= sanitize($search) ?>">
                        </div>
                        <?php if ($search !== ''): ?>
                            <a href="sell_orders.php?status=<?= urlencode($status_filter) ?>" class="btn btn-secondary" style="padding: 10px 16px;">Clear</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Sell ID</th>
                                <th>User Phone</th>
                                <th>Payout Amount (₹)</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th style="text-align: right; width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 32px 0;">No sell orders found under status "<?= $status_filter ?>".</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): 
                                    // Query active UPI status dynamically
                                    $stmtUpiCheck = $pdo->prepare("SELECT COUNT(*) FROM upi_accounts WHERE user_id = ? AND status = 'Active'");
                                    $stmtUpiCheck->execute([$o['user_id']]);
                                    $hasActiveUpi = ($stmtUpiCheck->fetchColumn() > 0);
                                ?>
                                    <tr>
                                        <td>#<?= $o['id'] ?></td>
                                        <td>
                                            <?= sanitize($o['phone']) ?>
                                            <?php if (!$hasActiveUpi): ?>
                                                <br>
                                                <span style="color: #ea5455; font-size: 11px; font-weight: bold; background: rgba(234, 84, 85, 0.1); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;">⚠️ Unlinked UPI</span>
                                            <?php else: 
                                                $stmtUpis = $pdo->prepare("SELECT partner, name, upi_no FROM upi_accounts WHERE user_id = ? AND status = 'Active' AND stopped = 0");
                                                $stmtUpis->execute([$o['user_id']]);
                                                $userUpis = $stmtUpis->fetchAll();
                                                foreach ($userUpis as $uu):
                                            ?>
                                                <br>
                                                <span style="color: #00b578; font-size: 11.5px; font-weight: bold; background: rgba(0, 181, 120, 0.08); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; font-family: monospace;">
                                                    💳 <?= sanitize($uu['partner']) ?>: <?= sanitize($uu['upi_no']) ?> (<?= sanitize($uu['name']) ?>)
                                                </span>
                                            <?php 
                                                endforeach;
                                            endif; ?>
                                        </td>
                                        <td style="font-weight: 700;">₹<?= number_format($o['amount'], 2) ?></td>
                                        <td>
                                            <span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span>
                                        </td>
                                        <td style="font-family: Arial, sans-serif; font-size: 13px; color: var(--text-secondary);"><?= $o['created_at'] ?></td>
                                        <td>
                                            <div class="actions-group" style="justify-content: flex-end;">
                                                <?php if ($o['status'] === 'Paying'): ?>
                                                    <!-- Success -->
                                                    <form method="POST" action="sell_orders.php" style="margin: 0;" onsubmit="return confirm('Mark sell order #<?= $o['id'] ?> as PAID (Success)?')">
                                                        <input type="hidden" name="action" value="success">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="btn btn-success-light" <?= !$hasActiveUpi ? 'disabled style="opacity: 0.5; cursor: not-allowed;" title="User has unlinked UPI"' : '' ?>>Mark Paid</button>
                                                    </form>
                                                    <!-- Failed -->
                                                    <form method="POST" action="sell_orders.php" style="margin: 0;" onsubmit="return confirm('Mark sell order #<?= $o['id'] ?> as FAILED?')">
                                                        <input type="hidden" name="action" value="failed">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="btn btn-danger-light">Mark Failed</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 12px; color: var(--text-secondary); font-style: italic;">Processed</span>
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
        </main>
    </div>
</body>
</html>

