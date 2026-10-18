<?php
// admin/config.php - Shared Configuration Constants

// 1. Database Connections
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jalwaga2_dm');
define('DB_USER', 'jalwaga2_dm');
define('DB_PASS', 'jalwaga2_dm');

// 2. Application Configs
define('SITE_NAME', 'NextPay Admin Panel');
define('ADMIN_VERSION', '1.0.0');

// 3. Session Security
define('SESSION_SECURE', false); // Set to true if running on HTTPS

// 4. Admin Credentials (Database-independent login)
define('ADMIN_PHONE', '9999999999');  // Admin Login ID / Phone Number
define('ADMIN_PASSWORD', 'admin123'); // Admin Login Password

// 5. SMS Gateway Configurations (Fast2SMS Default)
define('SMS_PROVIDER', 'fast2sms'); // Can be 'fast2sms', 'twilio', etc.
define('SMS_API_KEY', 'YOUR_FAST2SMS_API_KEY_HERE'); // Enter your Fast2SMS API key here

