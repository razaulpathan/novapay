<?php
// db.php - Database connection & session helpers
ob_start(); // Buffer all output so header() redirects always work

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_name = 'jalwaga2_dm';
$db_user = 'jalwaga2_dm';
$db_pass = 'jalwaga2_dm';

// Load global configuration constants if available
if (file_exists(__DIR__ . '/admin/config.php')) {
    require_once __DIR__ . '/admin/config.php';
    $db_host = defined('DB_HOST') ? DB_HOST : $db_host;
    $db_name = defined('DB_NAME') ? DB_NAME : $db_name;
    $db_user = defined('DB_USER') ? DB_USER : $db_user;
    $db_pass = defined('DB_PASS') ? DB_PASS : $db_pass;
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Auto-migration: Ensure table buy_orders has 'utr' column
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'utr'");
        if (!$checkCol->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN utr VARCHAR(100) DEFAULT NULL AFTER status");
        }
        // Ensure table buy_orders has 'cancel_reason' column
        $checkColReason = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'cancel_reason'");
        if (!$checkColReason->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN cancel_reason VARCHAR(255) DEFAULT NULL AFTER utr");
        }
        // Ensure table users has 'status' column
        $checkColUser = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
        if (!$checkColUser->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'Active' AFTER role");
        }
        // Ensure table users has 'allow_create_sub_agent' column
        $checkColAllowAgent = $pdo->query("SHOW COLUMNS FROM users LIKE 'allow_create_sub_agent'");
        if (!$checkColAllowAgent->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN allow_create_sub_agent TINYINT DEFAULT 0 AFTER status");
        }
        
        // Ensure table users has Google 2FA columns
        $check2FASecret = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_2fa_secret'");
        if (!$check2FASecret->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_2fa_secret VARCHAR(32) DEFAULT NULL AFTER referral_code");
        }
        $check2FAEnabled = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_2fa_enabled'");
        if (!$check2FAEnabled->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_2fa_enabled TINYINT DEFAULT 0 AFTER google_2fa_secret");
        }

        // Ensure table users has last_checkin column
        $checkLastCheckin = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_checkin'");
        if (!$checkLastCheckin->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_checkin DATETIME DEFAULT NULL");
        }

        // Ensure table users has failed_utr_attempts column
        $checkFailedUtr = $pdo->query("SHOW COLUMNS FROM users LIKE 'failed_utr_attempts'");
        if (!$checkFailedUtr->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN failed_utr_attempts INT DEFAULT 0");
        }

        // Create token_transfers table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `token_transfers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `agent_id` INT NOT NULL,
            `target_phone` VARCHAR(15) NOT NULL,
            `target_uid` INT NOT NULL,
            `amount` DECIMAL(15, 2) NOT NULL,
            `status` ENUM('Pending', 'Success', 'Cancelled') DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create support_tickets table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('Open', 'Replied', 'Closed') DEFAULT 'Open',
            `reply` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create audit_logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(255) NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Create payment_partners table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_partners` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `type` ENUM('Buy', 'Sell', 'Both') NOT NULL DEFAULT 'Sell',
            `logo_url` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed default payment partners if table is empty
        $countPartners = $pdo->query("SELECT COUNT(*) FROM payment_partners")->fetchColumn();
        if ($countPartners == 0) {
            $stmtInsert = $pdo->prepare("INSERT INTO payment_partners (name, type) VALUES (?, ?)");
            $stmtInsert->execute(['Amazon Pay', 'Both']);
            $stmtInsert->execute(['Freecharge', 'Buy']);
            $stmtInsert->execute(['Paytm', 'Sell']);
            $stmtInsert->execute(['Airtel', 'Sell']);
            $stmtInsert->execute(['PhonePe', 'Sell']);
            $stmtInsert->execute(['IndusPay', 'Sell']);
            $stmtInsert->execute(['BharatPe Business', 'Sell']);
            $stmtInsert->execute(['IOB UPI Vyapar', 'Sell']);
        }

        // Auto-migration: Ensure table users role column to VARCHAR(20)
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) DEFAULT 'user'");
        
        // Auto-migration: Set users AUTO_INCREMENT starting value to 1233825101
        $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1233825101");
        
        // Seed default settings (all keys used by the platform)
        $stmtSeed = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = setting_value");

        // Exchange / Rates
        $stmtSeed->execute(['payment_gateway_mode',       'Manual']);
        $stmtSeed->execute(['watchpay_merchant_id',        '100555004']);
        $stmtSeed->execute(['watchpay_api_key',            'b4051de2ecea122385551d9b28c78787']);
        $stmtSeed->execute(['watchpay_base_url',           'https://api.watchpays.com']);
        $stmtSeed->execute(['watchpay_create_path',        '/v1/create']);

        // System State
        $stmtSeed->execute(['site_maintenance',            '0']);
        $stmtSeed->execute(['site_name',                   'NextPay']);
        $stmtSeed->execute(['site_logo_url',               '']);
        $stmtSeed->execute(['site_currency',               'INR']);

        // Homepage Popup Banners
        $stmtSeed->execute(['site_popup_message',          'Welcome to NextPay! Refer your friends and earn rewards.']);
        $stmtSeed->execute(['site_popup_header_1',         'Night-Time Buy Ratio Increased to 3.0%']);
        $stmtSeed->execute(['site_popup_image_1',          'images/banner_nighttime.png']);
        $stmtSeed->execute(['site_popup_header_2',         'GUIDANCE REWARDS']);
        $stmtSeed->execute(['site_popup_image_2',          'images/banner_guidance.png']);
        $stmtSeed->execute(['site_popup_header_3',         'BUY ORDER REWARDS']);
        $stmtSeed->execute(['site_popup_image_3',          'images/banner_latest_event.png']);

        // Admin Payment Details
        $stmtSeed->execute(['admin_bank_name',             'AIRP0000001']);
        $stmtSeed->execute(['admin_bank_account',          '8506943929']);
        $stmtSeed->execute(['admin_bank_ifsc',             'AIRP0000001']);
        $stmtSeed->execute(['admin_bank_holder',           'PRATHAMJOHRI']);
        $stmtSeed->execute(['admin_pay_channel',           'freecharge(nirmal577@freecharge)']);

        // Telegram Bot
        $stmtSeed->execute(['telegram_bot_token',          '']);
        $stmtSeed->execute(['telegram_bot_secret',         'SECRET_BOT_TOKEN_HERE']);
        $stmtSeed->execute(['telegram_bot_status',         '0']);
        $stmtSeed->execute(['telegram_group_chat_id',      '']);
        $stmtSeed->execute(['telegram_admin_chat_id',      '']);

        // Customer Care / Support Links
        $stmtSeed->execute(['support_telegram_channel',    'https://t.me/NextPay_channel']);
        $stmtSeed->execute(['support_telegram_vip',        'https://t.me/NextPay_vip']);
        $stmtSeed->execute(['support_telegram_csr',        'https://t.me/NextPay_csr']);
        $stmtSeed->execute(['support_whatsapp',            '']);
        $stmtSeed->execute(['support_email',               '']);
        $stmtSeed->execute(['support_phone',               '']);
        $stmtSeed->execute(['support_working_hours',       '10:00 AM – 9:00 PM IST']);

        // Tutorial / Help
        $stmtSeed->execute(['tutorial_video_url',          '']);
        $stmtSeed->execute(['tutorial_video_title',        'NextPay Platform Beginner Tutorial']);
        $stmtSeed->execute(['faq_link',                    '']);
        $stmtSeed->execute(['terms_link',                  '']);
        $stmtSeed->execute(['privacy_link',                '']);

        // Social Media
        $stmtSeed->execute(['social_instagram',            '']);
        $stmtSeed->execute(['social_facebook',             '']);
        $stmtSeed->execute(['social_youtube',              '']);
        $stmtSeed->execute(['social_twitter',              '']);

        // Commission Rules
        $stmtSeed->execute(['commission_l1_rate',          '0.003']);
        $stmtSeed->execute(['commission_l2_rate',          '0.002']);
        $stmtSeed->execute(['commission_claim_min',        '500']);
        $stmtSeed->execute(['commission_claim_hours',      '11:00-21:00']);
        $stmtSeed->execute(['daily_checkin_bonus',         '5']);

        // Auto-Sell & Order Limits
        $stmtSeed->execute(['auto_sell_enabled',           '1']);
        $stmtSeed->execute(['order_min_default',           '100']);
        $stmtSeed->execute(['order_max_default',           '50000']);
        $stmtSeed->execute(['sell_min_amount',             '100']);
        $stmtSeed->execute(['sell_max_amount',             '50000']);

        // Fraud / Security
        $stmtSeed->execute(['max_failed_utr_attempts',     '3']);
        $stmtSeed->execute(['block_on_fake_utr',           '1']);
        $stmtSeed->execute(['login_otp_code',              '1234']);

        // Newbie Reward
        $stmtSeed->execute(['newbie_reward_amount',        '50']);
        $stmtSeed->execute(['newbie_reward_enabled',       '1']);

        // Create rotating_upis table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `rotating_upis` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `upi_id` VARCHAR(100) NOT NULL UNIQUE,
            `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
            `order_count` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure table buy_orders has assigned_upi_id, assigned_upi_name, and payment_proof_img columns
        $checkColAssignedUpi = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'assigned_upi_id'");
        if (!$checkColAssignedUpi->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN assigned_upi_id VARCHAR(100) DEFAULT NULL AFTER type");
        }
        $checkColAssignedUpiName = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'assigned_upi_name'");
        if (!$checkColAssignedUpiName->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN assigned_upi_name VARCHAR(100) DEFAULT NULL AFTER assigned_upi_id");
        }
        $checkColProofImg = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'payment_proof_img'");
        if (!$checkColProofImg->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN payment_proof_img VARCHAR(255) DEFAULT NULL AFTER utr");
        }
        $checkColUserPayUpi = $pdo->query("SHOW COLUMNS FROM buy_orders LIKE 'user_pay_upi'");
        if (!$checkColUserPayUpi->fetch()) {
            $pdo->exec("ALTER TABLE buy_orders ADD COLUMN user_pay_upi VARCHAR(100) DEFAULT NULL AFTER assigned_upi_name");
        }

        // Create referral_rewards table to track referrer claims on referred users
        $pdo->exec("CREATE TABLE IF NOT EXISTS `referral_rewards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `referrer_id` INT NOT NULL,
            `referred_id` INT NOT NULL UNIQUE,
            `amount` DECIMAL(15, 2) DEFAULT 300.00,
            `status` ENUM('Pending', 'Claimed') DEFAULT 'Pending',
            `claimed_at` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (PDOException $ex) {
        // Suppress errors if table doesn't exist yet or other issues occur
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Authentication Helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'sub_admin');
}

// Smart login redirect — agent pages go to agent/login.php, others to /login.php
function _getLoginUrl() {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    // If running from inside the /agent/ directory → redirect to agent login
    if (strpos(str_replace('\\', '/', $script), '/agent/') !== false) {
        return 'login.php'; // relative: agent/login.php
    }
    // If running from inside /admin/ directory → redirect to admin login area
    if (strpos(str_replace('\\', '/', $script), '/admin/') !== false) {
        return '../login.php';
    }
    return 'login.php'; // root pages
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . _getLoginUrl());
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: " . _getLoginUrl());
        exit;
    }
}

