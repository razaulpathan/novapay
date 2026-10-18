<?php
// admin/partners.php - Admin Manage Payment Partners
require_once '../db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_partner') {
        $name = isset($_POST['partner_name']) ? trim($_POST['partner_name']) : '';
        $type = isset($_POST['partner_type']) ? trim($_POST['partner_type']) : 'Sell';
        
        if (empty($name)) {
            $error_msg = "Partner name is required.";
        } else {
            try {
                $logo_url = null;
                if (isset($_FILES['partner_logo']) && $_FILES['partner_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['partner_logo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("File upload failed with error code: " . $_FILES['partner_logo']['error']);
                    }
                    $ext = strtolower(pathinfo($_FILES['partner_logo']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'])) {
                        throw new Exception("Invalid file type. Allowed: PNG, JPG, JPEG, SVG, WebP, GIF.");
                    }
                    $filename = 'partner_' . time() . '_' . rand(100,999) . '.' . $ext;
                    $dest_dir = __DIR__ . '/../images/';
                    if (!is_dir($dest_dir)) {
                        @mkdir($dest_dir, 0755, true);
                    }
                    $dest = $dest_dir . $filename;
                    if (!move_uploaded_file($_FILES['partner_logo']['tmp_name'], $dest)) {
                        throw new Exception("Failed to save image file.");
                    }
                    $logo_url = 'images/' . $filename;
                }
                
                $stmt = $pdo->prepare("INSERT INTO payment_partners (name, type, logo_url) VALUES (?, ?, ?)");
                $stmt->execute([$name, $type, $logo_url]);
                $success_msg = "Payment partner added successfully!";
            } catch (Exception $e) {
                $error_msg = "Error adding partner: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_partner') {
        $id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
        $name = isset($_POST['partner_name']) ? trim($_POST['partner_name']) : '';
        $type = isset($_POST['partner_type']) ? trim($_POST['partner_type']) : 'Sell';
        
        if ($id <= 0 || empty($name)) {
            $error_msg = "Invalid partner details.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT logo_url FROM payment_partners WHERE id = ?");
                $stmt->execute([$id]);
                $existing_logo = $stmt->fetchColumn();
                
                $logo_url = $existing_logo;
                if (isset($_FILES['partner_logo']) && $_FILES['partner_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['partner_logo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("File upload failed with error code: " . $_FILES['partner_logo']['error']);
                    }
                    $ext = strtolower(pathinfo($_FILES['partner_logo']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'])) {
                        throw new Exception("Invalid file type.");
                    }
                    $filename = 'partner_' . time() . '_' . rand(100,999) . '.' . $ext;
                    $dest_dir = __DIR__ . '/../images/';
                    if (!is_dir($dest_dir)) {
                        @mkdir($dest_dir, 0755, true);
                    }
                    $dest = $dest_dir . $filename;
                    if (!move_uploaded_file($_FILES['partner_logo']['tmp_name'], $dest)) {
                        throw new Exception("Failed to save image.");
                    }
                    $logo_url = 'images/' . $filename;
                    if ($existing_logo && file_exists(__DIR__ . '/../' . $existing_logo)) {
                        @unlink(__DIR__ . '/../' . $existing_logo);
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE payment_partners SET name = ?, type = ?, logo_url = ? WHERE id = ?");
                $stmt->execute([$name, $type, $logo_url, $id]);
                $success_msg = "Payment partner updated successfully!";
            } catch (Exception $e) {
                $error_msg = "Error updating partner: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_partner') {
        $id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT logo_url FROM payment_partners WHERE id = ?");
                $stmt->execute([$id]);
                $logo = $stmt->fetchColumn();
                if ($logo && file_exists(__DIR__ . '/../' . $logo)) {
                    @unlink(__DIR__ . '/../' . $logo);
                }
                
                $stmt = $pdo->prepare("DELETE FROM payment_partners WHERE id = ?");
                $stmt->execute([$id]);
                $success_msg = "Payment partner deleted successfully!";
            } catch (PDOException $e) {
                $error_msg = "Error deleting partner: " . $e->getMessage();
            }
        }
    }
}

// Fetch all partners
$stmtPartners = $pdo->query("SELECT * FROM payment_partners ORDER BY id DESC");
$partners = $stmtPartners->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Payment Partners</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Manage Payment Partners</h1>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body">
            <!-- Alerts -->
            <?php if ($success_msg !== ''): ?>
                <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg !== ''): ?>
                <div class="alert alert-error"><?= $error_msg ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start;">
                <!-- List Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Existing Partners</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Logo</th>
                                    <th>Partner Name</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($partners)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #a9b7d0;">No payment partners registered yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($partners as $p): ?>
                                        <tr>
                                            <td>
                                                <?php if ($p['logo_url']): ?>
                                                    <img src="../<?= htmlspecialchars($p['logo_url']) ?>" alt="logo" style="width: 32px; height: 32px; border-radius: 4px; object-fit: contain; background: #fff; padding: 2px;">
                                                <?php else: ?>
                                                    <div style="width: 32px; height: 32px; border-radius: 4px; background: #283046; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff;">N/A</div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: bold; color: #fff;"><?= sanitize($p['name']) ?></td>
                                            <td>
                                                <span class="status-badge" style="background: <?= $p['type'] === 'Buy' ? 'rgba(40, 199, 111, 0.15); color: #28c76f;' : 'rgba(255, 159, 67, 0.15); color: #ff9f43;' ?>">
                                                    <?= sanitize($p['type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 11px;" onclick='editPartner(<?= json_encode($p) ?>)'>Edit</button>
                                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this partner?');">
                                                        <input type="hidden" name="action" value="delete_partner">
                                                        <input type="hidden" name="partner_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 11px; background: #ea5455; color: white; border: 0; border-radius: 4px; cursor: pointer;">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add/Edit Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title" id="form-panel-title">Add Payment Partner</h2>
                    </div>
                    <form method="POST" action="partners.php" enctype="multipart/form-data" style="padding: 20px;">
                        <input type="hidden" name="action" id="partner-action" value="add_partner">
                        <input type="hidden" name="partner_id" id="partner-id" value="">

                        <div class="form-group">
                            <label class="form-label" for="partner_name">Partner Name</label>
                            <input type="text" name="partner_name" id="partner_name" class="form-control" placeholder="e.g. MobiKwik, Freecharge" required>
                        </div>

                        <div class="form-group" style="margin-top: 18px;">
                            <label class="form-label" for="partner_type">Partner Type</label>
                            <select name="partner_type" id="partner_type" class="form-control" style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; width: 100%; font-family: inherit;">
                                <option value="Buy">Buy Channel (Incoming User Deposits)</option>
                                <option value="Sell">Sell Channel (Outgoing Pay-outs)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-top: 18px;">
                            <label class="form-label" for="partner_logo">Partner Logo Image</label>
                            <input type="file" name="partner_logo" id="partner_logo" class="form-control" accept="image/*">
                            <span class="form-text" style="color: #a9b7d0; font-size: 11px;">Recommended size: Square 128x128px PNG/SVG.</span>
                        </div>

                        <div style="margin-top: 24px; display: flex; gap: 10px;">
                            <button type="submit" class="btn" style="background: #7367f0; color: white; border:0; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; flex: 1;">Save Partner</button>
                            <button type="button" id="btn-cancel-edit" class="btn btn-outline" style="display:none; padding: 10px 20px;" onclick="cancelEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function editPartner(partner) {
            document.getElementById('form-panel-title').textContent = 'Edit Payment Partner';
            document.getElementById('partner-action').value = 'update_partner';
            document.getElementById('partner-id').value = partner.id;
            document.getElementById('partner_name').value = partner.name;
            document.getElementById('partner_type').value = partner.type;
            document.getElementById('btn-cancel-edit').style.display = 'inline-block';
        }

        function cancelEdit() {
            document.getElementById('form-panel-title').textContent = 'Add Payment Partner';
            document.getElementById('partner-action').value = 'add_partner';
            document.getElementById('partner-id').value = '';
            document.getElementById('partner_name').value = '';
            document.getElementById('partner_type').value = 'Buy';
            document.getElementById('btn-cancel-edit').style.display = 'none';
        }
    </script>
</body>
</html>
