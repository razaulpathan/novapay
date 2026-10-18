<?php
// register.php - User Registration
require_once 'db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: home.php");
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $ref_code = isset($_POST['ref_code']) ? trim($_POST['ref_code']) : '';
    $agree = isset($_POST['agree']) ? true : false;

    // Validation
    if (strlen($phone) !== 10 || !is_numeric($phone)) {
        $error_msg = "Please enter a valid 10-digit phone number.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } elseif (!$agree) {
        $error_msg = "You must agree to the User Privacy Agreement.";
    } else {
        // Check if phone number already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error_msg = "Phone number is already registered.";
        } else {
            // Referral code check
            $referred_by = null;
            if (!empty($ref_code)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$ref_code]);
                $referred_user = $stmt->fetch();
                if ($referred_user) {
                    $referred_by = $referred_user['id'];
                } else {
                    $error_msg = "Invalid referral code.";
                }
            }

            if (empty($error_msg)) {
                // Generate a unique referral code
                $new_ref_code = 'REF' . strtoupper(substr(md5(uniqid($phone, true)), 0, 7));
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (phone, password, referral_code, referred_by) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$phone, $hashed_password, $new_ref_code, $referred_by])) {
                    // Give referral bonus to referee if referred_by is set
                    if ($referred_by) {
                        // In a real app, track commissions. Here, we can add newbie reward or just log it.
                    }
                    $success_msg = "Registration successful! You can now login.";
                } else {
                    $error_msg = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Register</title>
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
      padding: 30px 30px 28px;
    }

    .logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 18px;
      margin: 4px auto 40px;
    }

    .logo-mark {
      width: 100px;
      height: 110px;
      flex: 0 0 auto;
    }

    .brand {
      color: var(--deep-blue);
      font-family: Arial, Helvetica, sans-serif;
      font-size: clamp(48px, 12vw, 76px);
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
      height: 64px;
      margin: 0 8px 20px;
      border-radius: 32px;
      background: var(--soft-blue);
      border-bottom: 1px solid var(--line);
      overflow: hidden;
    }

    .field svg {
      width: 28px;
      height: 28px;
      margin-left: 24px;
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
      font: 700 17px/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .field input::placeholder {
      color: var(--muted);
      opacity: 1;
    }

    .options {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin: -4px 30px 30px 8px;
      font-size: 16px;
      font-weight: 700;
    }

    .forgot {
      color: #0f8edd;
      text-decoration: none;
    }

    .login-btn {
      width: 100%;
      height: 64px;
      border: 0;
      border-radius: 10px;
      background: var(--blue);
      color: white;
      cursor: pointer;
      font: 800 20px/1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      box-shadow: 0 4px 10px rgba(35, 149, 238, 0.2);
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
      width: 24px;
      height: 24px;
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
        padding: 30px 16px 20px;
      }
      .logo {
        margin-bottom: 24px;
      }
      .logo-mark {
        width: 80px;
        height: 90px;
      }
      .brand {
        font-size: 52px;
      }
      .field {
        height: 58px;
        margin-bottom: 16px;
      }
      .login-btn {
        height: 58px;
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

    <form class="form" method="POST" action="register.php">
      
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
        <input type="password" name="password" placeholder="Enter password" autocomplete="new-password" required>
      </label>

      <!-- Confirm Password Input -->
      <label class="field">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <rect x="4" y="10" width="16" height="11" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.7"/>
          <path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
        <input type="password" name="confirm_password" placeholder="Confirm password" autocomplete="new-password" required>
      </label>

      <!-- Referral Code Input (Optional) -->
      <label class="field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
        <input type="text" name="ref_code" placeholder="Referral Code (Optional)" value="<?= isset($_GET['ref']) ? sanitize($_GET['ref']) : (isset($_POST['ref_code']) ? sanitize($_POST['ref_code']) : '') ?>">
      </label>

      <div class="options">
        <a class="forgot" href="login.php">Already have an account? Login</a>
      </div>

      <button class="login-btn" type="submit">Register</button>

      <div class="agreement">
        <label class="check-container">
          <input type="checkbox" name="agree" class="check-input" checked required>
          <span class="check-checkmark">
            <svg width="14" height="14" viewBox="0 0 24 24">
              <path d="M5 12.4 9.3 17 19 7" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </label>
        <span>Agree <a href="useragreement.php">“User Privacy Agreement”</a></span>
      </div>
    </form>
  </main>

  <!-- OTP Verification Modal -->
  <div id="otp-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; border-radius: 16px; width: 340px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center; position: relative;">
      <!-- Close button (x) -->
      <span onclick="closeOtpModal()" style="position: absolute; right: 18px; top: 18px; font-size: 20px; color: #999; cursor: pointer;">&times;</span>
      
      <h2 style="font-size: 22px; font-weight: 800; color: #2c3e50; margin: 12px 0 6px;">Please enter OTP</h2>
      <p id="otp-phone-text" style="font-size: 13.5px; color: #7f8c8d; margin-bottom: 24px;">OTP sent to 830****8331</p>
      
      <!-- 4 Digit Inputs -->
      <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 24px;">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 1)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 2)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 3)" onkeydown="movePrev(this, event)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" style="width: 48px; height: 48px; border: none; border-bottom: 2px solid #cbd5e0; font-size: 24px; text-align: center; font-weight: bold; color: #2c3e50; outline: none; transition: border-color 0.2s;" oninput="moveNext(this, 4)" onkeydown="movePrev(this, event)">
      </div>
      
      <p id="otp-error-msg" style="color: #e74c3c; font-size: 13px; font-weight: bold; display: none; margin-bottom: 12px;"></p>
      
      <!-- Footer details matching screenshot -->
      <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-family: sans-serif;">
        <span style="color: #e74c3c; cursor: pointer;" onclick="showLimitError()">SMS send limit error</span>
        <span style="color: #2395ee; cursor: pointer; font-weight: bold;" onclick="resendOtp()">ReSend OTP</span>
      </div>
    </div>
  </div>

  <script>
    const form = document.querySelector('form.form');
    const otpDigits = document.querySelectorAll('.otp-digit');
    const otpModal = document.getElementById('otp-modal');
    const otpPhoneText = document.getElementById('otp-phone-text');
    const otpError = document.getElementById('otp-error-msg');
    let isOtpVerified = false;

    form.addEventListener('submit', function (e) {
      if (!isOtpVerified) {
        e.preventDefault();
        
        // Fetch phone number and mask it
        const phone = document.querySelector('input[name="phone"]').value.trim();
        let maskedPhone = "********";
        if (phone.length >= 8) {
          maskedPhone = phone.substring(0, 3) + "****" + phone.substring(phone.length - 4);
        } else if (phone.length > 0) {
          maskedPhone = phone;
        }
        
        otpPhoneText.textContent = `OTP sent to ${maskedPhone}`;
        otpError.style.display = 'none';
        
        // Clear previous input values
        otpDigits.forEach(input => {
          input.value = '';
          input.style.borderBottomColor = '#cbd5e0';
        });
        
        // Show modal and focus first input
        otpModal.style.display = 'flex';
        setTimeout(() => otpDigits[0].focus(), 100);
      }
    });

    function closeOtpModal() {
      otpModal.style.display = 'none';
    }

    function moveNext(input, index) {
      if (input.value) {
        input.style.borderBottomColor = '#2395ee';
      } else {
        input.style.borderBottomColor = '#cbd5e0';
      }
      
      // Auto-jump to next digit
      if (input.value && index < 4) {
        otpDigits[index].focus();
      }
      
      // If last input, check OTP
      if (index === 4 && input.value) {
        checkOtpCode();
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

    function checkOtpCode() {
      let code = '';
      otpDigits.forEach(input => code += input.value);
      
      if (code === '1234') {
        isOtpVerified = true;
        otpModal.style.display = 'none';
        form.submit();
      } else {
        otpError.textContent = 'Incorrect OTP code. Please try again.';
        otpError.style.display = 'block';
        
        // Reset inputs
        otpDigits.forEach(input => {
          input.value = '';
          input.style.borderBottomColor = '#cbd5e0';
        });
        otpDigits[0].focus();
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
  </script>
</body>
</html>

