<?php
// upimenger/suspended.php - Manage Telegram Bot Suspension Status
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'suspend_bot') {
        updateSetting('telegram_bot_status', 'Suspended');
        $success_msg = "Telegram Bot status set to Suspended by pahan.";
    } elseif ($action === 'unsuspend_bot') {
        updateSetting('telegram_bot_status', '1');
        $success_msg = "Telegram Bot has been successfully unsuspended (Active).";
    } elseif ($action === 'disable_bot') {
        updateSetting('telegram_bot_status', '0');
        $success_msg = "Telegram Bot has been disabled (Offline).";
    }
}

$bot_status = getSetting('telegram_bot_status') ?: '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Manager - Telegram Bot Status</title>
    <!-- Import Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Link to premium styles -->
    <link rel="stylesheet" href="upi_style.css">
</head>
<body>
    <!-- Topbar Header -->
    <header class="topbar">
        <a href="../home.php" class="brand-section">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <span class="brand-name">UPI Manager</span>
        </a>
        <nav class="nav-links">
            <a href="addupi.php" class="nav-link">Manage UPIs</a>
            <a href="timer.php" class="nav-link">Rotation settings</a>
            <a href="suspended.php" class="nav-link active">Telegram Bot</a>
            <a href="../logout.php" class="nav-link btn-logout">Logout</a>
        </nav>
    </header>

    <!-- Main Container -->
    <div class="container">
        <div class="panel">
            <h2 class="panel-title">🤖 Telegram Bot Status Controls</h2>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <span style="font-weight: bold;">✓</span> <?= sanitize($success_msg) ?>
                </div>
            <?php endif; ?>

            <div class="status-card">
                <div class="status-header">Current Bot Status</div>
                <div class="status-badge">
                    <?php if ($bot_status === 'Suspended'): ?>
                        <span class="badge-suspended">🔴 Suspended by pahan</span>
                    <?php elseif ($bot_status === '1'): ?>
                        <span class="badge-active">🟢 Active (Running)</span>
                    <?php else: ?>
                        <span class="badge-disabled">⚫ Disabled (Offline)</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="action-list">
                <?php if ($bot_status === 'Suspended'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="unsuspend_bot">
                        <button type="submit" class="btn btn-unsuspend">🟢 Unsuspend Telegram Bot Now</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="suspend_bot">
                        <button type="submit" class="btn btn-suspend">🔴 Suspend Telegram Bot Now</button>
                    </form>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="disable_bot">
                    <button type="submit" class="btn btn-disable">⚫ Deactivate / Turn Off Bot</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