// System Configurations Helper
function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function updateSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

// UPI Policy Helpers
function getPolicy(string $key, $default = null) {
    $value = getJsonSetting('upi_policy_' . $key, null);
    return $value !== null ? $value : $default;
}
function setPolicy(string $key, $value) {
    setJsonSetting('upi_policy_' . $key, $value);
}

// JSON Setting Helpers
function getJsonSetting($key, $default = null) {
    $json = getSetting($key);
    if ($json === null) {
        return $default;
    }
    $decoded = json_decode($json, true);
    return $decoded !== null ? $decoded : $default;
}
function setJsonSetting($key, $value) {
    $json = json_encode($value);
    return updateSetting($key, $json);
}

// Utility: Clean outputs
function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// SMS Helper: Sends SMS using Fast2SMS API
function sendSMS($phone, $message) {
    if (!defined('SMS_API_KEY') || empty(SMS_API_KEY) || SMS_API_KEY === 'YOUR_FAST2SMS_API_KEY_HERE') {
        // Fallback: If no API key, log to server error log
        error_log("SMS NOT SENT (API KEY MISSING): to $phone, msg: $message");
        return false;
    }
    
    // Clean phone number (keep only digits, must be 10 digits)
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) !== 10) {
        return false;
    }

    $provider = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'fast2sms';

    if ($provider === 'fast2sms') {
        $fields = [
            "route" => "q",
            "message" => $message,
            "language" => "english",
            "flash" => 0,
            "numbers" => $phone,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "authorization: " . SMS_API_KEY,
                "accept: */*",
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            error_log("Fast2SMS Error: " . $err);
            return false;
        } else {
            $resData = json_decode($response, true);
            if (isset($resData['return']) && $resData['return'] === true) {
                return true;
            }
            error_log("Fast2SMS Failed Response: " . $response);
            return false;
        }
    }
    return false;
}

