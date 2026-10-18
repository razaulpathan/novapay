<?php
// admin/buy_orders.php - Admin Buy Orders Approval Control
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Process Approve / Cancel action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($order_id <= 0) {
        $error_msg = "Invalid order ID.";
    } else {
        try {
            // Fetch buy order details
            $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                $error_msg = "Order not found.";
            } elseif ($order['status'] !== 'Pending') {
                $error_msg = "This order has already been processed (Status: " . $order['status'] . ").";
            } else {
                if ($action === 'approve') {
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
                    $success_msg = "Buy order #{$order['order_no']} approved successfully. Balance & profit credited to user. (Automatic sell created)";
                } elseif ($action === 'cancel') {
                    // Update order status to Cancelled
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled' WHERE id = ?");
                    $updateStmt->execute([$order_id]);
                    $success_msg = "Buy order #{$order['order_no']} marked as Cancelled.";
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Filters setup
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'Pending';
if (!in_array($status_filter, ['All', 'Pending', 'Success', 'Cancelled'])) {
    $status_filter = 'Pending';
}

$where_clause = '';
$params = [];
if ($status_filter !== 'All') {
    $where_clause = "WHERE b.status = ?";
    $params[] = $status_filter;
}

// Search order no
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    if ($where_clause === '') {
        $where_clause = "WHERE b.order_no LIKE ? OR u.phone LIKE ?";
    } else {
        $where_clause .= " AND (b.order_no LIKE ? OR u.phone LIKE ?)";
    }
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch buy orders
$query = "SELECT b.*, u.phone FROM buy_orders b JOIN users u ON b.user_id = u.id $where_clause ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Buy Orders</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Credit Control (Buy Orders)</h1>
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
                        <a href="buy_orders.php?status=Pending&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Pending' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Pending</a>
                        <a href="buy_orders.php?status=Success&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Success' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Success</a>
                        <a href="buy_orders.php?status=Cancelled&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'Cancelled' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">Cancelled</a>
                        <a href="buy_orders.php?status=All&search=<?= urlencode($search) ?>" class="btn <?= $status_filter === 'All' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; font-size: 13px;">All</a>
                    </div>

                    <form method="GET" class="table-filters" action="buy_orders.php">
                        <input type="hidden" name="status" value="<?= sanitize($status_filter) ?>">
                        <div class="search-input-wrapper">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" class="search-input" placeholder="Search Order # or Phone..." value="<?= sanitize($search) ?>">
                        </div>
                        <?php if ($search !== ''): ?>
                            <a href="buy_orders.php?status=<?= urlencode($status_filter) ?>" class="btn btn-secondary" style="padding: 10px 16px;">Clear</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>User Phone</th>
                                <th>Type</th>
                                <th>Price Paid</th>
                                <th>Reward (Profit)</th>
                                <th>ITokens Added</th>
                                <th>UTR / Reference Proof</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th style="text-align: right; width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; color: var(--text-secondary); padding: 32px 0;">No buy orders found under status "<?= $status_filter ?>".</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td class="offer-no" style="font-family: Arial, sans-serif; font-weight: bold;"><?= sanitize($o['order_no']) ?></td>
                                        <td>
                                            <?= sanitize($o['phone']) ?>
                                            <?php if (!empty($o['user_pay_upi'])): ?>
                                                <br>
                                                <span style="font-size: 11px; font-weight: bold; background: rgba(35, 149, 238, 0.1); color: #2395ee; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; font-family: monospace;">
                                                    💳 Pay UPI: <?= sanitize($o['user_pay_upi']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge" style="background-color:rgba(255,255,255,0.05); color:white; border: 1px solid var(--border-color);"><?= sanitize($o['type']) ?></span></td>
                                        <td style="font-weight: 700; color: var(--danger-color);"><?= $o['type'] === 'USDT' ? '' : '₹' ?><?= number_format($o['price'], 2) ?><?= $o['type'] === 'USDT' ? ' USDT' : '' ?></td>
                                        <td style="font-weight: bold;">₹<?= number_format($o['reward'], 2) ?></td>
                                        <td style="font-weight: 700; color: var(--success-color);">₹<?= number_format($o['itokens'], 2) ?></td>
                                        <td style="font-family: monospace; font-size: 13px;">
                                            <?php if (!empty($o['utr'])): ?>
                                                <span style="background: rgba(35, 149, 238, 0.1); color: #2395ee; padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(35, 149, 238, 0.2); font-weight: bold;"><?= sanitize($o['utr']) ?></span>
                                                <?php if (!empty($o['payment_proof_img'])): ?>
                                                    <div style="margin-top: 6px;">
                                                        <a href="../<?= sanitize($o['payment_proof_img']) ?>" target="_blank" style="color: #2ecc71; font-size: 11px; text-decoration: underline; font-family: sans-serif; font-weight: bold;">View Proof Image</a>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); font-style: italic; font-size: 12px;">No proof submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span>
                                        </td>
                                        <td style="font-family: Arial, sans-serif; font-size: 13px; color: var(--text-secondary);"><?= $o['created_at'] ?></td>
                                        <td>
                                            <div class="actions-group" style="justify-content: flex-end;">
                                                <?php if ($o['status'] === 'Pending'): ?>
                                                    <!-- Approve -->
                                                    <form method="POST" action="buy_orders.php" style="margin: 0;" onsubmit="return confirm('Approve order <?= sanitize($o['order_no']) ?> and credit user?')">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="btn btn-success-light">Approve</button>
                                                    </form>
                                                    <!-- Cancel -->
                                                    <form method="POST" action="buy_orders.php" style="margin: 0;" onsubmit="return confirm('Cancel/Reject order <?= sanitize($o['order_no']) ?>?')">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <button type="submit" class="btn btn-danger-light">Reject</button>
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

