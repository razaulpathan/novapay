<?php
// upimenger/addupi.php - UPI Manager Dashboard
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle add UPI submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_upi') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $upi_id = isset($_POST['upi_id']) ? trim($_POST['upi_id']) : '';
    $status = isset($_POST['status']) && $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';

    if (empty($name) || empty($upi_id)) {
        $error_msg = "Please enter both account name and UPI ID.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO rotating_upis (name, upi_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$name, $upi_id, $status]);
            $success_msg = "UPI ID successfully added to rotation list.";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error_msg = "This UPI ID is already registered in the system.";
            } else {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all configured rotating UPIs
$stmt = $pdo->query("SELECT * FROM rotating_upis ORDER BY id DESC");
$upis = $stmt->fetchAll();

// Get settings for display
$current_active_id = getSetting('current_active_upi_id') ?: 0;
$max_orders = getSetting('max_orders_per_upi') ?: 35;
$interval_mins = getSetting('rotation_interval_minutes') ?: 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Manager - Rotating UPI IDs</title>
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
            <a href="addupi.php" class="nav-link active">Manage UPIs</a>
            <a href="timer.php" class="nav-link">Rotation settings</a>
            <a href="suspended.php" class="nav-link">Telegram Bot</a>
            <a href="../logout.php" class="nav-link btn-logout">Logout</a>
        </nav>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Add UPI Panel -->
        <div class="panel">
            <h2 class="panel-title">Add new UPI ID</h2>
            
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

            <form method="POST" action="addupi.php">
                <input type="hidden" name="action" value="add_upi">
                
                <div class="form-group">
                    <label class="form-label">Holder Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. PRATHAMJOHRI" required>
                </div>

                <div class="form-group">
                    <label class="form-label">UPI ID</label>
                    <input type="text" name="upi_id" class="form-control" placeholder="e.g. name@paytm" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" style="background-color: var(--input-bg); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Add UPI</button>
            </form>
        </div>

        <!-- UPI List Panel -->
        <div class="panel">
            <h2 class="panel-title">
                Rotating UPI List
                <span style="font-size: 13px; font-weight: normal; color: var(--text-secondary);">
                    Limit: <?= $max_orders ?> Orders / Rotation: <?= $interval_mins ?> Min
                </span>
            </h2>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Active</th>
                            <th>Holder Name</th>
                            <th>UPI ID</th>
                            <th>Orders Processed</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upis)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 32px 0;">No rotating UPI accounts registered.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($upis as $u): ?>
                                <tr>
                                    <td>
                                        <?php if ($u['id'] == $current_active_id): ?>
                                            <span class="badge badge-indicator">ACTIVE NOW</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700;"><?= sanitize($u['name']) ?></td>
                                    <td style="font-family: monospace;"><?= sanitize($u['upi_id']) ?></td>
                                    <td><?= intval($u['order_count']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($u['status']) ?>"><?= $u['status'] ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="deleteupi.php?id=<?= $u['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this UPI ID?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
