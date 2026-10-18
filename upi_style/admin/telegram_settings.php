<?php
// admin/telegram_settings.php - Admin Telegram Bot Settings
require_once '../db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_telegram') {
    $bot_token = isset($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : '';
    $bot_secret = isset($_POST['telegram_bot_secret']) ? trim($_POST['telegram_bot_secret']) : '';
    $bot_status = isset($_POST['telegram_bot_status']) ? trim($_POST['telegram_bot_status']) : '0';
    $bot_group_chat_id = isset($_POST['telegram_group_chat_id']) ? trim($_POST['telegram_group_chat_id']) : '';
    $bot_admin_chat_id = isset($_POST['telegram_admin_chat_id']) ? trim($_POST['telegram_admin_chat_id']) : '';

    try {
        updateSetting('telegram_bot_token', $bot_token);
        updateSetting('telegram_bot_secret', $bot_secret);
        updateSetting('telegram_bot_status', $bot_status);
        updateSetting('telegram_group_chat_id', $bot_group_chat_id);
        updateSetting('telegram_admin_chat_id', $bot_admin_chat_id);

        $success_msg = "Telegram settings updated successfully.";
    } catch (PDOException $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}

// Fetch current values
$bot_token = getSetting('telegram_bot_token') ?: '';
$bot_status = getSetting('telegram_bot_status') ?: '0';
$bot_secret = getSetting('telegram_bot_secret') ?: 'SECRET_BOT_TOKEN_HERE';
$bot_group_chat_id = getSetting('telegram_group_chat_id') ?: '';
$bot_admin_chat_id = getSetting('telegram_admin_chat_id') ?: '';

$bot_username = '';
$bot_online = false;

if ($bot_status === '1' && !empty($bot_token)) {
    // Check live Telegram connection via getMe API call
    $url = "https://api.telegram.org/bot{$bot_token}/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    
    if ($res) {
        $res_arr = json_decode($res, true);
        if (isset($res_arr['ok']) && $res_arr['ok'] === true) {
            $bot_online = true;
            $bot_username = $res_arr['result']['username'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Telegram Bot Settings</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Telegram Bot Configuration</h1>
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

            <div class="panel" style="max-width: 600px; margin: 0 auto;">
                <div class="panel-header">
                    <h2 class="panel-title">🤖 Telegram Bot Settings</h2>
                </div>

                <div style="padding: 20px;">
                    <!-- Status Badge -->
                    <div style="padding: 14px; border-radius: 8px; background: #161d31; border: 1px solid var(--border-color); margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                        <?php if ($bot_status === 'Suspended'): ?>
                            <span style="font-size: 13.5px; color: #ea5455; font-weight: 600;">🔴 Suspended by pahan</span>
                        <?php elseif ($bot_status === '0'): ?>
                            <span style="font-size: 13.5px; color: #7f8c8d; font-weight: 600;">⚫ Bot Disabled</span>
                        <?php elseif ($bot_online): ?>
                            <span style="font-size: 13.5px; color: #2ecc71; font-weight: 600;">🟢 Connected → <a href="https://t.me/<?= $bot_username ?>" target="_blank" style="color: #2395ee; font-weight: bold;">@<?= $bot_username ?></a></span>
                        <?php else: ?>
                            <span style="font-size: 13.5px; color: #e74c3c; font-weight: 600;">🔴 Token invalid or offline</span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="telegram_settings.php">
                        <input type="hidden" name="action" value="update_telegram">

                        <!-- Token Field -->
                        <div class="form-group">
                            <label class="form-label" for="telegram_bot_token">🔑 Bot Token</label>
                            <input type="text" name="telegram_bot_token" id="telegram_bot_token" class="form-control"
                                value="<?= sanitize($bot_token) ?>" required
                                placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsT">
                            <span class="form-text">Generate via Telegram @BotFather by sending /newbot.</span>
                        </div>

                        <!-- Status Selection -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" for="telegram_bot_status">⚡ Bot Active Status</label>
                            <select name="telegram_bot_status" id="telegram_bot_status" class="form-control" style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; width: 100%; font-family: inherit;">
                                <option value="1" <?= $bot_status === '1' ? 'selected' : '' ?>>Enabled (Active)</option>
                                <option value="0" <?= $bot_status === '0' ? 'selected' : '' ?>>Disabled (Offline)</option>
                                <option value="Suspended" <?= $bot_status === 'Suspended' ? 'selected' : '' ?>>🔴 Suspended by pahan</option>
                            </select>
                        </div>

                        <!-- Secret Secret -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" for="telegram_bot_secret">🔒 Bot Secret Token (Optional)</label>
                            <input type="text" name="telegram_bot_secret" id="telegram_bot_secret" class="form-control" value="<?= sanitize($bot_secret) ?>">
                            <span class="form-text">Secures Telegram updates against malicious requests.</span>
                        </div>

                        <!-- Group Chat ID -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" for="telegram_group_chat_id">👥 Group Chat ID</label>
                            <input type="text" name="telegram_group_chat_id" id="telegram_group_chat_id" class="form-control" value="<?= sanitize($bot_group_chat_id) ?>" placeholder="e.g. -10019283746">
                            <span class="form-text">Telegram Group ID for public notifications.</span>
                        </div>

                        <!-- Admin Chat ID -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" for="telegram_admin_chat_id">👑 Admin Chat ID</label>
                            <input type="text" name="telegram_admin_chat_id" id="telegram_admin_chat_id" class="form-control" value="<?= sanitize($bot_admin_chat_id) ?>" placeholder="e.g. 520847593">
                            <span class="form-text">Telegram User ID of the administrator to receive alert logs.</span>
                        </div>

                        <!-- Webhook Action Section -->
                        <div style="margin-top: 28px; border-top: 1px dashed var(--border-color); padding-top: 20px;">
                            <label class="form-label">🔗 Manage Bot Webhook</label>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                                <button type="button" onclick="runWebhookAction('set')" style="background: #2395ee; color: white; border: 0; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">Set Webhook</button>
                                <button type="button" onclick="runWebhookAction('delete')" style="background: #ea5455; color: white; border: 0; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">Delete Webhook</button>
                                <button type="button" onclick="runWebhookAction('info')" style="background: #5d6d7e; color: white; border: 0; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">Webhook Info</button>
                            </div>
                            <pre id="webhook-console" style="display:none; margin-top: 12px; background: #0d121f; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; color: #a9b7d0; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 200px; overflow-y: auto;"></pre>
                        </div>

                        <div style="margin-top: 24px;">
                            <button type="submit" class="btn" style="background: #7367f0; color: white; border: 0; padding: 12px 24px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function runWebhookAction(action) {
            const consoleBox = document.getElementById('webhook-console');
            consoleBox.style.display = 'block';
            consoleBox.textContent = 'Calling Telegram Bot Webhook API... Please wait.';
            
            fetch('../tgbot/set_webhook.php?action=' + action)
                .then(response => response.json())
                .then(data => {
                    consoleBox.textContent = JSON.stringify(data, null, 4);
                })
                .catch(error => {
                    consoleBox.textContent = 'Error connecting to server: ' + error.message;
                });
        }
    </script>
</body>
</html>
