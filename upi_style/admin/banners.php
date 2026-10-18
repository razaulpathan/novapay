<?php
// admin/banners.php - Admin Banner & Tutorial Video Management
require_once '../db.php';
if (!isAdmin()) { header("Location: login.php"); exit; }

$success_msg = '';
$error_msg = '';

// ── CREATE TABLES IF NOT EXISTS ──────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `home_banners` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `title`      VARCHAR(255) NOT NULL,
        `subtitle`   VARCHAR(500) DEFAULT '',
        `image_url`  VARCHAR(255) DEFAULT '',
        `link_url`   VARCHAR(255) DEFAULT '',
        `sort_order` INT DEFAULT 0,
        `is_active`  TINYINT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tutorial_videos` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `title`       VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT '',
        `video_url`   VARCHAR(500) NOT NULL,
        `thumbnail`   VARCHAR(255) DEFAULT '',
        `sort_order`  INT DEFAULT 0,
        `is_active`   TINYINT DEFAULT 1,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    $error_msg = "Setup error: " . $e->getMessage();
}

// ── HANDLE POST ACTIONS ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── BANNER ACTIONS ──
    if ($action === 'add_banner') {
        $title    = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if (empty($title)) {
            $error_msg = "Banner title is required.";
        } else {
            try {
                $image_url = '';
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Image upload failed. Error code: " . $_FILES['banner_image']['error']);
                    }
                    $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png','jpg','jpeg','webp','gif'])) {
                        throw new Exception("Invalid image type. Use PNG, JPG, WebP, or GIF.");
                    }
                    $dest_dir = __DIR__ . '/../images/';
                    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                    $filename = 'banner_' . time() . '_' . rand(100,999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['banner_image']['tmp_name'], $dest_dir . $filename)) {
                        throw new Exception("Failed to save image. Check folder write permissions.");
                    }
                    $image_url = 'images/' . $filename;
                }
                $stmt = $pdo->prepare("INSERT INTO home_banners (title, subtitle, image_url, link_url, sort_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $subtitle, $image_url, $link_url, $sort_order]);
                $success_msg = "Banner added successfully!";
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_banner') {
        $id = intval($_POST['banner_id'] ?? 0);
        $pdo->prepare("UPDATE home_banners SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $success_msg = "Banner status toggled.";
    } elseif ($action === 'delete_banner') {
        $id = intval($_POST['banner_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_url FROM home_banners WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && file_exists(__DIR__ . '/../' . $img)) @unlink(__DIR__ . '/../' . $img);
        $pdo->prepare("DELETE FROM home_banners WHERE id = ?")->execute([$id]);
        $success_msg = "Banner deleted.";

    // ── TUTORIAL VIDEO ACTIONS ──
    } elseif ($action === 'add_video') {
        $title       = trim($_POST['vid_title'] ?? '');
        $description = trim($_POST['vid_description'] ?? '');
        $video_url   = trim($_POST['video_url'] ?? '');
        $sort_order  = intval($_POST['vid_sort_order'] ?? 0);

        if (empty($title) || empty($video_url)) {
            $error_msg = "Video title and URL are required.";
        } else {
            try {
                $thumbnail = '';
                if (isset($_FILES['vid_thumbnail']) && $_FILES['vid_thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['vid_thumbnail']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Thumbnail upload failed. Error code: " . $_FILES['vid_thumbnail']['error']);
                    }
                    $ext = strtolower(pathinfo($_FILES['vid_thumbnail']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
                        throw new Exception("Invalid thumbnail type.");
                    }
                    $dest_dir = __DIR__ . '/../images/';
                    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                    $filename = 'thumb_' . time() . '_' . rand(100,999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['vid_thumbnail']['tmp_name'], $dest_dir . $filename)) {
                        throw new Exception("Failed to save thumbnail.");
                    }
                    $thumbnail = 'images/' . $filename;
                }
                $stmt = $pdo->prepare("INSERT INTO tutorial_videos (title, description, video_url, thumbnail, sort_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $video_url, $thumbnail, $sort_order]);
                $success_msg = "Tutorial video added successfully!";
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_video') {
        $id = intval($_POST['video_id'] ?? 0);
        $pdo->prepare("UPDATE tutorial_videos SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $success_msg = "Video status toggled.";
    } elseif ($action === 'delete_video') {
        $id = intval($_POST['video_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT thumbnail FROM tutorial_videos WHERE id = ?");
        $stmt->execute([$id]);
        $thumb = $stmt->fetchColumn();
        if ($thumb && file_exists(__DIR__ . '/../' . $thumb)) @unlink(__DIR__ . '/../' . $thumb);
        $pdo->prepare("DELETE FROM tutorial_videos WHERE id = ?")->execute([$id]);
        $success_msg = "Tutorial video deleted.";
    }
}

// ── FETCH DATA ───────────────────────────────────────────────────
$banners = $pdo->query("SELECT * FROM home_banners ORDER BY sort_order ASC, created_at DESC")->fetchAll();
$videos  = $pdo->query("SELECT * FROM tutorial_videos ORDER BY sort_order ASC, created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin – Banners & Tutorials</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .section-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 28px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #60a5fa;
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-subtitle {
            font-size: 12px;
            color: #64748b;
            margin: 0 0 20px;
        }
        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 180px;
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            background: #0f172a;
            border: 1px solid #334155;
            color: #ffffff;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 13px;
            outline: none;
            width: 100%;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #60a5fa;
        }
        .form-group textarea { min-height: 70px; resize: vertical; }
        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .item-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .item-card:hover { border-color: #475569; }
        .item-card .preview-img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background: linear-gradient(135deg, #1e3a5f, #0f172a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 40px;
        }
        .item-card .card-body {
            padding: 12px;
        }
        .item-card .card-title {
            font-size: 14px;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-card .card-sub {
            font-size: 11px;
            color: #64748b;
            margin: 0 0 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .badge-active { background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .badge-inactive { background: #475569; color: #94a3b8; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #475569;
        }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 12px; }
        .divider {
            border: none;
            border-top: 1px solid #334155;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <?php include '_sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">🎨 Banners & Tutorial Videos</h1>
            <div class="user-profile-badge">
                <div class="user-avatar-dot"></div>
                <span>Admin (<?= sanitize($_SESSION['phone']) ?>)</span>
            </div>
        </header>

        <main class="content-body">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger">✗ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- ══════════════ HOME PAGE BANNERS ══════════════ -->
            <div class="section-card">
                <h2 class="section-title">🖼️ Home Page Banners</h2>
                <p class="section-subtitle">These banners appear in the blue slider at the top of the user home screen.</p>

                <!-- Add Banner Form -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_banner">
                    <div class="form-row">
                        <div class="form-group" style="flex:2; min-width:200px;">
                            <label>Banner Title *</label>
                            <input type="text" name="title" placeholder="e.g. Invite your friends & earn rewards" required>
                        </div>
                        <div class="form-group" style="flex:1; min-width:120px;">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="0" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subtitle</label>
                            <input type="text" name="subtitle" placeholder="Small description below title">
                        </div>
                        <div class="form-group">
                            <label>Link URL (optional)</label>
                            <input type="url" name="link_url" placeholder="https://... (tap action)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Banner Image (PNG/JPG/WebP)</label>
                            <input type="file" name="banner_image" accept="image/*">
                        </div>
                        <div class="form-group" style="justify-content:flex-end;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary" style="height: 36px;">+ Add Banner</button>
                        </div>
                    </div>
                </form>

                <hr class="divider">

                <!-- Banners List -->
                <?php if (empty($banners)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="m3 9 6-6 6 6 3-3 3 3"/></svg>
                    <p>No banners added yet. Add your first banner above.</p>
                </div>
                <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($banners as $b): ?>
                    <div class="item-card">
                        <?php if ($b['image_url']): ?>
                            <img src="../<?= htmlspecialchars($b['image_url']) ?>" class="preview-img" alt="banner" style="display:block;">
                        <?php else: ?>
                            <div class="preview-img">🖼️</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <div class="card-title"><?= htmlspecialchars($b['title']) ?></div>
                                <?= $b['is_active'] ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-inactive">HIDDEN</span>' ?>
                            </div>
                            <div class="card-sub"><?= htmlspecialchars($b['subtitle'] ?: '—') ?></div>
                            <div class="card-actions">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_banner">
                                    <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:11px;background:<?= $b['is_active'] ? '#475569' : '#10b981' ?>;color:white;border:0;border-radius:4px;cursor:pointer;">
                                        <?= $b['is_active'] ? 'Hide' : 'Show' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this banner?')">
                                    <input type="hidden" name="action" value="delete_banner">
                                    <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:11px;background:#ef4444;color:white;border:0;border-radius:4px;cursor:pointer;">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ══════════════ TUTORIAL VIDEOS ══════════════ -->
            <div class="section-card">
                <h2 class="section-title">🎬 Tutorial Videos</h2>
                <p class="section-subtitle">Add YouTube links or MP4 URLs. Users see these on the Tutorial page. Supports multiple videos.</p>

                <!-- Add Video Form -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_video">
                    <div class="form-row">
                        <div class="form-group" style="flex:2; min-width:200px;">
                            <label>Video Title *</label>
                            <input type="text" name="vid_title" placeholder="e.g. How to Link Your UPI ID" required>
                        </div>
                        <div class="form-group" style="flex:1; min-width:120px;">
                            <label>Sort Order</label>
                            <input type="number" name="vid_sort_order" value="0" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label>Video URL * (YouTube or MP4 link)</label>
                            <input type="text" name="video_url" placeholder="https://youtube.com/watch?v=... or https://example.com/video.mp4" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="vid_description" placeholder="Short description of what this video covers..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Custom Thumbnail (optional)</label>
                            <input type="file" name="vid_thumbnail" accept="image/*">
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <button type="submit" class="btn btn-primary" style="padding:8px 20px;">+ Add Video</button>
                    </div>
                </form>

                <hr class="divider">

                <!-- Videos List -->
                <?php if (empty($videos)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    <p>No tutorial videos added yet. Add your first video above.</p>
                </div>
                <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($videos as $v): 
                        // Generate YouTube thumbnail automatically if not set
                        $thumb = $v['thumbnail'];
                        if (empty($thumb)) {
                            preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $v['video_url'], $m);
                            if (!empty($m[1])) {
                                $thumb = 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
                            }
                        }
                    ?>
                    <div class="item-card">
                        <?php if ($thumb): ?>
                            <img src="<?= htmlspecialchars(str_starts_with($thumb, 'http') ? $thumb : '../' . $thumb) ?>" class="preview-img" alt="thumbnail" style="display:block;">
                        <?php else: ?>
                            <div class="preview-img">🎬</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <div class="card-title"><?= htmlspecialchars($v['title']) ?></div>
                                <?= $v['is_active'] ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-inactive">HIDDEN</span>' ?>
                            </div>
                            <div class="card-sub"><?= htmlspecialchars(strlen($v['description']) > 60 ? substr($v['description'],0,60).'…' : ($v['description'] ?: $v['video_url'])) ?></div>
                            <div class="card-actions">
                                <a href="<?= htmlspecialchars($v['video_url']) ?>" target="_blank" class="btn" style="padding:4px 10px;font-size:11px;background:#3b82f6;color:white;border:0;border-radius:4px;cursor:pointer;text-decoration:none;">Preview</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_video">
                                    <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:11px;background:<?= $v['is_active'] ? '#475569' : '#10b981' ?>;color:white;border:0;border-radius:4px;cursor:pointer;">
                                        <?= $v['is_active'] ? 'Hide' : 'Show' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this video?')">
                                    <input type="hidden" name="action" value="delete_video">
                                    <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px;font-size:11px;background:#ef4444;color:white;border:0;border-radius:4px;cursor:pointer;">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</body>
</html>
