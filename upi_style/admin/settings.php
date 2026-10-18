<?php
// admin/settings.php - Admin Global Configuration Control
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $exchange_rate = isset($_POST['exchange_rate_usdt']) ? trim($_POST['exchange_rate_usdt']) : '106.7';
    $night_ratio = isset($_POST['buy_ratio_nighttime']) ? trim($_POST['buy_ratio_nighttime']) : '3.0';
    $default_reward = isset($_POST['default_reward_percentage']) ? trim($_POST['default_reward_percentage']) : '2.5';

    // Basic validation
    if (!is_numeric($exchange_rate) || floatval($exchange_rate) <= 0) {
        $error_msg = "Exchange rate must be a positive number.";
    } elseif (!is_numeric($night_ratio) || floatval($night_ratio) < 0) {
        $error_msg = "Nighttime buy ratio must be a non-negative number.";
    } elseif (!is_numeric($default_reward) || floatval($default_reward) < 0 || floatval($default_reward) > 100) {
        $error_msg = "Default reward percentage must be between 0 and 100.";
    } else {
        $admin_upi = isset($_POST['admin_upi_id']) ? trim($_POST['admin_upi_id']) : '';
        $admin_usdt = isset($_POST['admin_usdt_address']) ? trim($_POST['admin_usdt_address']) : '';
        $gateway_mode = isset($_POST['payment_gateway_mode']) ? trim($_POST['payment_gateway_mode']) : 'Manual';
        $watchpay_merchant = isset($_POST['watchpay_merchant_id']) ? trim($_POST['watchpay_merchant_id']) : '';
        $watchpay_api = isset($_POST['watchpay_api_key']) ? trim($_POST['watchpay_api_key']) : '';
        $site_maintenance = isset($_POST['site_maintenance']) ? trim($_POST['site_maintenance']) : '0';
        $header1 = isset($_POST['site_popup_header_1']) ? trim($_POST['site_popup_header_1']) : '';
        $image1 = isset($_POST['site_popup_image_1']) ? trim($_POST['site_popup_image_1']) : '';
        $header2 = isset($_POST['site_popup_header_2']) ? trim($_POST['site_popup_header_2']) : '';
        $image2 = isset($_POST['site_popup_image_2']) ? trim($_POST['site_popup_image_2']) : '';
        $header3 = isset($_POST['site_popup_header_3']) ? trim($_POST['site_popup_header_3']) : '';
        $image3 = isset($_POST['site_popup_image_3']) ? trim($_POST['site_popup_image_3']) : '';
        $admin_bank_name = isset($_POST['admin_bank_name']) ? trim($_POST['admin_bank_name']) : '';
        $admin_bank_account = isset($_POST['admin_bank_account']) ? trim($_POST['admin_bank_account']) : '';
        $admin_bank_ifsc = isset($_POST['admin_bank_ifsc']) ? trim($_POST['admin_bank_ifsc']) : '';
        $admin_bank_holder = isset($_POST['admin_bank_holder']) ? trim($_POST['admin_bank_holder']) : '';
        $admin_pay_channel = isset($_POST['admin_pay_channel']) ? trim($_POST['admin_pay_channel']) : '';

        try {
            updateSetting('exchange_rate_usdt', $exchange_rate);
            updateSetting('buy_ratio_nighttime', $night_ratio);
            updateSetting('default_reward_percentage', $default_reward);
            if ($admin_upi !== '') {
                updateSetting('admin_upi_id', $admin_upi);
            }
            if ($admin_usdt !== '') {
                updateSetting('admin_usdt_address', $admin_usdt);
            }
            updateSetting('payment_gateway_mode', $gateway_mode);
            if ($watchpay_merchant !== '') {
                updateSetting('watchpay_merchant_id', $watchpay_merchant);
            }
            if ($watchpay_api !== '') {
                updateSetting('watchpay_api_key', $watchpay_api);
            }
            updateSetting('site_maintenance', $site_maintenance);
            updateSetting('site_popup_header_1', $header1);
            updateSetting('site_popup_image_1', $image1);
            updateSetting('site_popup_header_2', $header2);
            updateSetting('site_popup_image_2', $image2);
            updateSetting('site_popup_header_3', $header3);
            updateSetting('site_popup_image_3', $image3);
            updateSetting('admin_bank_name', $admin_bank_name);
            updateSetting('admin_bank_account', $admin_bank_account);
            updateSetting('admin_bank_ifsc', $admin_bank_ifsc);
            updateSetting('admin_bank_holder', $admin_bank_holder);
            updateSetting('admin_pay_channel', $admin_pay_channel);
            updateSetting('tutorial_video_url',     isset($_POST['tutorial_video_url'])     ? trim($_POST['tutorial_video_url'])     : '');
            updateSetting('support_telegram_vip',   isset($_POST['support_telegram_vip'])   ? trim($_POST['support_telegram_vip'])   : '');
            updateSetting('tutorial_video_url',     isset($_POST['tutorial_video_url'])     ? trim($_POST['tutorial_video_url'])     : '');

            // Customer Care / Support Links
            updateSetting('support_telegram_channel', isset($_POST['support_telegram_channel']) ? trim($_POST['support_telegram_channel']) : '');
            updateSetting('support_telegram_csr',     isset($_POST['support_telegram_csr'])     ? trim($_POST['support_telegram_csr'])     : '');
            updateSetting('support_whatsapp',         isset($_POST['support_whatsapp'])         ? trim($_POST['support_whatsapp'])         : '');
            updateSetting('support_email',            isset($_POST['support_email'])            ? trim($_POST['support_email'])            : '');
            updateSetting('support_phone',            isset($_POST['support_phone'])            ? trim($_POST['support_phone'])            : '');
            updateSetting('support_working_hours',    isset($_POST['support_working_hours'])    ? trim($_POST['support_working_hours'])    : '');

            // Social Media
            updateSetting('social_instagram', isset($_POST['social_instagram']) ? trim($_POST['social_instagram']) : '');
            updateSetting('social_facebook',  isset($_POST['social_facebook'])  ? trim($_POST['social_facebook'])  : '');
            updateSetting('social_youtube',   isset($_POST['social_youtube'])   ? trim($_POST['social_youtube'])   : '');
            updateSetting('social_twitter',   isset($_POST['social_twitter'])   ? trim($_POST['social_twitter'])   : '');

            // Tutorial / Help Links
            updateSetting('tutorial_video_title', isset($_POST['tutorial_video_title']) ? trim($_POST['tutorial_video_title']) : '');
            updateSetting('faq_link',             isset($_POST['faq_link'])             ? trim($_POST['faq_link'])             : '');
            updateSetting('terms_link',           isset($_POST['terms_link'])           ? trim($_POST['terms_link'])           : '');
            updateSetting('privacy_link',         isset($_POST['privacy_link'])         ? trim($_POST['privacy_link'])         : '');

            // Commission & Security Rules
            updateSetting('commission_l1_rate',       isset($_POST['commission_l1_rate'])       ? trim($_POST['commission_l1_rate'])       : '0.003');
            updateSetting('commission_l2_rate',       isset($_POST['commission_l2_rate'])       ? trim($_POST['commission_l2_rate'])       : '0.002');
            updateSetting('commission_l3_rate',       isset($_POST['commission_l3_rate'])       ? trim($_POST['commission_l3_rate'])       : '0.001');
            updateSetting('commission_l4_rate',       isset($_POST['commission_l4_rate'])       ? trim($_POST['commission_l4_rate'])       : '0.0005');
            updateSetting('commission_l5_rate',       isset($_POST['commission_l5_rate'])       ? trim($_POST['commission_l5_rate'])       : '0.0002');
            
            $agent_allowed = isset($_POST['agent_allowed_pages']) ? $_POST['agent_allowed_pages'] : [];
            updateSetting('agent_allowed_pages', json_encode($agent_allowed));
            updateSetting('commission_claim_min',     isset($_POST['commission_claim_min'])     ? trim($_POST['commission_claim_min'])     : '500');
            updateSetting('commission_claim_hours',   isset($_POST['commission_claim_hours'])   ? trim($_POST['commission_claim_hours'])   : '11:00-21:00');
            updateSetting('daily_checkin_bonus',      isset($_POST['daily_checkin_bonus'])      ? trim($_POST['daily_checkin_bonus'])      : '5');
            updateSetting('newbie_reward_amount',     isset($_POST['newbie_reward_amount'])     ? trim($_POST['newbie_reward_amount'])     : '50');
            updateSetting('max_failed_utr_attempts',  isset($_POST['max_failed_utr_attempts'])  ? trim($_POST['max_failed_utr_attempts'])  : '3');
            updateSetting('login_otp_code',           isset($_POST['login_otp_code'])           ? trim($_POST['login_otp_code'])           : '1234');
            updateSetting('order_min_default',        isset($_POST['order_min_default'])        ? trim($_POST['order_min_default'])        : '100');
            updateSetting('order_max_default',        isset($_POST['order_max_default'])        ? trim($_POST['order_max_default'])        : '50000');

            $success_msg = "Global settings updated successfully.";
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch current values
$exchange_rate = getSetting('exchange_rate_usdt') ?: '106.7';
$night_ratio = getSetting('buy_ratio_nighttime') ?: '3.0';
$default_reward = getSetting('default_reward_percentage') ?: '2.5';
$admin_upi = getSetting('admin_upi_id') ?: '';
$admin_usdt = getSetting('admin_usdt_address') ?: '';
$gateway_mode = getSetting('payment_gateway_mode') ?: 'Manual';
$watchpay_merchant = getSetting('watchpay_merchant_id') ?: '';
$watchpay_api = getSetting('watchpay_api_key') ?: '';
$site_maintenance = getSetting('site_maintenance') ?: '0';
$header1 = getSetting('site_popup_header_1') ?: 'Night-Time Buy Ratio Increased to 3.0%';
$image1 = getSetting('site_popup_image_1') ?: 'images/banner_nighttime.png';
$header2 = getSetting('site_popup_header_2') ?: 'GUIDANCE REWARDS';
$image2 = getSetting('site_popup_image_2') ?: 'images/banner_guidance.png';
$header3 = getSetting('site_popup_header_3') ?: 'BUY ORDER REWARDS';
$image3 = getSetting('site_popup_image_3') ?: 'images/banner_latest_event.png';
$admin_bank_name = getSetting('admin_bank_name') ?: 'AIRP0000001';
$admin_bank_account = getSetting('admin_bank_account') ?: '8506943929';
$admin_bank_ifsc = getSetting('admin_bank_ifsc') ?: 'AIRP0000001';
$admin_bank_holder = getSetting('admin_bank_holder') ?: 'PRATHAMJOHRI';
$admin_pay_channel = getSetting('admin_pay_channel') ?: 'freecharge(nirmal577@freecharge)';

$support_telegram_vip = getSetting('support_telegram_vip') ?: 'https://t.me/NextPay_vip';
$tutorial_video_url = getSetting('tutorial_video_url') ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextPay Admin - Global Settings</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include '_sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title">Global Configuration</h1>
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

            <div class="panel" style="max-width: 600px;">
                <div class="panel-header">
                    <h2 class="panel-title">System Variables</h2>
                </div>

                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_settings">

                    <!-- USDT Exchange Rate -->
                    <div class="form-group">
                        <label class="form-label" for="rate">USDT Exchange Rate (ITokens per 1 USDT)</label>
                        <input type="number" step="0.1" name="exchange_rate_usdt" id="rate" class="form-control" value="<?= sanitize($exchange_rate) ?>" required>
                        <span class="form-text">Sets the conversion rate for buying/selling USDT. Default: 106.7</span>
                    </div>

                    <!-- Nighttime Buy Ratio -->
                    <div class="form-group" style="margin-top: 24px;">
                        <label class="form-label" for="ratio">Night-Time Buy Ratio Increase (%)</label>
                        <input type="number" step="0.1" name="buy_ratio_nighttime" id="ratio" class="form-control" value="<?= sanitize($night_ratio) ?>" required>
                        <span class="form-text">Specifies reward multiplier rates during night operations. Default: 3.0</span>
                    </div>

                    <!-- Default Reward Percentage -->
                    <div class="form-group" style="margin-top: 24px;">
                        <label class="form-label" for="reward">Default Reward Percentage (%)</label>
                        <input type="number" step="0.1" name="default_reward_percentage" id="reward" class="form-control" value="<?= sanitize($default_reward) ?>" required>
                        <span class="form-text">System base reward payout rate for standard buy transactions. Default: 2.5</span>
                    </div>

                    <!-- Payment Settings Section -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 16px;">Payment Gateway Settings</h3>
                        
                        <div class="form-group">
                            <label class="form-label" for="payment_gateway_mode">Active Gateway Mode</label>
                            <select name="payment_gateway_mode" id="payment_gateway_mode" class="form-control" style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 12px; border-radius: 6px; width: 100%; font-family: inherit;">
                                <option value="WatchPay" <?= $gateway_mode === 'WatchPay' ? 'selected' : '' ?>>WatchPay Gateway (Automated)</option>
                                <option value="Manual" <?= $gateway_mode === 'Manual' ? 'selected' : '' ?>>Manual Payment (QR Code & UTR Verification)</option>
                            </select>
                            <span class="form-text">Choose whether UPI buy orders are automatically processed through WatchPay or manually checked via UTR submission.</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_upi_id">Admin UPI ID (For Manual Mode)</label>
                            <input type="text" name="admin_upi_id" id="admin_upi_id" class="form-control" value="<?= sanitize($admin_upi) ?>" required>
                            <span class="form-text">UPI ID where users will send INR deposits manually.</span>
                        </div>
                        
                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_usdt_address">Admin USDT TRC-20 Address (For Manual/Crypto Mode)</label>
                            <input type="text" name="admin_usdt_address" id="admin_usdt_address" class="form-control" value="<?= sanitize($admin_usdt) ?>" required>
                            <span class="form-text">Tether TRC-20 wallet address for manual cryptocurrency deposits.</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_bank_holder">Admin Account Holder Name</label>
                            <input type="text" name="admin_bank_holder" id="admin_bank_holder" class="form-control" value="<?= sanitize($admin_bank_holder) ?>" required>
                            <span class="form-text">Name of the bank account holder.</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_bank_account">Admin Bank Account Number</label>
                            <input type="text" name="admin_bank_account" id="admin_bank_account" class="form-control" value="<?= sanitize($admin_bank_account) ?>" required>
                            <span class="form-text">Bank account number for user deposits.</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_bank_ifsc">Admin Bank IFSC Code</label>
                            <input type="text" name="admin_bank_ifsc" id="admin_bank_ifsc" class="form-control" value="<?= sanitize($admin_bank_ifsc) ?>" required>
                            <span class="form-text">Bank IFSC code.</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_bank_name">Admin Bank Name</label>
                            <input type="text" name="admin_bank_name" id="admin_bank_name" class="form-control" value="<?= sanitize($admin_bank_name) ?>" required>
                            <span class="form-text">Bank name (e.g. Airtel Payments Bank).</span>
                        </div>

                        <div class="form-group" style="margin-top: 24px;">
                            <label class="form-label" for="admin_pay_channel">Admin Payment Channel Info</label>
                            <input type="text" name="admin_pay_channel" id="admin_pay_channel" class="form-control" value="<?= sanitize($admin_pay_channel) ?>" required>
                            <span class="form-text">Payment channel description text displayed to users (e.g., freecharge(nirmal577@freecharge)).</span>
                        </div>

                        <div style="margin-top: 24px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 20px;">
                            <h4 style="font-size: 14px; font-weight: bold; color: var(--blue); margin-bottom: 16px;">WatchPay Gateway Credentials</h4>
                            
                            <div class="form-group">
                                <label class="form-label" for="watchpay_merchant_id">WatchPay Merchant ID</label>
                                <input type="text" name="watchpay_merchant_id" id="watchpay_merchant_id" class="form-control" value="<?= sanitize($watchpay_merchant) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-top: 24px;">
                                <label class="form-label" for="watchpay_api_key">WatchPay API Key</label>
                                <input type="text" name="watchpay_api_key" id="watchpay_api_key" class="form-control" value="<?= sanitize($watchpay_api) ?>">
                            </div>
                        </div>
                    </div>



                    <!-- System State Settings -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 16px;">System State Settings</h3>
                        
                        <div class="form-group">
                            <label class="form-label" for="site_maintenance">System Maintenance Mode</label>
                            <select name="site_maintenance" id="site_maintenance" class="form-control" style="background: #161d31; color: #ffffff; border: 1px solid var(--border-color); padding: 12px; border-radius: 6px; width: 100%; font-family: inherit;">
                                <option value="0" <?= $site_maintenance === '0' ? 'selected' : '' ?>>Inactive (Online)</option>
                                <option value="1" <?= $site_maintenance === '1' ? 'selected' : '' ?>>Active (Maintenance Mode Blocked)</option>
                            </select>
                            <span class="form-text">When active, normal users are blocked and see the system maintenance message, but administrators can still access dashboards.</span>
                        </div>

                        <div style="margin-top: 24px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 20px;">
                            <h4 style="font-size: 14px; font-weight: bold; color: var(--blue); margin-bottom: 16px;">Homepage Popup Banners Settings (Up to 3 Slots)</h4>
                            
                            <!-- Banner Slot 1 -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="site_popup_header_1">Slot 1 Title / Header</label>
                                <input type="text" name="site_popup_header_1" id="site_popup_header_1" class="form-control" value="<?= sanitize($header1) ?>">
                                <label class="form-label" for="site_popup_image_1" style="margin-top: 8px;">Slot 1 Image Path</label>
                                <input type="text" name="site_popup_image_1" id="site_popup_image_1" class="form-control" value="<?= sanitize($image1) ?>">
                            </div>

                            <!-- Banner Slot 2 -->
                            <div class="form-group" style="margin-bottom: 20px; border-top: 1px dashed rgba(255,255,255,0.05); padding-top: 16px;">
                                <label class="form-label" for="site_popup_header_2">Slot 2 Title / Header</label>
                                <input type="text" name="site_popup_header_2" id="site_popup_header_2" class="form-control" value="<?= sanitize($header2) ?>">
                                <label class="form-label" for="site_popup_image_2" style="margin-top: 8px;">Slot 2 Image Path</label>
                                <input type="text" name="site_popup_image_2" id="site_popup_image_2" class="form-control" value="<?= sanitize($image2) ?>">
                            </div>

                            <!-- Banner Slot 3 -->
                            <div class="form-group" style="margin-bottom: 10px; border-top: 1px dashed rgba(255,255,255,0.05); padding-top: 16px;">
                                <label class="form-label" for="site_popup_header_3">Slot 3 Title / Header</label>
                                <input type="text" name="site_popup_header_3" id="site_popup_header_3" class="form-control" value="<?= sanitize($header3) ?>">
                                <label class="form-label" for="site_popup_image_3" style="margin-top: 8px;">Slot 3 Image Path</label>
                                <input type="text" name="site_popup_image_3" id="site_popup_image_3" class="form-control" value="<?= sanitize($image3) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════════ -->
                    <!-- SECTION: Customer Care & Support Links                  -->
                    <!-- ═══════════════════════════════════════════════════════ -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 6px;">📞 Customer Care &amp; Support Links</h3>
                        <p style="font-size: 12px; color: #a9b7d0; margin-bottom: 20px;">All customer-facing support links and contact details editable here. Users see these on the Customer Service page and popup buttons.</p>

                        <div style="display: grid; gap: 16px;">

                            <div class="form-group">
                                <label class="form-label" for="support_telegram_channel">📢 Telegram Main Channel Link</label>
                                <input type="url" name="support_telegram_channel" id="support_telegram_channel" class="form-control"
                                    value="<?= sanitize(getSetting('support_telegram_channel') ?: 'https://t.me/NextPay_channel') ?>"
                                    placeholder="https://t.me/yourchannel">
                                <span class="form-text">Main announcement / broadcast channel link shown to users.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_telegram_vip">⭐ Telegram VIP Group Link</label>
                                <input type="url" name="support_telegram_vip" id="support_telegram_vip" class="form-control"
                                    value="<?= sanitize($support_telegram_vip) ?>"
                                    placeholder="https://t.me/yourvipgroup">
                                <span class="form-text">VIP group link – shown when user claims commission reward (commission ≥ 500).</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_telegram_csr">🎧 Telegram Customer Support Link</label>
                                <input type="url" name="support_telegram_csr" id="support_telegram_csr" class="form-control"
                                    value="<?= sanitize(getSetting('support_telegram_csr') ?: 'https://t.me/NextPay_csr') ?>"
                                    placeholder="https://t.me/yourcsr">
                                <span class="form-text">Live customer service agent Telegram contact link.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_whatsapp">💬 WhatsApp Support Link</label>
                                <input type="url" name="support_whatsapp" id="support_whatsapp" class="form-control"
                                    value="<?= sanitize(getSetting('support_whatsapp') ?: '') ?>"
                                    placeholder="https://wa.me/9199XXXXXXXX">
                                <span class="form-text">WhatsApp click-to-chat link (optional).</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_email">📧 Support Email</label>
                                <input type="email" name="support_email" id="support_email" class="form-control"
                                    value="<?= sanitize(getSetting('support_email') ?: '') ?>"
                                    placeholder="support@NextPay.in">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_phone">📱 Support Phone / Mobile</label>
                                <input type="text" name="support_phone" id="support_phone" class="form-control"
                                    value="<?= sanitize(getSetting('support_phone') ?: '') ?>"
                                    placeholder="+91 9XXXXXXXXX">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="support_working_hours">🕐 Support Working Hours</label>
                                <input type="text" name="support_working_hours" id="support_working_hours" class="form-control"
                                    value="<?= sanitize(getSetting('support_working_hours') ?: '10:00 AM – 9:00 PM IST') ?>"
                                    placeholder="10:00 AM – 9:00 PM IST">
                                <span class="form-text">Shown on the customer support page as availability hours.</span>
                            </div>

                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════════ -->
                    <!-- SECTION: Social Media Links                             -->
                    <!-- ═══════════════════════════════════════════════════════ -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 6px;">🌐 Social Media Links</h3>
                        <p style="font-size: 12px; color: #a9b7d0; margin-bottom: 20px;">Optional social media profile URLs shown to users.</p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label" for="social_instagram">📸 Instagram</label>
                                <input type="url" name="social_instagram" id="social_instagram" class="form-control"
                                    value="<?= sanitize(getSetting('social_instagram') ?: '') ?>"
                                    placeholder="https://instagram.com/NextPay">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="social_facebook">👍 Facebook</label>
                                <input type="url" name="social_facebook" id="social_facebook" class="form-control"
                                    value="<?= sanitize(getSetting('social_facebook') ?: '') ?>"
                                    placeholder="https://facebook.com/NextPay">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="social_youtube">▶️ YouTube</label>
                                <input type="url" name="social_youtube" id="social_youtube" class="form-control"
                                    value="<?= sanitize(getSetting('social_youtube') ?: '') ?>"
                                    placeholder="https://youtube.com/@NextPay">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="social_twitter">🐦 Twitter / X</label>
                                <input type="url" name="social_twitter" id="social_twitter" class="form-control"
                                    value="<?= sanitize(getSetting('social_twitter') ?: '') ?>"
                                    placeholder="https://twitter.com/NextPay">
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════════ -->
                    <!-- SECTION: Tutorial Video & Help Links                    -->
                    <!-- ═══════════════════════════════════════════════════════ -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 6px;">🎬 Tutorial Video &amp; Help Links</h3>
                        <p style="font-size: 12px; color: #a9b7d0; margin-bottom: 20px;">Paste a YouTube link or direct MP4 URL. The tutorial page will auto-embed it for users.</p>

                        <div style="display: grid; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label" for="tutorial_video_url">🎥 Tutorial Video URL</label>
                                <input type="url" name="tutorial_video_url" id="tutorial_video_url" class="form-control"
                                    value="<?= sanitize($tutorial_video_url) ?>"
                                    placeholder="https://youtube.com/watch?v=... or https://example.com/tutorial.mp4">
                                <span class="form-text">Supports YouTube links (auto-converted to embed) and direct MP4 file URLs.</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="tutorial_video_title">🏷️ Tutorial Video Title</label>
                                <input type="text" name="tutorial_video_title" id="tutorial_video_title" class="form-control"
                                    value="<?= sanitize(getSetting('tutorial_video_title') ?: 'NextPay Platform Beginner Tutorial') ?>"
                                    placeholder="Tutorial Title">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="faq_link">❓ FAQ / Help Center Link</label>
                                <input type="url" name="faq_link" id="faq_link" class="form-control"
                                    value="<?= sanitize(getSetting('faq_link') ?: '') ?>"
                                    placeholder="https://...">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div class="form-group">
                                    <label class="form-label" for="terms_link">📄 Terms &amp; Conditions Link</label>
                                    <input type="url" name="terms_link" id="terms_link" class="form-control"
                                        value="<?= sanitize(getSetting('terms_link') ?: '') ?>"
                                        placeholder="https://...">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="privacy_link">🔒 Privacy Policy Link</label>
                                    <input type="url" name="privacy_link" id="privacy_link" class="form-control"
                                        value="<?= sanitize(getSetting('privacy_link') ?: '') ?>"
                                        placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════════ -->
                    <!-- SECTION: Commission & Security Rules                    -->
                    <!-- ═══════════════════════════════════════════════════════ -->
                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 6px;">⚙️ Commission &amp; Security Rules</h3>
                        <p style="font-size: 12px; color: #a9b7d0; margin-bottom: 20px;">Control referral commission rates, reward thresholds, OTP, and fraud protection.</p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label" for="commission_l1_rate">Level 1 Commission Rate</label>
                                <input type="number" step="0.0001" name="commission_l1_rate" id="commission_l1_rate" class="form-control"
                                    value="<?= sanitize(getSetting('commission_l1_rate') ?: '0.003') ?>">
                                <span class="form-text">e.g. 0.003 = 0.3% of buy order</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="commission_l2_rate">Level 2 Commission Rate</label>
                                <input type="number" step="0.0001" name="commission_l2_rate" id="commission_l2_rate" class="form-control"
                                    value="<?= sanitize(getSetting('commission_l2_rate') ?: '0.002') ?>">
                                <span class="form-text">e.g. 0.002 = 0.2% of buy order</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="commission_l3_rate">Level 3 Commission Rate</label>
                                <input type="number" step="0.0001" name="commission_l3_rate" id="commission_l3_rate" class="form-control"
                                    value="<?= sanitize(getSetting('commission_l3_rate') ?: '0.001') ?>">
                                <span class="form-text">e.g. 0.001 = 0.1% of buy order</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="commission_l4_rate">Level 4 Commission Rate</label>
                                <input type="number" step="0.0001" name="commission_l4_rate" id="commission_l4_rate" class="form-control"
                                    value="<?= sanitize(getSetting('commission_l4_rate') ?: '0.0005') ?>">
                                <span class="form-text">e.g. 0.0005 = 0.05% of buy order</span>
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label" for="commission_l5_rate">Level 5+ Commission Rate</label>
                                <input type="number" step="0.0001" name="commission_l5_rate" id="commission_l5_rate" class="form-control"
                                    value="<?= sanitize(getSetting('commission_l5_rate') ?: '0.0002') ?>">
                                <span class="form-text">e.g. 0.0002 = 0.02% of buy order</span>
                            </div>

                            <!-- Agent Panel Permissions -->
                            <div class="form-group" style="grid-column: span 2; margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                                <label class="form-label" style="font-weight: bold; margin-bottom: 12px; display: block;">Agent Panel Menu Access (Allowed Pages)</label>
                                <?php
                                $allowed_list = json_decode(getSetting('agent_allowed_pages'), true);
                                if ($allowed_list === null) {
                                    $allowed_list = [
                                        'team_daily.php', 'token_stmt.php', 'token_stmt_his.php', 
                                        'daily_stat.php', 'transfer_token.php', 'index.php', 
                                        'sub_agent.php', 'leaderboard.php', 'security.php', 
                                        'support_tickets.php', 'security_logs.php'
                                    ];
                                }
                                $all_agent_pages = [
                                    'index.php' => 'Home (Dashboard)',
                                    'team_daily.php' => 'Team Daily',
                                    'token_stmt.php' => 'Token Statement',
                                    'token_stmt_his.php' => 'Token Statement History',
                                    'daily_stat.php' => 'Daily Statistics',
                                    'transfer_token.php' => 'Transfer Token',
                                    'sub_agent.php' => 'Register Sub-Agent',
                                    'leaderboard.php' => 'Leaderboard',
                                    'security.php' => 'Security (Google 2FA)',
                                    'support_tickets.php' => 'Support Desk',
                                    'security_logs.php' => 'Security Logs'
                                ];
                                ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                                    <?php foreach ($all_agent_pages as $page_file => $page_label): ?>
                                        <label style="display: flex; align-items: center; gap: 8px; color: #a9b7d0; font-size: 14px; cursor: pointer;">
                                            <input type="checkbox" name="agent_allowed_pages[]" value="<?= $page_file ?>" <?= in_array($page_file, $allowed_list) ? 'checked' : '' ?> style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                                            <?= htmlspecialchars($page_label) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="commission_claim_min">Min Commission to Claim (₹)</label>
                                <input type="number" name="commission_claim_min" id="commission_claim_min" class="form-control"
                                    value="<?= sanitize(getSetting('commission_claim_min') ?: '500') ?>">
                                <span class="form-text">Users must reach this amount to see the claim popup.</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="commission_claim_hours">Commission Claim Hours</label>
                                <input type="text" name="commission_claim_hours" id="commission_claim_hours" class="form-control"
                                    value="<?= sanitize(getSetting('commission_claim_hours') ?: '11:00-21:00') ?>"
                                    placeholder="11:00-21:00">
                                <span class="form-text">Shown in the commission popup as valid claim window.</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="daily_checkin_bonus">Daily Check-in Bonus (ITokens)</label>
                                <input type="number" name="daily_checkin_bonus" id="daily_checkin_bonus" class="form-control"
                                    value="<?= sanitize(getSetting('daily_checkin_bonus') ?: '5') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="newbie_reward_amount">Newbie First-Deposit Reward (₹)</label>
                                <input type="number" name="newbie_reward_amount" id="newbie_reward_amount" class="form-control"
                                    value="<?= sanitize(getSetting('newbie_reward_amount') ?: '50') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="max_failed_utr_attempts">Max Fake UTR Attempts (before block)</label>
                                <input type="number" name="max_failed_utr_attempts" id="max_failed_utr_attempts" class="form-control"
                                    value="<?= sanitize(getSetting('max_failed_utr_attempts') ?: '3') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="login_otp_code">OTP Code (Login / Register verification)</label>
                                <input type="text" name="login_otp_code" id="login_otp_code" class="form-control"
                                    value="<?= sanitize(getSetting('login_otp_code') ?: '1234') ?>"
                                    placeholder="1234">
                                <span class="form-text">The static OTP code users must enter on login/register verification popup.</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="order_min_default">Default Order Min (₹)</label>
                                <input type="number" name="order_min_default" id="order_min_default" class="form-control"
                                    value="<?= sanitize(getSetting('order_min_default') ?: '100') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="order_max_default">Default Order Max (₹)</label>
                                <input type="number" name="order_max_default" id="order_max_default" class="form-control"
                                    value="<?= sanitize(getSetting('order_max_default') ?: '50000') ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>


        </main>
    </div>
</body>
</html>

