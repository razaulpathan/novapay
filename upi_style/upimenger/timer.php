<?php
// upimenger/timer.php - Manage Rotation Settings
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Settings Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_settings') {
        $interval = isset($_POST['interval']) ? intval($_POST['interval']) : 2;
        $max_orders = isset($_POST['max_orders']) ? intval($_POST['max_orders']) : 35;
        
        if ($interval < 1) {
            $error_msg = "Rotation interval must be at least 1 minute.";
        } elseif ($max_orders < 1) {
            $error_msg = "Order limit must be at least 1 order.";
        } else {
            updateSetting('rotation_interval_minutes', $interval);
            updateSetting('max_orders_per_upi', $max_orders);
            $success_msg = "Settings updated successfully.";
        }
    } elseif ($_POST['action'] === 'force_rotate') {
        // Force rotation by resetting active UPI settings
        // get_next_rotating_upi will auto-pick and trigger next active UPI on next order
        updateSetting('current_active_upi_id', 0);
        updateSetting('current_upi_order_count', 0);
        $success_msg = "UPI Rotation forced. The next order will automatically pick the next UPI in sequence.";
    } elseif ($_POST['action'] === 'unsuspend_bot') {
        updateSetting('telegram_bot_status', '1');
        $success_msg = "Telegram Bot has been successfully unsuspended.";
    }
}

// Fetch current values
$interval_mins = getSetting('rotation_interval_minutes') ?: 2;
$max_orders = getSetting('max_orders_per_upi') ?: 35;
$current_id = getSetting('current_active_upi_id') ?: 0;
$bot_status = getSetting('telegram_bot_status') ?: '0';
$current_count = getSetting('current_upi_order_count') ?: 0;
$last_time = getSetting('last_rotation_time') ?: 0;

$current_upi_details = null;
if ($current_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM rotating_upis WHERE id = ?");
    $stmt->execute([$current_id]);
    $current_upi_details = $stmt->fetch();
}

$time_passed = time() - $last_time;
$time_remaining = ($interval_mins * 60) - $time_passed;
if ($time_remaining < 0) {
    $time_remaining = 0;
}
$time_remaining_str = floor($time_remaining / 60) . 'm ' . ($time_remaining % 60) . 's';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Manager - Rotation settings</title>
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
            <a href="timer.php" class="nav-link active">Rotation settings</a>
            <a href="suspended.php" class="nav-link">Telegram Bot</a>
            <a href="../logout.php" class="nav-link btn-logout">Logout</a>
        </nav>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Settings Form Panel -->
        <div class="panel">
            <h2 class="panel-title">Edit Rotation settings</h2>
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-error">
                    <span style="font-weight: bold;">✕</span> <?= sanitize($error_msg) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <span style="font-weight: bold;">✓</span> <?= sanitize($success_msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="timer.php">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-group">
                    <label class="form-label">Rotation Interval (Minutes)</label>
                    <input type="number" name="interval" class="form-control" min="1" step="1" value="<?= intval($interval_mins) ?>" required>
                    <p style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">Time interval to rotate the active UPI ID (Default: 2 minutes).</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Max Orders Limit Per UPI</label>
                    <input type="number" name="max_orders" class="form-control" min="1" step="1" value="<?= intval($max_orders) ?>" required>
                    <p style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">Maximum number of orders assigned to one UPI ID before it rotates (Default: 35 orders).</p>
                </div>

                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>

        <!-- Live Status Panel -->
        <div class="panel">
            <h2 class="panel-title">Current Rotation Status</h2>

            <div class="status-grid">
                <div class="status-item">
                    <span class="status-label">Active UPI ID:</span>
                    <span class="status-value value-green">
                        <?= $current_upi_details ? sanitize($current_upi_details['upi_id']) : 'None Active / Default settings' ?>
                    </span>
                </div>

                <div class="status-item">
                    <span class="status-label">Active Account Name:</span>
                    <span class="status-value">
                        <?= $current_upi_details ? sanitize($current_upi_details['name']) : 'Default Admin' ?>
                    </span>
                </div>

                <div class="status-item">
                    <span class="status-label">Orders on current UPI:</span>
                    <span class="status-value value-blue">
                        <?= intval($current_count) ?> / <?= intval($max_orders) ?> Orders
                    </span>
                </div>

                <div class="status-item">
                    <span class="status-label">Time Remaining for Rotation:</span>
                    <span class="status-value">
                        <?= $current_id > 0 ? $time_remaining_str : 'N/A' ?>
                    </span>
                </div>
            </div>

            <form method="POST" action="timer.php" style="margin-top: 24px;">
                <input type="hidden" name="action" value="force_rotate">
                <button type="submit" class="btn btn-orange">Force Rotate UPI ID Now</button>
            </form>
        </div>

        <!-- Telegram Bot Suspension status panel -->
        <?php if ($bot_status === 'Suspended'): ?>
            <div class="panel" style="grid-column: 1 / -1; border-color: var(--accent-red); background: rgba(239, 68, 68, 0.05); margin-top: 16px;">
                <h2 class="panel-title" style="color: var(--accent-red); border-bottom-color: rgba(239, 68, 68, 0.2);">Telegram Bot Status</h2>
                <div style="display: flex; flex-direction: column; gap: 16px; align-items: flex-start;">
                    <p style="color: var(--text-secondary); font-size: 14px; font-weight: 500; line-height: 1.5;">
                        The Telegram Bot has been <strong style="color: var(--accent-red);">Suspended by pahan</strong>. System notifications are currently offline.
                    </p>
                    <form method="POST" action="timer.php" style="width: 100%; margin: 0;">
                        <input type="hidden" name="action" value="unsuspend_bot">
                        <button type="submit" class="btn" style="background-color: var(--accent-red); color: white; transition: background 0.2s;">Unsuspend Telegram Bot Now</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
