<?php
// admin/users.php - Admin User Management
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}
// Handle GET stats AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $usr = $stmt->fetch();
    
    if (!$usr) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    
    // 1. Buy Stats
    $stmtBuyAll = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(price), 0) as vol FROM buy_orders WHERE user_id = ? AND status = 'Success'");
    $stmtBuyAll->execute([$user_id]);
    $buyAll = $stmtBuyAll->fetch();
    
    $stmtBuyToday = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(price), 0) as vol FROM buy_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) = CURRENT_DATE()");
    $stmtBuyToday->execute([$user_id]);
    $buyToday = $stmtBuyToday->fetch();
    
    // 2. Sell Stats
    $stmtSellAll = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as vol FROM sell_orders WHERE user_id = ? AND status = 'Success'");
    $stmtSellAll->execute([$user_id]);
    $sellAll = $stmtSellAll->fetch();
    
    $stmtSellToday = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as vol FROM sell_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) = CURRENT_DATE()");
    $stmtSellToday->execute([$user_id]);
    $sellToday = $stmtSellToday->fetch();
    
    // 3. Linked UPI Accounts
    $stmtUPI = $pdo->prepare("SELECT * FROM upi_accounts WHERE user_id = ? ORDER BY created_at DESC");
    $stmtUPI->execute([$user_id]);
    $upis = $stmtUPI->fetchAll();

    // 4. Last 10 Transactions
    $stmtTx = $pdo->prepare("
        (SELECT 'Buy' as type, id, price as amount, status, created_at FROM buy_orders WHERE user_id = ?)
        UNION ALL
        (SELECT 'Sell' as type, id, amount, status, created_at FROM sell_orders WHERE user_id = ?)
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmtTx->execute([$user_id, $user_id]);
    $txs = $stmtTx->fetchAll();

    // 5. Downline Tree (Referrals) Level 1
    $stmtL1 = $pdo->prepare("SELECT id, phone, role, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC");
    $stmtL1->execute([$user_id]);
    $l1_referrals = $stmtL1->fetchAll();

    // Downline Tree (Referrals) Level 2
    $l2_referrals = [];
    if (!empty($l1_referrals)) {
        $l1_ids = array_column($l1_referrals, 'id');
        $in_clause = implode(',', array_fill(0, count($l1_ids), '?'));
        $stmtL2 = $pdo->prepare("SELECT id, phone, role, referred_by, created_at FROM users WHERE referred_by IN ($in_clause) ORDER BY created_at DESC");
        $stmtL2->execute($l1_ids);
        $l2_referrals = $stmtL2->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $usr['id'],
            'phone' => $usr['phone'],
            'password_hash' => $usr['password'],
            'itoken_balance' => floatval($usr['itoken_balance']),
            'role' => $usr['role'],
            'status' => isset($usr['status']) ? $usr['status'] : 'Active'
        ],
        'buy' => [
            'total_count' => intval($buyAll['cnt']),
            'total_volume' => floatval($buyAll['vol']),
            'today_count' => intval($buyToday['cnt']),
            'today_volume' => floatval($buyToday['vol'])
        ],
        'sell' => [
            'total_count' => intval($sellAll['cnt']),
            'total_volume' => floatval($sellAll['vol']),
            'today_count' => intval($sellToday['cnt']),
            'today_volume' => floatval($sellToday['vol'])
        ],
        'upis' => $upis,
        'transactions' => $txs,
        'referral_tree' => [
            'level1' => $l1_referrals,
            'level2' => $l2_referrals
        ]
    ]);
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_agent') {
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $allow_agent = isset($_POST['allow_create_sub_agent']) ? 1 : 0;
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';

        if (empty($phone) || empty($password)) {
            $error_msg = "Please enter both phone and password.";
        } else {
            try {
                // Check if user already exists
                $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
                $chk->execute([$phone]);
                if ($chk->fetchColumn() > 0) {
                    $error_msg = "User with phone number {$phone} already exists.";
                } else {
                    $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                    $ref_code = 'REF' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (phone, password, referral_code, status, allow_create_sub_agent, role) VALUES (?, ?, ?, 'Active', ?, ?)");
                    $stmt->execute([$phone, $pass_hash, $ref_code, $allow_agent, $role]);
                    $success_msg = "Promoter account for {$phone} (Role: {$role}) successfully created.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to create promoter: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_link_upi') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $partner = isset($_POST['partner']) ? trim($_POST['partner']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $upi_id = isset($_POST['upi_id']) ? trim($_POST['upi_id']) : '';

        if ($user_id <= 0 || empty($partner) || empty($name) || empty($upi_id)) {
            $error_msg = "Please fill out all fields to link UPI.";
        } elseif (!preg_match('/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9]+$/', $upi_id)) {
            $error_msg = "Please enter a valid UPI ID (e.g. username@upi).";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO upi_accounts (user_id, partner, name, upi_no, status, stopped) VALUES (?, ?, ?, ?, 'Active', 0)");
                $stmt->execute([$user_id, $partner, $name, $upi_id]);
                $success_msg = "Successfully linked UPI ID '{$upi_id}' for user #{$user_id}.";
            } catch (PDOException $e) {
                $error_msg = "Failed to link UPI account: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_unlink_upi') {
        $upi_acc_id = isset($_POST['upi_acc_id']) ? intval($_POST['upi_acc_id']) : 0;
        if ($upi_acc_id <= 0) {
            $error_msg = "Invalid UPI account ID.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE upi_accounts SET status = 'UnLink' WHERE id = ?");
                $stmt->execute([$upi_acc_id]);
                $success_msg = "Successfully unlinked UPI account.";
            } catch (PDOException $e) {
                $error_msg = "Failed to delete UPI account: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_toggle_upi_selling') {
        $upi_acc_id = isset($_POST['upi_acc_id']) ? intval($_POST['upi_acc_id']) : 0;
        if ($upi_acc_id <= 0) {
            $error_msg = "Invalid UPI account ID.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE upi_accounts SET stopped = NOT stopped WHERE id = ?");
                $stmt->execute([$upi_acc_id]);
                $success_msg = "Successfully updated UPI selling status.";
            } catch (PDOException $e) {
                $error_msg = "Failed to update UPI status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_edit_upi') {
        $upi_acc_id = isset($_POST['upi_acc_id']) ? intval($_POST['upi_acc_id']) : 0;
        $new_upi_id = isset($_POST['new_upi_id']) ? trim($_POST['new_upi_id']) : '';

        if ($upi_acc_id <= 0 || empty($new_upi_id)) {
            $error_msg = "Please enter a valid UPI ID.";
        } elseif (!preg_match('/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9]+$/', $new_upi_id)) {
            $error_msg = "Invalid UPI ID handle format.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE upi_accounts SET upi_no = ? WHERE id = ?");
                $stmt->execute([$new_upi_id, $upi_acc_id]);
                $success_msg = "Successfully updated/relinked UPI ID to '{$new_upi_id}'.";
            } catch (PDOException $e) {
                $error_msg = "Failed to update UPI account: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_change_password') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

        if ($user_id <= 0 || empty($new_password)) {
            $error_msg = "Please provide all password details.";
        } else {
            try {
                $pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$pass_hash, $user_id]);
                $success_msg = "Password successfully changed for user #{$user_id}.";
            } catch (PDOException $e) {
                $error_msg = "Failed to update password: " . $e->getMessage();
            }
        }
    } elseif ($action === 'admin_transfer_tokens') {
        $from_user_id = isset($_POST['from_user_id']) ? intval($_POST['from_user_id']) : 0;
        $to_user_id = isset($_POST['to_user_id']) ? intval($_POST['to_user_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;

        if ($from_user_id <= 0 || $to_user_id <= 0 || $amount <= 0) {
            $error_msg = "Please fill out all fields with valid values.";
        } elseif ($from_user_id === $to_user_id) {
            $error_msg = "Source and destination user cannot be the same.";
        } else {
            try {
                // Verify source user exists and has enough balance
                $stmt = $pdo->prepare("SELECT itoken_balance FROM users WHERE id = ?");
                $stmt->execute([$from_user_id]);
                $from_balance = $stmt->fetchColumn();

                if ($from_balance === false) {
                    $error_msg = "Source user #{$from_user_id} not found.";
                } elseif ($from_balance < $amount) {
                    $error_msg = "Source user #{$from_user_id} has insufficient balance (Current: ₹" . number_format($from_balance, 2) . ").";
                } else {
                    // Verify destination user exists
                    $stmtTo = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
                    $stmtTo->execute([$to_user_id]);
                    if ($stmtTo->fetchColumn() == 0) {
                        $error_msg = "Destination user #{$to_user_id} not found.";
                    } else {
                        // Perform transfer inside a transaction
                        $pdo->beginTransaction();

                        $stmtSub = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance - ? WHERE id = ?");
                        $stmtSub->execute([$amount, $from_user_id]);

                        $stmtAdd = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ? WHERE id = ?");
                        $stmtAdd->execute([$amount, $to_user_id]);

                        // Log in token_transfers
                        $stmtLog = $pdo->prepare("INSERT INTO token_transfers (agent_id, target_phone, target_uid, amount, status) VALUES (?, (SELECT phone FROM users WHERE id = ?), ?, ?, 'Success')");
                        $stmtLog->execute([$from_user_id, $to_user_id, $to_user_id, $amount]);

                        $pdo->commit();
                        $success_msg = "Successfully transferred ₹" . number_format($amount, 2) . " from user #{$from_user_id} to user #{$to_user_id}.";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "Transfer failed: " . $e->getMessage();
            }
        }
    } else {
        $target_uid = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($target_uid <= 0) {
            $error_msg = "Invalid target User ID.";
        } else {
            try {
                // Fetch target user details
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$target_uid]);
                $target_user = $stmt->fetch();

                if (!$target_user) {
                    $error_msg = "User not found.";
                } else {
                    if ($action === 'toggle_status') {
                        $new_status = (isset($target_user['status']) && $target_user['status'] === 'Blocked') ? 'Active' : 'Blocked';
                        $updateStmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $updateStmt->execute([$new_status, $target_uid]);
                        $success_msg = "User #{$target_uid} status successfully updated to {$new_status}.";
                    } elseif ($action === 'adjust_balance') {
                        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;
                        $updateStmt = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ? WHERE id = ?");
                        $updateStmt->execute([$amount, $target_uid]);
                        $success_msg = "Successfully adjusted balance of user #{$target_uid} by " . ($amount >= 0 ? "+" : "") . number_format($amount, 2) . " ITokens.";
                    } elseif ($action === 'adjust_reward') {
                        $rate = isset($_POST['reward_rate']) ? floatval($_POST['reward_rate']) : 2.50;
                        if ($rate < 0 || $rate > 100) {
                            $error_msg = "Reward rate must be between 0 and 100%.";
                        } else {
                            $updateStmt = $pdo->prepare("UPDATE users SET reward_rate = ? WHERE id = ?");
                            $updateStmt->execute([$rate, $target_uid]);
                            $success_msg = "Successfully updated reward rate of user #{$target_uid} to " . number_format($rate, 2) . "%.";
                        }
                    } elseif ($action === 'toggle_sub_agent_permission') {
                        $new_permission = (isset($target_user['allow_create_sub_agent']) && $target_user['allow_create_sub_agent'] == 1) ? 0 : 1;
                        $updateStmt = $pdo->prepare("UPDATE users SET allow_create_sub_agent = ? WHERE id = ?");
                        $updateStmt->execute([$new_permission, $target_uid]);
                        $success_msg = "Successfully updated sub-agent creation permission for user #{$target_uid} to " . ($new_permission == 1 ? "Allowed" : "Disallowed") . ".";
                    } elseif ($action === 'adjust_role') {
                        $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';
                        $updateStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $updateStmt->execute([$role, $target_uid]);
                        $success_msg = "Successfully updated role of user #{$target_uid} to {$role}.";
                    }
                }
            } catch (PDOException $e) {
                $error_msg = "Database operation failed: " . $e->getMessage();
            }
        }
    }
}

// Handle User Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if ($search !== '') {
    $where_clause = "WHERE phone LIKE ? OR id = ?";
    $params[] = "%$search%";
    $params[] = intval($search);
}

// Fetch all users
$query = "SELECT * FROM users $where_clause ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Users</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">User Management</h1>
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

            <!-- Create New Agent Form Panel -->
            <div class="panel" style="margin-bottom: 24px; border: 1px solid var(--border-color); background: var(--card-bg); border-radius: 8px;">
                <div class="panel-header" style="border-bottom: 1px solid var(--border-color); padding: 16px 20px;">
                    <h2 class="panel-title" style="font-size: 15px; font-weight: bold; color: #ffffff;">Create New Promoter/Agent</h2>
                </div>
                <div style="padding: 20px;">
                    <form method="POST" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                        <input type="hidden" name="action" value="create_agent">
                        
                        <div style="flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 600; color: #a9b7d0;">Phone Number</label>
                            <input type="tel" name="phone" placeholder="Promoter Phone Number" required style="width: 100%; padding: 10px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 13px;">
                        </div>

                        <div style="flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 600; color: #a9b7d0;">Password</label>
                            <input type="password" name="password" placeholder="Promoter Password" required style="width: 100%; padding: 10px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 13px;">
                        </div>

                        <div style="flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 6px;">
                            <label style="font-size: 12px; font-weight: 600; color: #a9b7d0;">Assign Role</label>
                            <select name="role" required style="width: 100%; padding: 10px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 13px; outline: 0;">
                                <option value="user">User / Agent</option>
                                <option value="masteragent">Master Agent</option>
                                <option value="sub_admin">Sub Admin</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; height: 38px;">
                            <input type="checkbox" name="allow_create_sub_agent" id="allow_create_sub_agent" value="1" style="width: 18px; height: 18px; cursor: pointer;">
                            <label for="allow_create_sub_agent" style="font-size: 13px; color: #ffffff; cursor: pointer; user-select: none;">Allow Sub-Agent Creation</label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 24px; font-weight: bold; border-radius: 6px;">Create Agent</button>
                    </form>
                </div>
            </div>

            <!-- Panel table -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Registered System Users</h2>
                    <form method="GET" class="table-filters" action="users.php">
                        <div class="search-input-wrapper">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" class="search-input" placeholder="Search ID or Phone number..." value="<?= sanitize($search) ?>">
                        </div>
                        <?php if ($search !== ''): ?>
                            <a href="users.php" class="btn btn-secondary" style="padding: 10px 16px;">Clear</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Phone Number</th>
                                <th>Balance (IToken)</th>
                                <th>Reward Rate</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Sub-Agent Create</th>
                                <th>Joined Date</th>
                                <th style="text-align: right; width: 560px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 32px 0;">No matching users found in the system.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): 
                                    $user_status = isset($u['status']) ? $u['status'] : 'Active';
                                ?>
                                    <tr>
                                        <td>#<?= $u['id'] ?></td>
                                        <td style="font-weight: bold;"><?= sanitize($u['phone']) ?></td>
                                        <td style="font-weight: 700; color: var(--success-color);">₹<?= number_format($u['itoken_balance'], 2) ?></td>
                                        <td><?= number_format($u['reward_rate'], 2) ?>%</td>
                                        <td>
                                            <span class="badge badge-role-<?= $u['role'] ?>"><?= $u['role'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $user_status === 'Blocked' ? 'cancelled' : 'success' ?>"><?= $user_status ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= (isset($u['allow_create_sub_agent']) && $u['allow_create_sub_agent'] == 1) ? 'success' : 'cancelled' ?>">
                                                <?= (isset($u['allow_create_sub_agent']) && $u['allow_create_sub_agent'] == 1) ? 'Allowed' : 'Disallowed' ?>
                                            </span>
                                        </td>
                                        <td style="font-family: Arial, sans-serif; font-size: 13px; color: var(--text-secondary);"><?= $u['created_at'] ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                <!-- Adjust Balance Form -->
                                                <form method="POST" style="margin: 0; display: flex; gap: 4px; align-items: center;">
                                                    <input type="hidden" name="action" value="adjust_balance">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <input type="number" step="0.01" name="amount" placeholder="+/- Amount" style="width: 84px; padding: 6px; font-size: 11px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 4px;" required>
                                                    <button type="submit" class="btn btn-primary" style="padding: 6px 8px; font-size: 11px; border-radius: 4px;">Adjust</button>
                                                </form>
 
                                                <!-- Adjust Reward Form -->
                                                <form method="POST" style="margin: 0; display: flex; gap: 4px; align-items: center;">
                                                    <input type="hidden" name="action" value="adjust_reward">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <input type="number" step="0.1" name="reward_rate" placeholder="Rate %" style="width: 64px; padding: 6px; font-size: 11px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 4px;" required>
                                                    <button type="submit" class="btn btn-secondary" style="padding: 6px 8px; font-size: 11px; border-radius: 4px;">Rate</button>
                                                </form>

                                                <!-- Adjust Role Form -->
                                                <form method="POST" style="margin: 0; display: flex; gap: 4px; align-items: center;">
                                                    <input type="hidden" name="action" value="adjust_role">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <select name="role" style="width: 84px; padding: 6px; font-size: 11px; background: #161d31; color: white; border: 1px solid var(--border-color); border-radius: 4px; outline: 0;">
                                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                        <option value="masteragent" <?= $u['role'] === 'masteragent' ? 'selected' : '' ?>>Master Agent</option>
                                                        <option value="sub_admin" <?= $u['role'] === 'sub_admin' ? 'selected' : '' ?>>Sub Admin</option>
                                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-secondary" style="padding: 6px 8px; font-size: 11px; border-radius: 4px; background: #fa8c16; color: white; border: 0;">Role</button>
                                                </form>

                                                <!-- Toggle Sub-Agent Permission Form -->
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="toggle_sub_agent_permission">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary" style="padding: 6px 8px; font-size: 11px; border-radius: 4px; background: var(--primary-color);">Toggle Sub</button>
                                                </form>
 
                                                <!-- Block / Unblock Form -->
                                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Change status for User #<?= $u['id'] ?>?')">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn <?= $user_status === 'Blocked' ? 'btn-success-light' : 'btn-danger-light' ?>" style="padding: 6px 12px; font-size: 11px; border-radius: 4px;">
                                                        <?= $user_status === 'Blocked' ? 'Unblock' : 'Block' ?>
                                                    </button>
                                                </form>

                                                <!-- View Stats Button -->
                                                <button type="button" class="btn btn-primary" onclick="showUserStats(<?= $u['id'] ?>)" style="padding: 6px 12px; font-size: 11px; border-radius: 4px; background: #2395ee; border: 0; color: white;">Stats</button>
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
    <!-- User Stats Modal -->
    <div id="statsModal" class="admin-modal-overlay" style="display: none;">
        <div class="admin-modal-card">
            <div class="admin-modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 18px; color: #ffffff;">User Report &amp; Control Hub</h3>
                <span onclick="closeStatsModal()" style="cursor: pointer; font-size: 24px; color: var(--text-secondary);">&times;</span>
            </div>
            <div class="admin-modal-body" id="statsModalBody" style="color: #ffffff; text-align: left; max-height: 80vh; overflow-y: auto;">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>
 
    <style>
        .admin-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .admin-modal-card {
            background: #1e293b;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 100%;
            max-width: 700px;
            padding: 24px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.6);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .stats-box {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .stats-box-title {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .stats-box-value {
            font-size: 20px;
            font-weight: 700;
        }
        .stats-sub-text {
            font-size: 12px;
            color: #475569;
            margin-top: 4px;
        }
        .upi-badge-row {
            background: #0f172a;
            border: 1px solid #334155;
            padding: 10px 14px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
    </style>
 
    <script>
        function showUserStats(userId) {
            const body = document.getElementById('statsModalBody');
            body.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Loading stats...</p>';
            document.getElementById('statsModal').style.display = 'flex';
 
            fetch('users.php?action=get_stats&user_id=' + userId)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        body.innerHTML = `<p style="color: #ef4444;">${data.message}</p>`;
                        return;
                    }
 
                    // 1. Render UPIs List
                    let upiHtml = '';
                    if (data.upis.length === 0) {
                        upiHtml = '<p style="font-style: italic; color: #64748b; font-size: 13px;">No linked UPI accounts found.</p>';
                    } else {
                        data.upis.forEach(upi => {
                            const isStoppedBadge = upi.stopped == 1 
                                ? '<span style="background: #ef4444; color: #ffffff; padding: 2px 6px; font-size: 10px; border-radius: 4px; font-weight: bold;">STOPPED</span>' 
                                : '<span style="background: #10b981; color: #ffffff; padding: 2px 6px; font-size: 10px; border-radius: 4px; font-weight: bold;">SELLING</span>';
                            
                            upiHtml += `
                                <div class="upi-badge-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <span style="font-weight: bold; color: #60a5fa; font-family: monospace; font-size: 14px;">${upi.upi_id || upi.upi_no}</span>
                                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                                Partner: ${upi.partner} | Status: ${upi.status}
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <span style="font-size: 11px; color: #94a3b8; font-weight: bold;">${upi.tab_type || 'Sell'}</span>
                                            ${isStoppedBadge}
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; border-top: 1px dashed #334155; padding-top: 8px; margin-top: 4px;">
                                        <button class="btn" onclick="adminEditUpi(${upi.id}, '${upi.upi_no}')" style="padding: 4px 8px; font-size: 10px; border-radius: 4px; background: #3b82f6; color: white; border: 0; cursor: pointer;">Edit ID</button>
                                        <button class="btn" onclick="adminToggleUpiSelling(${upi.id})" style="padding: 4px 8px; font-size: 10px; border-radius: 4px; background: #d97706; color: white; border: 0; cursor: pointer;">
                                            ${upi.stopped == 1 ? 'Activate' : 'Stop'}
                                        </button>
                                        <button class="btn" onclick="adminUnlinkUpi(${upi.id})" style="padding: 4px 8px; font-size: 10px; border-radius: 4px; background: #ef4444; color: white; border: 0; cursor: pointer;">Unlink</button>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    // 2. Render Last 10 Transactions
                    let txHtml = '';
                    if (data.transactions.length === 0) {
                        txHtml = '<p style="font-style: italic; color: #64748b; font-size: 13px;">No recent transactions found.</p>';
                    } else {
                        txHtml = `
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #334155; color: #94a3b8;">
                                        <th style="padding: 6px 4px;">Date</th>
                                        <th style="padding: 6px 4px;">Type</th>
                                        <th style="padding: 6px 4px;">Amount</th>
                                        <th style="padding: 6px 4px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        data.transactions.forEach(tx => {
                            const typeColor = tx.type === 'Buy' ? '#10b981' : '#f59e0b';
                            const statusColor = tx.status === 'Success' ? '#10b981' : (tx.status === 'Cancelled' ? '#ef4444' : '#94a3b8');
                            txHtml += `
                                <tr style="border-bottom: 1px solid #1e293b;">
                                    <td style="padding: 6px 4px; color: #94a3b8;">${tx.created_at}</td>
                                    <td style="padding: 6px 4px; font-weight: bold; color: ${typeColor};">${tx.type}</td>
                                    <td style="padding: 6px 4px; font-weight: bold;">₹${parseFloat(tx.amount).toFixed(2)}</td>
                                    <td style="padding: 6px 4px;"><span style="color: ${statusColor}; font-weight: bold;">${tx.status}</span></td>
                                </tr>
                            `;
                        });
                        txHtml += '</tbody></table>';
                    }

                    // 3. Render Downline tree (Referral tree Level 1 & 2)
                    let treeHtml = '';
                    if (data.referral_tree.level1.length === 0) {
                        treeHtml = '<p style="font-style: italic; color: #64748b; font-size: 13px;">No downline referrals found (Level 1 is empty).</p>';
                    } else {
                        treeHtml += '<div style="font-size: 12px; display: flex; flex-direction: column; gap: 8px;">';
                        data.referral_tree.level1.forEach(l1 => {
                            const subReferrals = data.referral_tree.level2.filter(l2 => l2.referred_by == l1.id);
                            let subTreeHtml = '';
                            if (subReferrals.length > 0) {
                                subTreeHtml += `<div style="margin-left: 20px; padding-left: 10px; border-left: 1px dashed #475569; display: flex; flex-direction: column; gap: 4px; margin-top: 4px;">`;
                                subReferrals.forEach(l2 => {
                                    subTreeHtml += `
                                        <div style="color: #94a3b8; font-size: 11px;">
                                            └── Level 2: #${l2.id} - ${l2.phone} (${l2.role.toUpperCase()}) - Joined: ${l2.created_at}
                                        </div>
                                    `;
                                });
                                subTreeHtml += `</div>`;
                            }
                            treeHtml += `
                                <div style="background: #0f172a; padding: 8px; border-radius: 6px; border: 1px solid #334155;">
                                    <div style="font-weight: bold; color: #3b82f6;">
                                        👤 Level 1: #${l1.id} - ${l1.phone} (${l1.role.toUpperCase()}) - Joined: ${l1.created_at}
                                    </div>
                                    ${subTreeHtml}
                                </div>
                            `;
                        });
                        treeHtml += '</div>';
                    }
 
                    body.innerHTML = `
                        <!-- User Basic Info with Password Change -->
                        <div style="background: #0f172a; padding: 16px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: bold;">User Profile Details:</span>
                                <span style="font-size: 16px; font-weight: bold; color: #60a5fa; display: block; margin-top: 4px;">Phone: ${data.user.phone} (ID: #${data.user.id})</span>
                                <span style="font-size: 12px; color: #94a3b8; display: block; margin-top: 4px; word-break: break-all; font-family: monospace;">Password Hash: ${data.user.password_hash}</span>
                                <span style="font-size: 12px; color: #64748b; display: block; margin-top: 2px;">Role: ${data.user.role.toUpperCase()} | Balance: ₹${data.user.itoken_balance.toFixed(2)} | Status: ${data.user.status}</span>
                            </div>
                            
                            <!-- Change Password Form -->
                            <div style="border-top: 1px solid #334155; padding-top: 12px; margin-top: 4px;">
                                <h5 style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; color: #fa8c16; font-weight: bold;">Modify User Password</h5>
                                <form method="POST" action="users.php" style="display: flex; gap: 8px; margin: 0;">
                                    <input type="hidden" name="action" value="admin_change_password">
                                    <input type="hidden" name="user_id" value="${data.user.id}">
                                    <input type="text" name="new_password" placeholder="Enter new password" required style="flex: 1; padding: 6px 8px; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                    <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; background: #fa8c16; color: white; border: 0; font-weight: bold; cursor: pointer;">Change Password</button>
                                </form>
                            </div>
                        </div>

                        <!-- Token Transfer Form (Any to Any user) -->
                        <div style="background: #1e293b; padding: 14px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #3b82f6; font-weight: bold;">Admin Token Transfer</h4>
                            <p style="font-size: 11px; color: #94a3b8; margin: 0 0 12px;">Transfer tokens from user #${data.user.id} (${data.user.phone}) to any other target User ID.</p>
                            <form method="POST" action="users.php" style="display: flex; gap: 8px; flex-wrap: wrap; margin: 0;" onsubmit="return confirm('Confirm token transfer from user #${data.user.id}?')">
                                <input type="hidden" name="action" value="admin_transfer_tokens">
                                <input type="hidden" name="from_user_id" value="${data.user.id}">
                                <div style="flex: 2; min-width: 150px;">
                                    <input type="number" name="to_user_id" placeholder="Destination User ID" required style="width: 100%; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                </div>
                                <div style="flex: 1; min-width: 100px;">
                                    <input type="number" step="0.01" name="amount" placeholder="Amount (₹)" required style="width: 100%; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                </div>
                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: auto;">Transfer Tokens</button>
                            </form>
                        </div>

                        <!-- Link New UPI ID Box -->
                        <div style="background: #1e293b; padding: 14px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #60a5fa; font-weight: bold;">Link New UPI ID</h4>
                            <form method="POST" action="users.php" style="display: flex; gap: 8px; flex-wrap: wrap; margin: 0;">
                                <input type="hidden" name="action" value="admin_link_upi">
                                <input type="hidden" name="user_id" value="${data.user.id}">
                                <div style="flex: 1; min-width: 120px;">
                                    <input type="text" name="name" placeholder="Holder Name" required style="width: 100%; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                </div>
                                <div style="flex: 1; min-width: 120px;">
                                    <select name="partner" required style="width: 100%; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                        <option value="PhonePe">PhonePe</option>
                                        <option value="Paytm">Paytm</option>
                                        <option value="Google Pay">Google Pay</option>
                                        <option value="BHIM">BHIM</option>
                                        <option value="Amazon Pay">Amazon Pay</option>
                                        <option value="Freecharge">Freecharge</option>
                                    </select>
                                </div>
                                <div style="flex: 2; min-width: 180px;">
                                    <input type="text" name="upi_id" placeholder="UPI ID (e.g. name@okaxis)" required style="width: 100%; padding: 6px 8px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 4px; font-size: 12px; outline: 0;">
                                </div>
                                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; border-radius: 4px; height: auto;">Link</button>
                            </form>
                        </div>
 
                        <!-- Trading Volume & Success Rates -->
                        <h4 style="margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; font-weight: bold;">Trading Volume Statistics</h4>
                        <div style="background: #1e293b; padding: 16px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 20px;">
                            <div style="font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: bold; margin-bottom: 6px;">Total Success Trade Volume</div>
                            <div style="font-size: 26px; font-weight: 800; color: #2ecc71;">₹${(data.buy.total_volume + data.sell.total_volume).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                            <div style="font-size: 12px; color: #94a3b8; margin-top: 6px; border-top: 1px solid #334155; padding-top: 8px;">
                                Buy Volume: <strong style="color:#ffffff;">₹${data.buy.total_volume.toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong> | 
                                Sell Volume: <strong style="color:#ffffff;">₹${data.sell.total_volume.toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stats-box">
                                <div class="stats-box-title">Total Buy Count</div>
                                <div class="stats-box-value" style="color: #10b981;">${data.buy.total_count} Success</div>
                                <div class="stats-sub-text">Today: ${data.buy.today_count} orders (₹${data.buy.today_volume.toLocaleString('en-IN', {minimumFractionDigits: 2})})</div>
                            </div>
                            <div class="stats-box">
                                <div class="stats-box-title">Total Sell Count</div>
                                <div class="stats-box-value" style="color: #f59e0b;">${data.sell.total_count} Success</div>
                                <div class="stats-sub-text">Today: ${data.sell.today_count} orders (₹${data.sell.today_volume.toLocaleString('en-IN', {minimumFractionDigits: 2})})</div>
                            </div>
                        </div>

                        <!-- Recent 10 Transactions Log -->
                        <h4 style="margin: 20px 0 10px; font-size: 13px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; font-weight: bold;">Last 10 Transactions Log</h4>
                        <div style="background: #0f172a; padding: 14px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 20px; max-height: 200px; overflow-y: auto;">
                            ${txHtml}
                        </div>

                        <!-- Referral Tree / Linked Network -->
                        <h4 style="margin: 20px 0 10px; font-size: 13px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; font-weight: bold;">User Referral Downline Tree</h4>
                        <div style="background: #1e293b; padding: 14px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 10px; max-height: 250px; overflow-y: auto;">
                            ${treeHtml}
                        </div>

                        <!-- UPI accounts directory -->
                        <h4 style="margin: 20px 0 10px; font-size: 13px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; font-weight: bold;">Linked UPI Accounts Directory</h4>
                        <div style="max-height: 250px; overflow-y: auto;">
                            ${upiHtml}
                        </div>
                    `;
                })
                .catch(err => {
                    console.error(err);
                    body.innerHTML = '<p style="color: #ef4444;">Failed to load user stats reports.</p>';
                });
        }

        function adminEditUpi(upiAccId, currentVal) {
            const newVal = prompt("Enter new UPI ID (e.g. username@handle):", currentVal);
            if (newVal !== null && newVal.trim() !== "") {
                if (!newVal.includes('@')) {
                    alert("Invalid UPI ID format. It must contain '@'.");
                    return;
                }
                postUpiAction('admin_edit_upi', upiAccId, newVal.trim());
            }
        }

        function adminToggleUpiSelling(upiAccId) {
            if (confirm("Are you sure you want to toggle the selling status of this UPI account?")) {
                postUpiAction('admin_toggle_upi_selling', upiAccId);
            }
        }

        function adminUnlinkUpi(upiAccId) {
            if (confirm("Are you sure you want to permanently delete/unlink this UPI account?")) {
                postUpiAction('admin_unlink_upi', upiAccId);
            }
        }

        function postUpiAction(action, upiAccId, extraVal = '') {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'users.php';

            const actInput = document.createElement('input');
            actInput.type = 'hidden';
            actInput.name = 'action';
            actInput.value = action;
            form.appendChild(actInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'upi_acc_id';
            idInput.value = upiAccId;
            form.appendChild(idInput);

            if (extraVal !== '') {
                const extraInput = document.createElement('input');
                extraInput.type = 'hidden';
                extraInput.name = 'new_upi_id';
                extraInput.value = extraVal;
                form.appendChild(extraInput);
            }

            document.body.appendChild(form);
            form.submit();
        }
 
        // Close modal when clicking outside of modal card
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('statsModal');
            if (e.target === modal) {
                closeStatsModal();
            }
        });
 
        function closeStatsModal() {
            document.getElementById('statsModal').style.display = 'none';
        }
    </script>
</body>
</html>
</body>
</html>
