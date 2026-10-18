<?php
// editpass.php - Modify user password
require_once 'db.php';

$error_msg = '';
$success_msg = '';

// Check if user is logged in to pre-fill phone
$prefilled_phone = '';
if (isLoggedIn()) {
    $prefilled_phone = $_SESSION['phone'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';

    if (empty($phone) || empty($password) || empty($confirm)) {
        $error_msg = 'Please fill out all fields.';
    } elseif ($password !== $confirm) {
        $error_msg = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters long.';
    } else {
        // Look up user by phone
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user_id = $stmt->fetchColumn();

        if ($user_id) {
            // If logged in, ensure they are modifying their own password
            if (isLoggedIn() && $_SESSION['user_id'] != $user_id) {
                $error_msg = 'You can only modify your own password.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$hashed_password, $user_id]);
                $success_msg = 'Password modified successfully! Redirecting...';
                echo "<script>setTimeout(() => { window.location.href = 'login.php'; }, 2000);</script>";
            }
        } else {
            $error_msg = 'Phone number not registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Modify Password</title>
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --blue: #2395ee;
      --deep-blue: #07588f;
      --soft-blue: #f8fcff;
      --line: #e8f1f7;
      --muted: #b8c0c8;
      --bg-color: #f7f9fc;
      --text-dark: #333333;
      --error-red: #e74c3c;
      --success-green: #2ecc71;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #eef2f5;
      color: var(--text-dark);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .screen {
      width: min(100%, 574px);
      min-height: 100vh;
      background: #ffffff;
      position: relative;
      padding-bottom: 80px; /* Space for fixed bottom nav */
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Header styling */
    .header {
      height: 56px;
      display: flex;
      align-items: center;
      padding: 0 16px;
      border-bottom: 1px solid #f5f7fa;
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 10;
    }

    .header-back {
      color: #2c3e50;
      text-decoration: none;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .header-back svg {
      width: 24px;
      height: 24px;
    }

    .header-title {
      font-size: 18px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0 auto;
      transform: translateX(-12px); /* Centering offset */
    }

    .content {
      padding: 24px 20px;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Alerts */
    .alert {
      width: 100%;
      padding: 12px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
    }
    .alert-error {
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      color: var(--error-red);
    }
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #dcfce7;
      color: var(--success-green);
    }

    /* Avatar header */
    .avatar-wrapper {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: #eef5fc;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #d4e6f1;
      overflow: hidden;
      margin-bottom: 14px;
    }

    .form-heading {
      font-size: 20px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0 0 28px 0;
    }

    /* Form Fields container */
    .form-container {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .form-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 4px;
      border-bottom: 1.5px solid #f2f5f8;
      position: relative;
    }

    .row-label {
      font-size: 18px;
      font-weight: 800;
      color: #111111;
      width: 100px;
      flex-shrink: 0;
    }

    .text-input {
      flex: 1;
      border: none;
      outline: none;
      font-size: 18px;
      font-family: inherit;
      color: #2c3e50;
      padding: 0;
      background: transparent;
      text-align: right;
    }

    .text-input::placeholder {
      color: var(--muted);
      font-family: Arial, sans-serif;
    }

    /* Login redirect link */
    .login-redirect-row {
      width: 100%;
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }

    .login-link {
      color: var(--blue);
      text-decoration: none;
      font-size: 14px;
      font-weight: 800;
    }

    .login-link:hover {
      text-decoration: underline;
    }

    /* Cloudflare Turnstile Box Mock */
    .turnstile-wrapper {
      margin-top: 24px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 6px;
    }

    .turnstile-box {
      width: 100%;
      max-width: 300px;
      height: 65px;
      border: 1px solid #d4d8db;
      border-radius: 4px;
      background: #fafafa;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .turnstile-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Loader circle animation */
    .turnstile-loader {
      width: 24px;
      height: 24px;
      position: relative;
    }

    .loader-dots {
      width: 100%;
      height: 100%;
      animation: spin 3s linear infinite;
    }

    .loader-dot {
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #27ae60;
      position: absolute;
    }

    /* Position dots in circle */
    .loader-dot:nth-child(1) { top: 2px; left: 11px; opacity: 1; }
    .loader-dot:nth-child(2) { top: 5px; right: 5px; opacity: 0.9; }
    .loader-dot:nth-child(3) { top: 11px; right: 2px; opacity: 0.8; }
    .loader-dot:nth-child(4) { bottom: 5px; right: 5px; opacity: 0.7; }
    .loader-dot:nth-child(5) { bottom: 2px; left: 11px; opacity: 0.6; }
    .loader-dot:nth-child(6) { bottom: 5px; left: 5px; opacity: 0.5; }
    .loader-dot:nth-child(7) { top: 11px; left: 2px; opacity: 0.4; }
    .loader-dot:nth-child(8) { top: 5px; left: 5px; opacity: 0.3; }

    @keyframes spin {
      100% { transform: rotate(360deg); }
    }

    /* Green checkmark */
    .checkmark-svg {
      width: 24px;
      height: 24px;
      color: #27ae60;
      display: none;
    }

    .turnstile-status {
      font-size: 14px;
      font-weight: 500;
      color: #333333;
      font-family: Arial, sans-serif;
    }

    .turnstile-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      line-height: 1;
    }

    .cf-logo-row {
      display: flex;
      align-items: center;
      gap: 4px;
      margin-bottom: 2px;
    }

    .cf-logo-text {
      font-size: 9px;
      font-weight: 900;
      letter-spacing: 0.2px;
      color: #111111;
      font-family: 'Segoe UI', Arial, sans-serif;
    }

    .cf-logo-cloud {
      width: 14px;
      height: 10px;
      color: #f38020;
    }

    .cf-subtext {
      font-size: 8px;
      color: #777777;
      font-family: Arial, sans-serif;
    }

    .turnstile-footer-warning {
      border: 1px solid #ea8685;
      padding: 3px 6px;
      font-size: 9.5px;
      color: #cf6a87;
      font-family: monospace;
      max-width: 300px;
    }

    /* Submit Button styling */
    .submit-btn-container {
      width: 100%;
      margin-top: 48px;
    }

    .submit-btn {
      width: 100%;
      height: 54px;
      border: none;
      background: #2395ee;
      color: #ffffff;
      border-radius: 12px;
      font: 800 18px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(35, 149, 238, 0.25);
      transition: background-color 0.2s, transform 0.1s;
    }

    .submit-btn:hover {
      background: #0d7dd4;
    }

    .submit-btn:active {
      transform: scale(0.98);
    }

    /* Floating Chat Button */
    .fab {
      position: absolute;
      bottom: 24px;
      right: 18px;
      width: 48px;
      height: 48px;
      background: #3a6bf6;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 4px 12px rgba(58, 107, 246, 0.4);
      cursor: pointer;
      z-index: 10;
    }

    .fab svg {
      width: 24px;
      height: 24px;
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <a href="mine.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Modify password</h1>
    </header>

    <div class="content">
      
      <!-- Avatar vector -->
      <div class="avatar-wrapper">
        <svg viewBox="0 0 120 120" style="width: 100%; height: 100%;">
          <circle cx="60" cy="60" r="60" fill="#e2eefc"/>
          <path d="M60 70 A 24 24 0 0 0 36 94 A 8 8 0 0 0 44 102 L 76 102 A 8 8 0 0 0 84 94 A 24 24 0 0 0 60 70 Z" fill="#07588f"/>
          <circle cx="60" cy="46" r="16" fill="#fbb0ac" />
          <path d="M44 46 Q60 54 76 46 Z" fill="#07588f" opacity="0.3"/>
          <rect x="58" y="58" width="4" height="14" rx="2" fill="#fbb0ac"/>
          <path d="M44 38 Q60 30 76 38" fill="none" stroke="#07588f" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>

      <h2 class="form-heading">Modify password Form</h2>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error"><?= sanitize($error_msg) ?></div>
      <?php endif; ?>

      <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= sanitize($success_msg) ?></div>
      <?php endif; ?>

      <!-- Modify form container -->
      <form class="form-container" method="POST" action="editpass.php" onsubmit="return validateForm()">
        
        <!-- Phone Field -->
        <div class="form-row">
          <span class="row-label">Phone</span>
          <input type="tel" name="phone" class="text-input" placeholder="Enter phone number" id="phoneInput" required value="<?= sanitize($prefilled_phone) ?>" <?= !empty($prefilled_phone) ? 'readonly' : '' ?>>
        </div>

        <!-- Password Field -->
        <div class="form-row">
          <span class="row-label">Password</span>
          <input type="password" name="password" class="text-input" placeholder="Enter password" id="passInput" required>
        </div>

        <!-- Confirm Field -->
        <div class="form-row">
          <span class="row-label">Confirm</span>
          <input type="password" name="confirm" class="text-input" placeholder="Password, Again" id="confirmInput" required>
        </div>

        <!-- Go Login Redirect -->
        <div class="login-redirect-row">
          <a href="login.php" class="login-link">Already have an account, Go Login</a>
        </div>

        <!-- Captcha Turnstile Widget -->
        <div class="turnstile-wrapper">
          <div class="turnstile-box">
            <div class="turnstile-left">
              <div class="turnstile-loader" id="turnstileLoader">
                <div class="loader-dots">
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                  <div class="loader-dot"></div>
                </div>
              </div>
              <!-- Green Checkmark SVG -->
              <svg class="checkmark-svg" id="turnstileSuccess" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span class="turnstile-status" id="turnstileStatus">Verifying...</span>
            </div>
            <div class="turnstile-right">
              <div class="cf-logo-row">
                <span class="cf-logo-text">CLOUDFLARE</span>
                <svg class="cf-logo-cloud" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
                </svg>
              </div>
              <span class="cf-subtext">Privacy · Help</span>
            </div>
          </div>
          <div class="turnstile-footer-warning">
            For testing only. If seen, report to site owner
          </div>
        </div>

        <!-- Submit Action -->
        <div class="submit-btn-container">
          <button type="submit" class="submit-btn">Modify</button>
        </div>

      </form>

    </div>

    <!-- Floating Chat Button -->
    <div class="fab" onclick="location.href='customer.php'">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <circle cx="9" cy="10" r="1"></circle>
        <circle cx="13" cy="10" r="1"></circle>
        <circle cx="17" cy="10" r="1"></circle>
      </svg>
    </div>

  </div>

  <script>
    let isVerified = false;

    // Simulate Cloudflare Turnstile Verification
    setTimeout(() => {
      document.getElementById('turnstileLoader').style.display = 'none';
      document.getElementById('turnstileSuccess').style.display = 'block';
      document.getElementById('turnstileStatus').innerText = 'Success';
      isVerified = true;
    }, 1500);

    function validateForm() {
      const pass = document.getElementById('passInput').value;
      const confirm = document.getElementById('confirmInput').value;

      if (!isVerified) {
        alert("Please wait for security verification to complete.");
        return false;
      }

      if (pass !== confirm) {
        alert("Passwords do not match!");
        return false;
      }
      return true;
    }
  </script>
</body>
</html>

