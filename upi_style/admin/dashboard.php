<?php
// admin/dashboard.php - Main Admin Dashboard
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// Silent background poll action
if (isset($_GET['action']) && $_GET['action'] === 'poll_alerts') {
    header('Content-Type: application/json');
    $pending_buy = intval($pdo->query("SELECT COUNT(*) FROM buy_orders WHERE status = 'Pending'")->fetchColumn());
    $open_tickets = intval($pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'")->fetchColumn());
    
    echo json_encode([
        'pending_buy_orders' => $pending_buy,
        'open_support_tickets' => $open_tickets
    ]);
    exit;
}

// Fetch counts & stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$blockedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Blocked'")->fetchColumn();
$pendingBuy = $pdo->query("SELECT COUNT(*) FROM buy_orders WHERE status = 'Pending'")->fetchColumn();
$pendingSell = $pdo->query("SELECT COUNT(*) FROM sell_orders WHERE status = 'Paying'")->fetchColumn();
$pendingTransfers = $pdo->query("SELECT COUNT(*) FROM token_transfers WHERE status = 'Pending'")->fetchColumn();
$openTickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'")->fetchColumn();
$activeUpi = $pdo->query("SELECT COUNT(*) FROM upi_accounts WHERE status = 'Active'")->fetchColumn();

// Financial statistics
$totalSystemBalance = $pdo->query("SELECT SUM(itoken_balance) FROM users")->fetchColumn() ?: 0.00;
$totalSystemProfit = $pdo->query("SELECT SUM(today_profit) FROM users")->fetchColumn() ?: 0.00;
$totalSuccessfulDeposits = $pdo->query("SELECT SUM(price) FROM buy_orders WHERE status = 'Success'")->fetchColumn() ?: 0.00;
$totalSuccessfulWithdrawals = $pdo->query("SELECT SUM(amount) FROM sell_orders WHERE status = 'Success'")->fetchColumn() ?: 0.00;

// Fetch 5 recent pending buy orders with user details
$recentBuys = $pdo->query("SELECT b.*, u.phone FROM buy_orders b JOIN users u ON b.user_id = u.id WHERE b.status = 'Pending' ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

// Fetch 5 recent pending sell orders with user details
$recentSells = $pdo->query("SELECT s.*, u.phone FROM sell_orders s JOIN users u ON s.user_id = u.id WHERE s.status = 'Paying' ORDER BY s.created_at DESC LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>


    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <h1 class="page-title">Dashboard</h1>
                <a href="backup.php" class="btn btn-secondary" style="padding: 8px 14px; font-size: 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 14px; height: 14px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Download SQL Backup
                </a>
            </div>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body">
            <!-- Stats Cards Grid -->
            <div class="stats-grid">
                <div class="stat-card" onclick="location.href='users.php'" style="cursor: pointer;">
                    <div class="stat-card-left">
                        <span class="stat-label">Total Users</span>
                        <span class="stat-number"><?= $totalUsers ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-left">
                        <span class="stat-label">Active UPIs</span>
                        <span class="stat-number"><?= $activeUpi ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                </div>

                <div class="stat-card" onclick="location.href='buy_orders.php'" style="cursor: pointer;">
                    <div class="stat-card-left">
                        <span class="stat-label">Pending Buys</span>
                        <span class="stat-number"><?= $pendingBuy ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                </div>

                <div class="stat-card" onclick="location.href='sell_orders.php'" style="cursor: pointer;">
                    <div class="stat-card-left">
                        <span class="stat-label">Pending Sells</span>
                        <span class="stat-number"><?= $pendingSell ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Financial Stats Grid -->
            <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-top: 32px; margin-bottom: 16px;">Platform Financial Overview</h3>
            <div class="stats-grid" style="margin-bottom: 32px;">
                <div class="stat-card">
                    <div class="stat-card-left">
                        <span class="stat-label">Total User Balances</span>
                        <span class="stat-number" style="color: var(--success-color);">₹<?= number_format($totalSystemBalance, 2) ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; color: var(--success-color);">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-left">
                        <span class="stat-label">Total Commission Paid</span>
                        <span class="stat-number" style="color: var(--primary-color);">₹<?= number_format($totalSystemProfit, 2) ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; color: var(--primary-color);">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-left">
                        <span class="stat-label">Total Deposits (Success)</span>
                        <span class="stat-number" style="color: var(--warning-color);">₹<?= number_format($totalSuccessfulDeposits, 2) ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; color: var(--warning-color);">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-left">
                        <span class="stat-label">Total Withdrawals (Success)</span>
                        <span class="stat-number" style="color: var(--danger-color);">₹<?= number_format($totalSuccessfulWithdrawals, 2) ?></span>
                    </div>
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; color: var(--danger-color);">
                            <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline>
                            <polyline points="17 18 23 18 23 12"></polyline>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Double Column layout for recent items -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(45%, 1fr)); gap: 24px;">
                <!-- Column 1: Recent Pending Buy Orders -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Pending Buy Requests</h2>
                        <a href="buy_orders.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Manage All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>User</th>
                                    <th>Amount (₹)</th>
                                    <th>IToken</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBuys)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 24px 0;">No pending buy orders.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBuys as $order): ?>
                                        <tr>
                                            <td class="offer-no" style="font-family: Arial, sans-serif;"><?= sanitize(substr($order['order_no'], -6)) ?>...</td>
                                            <td><?= sanitize($order['phone']) ?></td>
                                            <td style="font-weight: 700; color: var(--danger-color);">₹<?= floatval($order['price']) ?></td>
                                            <td style="font-weight: 700; color: var(--primary-color);"><?= floatval($order['itokens']) ?></td>
                                            <td>
                                                <a href="buy_orders.php" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px; border-radius: 4px;">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Column 2: Recent Pending Sell Orders -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Pending Sell Requests</h2>
                        <a href="sell_orders.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Manage All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Amount (₹)</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSells)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 24px 0;">No pending sell orders.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSells as $order): ?>
                                        <tr>
                                            <td style="font-family: Arial, sans-serif;">#<?= $order['id'] ?></td>
                                            <td><?= sanitize($order['phone']) ?></td>
                                            <td style="font-weight: 700;">₹<?= floatval($order['amount']) ?></td>
                                            <td><span class="badge badge-paying"><?= $order['status'] ?></span></td>
                                            <td>
                                                <a href="sell_orders.php" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px; border-radius: 4px;">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Live Notifications Chime Checker script -->
    <script>
    let lastPendingCount = 0;
    let lastTicketCount = 0;
    let isFirstPoll = true;

    function playAlertChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const audioCtx = new AudioContext();
            
            // Double tone chime sequence
            const osc = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); // C5 note
            osc.frequency.exponentialRampToValueAtTime(783.99, audioCtx.currentTime + 0.12); // G5 note
            
            gainNode.gain.setValueAtTime(0.12, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            
            osc.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            osc.start();
            osc.stop(audioCtx.currentTime + 0.4);
        } catch (e) {
            console.error("Synthesizer alert failed: " + e.message);
        }
    }

    function checkDashboardAlerts() {
        fetch('dashboard.php?action=poll_alerts')
            .then(res => res.json())
            .then(data => {
                const buyCount = data.pending_buy_orders || 0;
                const ticketCount = data.open_support_tickets || 0;
                
                // Play notification if count has increased or new orders arrived
                if (!isFirstPoll) {
                    if (buyCount > lastPendingCount || ticketCount > lastTicketCount) {
                        playAlertChime();
                    }
                }
                
                lastPendingCount = buyCount;
                lastTicketCount = ticketCount;
                isFirstPoll = false;
            })
            .catch(err => console.error("Poll warning: " + err.message));
    }

    // Run poll check every 15 seconds
    setInterval(checkDashboardAlerts, 15000);
    // Trigger initial load
    setTimeout(checkDashboardAlerts, 2000);
    </script>
</body>
</html>

