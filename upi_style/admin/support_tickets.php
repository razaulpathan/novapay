<?php
// admin/support_tickets.php - Admin Support Ticket management
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle ticket reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $reply_text = isset($_POST['reply_text']) ? trim($_POST['reply_text']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Replied';

    if ($ticket_id <= 0 || empty($reply_text)) {
        $error_msg = "Please compose a valid reply message.";
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE support_tickets SET reply = ?, status = ? WHERE id = ?");
        $stmtUpdate->execute([$reply_text, $status, $ticket_id]);
        
        $success_msg = "Reply submitted successfully! Ticket status marked as " . sanitize($status) . ".";
    }
}

// Fetch all tickets
$tickets = $pdo->query("
    SELECT t.*, u.phone as user_phone
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
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
    <title>NextPay Admin - Support Tickets</title>
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
        .ticket-panel {
            background: #1c2230;
            border: 1px solid #2d3748;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .ticket-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2d3748;
            padding-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ticket-id {
            font-family: monospace;
            background: #2d3748;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .ticket-subject {
            font-size: 15px;
            font-weight: bold;
            color: white;
            margin-left: 8px;
        }
        .ticket-meta-info {
            font-size: 12.5px;
            color: var(--text-secondary);
        }
        .ticket-message-box {
            background: #131722;
            padding: 14px 18px;
            border-radius: 6px;
            border-left: 3.5px solid var(--primary-color);
            color: #d1d5db;
            line-height: 1.5;
            font-size: 13.5px;
        }
        .ticket-reply-box {
            background: rgba(46, 204, 113, 0.05);
            padding: 14px 18px;
            border-radius: 6px;
            border-left: 3.5px solid #2ecc71;
            color: #d1d5db;
            line-height: 1.5;
            font-size: 13.5px;
            align-self: flex-end;
            width: 95%;
        }
        .reply-editor-form {
            background: #131722;
            padding: 16px;
            border-radius: 8px;
            border: 1px dashed #2d3748;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .reply-textarea {
            width: 100%;
            height: 80px;
            background: #1c2230;
            border: 1px solid #2d3748;
            color: white;
            border-radius: 6px;
            padding: 10px;
            font-family: inherit;
            resize: none;
            outline: 0;
        }
        .reply-textarea:focus {
            border-color: var(--primary-color);
        }
        .reply-actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .select-status {
            background: #1c2230;
            border: 1px solid #2d3748;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-submit-reply {
            background: var(--primary-color);
            color: white;
            border: 0;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-submit-reply:hover {
            opacity: 0.9;
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
            <h1 class="page-title">Support Ticket Desk</h1>
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

            <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 20px;">Promoter & Agent Support Enquiries</h3>
            
            <?php if (empty($tickets)): ?>
                <div class="panel" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                    No support tickets have been opened yet.
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-panel">
                        <div class="ticket-header-row">
                            <div>
                                <span class="ticket-id">#<?= $ticket['id'] ?></span>
                                <span class="ticket-subject"><?= sanitize($ticket['subject']) ?></span>
                            </div>
                            <div class="ticket-meta-info">
                                <span>Agent: <?= sanitize($ticket['user_phone']) ?></span> |
                                <span>Date: <?= $ticket['created_at'] ?></span> |
                                <?php if ($ticket['status'] === 'Open'): ?>
                                    <span class="badge badge-pending">Open</span>
                                <?php elseif ($ticket['status'] === 'Replied'): ?>
                                    <span class="badge badge-success">Replied</span>
                                <?php else: ?>
                                    <span class="badge badge-paying">Closed</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Agent Query -->
                        <div class="ticket-message-box">
                            <strong style="color: var(--primary-color); display: block; margin-bottom: 6px;">Query Message:</strong>
                            <?= nl2br(sanitize($ticket['message'])) ?>
                        </div>

                        <!-- Previous Admin Response if any -->
                        <?php if (!empty($ticket['reply'])): ?>
                            <div class="ticket-reply-box">
                                <strong style="color: #2ecc71; display: block; margin-bottom: 6px;">Previous Response:</strong>
                                <?= nl2br(sanitize($ticket['reply'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Editor to compose reply (Only show if open or to override) -->
                        <form method="POST" class="reply-editor-form">
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                            <input type="hidden" name="action" value="reply_ticket">
                            
                            <textarea name="reply_text" class="reply-textarea" placeholder="Compose a reply description..." required></textarea>
                            
                            <div class="reply-actions-row">
                                <div>
                                    <span style="font-size: 13px; color: var(--text-secondary); margin-right: 8px;">Mark Status As:</span>
                                    <select name="status" class="select-status">
                                        <option value="Replied" <?= $ticket['status'] === 'Replied' ? 'selected' : '' ?>>Replied</option>
                                        <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-submit-reply">Submit Response</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

