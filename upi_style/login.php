<?php
// login.php - User Login
ob_start();
require_once 'db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: home.php");
    }
    exit;
}

$error_msg = '';
$success_msg = isset($_GET['success']) ? 'Registration successful! Please login.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $agree = isset($_POST['agree']) ? true : false;

    if (empty($phone) || empty($password)) {
        $error_msg = "Please enter both phone number and password.";
    } elseif (!$agree) {
        $error_msg = "You must agree to the User Privacy Agreement.";
    } else {
        // Fetch user from DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check if user is blocked
            if (isset($user['status']) && $user['status'] === 'Blocked') {
                $error_msg = "Your account has been blocked by the administrator.";
            } elseif ($user['role'] === 'Agent') {
                $error_msg = "Agent accounts cannot log in to the user portal.";
            } else {
                // Set temporary session for 2FA/OTP verification
                $_SESSION['temp_2fa_user_id'] = $user['id'];
                $_SESSION['temp_2fa_phone']   = $user['phone'];
                $_SESSION['temp_2fa_role']    = $user['role'];

                // Audit log
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, ip_address, user_agent, action) VALUES (?, ?, ?, 'Login Initiated')");
                    $stmtLog->execute([$user['id'], $ip, $ua]);
                } catch (Exception $e) { /* suppress if audit_logs not ready */ }

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
  <title>NextPay - Login</title>
  <!-- Link to premium styles -->
  <link rel="stylesheet" href="website_style.css">
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
      --error-red: #e74c3c;
      --success-green: #2ecc71;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #ffffff;
      color: var(--deep-blue);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .screen {
      width: min(100%, 574px);
      min-height: 100vh;
      margin: 0 auto;
      background: #ffffff;
      padding: 48px 30px 28px;
    }

    .logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 18px;
      margin: 4px auto 80px;
    }

    .logo-mark {
      width: 120px;
      height: 140px;
      flex: 0 0 auto;
    }

    .brand {
      color: var(--deep-blue);
      font-family: Arial, Helvetica, sans-serif;
      font-size: clamp(52px, 14vw, 84px);
      font-weight: 800;
      letter-spacing: 0;
      line-height: 1;
    }

    .form {
      width: 100%;
    }

    /* Alerts */
    .alert {
      padding: 12px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      font-weight: bold;
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

    .field {
      position: relative;
      display: flex;
      align-items: center;
      height: 76px;
      margin: 0 8px 28px;
      border-radius: 42px;
      background: var(--soft-blue);
      border-bottom: 1px solid var(--line);
      overflow: hidden;
    }

    .field svg {
      width: 34px;
      height: 34px;
      margin-left: 28px;
      color: #1c94df;
      flex: 0 0 auto;
    }

    .field input {
      width: 100%;
      min-width: 0;
      height: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      padding: 0 22px 0 16px;
      color: #3f596b;
      font: 700 19px/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .field input::placeholder {
      color: var(--muted);
      opacity: 1;
    }

    .options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      margin: -4px 30px 48px 8px;
      color: #0f8edd;
      font-size: 18px;
      font-weight: 700;
      line-height: 1.2;
    }

    .save {
      display: inline-flex;
      align-items: center;
      gap: 11px;
      white-space: nowrap;
      cursor: pointer;
    }

    .radio {
      width: 22px;
      height: 22px;
      border: 2px solid #c9cfd4;
      border-radius: 50%;
      display: inline-block;
      background: #fff;
      flex: 0 0 auto;
      position: relative;
    }

    .radio-input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .radio-input:checked ~ .radio .radio-checkmark {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--blue);
      display: block;
      position: absolute;
      top: 3px;
      left: 3px;
    }

    .forgot {
      color: #0f8edd;
      text-decoration: none;
      white-space: nowrap;
    }

    .login-btn {
      width: 100%;
      height: 76px;
      border: 0;
      border-radius: 10px;
      background: var(--blue);
      color: white;
      cursor: pointer;
      font: 800 22px/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      box-shadow: 0 4px 12px rgba(35, 149, 238, 0.2);
    }

    .login-btn:hover {
      background: #0f7cd5;
    }

    .agreement {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 14px;
      color: #197cbd;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.2;
    }

    .check-container {
      cursor: pointer;
      display: flex;
      align-items: center;
      position: relative;
    }

    .check-input {
      position: absolute;
      opacity: 0;
      cursor: pointer;
      height: 0;
      width: 0;
    }

    .check-checkmark {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #cbd5e0;
      display: inline-grid;
      place-items: center;
      color: #fff;
    }

    .check-input:checked ~ .check-checkmark {
      background: var(--blue);
    }

    .agreement a {
      color: #197cbd;
      text-decoration: underline;
    }

    @media (max-width: 430px) {
      .screen {
        padding: 42px 22px 24px;
      }
      .logo {
        gap: 12px;
        margin-bottom: 56px;
      }
      .logo-mark {
        width: 96px;
        height: 110px;
      }
      .brand {
        font-size: 60px;
      }
      .field {
        height: 68px;
        margin-bottom: 20px;
      }
      .options {
        margin-right: 8px;
        font-size: 15px;
        margin-bottom: 36px;
      }
      .login-btn {
        height: 68px;
      }
    }
  </style>
</head>
<body>
  <main class="screen">
    <section class="logo" aria-label="NextPay">
      <img class="logo-mark" src="images/Login_Logo.png" alt="NextPay logo" style="width: 100px; height: auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
      <div class="brand">NextPay</div>
    </section>

    <?php if (!empty($error_msg)): ?>
      <div class="alert alert-error"><?= sanitize($error_msg) ?></div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
      <div class="alert alert-success"><?= sanitize($success_msg) ?></div>
    <?php endif; ?>

    <form class="form" method="POST" action="login.php">
      
      <!-- Phone Input -->
      <label class="field">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <rect x="7" y="2" width="10" height="20" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/>
          <path d="M10 5h4M10.5 19h3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <input type="tel" name="phone" placeholder="Enter phone number" autocomplete="tel" required value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>">
      </label>

      <!-- Password Input -->
      <label class="field">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <rect x="4" y="10" width="16" height="11" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.7"/>
          <path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
        <input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
      </label>

      <div class="options">
        <label class="save">
          <input type="checkbox" name="save_password" class="radio-input" checked>
          <span class="radio" aria-hidden="true">
            <span class="radio-checkmark"></span>
          </span>
          <span>Save Password</span>
        </label>
        <a class="forgot" href="register.php">Register</a>
      </div>

      <button class="login-btn" type="submit">Login</button>

      <div class="agreement">
        <label class="check-container">
          <input type="checkbox" name="agree" class="check-input" checked required>
          <span class="check-checkmark">
            <svg width="18" height="18" viewBox="0 0 24 24">
              <path d="M5 12.4 9.3 17 19 7" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </label>
        <span>Agree <a href="useragreement.php">“User Privacy Agreement”</a></span>
      </div>
    </form>
  </main>

</body>
</html>

