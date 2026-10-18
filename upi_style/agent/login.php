<?php
// agent/login.php - Promoter Login
ob_start();
require_once '../db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = isset($_POST['phone'])    ? trim($_POST['phone'])  : '';
    $password = isset($_POST['password']) ? $_POST['password']    : '';

    if (empty($phone) || empty($password)) {
        $error_msg = "Please enter both phone number and password.";
    } else {
        // Fetch user from DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'Blocked') {
                $error_msg = "Your account has been blocked by the administrator.";
            } elseif (!in_array($user['role'], ['Agent', 'Admin', 'SubAdmin'])) {
                $error_msg = "This panel is restricted to promoter/agent accounts only.";
            } else {
                // Set temporary session for 2FA verification
                $_SESSION['temp_2fa_user_id'] = $user['id'];
                $_SESSION['temp_2fa_phone']   = $user['phone'];
                $_SESSION['temp_2fa_role']    = $user['role'];

                // Log the login attempt
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, ip_address, user_agent, action) VALUES (?, ?, ?, 'Agent Login Initiated')");
                    $stmtLog->execute([$user['id'], $ip, $ua]);
                } catch (Exception $e) {}

                header("Location: verify_2fa.php");
                exit;
            }
        } else {
            $error_msg = "Invalid phone number or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay Promoter / Agent Panel - Sign In</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background: #ffffff;
      color: #333333;
      font-family: 'Outfit', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-wrapper {
      width: 100%;
      max-width: 1100px;
      padding: 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 60px;
    }

    /* Left panel */
    .hero-section {
      display: flex;
      flex-direction: column;
      gap: 20px;
      max-width: 480px;
      width: 50%;
    }

    .hero-header {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .hero-brand {
      font-size: 36px;
      font-weight: 800;
      color: #ff2d55; /* Magenta theme color */
      letter-spacing: -0.5px;
    }

    .hero-tagline {
      font-size: 13.5px;
      color: #8c9cb5;
      font-weight: 500;
      letter-spacing: 0.5px;
    }

    /* Interactive vector login graphic */
    .graphic-box {
      width: 100%;
      height: 300px;
      background: #fff5f7;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px dashed rgba(255, 45, 85, 0.15);
      position: relative;
      overflow: hidden;
    }

    .graphic-circle {
      position: absolute;
      width: 160px;
      height: 160px;
      background: rgba(255, 45, 85, 0.05);
      border-radius: 50%;
    }

    .graphic-circle.c1 {
      top: -30px;
      left: -30px;
    }

    .graphic-circle.c2 {
      bottom: -40px;
      right: -20px;
      width: 220px;
      height: 220px;
    }

    .graphic-svg {
      width: 180px;
      height: auto;
      z-index: 2;
    }

    /* Right panel */
    .form-section {
      width: 50%;
      max-width: 420px;
      display: flex;
      justify-content: center;
    }

    .login-card {
      width: 100%;
      background: #ffffff;
      border-radius: 16px;
      padding: 36px 30px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
      border: 1px solid #ebedf2;
    }

    .form-title {
      font-size: 22px;
      font-weight: 800;
      color: #2c3e50;
      margin-bottom: 24px;
      text-align: center;
    }

    /* Alerts */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 13px;
      font-weight: 600;
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      color: #e74c3c;
    }

    .field {
      margin-bottom: 18px;
    }

    .field-input-wrapper {
      display: flex;
      align-items: center;
      height: 50px;
      border-radius: 8px;
      background: #ffffff;
      border: 1px solid #ebedf2;
      overflow: hidden;
      padding: 0 16px;
      gap: 12px;
      transition: all 0.2s;
    }

    .field-input-wrapper:focus-within {
      border-color: #ff2d55;
      box-shadow: 0 0 0 3px rgba(255, 45, 85, 0.08);
    }

    .field-input-wrapper svg {
      width: 20px;
      height: 20px;
      color: #8c9cb5;
      flex: 0 0 auto;
    }

    .field-input-wrapper input {
      width: 100%;
      height: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      color: #333333;
      font-size: 14.5px;
      font-weight: 500;
      font-family: inherit;
    }

    .login-btn {
      width: 100%;
      height: 50px;
      border: 0;
      border-radius: 8px;
      background: #ff2d55; /* Magenta theme */
      color: white;
      cursor: pointer;
      font-size: 15px;
      font-weight: 700;
      margin-top: 10px;
      box-shadow: 0 4px 14px rgba(255, 45, 85, 0.3);
      transition: all 0.2s;
    }

    .login-btn:hover {
      opacity: 0.95;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(255, 45, 85, 0.4);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .return-link {
      display: block;
      text-align: center;
      margin-top: 24px;
      font-size: 13.5px;
      color: #8c9cb5;
      text-decoration: none;
      font-weight: 600;
    }
    
    .return-link:hover {
      color: #ff2d55;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .login-wrapper {
        flex-direction: column;
        padding: 24px;
        gap: 30px;
      }
      .hero-section {
        display: none;
      }
      .form-section {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <!-- Left Hero Section -->
    <div class="hero-section">
      <div class="hero-header">
        <h1 class="hero-brand">NextPay</h1>
        <p class="hero-tagline">Fast, highly, security, respect</p>
      </div>
      <!-- Premium CSS vector graphic illustration -->
      <div class="graphic-box">
        <div class="graphic-circle c1"></div>
        <div class="graphic-circle c2"></div>
        <svg class="graphic-svg" viewBox="0 0 200 200" fill="none">
          <!-- Stand/Desk -->
          <rect x="30" y="160" width="140" height="8" rx="4" fill="#ff2d55" opacity="0.3"/>
          <rect x="40" y="152" width="120" height="8" rx="2" fill="#ff2d55" opacity="0.6"/>
          <!-- Device Shield / Card -->
          <rect x="60" y="40" width="80" height="110" rx="12" fill="#ffffff" stroke="#ff2d55" stroke-width="6"/>
          <!-- Security lock checkmark -->
          <circle cx="100" cy="90" r="28" fill="#ff2d55"/>
          <path d="M90 90 L97 97 L110 84" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M90 55 C90 55 90 40 100 40 C110 40 110 55 110 55" stroke="#ff2d55" stroke-width="4" stroke-linecap="round"/>
        </svg>
      </div>
    </div>

    <!-- Right Login Section -->
    <div class="form-section">
      <main class="login-card">
        <!-- Agent Panel Label -->
        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255,45,85,0.08); border: 1px solid rgba(255,45,85,0.2); border-radius: 20px; padding: 5px 14px; margin-bottom: 16px;">
          <span style="font-size: 11px; font-weight: 800; color: #ff2d55; text-transform: uppercase; letter-spacing: 1px;">🎯 Promoter / Agent Panel</span>
        </div>
        <h2 class="form-title">Agent Sign In</h2>
        <p style="font-size: 13px; color: #8c9cb5; margin-bottom: 24px; margin-top: -8px;">This login is for Promoters & Agents only.</p>
        
        <?php if (!empty($error_msg)): ?>
          <div class="alert"><?= sanitize($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
          <!-- Username/Phone -->
          <div class="field">
            <div class="field-input-wrapper">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <input type="tel" name="phone" placeholder="Phone Number" required value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>">
            </div>
          </div>

          <!-- Password -->
          <div class="field">
            <div class="field-input-wrapper">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <input type="password" name="password" placeholder="Password" required>
            </div>
          </div>

          <button class="login-btn" type="submit">Sign In to Agent Panel</button>
          
          <a href="../login.php" class="return-link">&larr; Not an agent? Go to User Login</a>
        </form>
      </main>
    </div>
  </div>
</body>
</html>

