<?php
// admin/token_transfers.php - Admin Token Settlement requests management
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle actions: Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $transfer_id = isset($_POST['transfer_id']) ? intval($_POST['transfer_id']) : 0;
    $action = $_POST['action'];

    // Fetch transfer details
    $stmtTr = $pdo->prepare("SELECT * FROM token_transfers WHERE id = ?");
    $stmtTr->execute([$transfer_id]);
    $transfer = $stmtTr->fetch();

    if (!$transfer) {
        $error_msg = "Settlement transfer record not found.";
    } elseif ($transfer['status'] !== 'Pending') {
        $error_msg = "This transfer request has already been processed.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'approve') {
                // Credit target customer
                $stmtCredit = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ? WHERE id = ? AND phone = ?");
                $stmtCredit->execute([$transfer['amount'], $transfer['target_uid'], $transfer['target_phone']]);

                if ($stmtCredit->rowCount() === 0) {
                    throw new Exception("Recipient mismatch. Target UID and phone could not be credited.");
                }

                // Update transfer status
                $stmtUpdate = $pdo->prepare("UPDATE token_transfers SET status = 'Success' WHERE id = ?");
                $stmtUpdate->execute([$transfer_id]);
                
                // Add buy order ledger row to show NextPay transferred funds
                $order_no = 'TR' . str_pad($transfer_id, 8, '0', STR_PAD_LEFT);
                $stmtOrder = $pdo->prepare("INSERT INTO buy_orders (order_no, user_id, type, price, reward, itokens, status, utr) VALUES (?, ?, 'UPI_PLUS', ?, 0, ?, 'Success', ?)");
                $stmtOrder->execute([$order_no, $transfer['target_uid'], $transfer['amount'], $transfer['amount'], 'Transferred from Agent ID ' . $transfer['agent_id']]);

                $success_msg = "Transfer settlement request approved and credited immediately!";
            } elseif ($action === 'reject') {
                // Refund agent
                $stmtRefund = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ? WHERE id = ?");
                $stmtRefund->execute([$transfer['amount'], $transfer['agent_id']]);

                // Update status
                $stmtUpdate = $pdo->prepare("UPDATE token_transfers SET status = 'Cancelled' WHERE id = ?");
                $stmtUpdate->execute([$transfer_id]);

                $success_msg = "Transfer settlement request rejected and balance refunded to agent.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Action failed: " . $e->getMessage();
        }
    }
}

// Fetch all transfers
$transfers = $pdo->query("
    SELECT t.*, u.phone as agent_phone
    FROM token_transfers t
    JOIN users u ON t.agent_id = u.id
    ORDER BY t.created_at DESC
")->fetchAll();

// Count pending requests
$pendingBuy = $pdo->query("SELECT COUNT(*) FROM buy_orders WHERE status = 'Pending'")->fetchColumn();
$pendingSell = $pdo->query("SELECT COUNT(*) FROM sell_orders WHERE status = 'Paying'")->fetchColumn();
$pendingTransfers = $pdo->query("SELECT COUNT(*) FROM token_transfers WHERE status = 'Pending'")->fetchColumn();
$openTickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Token Transfers</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .badge-pending-count {
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 10px;
            margin-left: auto;
        }
        .btn-approve {
            background: #2ecc71;
            color: white;
            border: 0;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-reject {
            background: #e74c3c;
            color: white;
            border: 0;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .actions-flex {
            display: flex;
            gap: 8px;
        }
        .alert-bar {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-weight: bold;
            font-size: 13.5px;
        }
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Agent Token Transfers</h1>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body">
            <?php if (!empty($error_msg)): ?>
                <div class="alert-bar alert-danger"><?= sanitize($error_msg) ?></div>
            <?php endif; ?>
            <?php if (!empty($success_msg)): ?>
                <div class="alert-bar alert-success"><?= sanitize($success_msg) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Transfer Settlement History & Requests</h2>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Agent / Source</th>
                                <th>Target UID</th>
                                <th>Target Phone</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transfers)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 24px 0;">No settlement transfer requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transfers as $tr): ?>
                                    <tr>
                                        <td>#<?= $tr['id'] ?></td>
                                        <td><?= sanitize($tr['agent_phone']) ?> (ID <?= $tr['agent_id'] ?>)</td>
                                        <td><?= intval($tr['target_uid']) ?></td>
                                        <td style="font-weight: 700;"><?= sanitize($tr['target_phone']) ?></td>
                                        <td style="font-weight: 700; color: var(--success-color);">₹<?= number_format($tr['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($tr['status'] === 'Pending'): ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php elseif ($tr['status'] === 'Success'): ?>
                                                <span class="badge badge-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge badge-paying">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $tr['created_at'] ?></td>
                                        <td>
                                            <?php if ($tr['status'] === 'Pending'): ?>
                                                <div class="actions-flex">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="transfer_id" value="<?= $tr['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn-approve">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="transfer_id" value="<?= $tr['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn-reject">Reject</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); font-size: 11px;">Processed</span>
                                            <?php endif; ?>
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

