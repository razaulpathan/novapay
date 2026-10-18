<?php
// admin/announcements.php - Manage News & Announcements
require_once '../db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_announcement') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $body = isset($_POST['body']) ? trim($_POST['body']) : '';
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

        if (empty($title) || empty($body)) {
            $error_msg = "Title and Body are required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, body, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$title, $body, $is_active]);
                $success_msg = "Announcement added successfully!";
            } catch (PDOException $e) {
                $error_msg = "Error adding announcement: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_announcement') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $body = isset($_POST['body']) ? trim($_POST['body']) : '';
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

        if ($id <= 0 || empty($title) || empty($body)) {
            $error_msg = "Invalid inputs.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE announcements SET title = ?, body = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $body, $is_active, $id]);
                $success_msg = "Announcement updated successfully!";
            } catch (PDOException $e) {
                $error_msg = "Error updating announcement: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_announcement') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $success_msg = "Announcement deleted successfully!";
            } catch (PDOException $e) {
                $error_msg = "Error deleting announcement: " . $e->getMessage();
            }
        }
    }
}

// Fetch announcements
$stmtAnn = $pdo->query("SELECT * FROM announcements ORDER BY id DESC");
$announcements = $stmtAnn->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Manage Announcements</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Manage News &amp; Announcements</h1>
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
                        <h2 class="panel-title">Active / Past Announcements</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($announcements)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #a9b7d0;">No announcements created yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $ann): ?>
                                        <tr>
                                            <td style="font-weight: bold; color: #fff; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= sanitize($ann['title']) ?></td>
                                            <td>
                                                <span class="status-badge" style="background: <?= $ann['is_active'] ? 'rgba(40, 199, 111, 0.15); color: #28c76f;' : 'rgba(234, 84, 85, 0.15); color: #ea5455;' ?>">
                                                    <?= $ann['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 11px; font-family: monospace;"><?= $ann['created_at'] ?></td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 11px;" onclick='editAnn(<?= json_encode($ann) ?>)'>Edit</button>
                                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                        <input type="hidden" name="action" value="delete_announcement">
                                                        <input type="hidden" name="id" value="<?= $ann['id'] ?>">
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

                <!-- Form Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title" id="form-panel-title">Add New Announcement</h2>
                    </div>
                    <form method="POST" action="announcements.php" style="padding: 20px;">
                        <input type="hidden" name="action" id="ann-action" value="add_announcement">
                        <input type="hidden" name="id" id="ann-id" value="">

                        <div class="form-group">
                            <label class="form-label" for="title">Title</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="e.g. System upgrade complete" required>
                        </div>

                        <div class="form-group" style="margin-top: 18px;">
                            <label class="form-label" for="body">Announcement Message / Body</label>
                            <textarea name="body" id="body" class="form-control" rows="8" placeholder="Enter announcement body text here..." style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 12px; border-radius: 6px; width: 100%; font-family: inherit; resize: vertical;" required></textarea>
                        </div>

                        <div class="form-group" style="margin-top: 18px;">
                            <label class="form-label" for="is_active">Status</label>
                            <select name="is_active" id="is_active" class="form-control" style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; width: 100%; font-family: inherit;">
                                <option value="1">Active (Visible to users)</option>
                                <option value="0">Inactive (Hidden)</option>
                            </select>
                        </div>

                        <div style="margin-top: 24px; display: flex; gap: 10px;">
                            <button type="submit" class="btn" style="background: #7367f0; color: white; border:0; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; flex: 1;">Save Announcement</button>
                            <button type="button" id="btn-cancel-edit" class="btn btn-outline" style="display:none; padding: 10px 20px;" onclick="cancelEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function editAnn(ann) {
            document.getElementById('form-panel-title').textContent = 'Edit Announcement';
            document.getElementById('ann-action').value = 'update_announcement';
            document.getElementById('ann-id').value = ann.id;
            document.getElementById('title').value = ann.title;
            document.getElementById('body').value = ann.body;
            document.getElementById('is_active').value = ann.is_active;
            document.getElementById('btn-cancel-edit').style.display = 'inline-block';
        }

        function cancelEdit() {
            document.getElementById('form-panel-title').textContent = 'Add New Announcement';
            document.getElementById('ann-action').value = 'add_announcement';
            document.getElementById('ann-id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('body').value = '';
            document.getElementById('is_active').value = '1';
            document.getElementById('btn-cancel-edit').style.display = 'none';
        }
    </script>
</body>
</html>
