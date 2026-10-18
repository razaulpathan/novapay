-- ============================================================
-- Database Schema for NextPay Full-Stack Application
-- ============================================================
-- Setup Instructions:
--   1. Create a MySQL database (e.g. NextPay_db)
--   2. Import via phpMyAdmin or:  mysql -u root -p < schema.sql
--   3. Configure db.php with your host / user / pass / dbname
--
-- Default Admin Login:
--   Phone : 9999999999
--   Pass  : admin123
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- 1. USERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`                     INT AUTO_INCREMENT PRIMARY KEY,
  `phone`                  VARCHAR(15) UNIQUE NOT NULL,
  `password`               VARCHAR(255) NOT NULL,
  `itoken_balance`         DECIMAL(15,2) DEFAULT 0.00,
  `today_profit`           DECIMAL(15,2) DEFAULT 0.00,
  `reward_rate`            DECIMAL(5,2)  DEFAULT 2.50,
  `auto_selling`           TINYINT       DEFAULT 1     COMMENT '1=Sell Set active, 0=inactive',
  `order_min`              INT           DEFAULT 100,
  `order_max`              INT           DEFAULT 2000,
  `referred_by`            INT           DEFAULT NULL,
  `referral_code`          VARCHAR(20) UNIQUE NOT NULL,
  `google_2fa_secret`      VARCHAR(32)   DEFAULT NULL,
  `google_2fa_enabled`     TINYINT       DEFAULT 0,
  `role`                   VARCHAR(20)   DEFAULT 'user'   COMMENT 'user | agent | sub_admin | admin',
  `status`                 VARCHAR(20)   DEFAULT 'Active' COMMENT 'Active | Blocked | Suspended',
  `allow_create_sub_agent` TINYINT       DEFAULT 0,
  `last_checkin`           DATETIME      DEFAULT NULL,
  `failed_utr_attempts`    INT           DEFAULT 0,
  `created_at`             TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1233825101 DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 2. UPI ACCOUNTS  (linked payout UPI IDs)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `upi_accounts` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `partner`    VARCHAR(50)  NOT NULL  COMMENT 'Payment partner name e.g. PhonePe',
  `name`       VARCHAR(100) NOT NULL  COMMENT 'Account holder name',
  `upi_no`     VARCHAR(50)  NOT NULL  COMMENT 'UPI handle / IFSC / Account number',
  `status`     ENUM('Active','UnLink') DEFAULT 'Active',
  `stopped`    TINYINT DEFAULT 0      COMMENT '0=flowing, 1=stopped by admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 3. BUY ORDERS  (deposit orders)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `buy_orders` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `order_no`      VARCHAR(30) UNIQUE NOT NULL,
  `user_id`       INT NOT NULL,
  `type`          ENUM('UPI_PLUS','UPI','USDT') NOT NULL,
  `price`         DECIMAL(15,2) NOT NULL,
  `reward`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `itokens`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('Pending','Success','Cancelled') DEFAULT 'Pending',
  `utr`           VARCHAR(100) DEFAULT NULL   COMMENT 'UTR / transaction reference',
  `cancel_reason` VARCHAR(255) DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 4. SELL ORDERS  (withdrawal orders)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sell_orders` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `amount`     DECIMAL(15,2) NOT NULL,
  `status`     ENUM('Paying','Success','Failed') DEFAULT 'Paying',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 5. TOKEN TRANSFERS  (agent wallet transfers)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `token_transfers` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id`     INT NOT NULL,
  `target_phone` VARCHAR(15) NOT NULL,
  `target_uid`   INT NOT NULL,
  `amount`       DECIMAL(15,2) NOT NULL,
  `status`       ENUM('Pending','Success','Cancelled') DEFAULT 'Pending',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 6. SUPPORT TICKETS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `status`     ENUM('Open','Replied','Closed') DEFAULT 'Open',
  `reply`      TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 7. AUDIT LOGS  (login / action history)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 8. SETTINGS  (all platform config key/values)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 9. NEWBIE REWARDS  (first-time deposit bonuses)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `newbie_rewards` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL UNIQUE,
  `claimed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 10. DAILY CHECK-IN LOG  (Telegram bot /daily)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `daily_checkins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `bonus`      DECIMAL(10,2) DEFAULT 5.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 11. SITE ANNOUNCEMENTS / BROADCAST MESSAGES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT NOT NULL,
  `is_active`  TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 12. PUSH NOTIFICATION SUBSCRIBERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `push_subscribers` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT NOT NULL,
  `endpoint`     TEXT NOT NULL,
  `p256dh_key`   TEXT NOT NULL,
  `auth_key`     TEXT NOT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 13. DEVICE / SESSION FINGERPRINTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `device_sessions` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT NOT NULL,
  `fingerprint` VARCHAR(64) NOT NULL,
  `ip_address`  VARCHAR(45) NOT NULL,
  `user_agent`  VARCHAR(255) NOT NULL,
  `last_seen`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 14. COMMISSION PAYOUTS  (recorded commission credits)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `commission_payouts` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `earner_id`   INT NOT NULL   COMMENT 'User who earned the commission',
  `source_id`   INT NOT NULL   COMMENT 'User whose buy order triggered it',
  `level`       TINYINT NOT NULL COMMENT '1=direct referral, 2=indirect',
  `amount`      DECIMAL(15,2) NOT NULL,
  `order_no`    VARCHAR(30) DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`earner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`source_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 15. ADMIN OPERATION LOGS (admin action trail)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id`    INT NOT NULL,
  `action`      VARCHAR(255) NOT NULL,
  `target_id`   INT DEFAULT NULL,
  `target_type` VARCHAR(50)  DEFAULT NULL COMMENT 'user | order | ticket | setting',
  `details`     TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 16. PAYMENT PARTNERS (dynamic gateway partners)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payment_partners` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL UNIQUE,
  `type`       ENUM('Buy','Sell','Both') NOT NULL DEFAULT 'Sell',
  `logo_url`   VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_partners` (`name`, `type`) VALUES