// Global Maintenance Mode check
if (getSetting('site_maintenance') === '1' && !isAdmin()) {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    // Restrict access to standard user facing files
    if ($current_script !== 'login.php' && $current_script !== 'logout.php' && strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>NextPay - Under Maintenance</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
            <style>
                body {
                    margin: 0;
                    padding: 24px;
                    min-height: 100vh;
                    background: radial-gradient(circle at top left, #1b2640 0%, #0d121f 70%);
                    color: #f3f6f9;
                    font-family: 'Outfit', sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    text-align: center;
                }
                .card {
                    background: rgba(22, 29, 49, 0.7);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 20px;
                    padding: 40px 24px;
                    max-width: 450px;
                    backdrop-filter: blur(12px);
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
                }
                .icon {
                    width: 70px;
                    height: 70px;
                    background: rgba(35, 149, 238, 0.15);
                    color: #2395ee;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    box-shadow: 0 0 20px rgba(35, 149, 238, 0.2);
                }
                h1 {
                    font-size: 26px;
                    font-weight: 800;
                    margin: 0 0 12px;
                }
                p {
                    font-size: 15px;
                    color: #8c9cb5;
                    line-height: 1.5;
                    margin: 0 0 24px;
                }
                .btn-admin {
                    display: inline-block;
                    background: #1c82e6;
                    color: white;
                    text-decoration: none;
                    font-weight: bold;
                    padding: 12px 24px;
                    border-radius: 12px;
                    transition: background 0.2s;
                }
                .btn-admin:hover {
                    background: #0f6fcd;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width: 32px; height: 32px;">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </div>
                <h1>System Maintenance</h1>
                <p>We are currently performing scheduled maintenance to upgrade NextPay. We will be back online shortly. Thank you for your patience.</p>
                <a href="login.php" class="btn-admin">Admin Login</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Google Authenticator TOTP Helper Class
class Google2FA {
    private static $lut = [
        'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,  'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
        'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    ];

    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $base32 = str_replace('=', '', $base32);
        $len = strlen($base32);
        $n = 0;
        $j = 0;
        $binary = '';

        for ($i = 0; $i < $len; $i++) {
            if (!isset(self::$lut[$base32[$i]])) {
                continue;
            }
            $n = ($n << 5) | self::$lut[$base32[$i]];
            $j += 5;

            if ($j >= 8) {
                $j -= 8;
                $binary .= chr(($n >> $j) & 0xFF);
            }
        }
        return $binary;
    }

    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $key = self::base32Decode($secret);
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timeSlice = $currentTimeSlice + $i;
            $time = pack('N*', 0) . pack('N*', $timeSlice);
            $hmac = hash_hmac('sha1', $time, $key, true);
            $offset = ord(substr($hmac, -1)) & 0x0F;
            $hashpart = substr($hmac, $offset, 4);
            $value = unpack('N', $hashpart);
            $value = $value[1] & 0x7FFFFFFF;
            $calculatedCode = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);

            if ($calculatedCode === trim($code)) {
                return true;
            }
        }
        return false;
    }
}

// Multi-Level Commission Distribution Function
function distributeMultiLevelCommission($userId, $price) {
    global $pdo;
    $currentUserId = $userId;
    $level = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$currentUserId]);
        $parent = $stmt->fetch();
        
        if (!$parent || empty($parent['referred_by'])) {
            break;
        }
        
        $parentId = $parent['referred_by'];
        
        // Define commission rates for levels dynamically from settings
        if ($level === 1) {
            $rate = floatval(getSetting('commission_l1_rate') ?: '0.003');
        } elseif ($level === 2) {
            $rate = floatval(getSetting('commission_l2_rate') ?: '0.002');
        } elseif ($level === 3) {
            $rate = floatval(getSetting('commission_l3_rate') ?: '0.001');
        } elseif ($level === 4) {
            $rate = floatval(getSetting('commission_l4_rate') ?: '0.0005');
        } else {
            $rate = floatval(getSetting('commission_l5_rate') ?: '0.0002');
        }
        
        $commission = $price * $rate;
        
        if ($commission > 0) {
            $stmtUpdate = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, today_profit = today_profit + ? WHERE id = ?");
            $stmtUpdate->execute([$commission, $commission, $parentId]);
        }
        
        $currentUserId = $parentId;
        $level++;
        
        if ($level > 10) {
            break;
        }
    }
}

