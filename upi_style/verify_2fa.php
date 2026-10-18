<?php
// verify_2fa.php - Website Login OTP verification
ob_start();
require_once 'db.php';

// Check if we have temporary login credentials stored
if (!isset($_SESSION['temp_2fa_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['temp_2fa_user_id'];
$phone = $_SESSION['temp_2fa_phone'];
$role = $_SESSION['temp_2fa_role'];

// Fetch user profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'Blocked') {
    unset($_SESSION['temp_2fa_user_id']);
    header("Location: login.php");
    exit;
}

$error_msg = '';
$login_otp = getSetting('login_otp_code') ?: '1234';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['otp_code']) ? trim($_POST['otp_code']) : '';
    
    if (empty($code)) {
        $error_msg = "Please enter the OTP verification code.";
    } elseif ($code === $login_otp) {
        // Verification success!
        
        // Populate active session details
        $_SESSION['user_id'] = $user_id;
        $_SESSION['phone'] = $phone;
        $_SESSION['role'] = $role;
        
        // Audit log success
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, ip_address, user_agent, action) VALUES (?, ?, ?, 'Login OTP Verified')");
        $stmtLog->execute([$user_id, $ip, $ua]);
        
        // Clean temporary sessions
        unset($_SESSION['temp_2fa_user_id']);
        unset($_SESSION['temp_2fa_phone']);
        unset($_SESSION['temp_2fa_role']);
        
        // Redirect based on role
        if ($role === 'admin' || $role === 'sub_admin') {
            header("Location: admin/dashboard.php");
        } elseif ($role === 'masteragent') {
            header("Location: masteragent/index.php");
        } elseif ($role === 'agent') {
            header("Location: agent/index.php");
        } else {
            header("Location: home.php");
        }
        exit;
    } else {
        $error_msg = "Incorrect OTP code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - OTP Verification</title>
  <!-- Link to premium styles -->
  <link rel="stylesheet" href="website_style.css">
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
      padding: 20px;
    }
    .card {
      width: 100%;
      max-width: 440px;
      background: #ffffff;
      border: 1px solid #ebedf2;
      border-radius: 16px;
      padding: 36px 30px 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
      text-align: center;
      position: relative;
    }
    .logo-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(35,149,238,0.08);
      border: 1px solid rgba(35,149,238,0.2);
      border-radius: 20px;
      padding: 5px 14px;
      margin-bottom: 16px;
      font-size: 11px;
      font-weight: 800;
      color: #2395ee;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .title {
      font-size: 22px;
      font-weight: 800;
      color: #2c3e50;
      margin-bottom: 12px;
    }
    .desc {
      font-size: 13.5px;
      color: #8c9cb5;
      line-height: 1.5;
      margin-bottom: 24px;
    }
    .alert-error {
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      color: #e74c3c;
      padding: 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 20px;
      text-align: left;
    }
    .cancel-link {
      display: block;
      margin-top: 10px;
      font-size: 13.5px;
      color: #8c9cb5;
      text-decoration: none;
      font-weight: 600;
    }
    .cancel-link:hover {
      color: #2395ee;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo-badge">🎯 NextPay Verification</div>
    
    <h2 class="title">Please enter OTP</h2>
    <?php
      // Mask phone number
      $maskedPhone = "********";
      if (strlen($phone) >= 8) {
          $maskedPhone = substr($phone, 0, 3) . "****" . substr($phone, -4);
      }
    ?>
    <p class="desc">OTP sent to <?= htmlspecialchars($maskedPhone) ?></p>

    <!-- JS Error display -->
    <p id="js-otp-error" style="color: #e74c3c; font-size: 13px; font-weight: bold; display: none; margin-bottom: 16px; text-align: left; background: #fdf2f2; padding: 10px; border-radius: 6px;"></p>

    <?php if (!empty($error_msg)): ?>
      <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="verify_2fa.php" id="otp-form">
      <input type="hidden" name="otp_code" id="hidden_otp_code">
      
      <!-- 4 Digit Inputs -->
      <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 24px;">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 0)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 1)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 2)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 3)" onkeydown="movePrev(this, event)">
      </div>
      
      <!-- Footer details matching screenshot -->
      <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-family: sans-serif; margin-bottom: 24px;">
        <span style="color: #e74c3c; cursor: pointer;" onclick="showLimitError()">SMS send limit error</span>
        <span style="color: #2395ee; cursor: pointer; font-weight: bold;" onclick="resendOtp()">ReSend OTP</span>
      </div>

      <a href="logout.php" class="cancel-link">Cancel and Log Out</a>
    </form>
  </div>

  <script>
    const otpDigits = document.querySelectorAll('.otp-digit');
    const hiddenOtpInput = document.getElementById('hidden_otp_code');
    const form = document.getElementById('otp-form');
    const otpError = document.getElementById('js-otp-error');

    function moveNext(input, index) {
      if (input.value) {
        input.style.borderBottomColor = '#2395ee';
      } else {
        input.style.borderBottomColor = '#cbd5e0';
      }
      
      // Auto-jump to next digit
      if (input.value && index < 3) {
        otpDigits[index + 1].focus();
      }
      
      // If last input, check and submit
      if (index === 3 && input.value) {
        let code = '';
        otpDigits.forEach(inp => code += inp.value);
        hiddenOtpInput.value = code;
        form.submit();
      }
    }

    function movePrev(input, event) {
      if (event.key === 'Backspace' && !input.value) {
        const index = Array.from(otpDigits).indexOf(input);
        if (index > 0) {
          otpDigits[index - 1].focus();
        }
      }
    }

    function showLimitError() {
      otpError.textContent = 'SMS sending quota exceeded. Limit reached.';
      otpError.style.display = 'block';
      otpError.style.color = '#e74c3c';
    }

    function resendOtp() {
      otpError.textContent = 'A new verification code has been dispatched.';
      otpError.style.display = 'block';
      otpError.style.color = '#2ecc71';
      setTimeout(() => {
        otpError.style.color = '#e74c3c';
        otpError.style.display = 'none';
      }, 3000);
    }
    
    // Auto-focus first input
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => otpDigits[0].focus(), 100);
    });
  </script>
</body>
</html>