('Amazon Pay', 'Both'),
('Freecharge', 'Buy'),
('Paytm', 'Sell'),
('Airtel', 'Sell'),
('PhonePe', 'Sell'),
('IndusPay', 'Sell'),
('BharatPe Business', 'Sell'),
('IOB UPI Vyapar', 'Sell')
ON DUPLICATE KEY UPDATE `type` = VALUES(`type`);

-- ─────────────────────────────────────────────
-- 17. HOME PAGE BANNERS  (slider banners on user home)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `home_banners` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `subtitle`   VARCHAR(500) DEFAULT '',
  `image_url`  VARCHAR(255) DEFAULT '',
  `link_url`   VARCHAR(255) DEFAULT '',
  `sort_order` INT          DEFAULT 0,
  `is_active`  TINYINT      DEFAULT 1,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 18. TUTORIAL VIDEOS  (multiple admin-uploaded videos)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tutorial_videos` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT         DEFAULT '',
  `video_url`   VARCHAR(500) NOT NULL    COMMENT 'YouTube URL or direct MP4 link',
  `thumbnail`   VARCHAR(255) DEFAULT ''  COMMENT 'Custom thumbnail path or empty (auto from YouTube)',
  `sort_order`  INT          DEFAULT 0,
  `is_active`   TINYINT      DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED: DEFAULT GLOBAL SETTINGS  (all keys used by the app)
-- ============================================================
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES

-- ── Exchange / Rates ──────────────────────────────────────────
('exchange_rate_usdt',          '106.7'),
('buy_ratio_nighttime',         '3.0'),
('default_reward_percentage',   '2.5'),

-- ── Admin Payment Receiving Details ──────────────────────────
('admin_upi_id',                'NextPayadmin@paytm'),
('admin_usdt_address',          'TY3q4UoE9sHh1eA7N2G1sW8yU7xP4mQ9zT'),
('admin_bank_name',             'AIRP0000001'),
('admin_bank_account',          '8506943929'),
('admin_bank_ifsc',             'AIRP0000001'),
('admin_bank_holder',           'PRATHAMJOHRI'),
('admin_pay_channel',           'freecharge(nirmal577@freecharge)'),

-- ── WatchPay Gateway ─────────────────────────────────────────
('payment_gateway_mode',        'Manual'),
('watchpay_merchant_id',        '100555004'),
('watchpay_api_key',            'b4051de2ecea122385551d9b28c78787'),
('watchpay_base_url',           'https://api.watchpays.com'),
('watchpay_create_path',        '/v1/create'),

-- ── System State ─────────────────────────────────────────────
('site_maintenance',            '0'),
('site_name',                   'NextPay'),
('site_logo_url',               ''),
('site_currency',               'INR'),

-- ── Homepage Popup Banners (3 slots) ─────────────────────────
('site_popup_message',          'Welcome to NextPay! Refer your friends and earn rewards.'),
('site_popup_header_1',         'Night-Time Buy Ratio Increased to 3.0%'),
('site_popup_image_1',          'images/banner_nighttime.png'),
('site_popup_header_2',         'GUIDANCE REWARDS'),
('site_popup_image_2',          'images/banner_guidance.png'),
('site_popup_header_3',         'BUY ORDER REWARDS'),
('site_popup_image_3',          'images/banner_latest_event.png'),

-- ── Telegram Bot ─────────────────────────────────────────────
('telegram_bot_token',          ''),
('telegram_bot_secret',         'SECRET_BOT_TOKEN_HERE'),
('telegram_bot_status',         '0'),
('telegram_group_chat_id',      ''),
('telegram_admin_chat_id',      ''),

-- ── Customer Care / Support Links ────────────────────────────
('support_telegram_channel',    'https://t.me/NextPay_channel'),
('support_telegram_vip',        'https://t.me/NextPay_vip'),
('support_telegram_csr',        'https://t.me/NextPay_csr'),
('support_whatsapp',            ''),
('support_email',               ''),
('support_phone',               ''),
('support_working_hours',       '10:00 AM – 9:00 PM IST'),

-- ── Tutorial / Help ──────────────────────────────────────────
('tutorial_video_url',          ''),
('tutorial_video_title',        'NextPay Platform Beginner Tutorial'),
('faq_link',                    ''),
('terms_link',                  ''),
('privacy_link',                ''),

-- ── Social Media ─────────────────────────────────────────────
('social_instagram',            ''),
('social_facebook',             ''),
('social_youtube',              ''),
('social_twitter',              ''),

-- ── Commission Rules ─────────────────────────────────────────
('commission_l1_rate',          '0.003'),
('commission_l2_rate',          '0.002'),
('commission_claim_min',        '500'),
('commission_claim_hours',      '11:00-21:00'),
('daily_checkin_bonus',         '5'),

-- ── Auto-Sell & Order Limits ─────────────────────────────────
('auto_sell_enabled',           '1'),
('order_min_default',           '100'),
('order_max_default',           '50000'),
('sell_min_amount',             '100'),
('sell_max_amount',             '50000'),

-- ── Fraud / Security ─────────────────────────────────────────
('max_failed_utr_attempts',     '3'),
('block_on_fake_utr',           '1'),
('login_otp_code',              '1234'),

-- ── Newbie Reward ────────────────────────────────────────────
('newbie_reward_amount',        '50'),
('newbie_reward_enabled',       '1')

ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ============================================================
-- SEED: DEFAULT ADMIN USER
-- Credentials:  Phone 9999999999  /  Password admin123
-- ============================================================
INSERT INTO `users`
  (`id`, `phone`, `password`, `itoken_balance`, `referral_code`, `role`, `status`)
VALUES
  (1, '9999999999',
   '$2y$10$D/j5qY2F1z3L8/b.rWwLueVnB42TfG21yJ9z2p4wG3e7F3x5o2q3e',
   100000.00, 'ADMINCODE', 'admin', 'Active')
ON DUPLICATE KEY UPDATE `phone` = `phone`;