// Global UPI Rotation logic helper
function get_next_rotating_upi($pdo) {
    // 1. Fetch all active rotating UPIs
    $stmt = $pdo->query("SELECT * FROM rotating_upis WHERE status = 'Active' ORDER BY id ASC");
    $active_upis = $stmt->fetchAll();
    
    if (empty($active_upis)) {
        // Fallback to settings
        $admin_upi = getSetting('admin_upi_id') ?: 'NextPayadmin@paytm';
        $admin_bank_holder = getSetting('admin_bank_holder') ?: 'PRATHAMJOHRI';
        return [
            'upi_id' => $admin_upi,
            'name' => $admin_bank_holder
        ];
    }
    
    // 2. Load settings for rotation
    $interval_mins = intval(getSetting('rotation_interval_minutes') ?: '2');
    $max_orders = intval(getSetting('max_orders_per_upi') ?: '35');
    $current_id = intval(getSetting('current_active_upi_id') ?: '0');
    $last_time = intval(getSetting('last_rotation_time') ?: '0');
    $current_count = intval(getSetting('current_upi_order_count') ?: '0');
    
    // Find if current active UPI exists in active list
    $current_upi = null;
    $current_index = -1;
    foreach ($active_upis as $index => $upi) {
        if ($upi['id'] == $current_id) {
            $current_upi = $upi;
            $current_index = $index;
            break;
        }
    }
    
    // If no active UPI set or it's not active anymore, pick the first one
    if (!$current_upi) {
        $current_upi = $active_upis[0];
        $current_index = 0;
        $current_id = $current_upi['id'];
        $last_time = time();
        $current_count = 0;
        
        updateSetting('current_active_upi_id', $current_id);
        updateSetting('last_rotation_time', $last_time);
        updateSetting('current_upi_order_count', $current_count);
    }
    
    // Check rotation triggers: time or order count limit
    $now = time();
    $time_elapsed = $now - $last_time;
    $should_rotate = false;
    
    if ($time_elapsed >= ($interval_mins * 60)) {
        $should_rotate = true;
    }
    if ($current_count >= $max_orders) {
        $should_rotate = true;
    }
    
    if ($should_rotate) {
        // Switch to the next UPI in sequence
        $next_index = ($current_index + 1) % count($active_upis);
        $current_upi = $active_upis[$next_index];
        $current_id = $current_upi['id'];
        $last_time = $now;
        $current_count = 0;
        
        updateSetting('current_active_upi_id', $current_id);
        updateSetting('last_rotation_time', $last_time);
        updateSetting('current_upi_order_count', $current_count);
    }
    
    // Increment the order count
    $current_count++;
    updateSetting('current_upi_order_count', $current_count);
    
    // Update total count on the rotating_upis table
    $stmtUpdate = $pdo->prepare("UPDATE rotating_upis SET order_count = order_count + 1 WHERE id = ?");
    $stmtUpdate->execute([$current_id]);
    
    return [
        'upi_id' => $current_upi['upi_id'],
        'name' => $current_upi['name']
    ];
}

