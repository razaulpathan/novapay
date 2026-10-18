<?php
// admin/transfer_user.php - Admin User Referrer Shift Panel
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_input = isset($_POST['target_user']) ? trim($_POST['target_user']) : '';
    $new_ref_input = isset($_POST['new_referrer']) ? trim($_POST['new_referrer']) : '';

    if (empty($target_input)) {
        $error_msg = "Please enter the target User Phone or ID to transfer.";
    } else {
        try {
            // Find target user by ID or Phone
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? OR phone = ?");
            $stmt->execute([$target_input, $target_input]);
            $target_user = $stmt->fetch();

            if (!$target_user) {
                $error_msg = "Target user not found.";
            } else {
                $target_id = $target_user['id'];
                $new_ref_id = null;

                if (!empty($new_ref_input)) {
                    // Find new referrer by ID or Phone
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? OR phone = ?");
                    $stmt->execute([$new_ref_input, $new_ref_input]);
                    $new_referrer = $stmt->fetch();

                    if (!$new_referrer) {
                        $error_msg = "New referrer user not found.";
                    } else {
                        $new_ref_id = $new_referrer['id'];
                    }
                }

                // If no error, continue checks and update
                if (empty($error_msg)) {
                    if ($target_id === $new_ref_id) {
                        $error_msg = "A user cannot be referred by themselves.";
                    } else {
                        // Circular dependency loop check
                        $is_circular = false;
                        $check_id = $new_ref_id;
                        while ($check_id !== null) {
                            if ($check_id == $target_id) {
                                $is_circular = true;
                                break;
                            }
                            // Get parent referrer of check_id
                            $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                            $stmt->execute([$check_id]);
                            $check_id = $stmt->fetchColumn() ?: null;
                        }

                        if ($is_circular) {
                            $error_msg = "Circular dependency detected! The new referrer is a downline subordinate of the target user.";
                        } else {
                            // Update referrer
                            $stmtUpdate = $pdo->prepare("UPDATE users SET referred_by = ? WHERE id = ?");
                            $stmtUpdate->execute([$new_ref_id, $target_id]);

                            // Log action
                            $target_label = sanitize($target_user['phone']) . " (ID: " . $target_id . ")";
                            $referrer_label = $new_ref_id ? "User ID " . $new_ref_id : "None (Root level)";
                            $success_msg = "Successfully transferred {$target_label} to referrer: {$referrer_label}.";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Transfer Referrer</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Transfer Referrer / Shift Downline</h1>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body" style="max-width: 800px;">
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

            <div class="panel">
                <div class="panel-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
                    <h2 style="font-size: 16px; font-weight: bold; color: #ffffff;">🔄 Shift User Referral Tree Link</h2>
                </div>

                <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="target_user" style="font-weight: bold;">1. Target User to Transfer</label>
                        <input type="text" name="target_user" id="target_user" class="form-control" 
                               placeholder="Enter target user's Phone Number or User ID..." required
                               value="<?= isset($_POST['target_user']) ? sanitize($_POST['target_user']) : '' ?>">
                        <span class="form-text">This is the user whose parent referrer you want to change.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_referrer" style="font-weight: bold;">2. New Referrer (New Parent)</label>
                        <input type="text" name="new_referrer" id="new_referrer" class="form-control" 
                               placeholder="Enter new referrer's Phone Number or User ID (Leave blank for NONE)..."
                               value="<?= isset($_POST['new_referrer']) ? sanitize($_POST['new_referrer']) : '' ?>">
                        <span class="form-text">This will become the new direct referrer (upline parent) of the target user.</span>
                    </div>

                    <div style="margin-top: 10px; display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Confirm &amp; Transfer Referrer</button>
                        <a href="users.php" class="btn btn-secondary" style="padding: 12px 24px; text-decoration: none; text-align: center;">Back to Users</a>
                    </div>
                </form>
            </div>

            <!-- Explanation / Notes Panel -->
            <div class="panel" style="margin-top: 24px; background: rgba(35, 149, 238, 0.05); border: 1px dashed rgba(35, 149, 238, 0.25);">
                <h3 style="font-size: 14px; font-weight: bold; color: #2395ee; margin-bottom: 8px;">💡 How Referrer Transfer Works:</h3>
                <ul style="color: #a9b7d0; font-size: 13px; padding-left: 20px; line-height: 1.6;">
                    <li>You can enter either a 10-digit <strong>phone number</strong> or a numeric <strong>User ID</strong>.</li>
                    <li>If the "New Referrer" is left blank, the user will be shifted to the root level (no direct parent).</li>
                    <li>The system automatically detects and blocks circular referrers to keep the database tree structure valid.</li>
                    <li>All downstream subordinates (downline team members) of the target user will move along with them as a block.</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