/**
 * Triggers an unconditional automatic token sell order for the user
 * when their buy order successfully completes. Sends Telegram alert to Admin.
 */
function triggerAutoSell($pdo, $order_id) {
    // 1. Fetch the completed buy order details
    $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order) return;

    $user_id = $order['user_id'];
    $amount = $order['itokens']; // The tokens they just successfully bought

    // 2. Fetch user's phone number
    $uStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $user = $uStmt->fetch();
    if (!$user) return;
    $user_phone = $user['phone'];

    // 3. Fetch user's active UPI account (their "selling UPI ID")
    $upiStmt = $pdo->prepare("SELECT partner, name, upi_no FROM upi_accounts WHERE user_id = ? AND status = 'Active' AND stopped = 0 LIMIT 1");
    $upiStmt->execute([$user_id]);
    $upi = $upiStmt->fetch();
    
    $upi_no = 'None Link';
    $upi_partner = '-';
    $upi_name = '-';
    if ($upi) {
        $upi_no = $upi['upi_no'];
        $upi_partner = $upi['partner'];
        $upi_name = $upi['name'];
    }

    // 4. Create a new sell order unconditionally
    $sellStmt = $pdo->prepare("INSERT INTO sell_orders (user_id, amount, status) VALUES (?, ?, 'Paying')");
    $sellStmt->execute([$user_id, $amount]);
    $sell_order_id = $pdo->lastInsertId();

    // 5. Send Telegram Bot notification to the admin with user phone number, sell order number, and selling UPI ID
    $bot_token = getSetting('telegram_bot_token');
    $bot_status = getSetting('telegram_bot_status');
    $admin_chat_id = getSetting('telegram_admin_chat_id');

    if ($bot_status === '1' && !empty($bot_token) && !empty($admin_chat_id)) {
        $msg = "📤 <b>Automatic Sell Order Created!</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "👤 <b>User Phone:</b> <code>{$user_phone}</code> (ID: {$user_id})\n";
        $msg .= "🏷️ <b>Sell Order ID:</b> <code>#{$sell_order_id}</code>\n";
        $msg .= "📥 <b>From Buy Order:</b> <code>#{$order['order_no']}</code>\n";
        $msg .= "💵 <b>Amount:</b> <code>₹ " . number_format($amount, 2) . "</code>\n";
        $msg .= "💳 <b>Selling UPI ID:</b> <code>{$upi_no}</code> ({$upi_partner} - {$upi_name})\n";
        $msg .= "🕒 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";

        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $post_fields = [
            'chat_id' => $admin_chat_id,
            'text' => $msg,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}
?>

