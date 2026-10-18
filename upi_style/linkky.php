<?php
// linkky.php - Link, Edit or View UPI Account details
require_once 'db.php';
requireLogin();

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$view_id = isset($_GET['view']) ? intval($_GET['view']) : null;
$relink_id = isset($_GET['relink']) ? intval($_GET['relink']) : null;

$account = null;
$is_view_mode = false;
$is_edit_mode = false;
$is_relink_mode = false;

// If viewing or editing, fetch the existing UPI details
if ($edit_id) {
    header("Location: kycpa.php");
    exit;
} elseif ($view_id) {
    $stmt = $pdo->prepare("SELECT * FROM upi_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$view_id, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    $is_view_mode = true;
} elseif ($relink_id) {
    $stmt = $pdo->prepare("SELECT * FROM upi_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$relink_id, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    $is_relink_mode = true;
}

$error = '';
$success = '';

// Handle AJAX PhonePe/Paytm/Mobikwik verification & linking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_phonepe_upis') {
    header('Content-Type: application/json');
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $upis = isset($_POST['upis']) ? $_POST['upis'] : [];
    $partner = isset($_SESSION['link_partner']) ? $_SESSION['link_partner'] : 'PhonePe';

    if (empty($name) || empty($upis)) {
        echo json_encode(['success' => false, 'message' => 'Please fill out all details.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO upi_accounts (user_id, partner, name, upi_no, status, stopped) VALUES (?, ?, ?, ?, 'Active', 0)");
        foreach ($upis as $upi) {
            $stmt->execute([$_SESSION['user_id'], $partner, $name, trim($upi)]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_link_otp') {
    header('Content-Type: application/json');
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $partner = isset($_POST['partner']) ? trim($_POST['partner']) : 'PhonePe';

    if (strlen($phone) !== 10 || !is_numeric($phone)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.']);
        exit;
    }

    // Generate random 4-digit OTP
    $otp = rand(1000, 9999);
    $_SESSION['link_otp'] = $otp;
    $_SESSION['link_phone'] = $phone;
    $_SESSION['link_partner'] = $partner;

    $message = "Your OTP for linking $partner on NextPay is $otp. Do not share this code with anyone.";
    $sent = sendSMS($phone, $message);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully!']);
    } else {
        // Fallback for development if API key is not set
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent successfully! (Dev Mode: OTP is ' . $otp . ')'
        ]);
        error_log("OTP for $phone ($partner): $otp");
    }
    exit;
}

// Handle AJAX verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_link_otp') {
    header('Content-Type: application/json');
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if (empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Please enter the OTP.']);
        exit;
    }

    if (isset($_SESSION['link_otp']) && $otp === (string)$_SESSION['link_otp']) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    exit;
}


// Handle linking or updating form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner = isset($_POST['partner']) ? trim($_POST['partner']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $upi_no = isset($_POST['upi_no']) ? trim($_POST['upi_no']) : '';
    $upi_id = isset($_POST['upi_id']) ? trim($_POST['upi_id']) : '';
    $post_edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
    $post_relink_id = isset($_POST['relink_id']) ? intval($_POST['relink_id']) : null;

    if (empty($partner) || empty($name) || empty($upi_id)) {
        $error = 'Please fill out all fields.';
    } elseif (!empty($upi_no) && !preg_match('/^[0-9]{10}$/', $upi_no)) {
        $error = 'Please enter a valid 10-digit mobile number.';
    } elseif (!preg_match('/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9]+$/', $upi_id)) {
        $error = 'Please enter a valid UPI ID (e.g. username@upi).';
    }

    if (empty($error)) {
        try {
            if ($post_relink_id) {
                // Relink existing unlinked account
                $stmt = $pdo->prepare("UPDATE upi_accounts SET partner = ?, name = ?, upi_no = ?, status = 'Active', stopped = 0 WHERE id = ? AND user_id = ?");
                $stmt->execute([$partner, $name, $upi_id, $post_relink_id, $_SESSION['user_id']]);
                header("Location: kycpa.php?success=relinked");
                exit;
            } elseif ($post_edit_id) {
                // Block edits
                $error = 'Linked UPI accounts cannot be edited or removed. Please contact customer support.';
            } else {
                // Link new account
                $stmt = $pdo->prepare("INSERT INTO upi_accounts (user_id, partner, name, upi_no, status, stopped) VALUES (?, ?, ?, ?, 'Active', 0)");
                $stmt->execute([$_SESSION['user_id'], $partner, $name, $upi_id]);
                header("Location: kycpa.php?success=linked");
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - <?= $is_view_mode ? 'UPI Details' : ($is_edit_mode ? 'Edit UPI' : 'Link New UPI') ?></title>
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
      --bg-color: #f7f9fc;
      --text-dark: #333333;
      --red-warning: #e74c3c;
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
      padding-bottom: 80px;
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
      transform: translateX(-12px);
    }

    .content {
      padding: 24px 20px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* List Item Box Row */
    .link-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 4px;
      border-bottom: 1.5px solid #f2f5f8;
      cursor: pointer;
      position: relative;
    }

    .row-label {
      font-size: 18px;
      font-weight: 800;
      color: #111111;
      width: 100px;
      flex-shrink: 0;
    }

    .row-value {
      font-size: 18px;
      font-weight: bold;
      color: #2c3e50;
      flex: 1;
      text-align: left;
    }

    .row-value.placeholder-style {
      color: var(--muted);
      font-weight: normal;
    }

    .chevron {
      color: var(--muted);
      width: 20px;
      height: 20px;
    }

    /* Form Fields Container */
    .form-fields {
      display: <?= ($account || $is_view_mode || $is_edit_mode) ? 'flex' : 'none' ?>;
      flex-direction: column;
      gap: 16px;
    }

    /* Input styling */
    .text-input {
      flex: 1;
      border: none;
      outline: none;
      font-size: 18px;
      font-family: inherit;
      color: #2c3e50;
      padding: 0;
      background: transparent;
    }

    .text-input::placeholder {
      color: var(--muted);
    }

    /* Red error prompt */
    .error-prompt {
      color: var(--red-warning);
      font-size: 12px;
      font-weight: bold;
      margin: 4px 4px 0;
      display: none;
    }

    .error-prompt.show {
      display: block;
    }

    /* Blue subtext */
    .retry-text {
      color: var(--blue);
      font-size: 13px;
      font-weight: bold;
      margin-top: 4px;
      cursor: pointer;
    }

    /* Bottom Button styling */
    .submit-btn-container {
      margin-top: auto;
      padding: 10px 0;
      display: <?= ($account || $is_edit_mode) ? 'block' : 'none' ?>;
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

    /* Partner Selection Sheet Overlay */
    .selection-sheet {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 150;
      display: none;
      justify-content: center;
      align-items: flex-end;
    }

    .sheet-content {
      background: #ffffff;
      width: 100%;
      border-radius: 20px 20px 0 0;
      padding: 16px 0;
      box-shadow: 0 -4px 15px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      gap: 4px;
      animation: slideUp 0.2s ease forwards;
    }

    @keyframes slideUp {
      from { transform: translateY(100%); }
      to { transform: translateY(0); }
    }

    .sheet-title {
      font-size: 16px;
      font-weight: 900;
      color: #7f8c8d;
      padding: 10px 20px;
      border-bottom: 1px solid #f2f5f8;
      text-align: center;
    }

    .sheet-option-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border-bottom: 1.5px solid #f2f5f8;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    .sheet-option-card:hover {
      background: #f7f9fc;
    }
    .sheet-option-left {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      min-width: 0;
    }
    .sheet-option-logo {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      overflow: hidden;
      flex-shrink: 0;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      border: 1px solid #ebedf2;
    }
    .sheet-option-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 0;
      text-align: left;
    }
    .sheet-option-title {
      font-size: 17px;
      font-weight: 700;
      color: #2c3e50;
      transition: color 0.2s;
    }
    .sheet-option-desc {
      font-size: 13px;
      color: #8c9cb5;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sheet-option-right {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-shrink: 0;
    }
    .sheet-dl-btn {
      background: #2395ee;
      color: #ffffff;
      border: 0;
      border-radius: 6px;
      padding: 8px 16px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(35, 149, 238, 0.2);
      transition: background-color 0.2s;
      font-family: inherit;
    }
    .sheet-dl-btn:hover {
      background: #1886df;
    }
    .sheet-check-circle {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
    }

    .sheet-cancel {
      padding: 16px 20px;
      font-size: 16px;
      font-weight: 800;
      color: var(--blue);
      cursor: pointer;
      text-align: center;
      border-top: 5px solid #f2f5f8;
    }

    /* Alert Banner */
    .alert-error {
      background: #fdf2f2;
      border: 1px solid #fde8e8;
      color: var(--red-warning);
      padding: 12px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
      text-align: center;
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

    /* OTP Modal & Fetch Handles Styles */
    .otp-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(5px);
      z-index: 1000;
      display: none;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    
    .otp-content {
      background: #ffffff;
      width: min(100%, 400px);
      border-radius: 20px;
      padding: 28px 24px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 16px;
      animation: zoomIn 0.25s ease forwards;
    }
    
    @keyframes zoomIn {
      from { transform: scale(0.95); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    
    .otp-title {
      font-size: 22px;
      font-weight: 900;
      color: #2c3e50;
    }
    
    .otp-subtitle {
      font-size: 14px;
      font-weight: bold;
      color: #7f8c8d;
      line-height: 1.4;
    }
    
    .otp-input-container {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin: 10px 0;
    }
    
    .otp-box {
      width: 50px;
      height: 54px;
      border: 2px solid #d4e1eb;
      border-radius: 12px;
      text-align: center;
      font-size: 24px;
      font-weight: 800;
      color: #2c3e50;
      outline: none;
      background: #f8fcff;
      transition: all 0.2s;
    }
    
    .otp-box:focus {
      border-color: #2395ee;
      background: #ffffff;
      box-shadow: 0 0 8px rgba(35, 149, 238, 0.2);
    }
    
    .otp-timer {
      font-size: 13px;
      font-weight: 800;
      color: #7f8c8d;
    }
    
    .otp-timer span {
      color: #2395ee;
    }
    
    .otp-btn {
      height: 52px;
      border: none;
      background: #2395ee;
      color: #ffffff;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 800;
      cursor: pointer;
      font-family: inherit;
      box-shadow: 0 4px 12px rgba(35, 149, 238, 0.25);
    }
    
    .otp-btn:disabled {
      background: #bdc3c7;
      box-shadow: none;
      cursor: not-allowed;
    }
    
    /* Fetch accounts list styles */
    .upi-list-panel {
      display: flex;
      flex-direction: column;
      gap: 12px;
      text-align: left;
      margin-top: 10px;
    }
    
    .upi-option-card {
      border: 1.5px solid #e1e8ed;
      border-radius: 12px;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .upi-option-card.selected {
      border-color: #2395ee;
      background: #f0f8ff;
    }
    
    .upi-details {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .upi-logo-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #5f259f;
      color: white;
      font-size: 14px;
      font-weight: 900;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .upi-id-string {
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
      font-family: Arial, sans-serif;
    }
    
    .upi-checkbox {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      border: 2px solid #bdc3c7;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .upi-option-card.selected .upi-checkbox {
      border-color: #2395ee;
      background: #2395ee;
      color: white;
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <a href="kycpa.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">
        <?php if ($is_view_mode): ?>
          UPI Details
        <?php elseif ($is_edit_mode): ?>
          Edit UPI Account
        <?php elseif ($is_relink_mode): ?>
          Relink UPI Account
        <?php else: ?>
          Link New UPI
        <?php endif; ?>
      </h1>
    </header>

    <div class="content">
      
      <?php if (!empty($error)): ?>
        <div class="alert-error"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="linkky.php" onsubmit="return handleFormSubmit(event)">
        <?php if ($is_edit_mode): ?>
          <input type="hidden" name="edit_id" value="<?= $account['id'] ?>">
        <?php endif; ?>
        <?php if ($is_relink_mode): ?>
          <input type="hidden" name="relink_id" value="<?= $account['id'] ?>">
        <?php endif; ?>

        <!-- Partner Selection row -->
        <div class="link-row" onclick="<?php echo $is_view_mode ? '' : 'openPartnerSheet()' ?>">
          <span class="row-label">Partner</span>
          <span class="row-value <?= !$account ? 'placeholder-style' : '' ?>" id="partnerVal">
            <?= $account ? sanitize($account['partner']) : 'select the kyc partner' ?>
          </span>
          <input type="hidden" name="partner" id="partnerInput" value="<?= $account ? sanitize($account['partner']) : '' ?>">
          <?php if (!$is_view_mode): ?>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          <?php endif; ?>
        </div>

        <!-- Additional fields -->
        <div class="form-fields" id="formFields" style="display: <?= ($account || $is_view_mode || $is_edit_mode) ? 'flex' : 'none' ?>;">
          
          <!-- Name Field -->
          <div class="link-row">
            <span class="row-label">Name</span>
            <input type="text" name="name" class="text-input" placeholder="Enter your name" id="nameInput" required value="<?= $account ? sanitize($account['name']) : '' ?>" <?= $is_view_mode ? 'readonly' : '' ?>>
          </div>

          <!-- Phone No Field (only shown when creating a new account) -->
          <?php if (!$account): ?>
          <div class="link-row" style="flex-direction: column; align-items: stretch; border-bottom: none; padding-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f2f5f8; padding-bottom: 16px;">
              <span class="row-label">Phone No</span>
              <input type="tel" name="upi_no" class="text-input" placeholder="Enter Phone No" id="phoneInput" required oninput="validatePhone()" value="">
            </div>
            <!-- Red error tag -->
            <div class="error-prompt" id="errorPrompt">Please enter a 10-digit mobile number.</div>
          </div>
          <?php endif; ?>

          <!-- Enter UPI ID Field -->
          <div class="link-row">
            <span class="row-label">UPI ID</span>
            <input type="text" name="upi_id" class="text-input" placeholder="Enter UPI ID (e.g. username@upi)" id="upiIdInput" required value="<?= $account ? sanitize($account['upi_no']) : '' ?>" <?= $is_view_mode ? 'readonly' : '' ?>>
          </div>

        </div>

        <!-- Bottom submit btn -->
        <div class="submit-btn-container" id="submitBtnContainer" style="display: <?= ($account && !$is_view_mode) ? 'block' : 'none' ?>;">
          <button class="submit-btn" type="submit">Link Kyc</button>
        </div>
      </form>

    </div>

    <!-- Partner Select Sheet Dialog -->
    <?php if (!$is_view_mode): ?>
      <div class="selection-sheet" id="partnerSheet">
        <div class="sheet-content" style="max-height: 80vh; overflow-y: auto; gap: 0; padding: 0 0 16px;">
          <div class="sheet-title" style="border-bottom: 1.5px solid #f2f5f8; padding: 20px 0;">Select KYC Partner</div>
          <?php 
          $partners_list = $pdo->query("SELECT * FROM payment_partners ORDER BY name ASC")->fetchAll();
          foreach ($partners_list as $p): 
              $partner_name = sanitize($p['name']);
              $shortLetter = strtoupper(substr($partner_name, 0, 1));
              $bgColor = '#' . substr(md5($partner_name), 0, 6);
          ?>
            <div class="sheet-option-card" onclick="selectPartner(<?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8') ?>)" data-partner="<?= $partner_name ?>">
              <div class="sheet-option-left">
                <div class="sheet-option-logo">
                  <?php if (!empty($p['logo_url']) && file_exists(__DIR__ . '/' . $p['logo_url'])): ?>
                    <img src="<?= sanitize($p['logo_url']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                  <?php else: ?>
                    <div class="sheet-logo-mock" style="background: <?= $bgColor ?>; color: #fff; border-radius: 50%; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px;"><?= $shortLetter ?></div>
                  <?php endif; ?>
                </div>
                <div class="sheet-option-info">
                  <div class="sheet-option-title"><?= $partner_name ?></div>
                  <div class="sheet-option-desc"><?= $partner_name ?> is an Indian digital payment platform.</div>
                </div>
              </div>
              <div class="sheet-option-right">
                <button class="sheet-dl-btn" type="button" onclick="event.stopPropagation(); window.open('https://play.google.com/store/search?q=' + encodeURIComponent('<?= $partner_name ?>') + '&c=apps', '_blank')">Download</button>
                <div class="sheet-check-circle"></div>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="sheet-cancel" onclick="closePartnerSheet()" style="text-align: center; border-top: 1.5px solid #f2f5f8; padding-top: 16px; margin-top: 10px;">Cancel</div>
        </div>
      </div>
    <?php endif; ?>

    <!-- PhonePe OTP and UPI Fetch Modal -->
    <?php if (!$is_view_mode): ?>
      <div class="otp-modal" id="otpModal">
        <div class="otp-content">
          <h3 class="otp-title">PhonePe Verification</h3>
          
          <!-- Panel 1: OTP Entry -->
          <div id="otpInputsPanel" style="display: flex; flex-direction: column; gap: 16px;">
            <p class="otp-subtitle">We have sent a 4-digit verification code to the number associated with PhonePe: <br><strong id="otpPhoneDisplay" style="color: #2c3e50; font-family: Arial, sans-serif;"></strong></p>
            <div class="otp-input-container">
              <input type="text" maxlength="1" class="otp-box" pattern="[0-9]*" inputmode="numeric" oninput="moveToNext(this, 0)">
              <input type="text" maxlength="1" class="otp-box" pattern="[0-9]*" inputmode="numeric" oninput="moveToNext(this, 1)">
              <input type="text" maxlength="1" class="otp-box" pattern="[0-9]*" inputmode="numeric" oninput="moveToNext(this, 2)">
              <input type="text" maxlength="1" class="otp-box" pattern="[0-9]*" inputmode="numeric" oninput="moveToNext(this, 3)">
            </div>
            <div class="otp-timer" id="otpTimerDisplay">Resend OTP in <span>60s</span></div>
            <button type="button" class="otp-btn" onclick="verifyOtp()">Verify OTP</button>
            <button type="button" id="otpResendBtn" class="submit-btn" style="background: transparent; color: #2395ee; border: 1px solid #2395ee; box-shadow: none; height: 44px; font-size: 15px;" onclick="resendOtp()" disabled>Resend Code</button>
            <button type="button" class="submit-btn" style="background: transparent; color: #7f8c8d; border: 1px solid #bdc3c7; box-shadow: none; height: 44px; font-size: 15px;" onclick="closeOtpModal()">Cancel</button>
          </div>

          <!-- Panel 2: Fetch Loader -->
          <div id="otpLoaderPanel" style="display: none; flex-direction: column; align-items: center; justify-content: center; padding: 30px 10px; gap: 16px;">
            <svg viewBox="0 0 50 50" style="width: 48px; height: 48px; animation: spin 1s linear infinite; color: #2395ee;">
              <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" style="opacity: 0.15;"></circle>
              <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="80 200" stroke-dashoffset="0"></circle>
            </svg>
            <p class="otp-subtitle" style="font-weight: bold; font-size: 16px;">Fetching all linked PhonePe UPI IDs...</p>
            <style>
              @keyframes spin { 100% { transform: rotate(360deg); } }
            </style>
          </div>

          <!-- Panel 3: UPI handles Selection list -->
          <div id="otpUpiListPanel" style="display: none; flex-direction: column; gap: 16px;">
            <p class="otp-subtitle" style="font-size: 15px; font-weight: bold; color: #2ecc71;">✓ PhonePe OTP Verified Successfully!</p>
            <p class="otp-subtitle">Select the UPI handles you wish to link to NextPay:</p>
            <div class="upi-list-panel">
              
              <div class="upi-option-card selected" id="cardYbl" onclick="toggleUpiSelection('cardYbl')">
                <div class="upi-details">
                  <div class="upi-logo-circle">Ph</div>
                  <span class="upi-id-string" id="upiIdYbl"></span>
                </div>
                <div class="upi-checkbox">✓</div>
              </div>
              
              <div class="upi-option-card selected" id="cardAxl" onclick="toggleUpiSelection('cardAxl')">
                <div class="upi-details">
                  <div class="upi-logo-circle">Ph</div>
                  <span class="upi-id-string" id="upiIdAxl"></span>
                </div>
                <div class="upi-checkbox">✓</div>
              </div>

              <div class="upi-option-card selected" id="cardIbl" onclick="toggleUpiSelection('cardIbl')">
                <div class="upi-details">
                  <div class="upi-logo-circle">Ph</div>
                  <span class="upi-id-string" id="upiIdIbl"></span>
                </div>
                <div class="upi-checkbox">✓</div>
              </div>

            </div>
            <button type="button" class="otp-btn" onclick="submitPhonePeUpis()" style="margin-top: 10px;">Link Selected UPIs</button>
            <button type="button" class="submit-btn" style="background: transparent; color: #7f8c8d; border: 1px solid #bdc3c7; box-shadow: none; height: 44px; font-size: 15px;" onclick="closeOtpModal()">Cancel</button>
          </div>

        </div>
      </div>
    <?php endif; ?>

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
    function openPartnerSheet() {
      const currentSelected = document.getElementById('partnerInput').value;
      const optionCards = document.querySelectorAll('.sheet-option-card');
      optionCards.forEach(card => {
          const partner = card.getAttribute('data-partner');
          const checkCircle = card.querySelector('.sheet-check-circle');
          const title = card.querySelector('.sheet-option-title');
          
          if (partner === currentSelected) {
              card.classList.add('selected');
              title.style.color = '#2395ee'; // blue color
              checkCircle.innerHTML = `
                  <svg viewBox="0 0 24 24" fill="#2395ee" style="width: 24px; height: 24px;">
                      <circle cx="12" cy="12" r="11" fill="#2395ee"/>
                      <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" fill="#ffffff"/>
                  </svg>
              `;
          } else {
              card.classList.remove('selected');
              title.style.color = '#2c3e50'; // dark color
              checkCircle.innerHTML = `
                  <div style="width: 22px; height: 22px; border: 2px solid #cbd5e0; border-radius: 50%;"></div>
              `;
          }
      });
      document.getElementById('partnerSheet').style.display = 'flex';
    }

    function closePartnerSheet() {
      document.getElementById('partnerSheet').style.display = 'none';
    }

    function selectPartner(partnerName) {
      const partnerVal = document.getElementById('partnerVal');
      const partnerInput = document.getElementById('partnerInput');
      
      partnerVal.textContent = partnerName;
      partnerVal.classList.remove('placeholder-style');
      partnerInput.value = partnerName;
      closePartnerSheet();

      // Show other fields and action buttons
      document.getElementById('formFields').style.display = 'flex';
      document.getElementById('submitBtnContainer').style.display = 'block';

      // Update button text for the selected partner
      const submitBtn = document.querySelector('.submit-btn-container button');
      submitBtn.textContent = 'Link with ' + partnerName;
    }

    function validatePhone() {
      const phoneInput = document.getElementById('phoneInput');
      const errorPrompt = document.getElementById('errorPrompt');
      const digits = phoneInput.value.replace(/\D/g, '');

      if (digits.length > 0 && digits.length !== 10) {
        errorPrompt.classList.add('show');
      } else {
        errorPrompt.classList.remove('show');
      }
    }

    // Dynamic OTP & UPI Verification Flow
    let timerInterval;

    function handleFormSubmit(event) {
      const partner = document.getElementById('partnerInput').value;
      if (partner) {
        const phoneInput = document.getElementById('phoneInput');
        const phone = phoneInput ? phoneInput.value : '';
        const name = document.getElementById('nameInput').value;
        const upiId = document.getElementById('upiIdInput').value;

        if (!name || !upiId) {
          alert('Please fill out all fields.');
          event.preventDefault();
          return false;
        }

        if (phone) {
          const digits = phone.replace(/\D/g, '');
          if (digits.length !== 10) {
            alert('Please enter a valid 10-digit mobile number.');
            event.preventDefault();
            return false;
          }
        }
      }
      return true; // standard direct form submission
    }

    function openOtpModal(phone, partner) {
      // Set dynamic text for modal depending on the partner selected
      document.querySelector('.otp-title').innerText = partner + " Verification";
      
      const subtitles = document.querySelectorAll('.otp-subtitle');
      subtitles[0].innerHTML = `Fetching UPI accounts from ${partner} for mobile <br><strong id="otpPhoneDisplay" style="color: #2c3e50; font-family: Arial, sans-serif;">+91 ${phone}</strong>`;
      subtitles[1].innerText = `Fetching all linked ${partner} UPI IDs...`;
      subtitles[2].innerHTML = `✓ ${partner} Verification Successful!`;
      subtitles[3].innerHTML = `Select the UPI handles you wish to link to NextPay:`;

      // Bypass OTP inputs and show loader directly
      document.getElementById('otpInputsPanel').style.display = 'none';
      document.getElementById('otpLoaderPanel').style.display = 'flex';
      document.getElementById('otpUpiListPanel').style.display = 'none';
      
      document.getElementById('otpModal').style.display = 'flex';

      // Simulate loading/fetching linked UPI accounts directly
      setTimeout(() => {
        document.getElementById('otpLoaderPanel').style.display = 'none';
        document.getElementById('otpUpiListPanel').style.display = 'flex';

        // Set list details based on selected partner
        let upiHandles = [];
        let shortLabel = partner.substring(0, 2);
        let logoColor = '#2395ee'; // default blue

        if (partner === 'PhonePe') {
          logoColor = '#5f259f';
          upiHandles = [
            { id: 'cardYbl', upi: phone + '@ybl', label: 'Ph' },
            { id: 'cardAxl', upi: phone + '@axl', label: 'Ph' },
            { id: 'cardIbl', upi: phone + '@ibl', label: 'Ph' }
          ];
        } else if (partner === 'Paytm') {
          logoColor = '#00baf2';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@paytm', label: 'Py' }];
        } else if (partner === 'Mobikwik') {
          logoColor = '#0059a3';
          upiHandles = [
            { id: 'cardYbl', upi: phone + '@ikwik', label: 'Mk' },
            { id: 'cardAxl', upi: phone + '@mbk', label: 'Mk' }
          ];
        } else if (partner === 'Amazon Pay') {
          logoColor = '#ffe700';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@apl', label: 'Ap' }];
        } else if (partner === 'Freecharge') {
          logoColor = '#ff5c2c';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@freecharge', label: 'Fc' }];
        } else if (partner === 'Airtel') {
          logoColor = '#ff0000';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@airtel', label: 'At' }];
        } else if (partner === 'IndusPay') {
          logoColor = '#800080';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@indus', label: 'Ip' }];
        } else if (partner.includes('BharatPe')) {
          logoColor = '#00bcd4';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@bharatpe', label: 'Bp' }];
        } else if (partner.includes('IOB')) {
          logoColor = '#0033a0';
          upiHandles = [{ id: 'cardYbl', upi: phone + '@iob', label: 'Ib' }];
        } else {
          let handleName = partner.toLowerCase().replace(/[^a-z0-9]/g, '');
          upiHandles = [{ id: 'cardYbl', upi: phone + '@' + handleName, label: shortLabel }];
        }

        // Render options dynamically
        const container = document.querySelector('.upi-list-panel');
        container.innerHTML = ''; // Clear existing hardcoded cards

        upiHandles.forEach(h => {
          const card = document.createElement('div');
          card.className = "upi-option-card selected";
          card.id = h.id;
          card.setAttribute('data-upi', h.upi);
          card.onclick = () => toggleUpiSelection(h.id);

          card.innerHTML = `
            <div class="upi-details">
              <div class="upi-logo-circle" style="background: ${logoColor}; color: ${logoColor === '#ffe700' ? '#111' : '#fff'}">${h.label}</div>
              <span class="upi-id-string">${h.upi}</span>
            </div>
            <div class="upi-checkbox">✓</div>
          `;
          container.appendChild(card);
        });
      }, 1000);
    }

    function closeOtpModal() {
      document.getElementById('otpModal').style.display = 'none';
      clearInterval(timerInterval);
    }

    function startTimer(seconds) {
      clearInterval(timerInterval);
      const resendBtn = document.getElementById('otpResendBtn');
      const timerDisplay = document.getElementById('otpTimerDisplay');
      resendBtn.disabled = true;
      
      let timeRemaining = seconds;
      timerDisplay.innerHTML = `Resend OTP in <span>${timeRemaining}s</span>`;
      
      timerInterval = setInterval(() => {
        timeRemaining--;
        if (timeRemaining <= 0) {
          clearInterval(timerInterval);
          timerDisplay.innerHTML = "";
          resendBtn.disabled = false;
        } else {
          timerDisplay.innerHTML = `Resend OTP in <span>${timeRemaining}s</span>`;
        }
      }, 1000);
    }

    function resendOtp() {
      const phone = document.getElementById('phoneInput').value;
      const partner = document.getElementById('partnerInput').value;
      
      const formData = new FormData();
      formData.append('action', 'send_link_otp');
      formData.append('phone', phone);
      formData.append('partner', partner);

      fetch('linkky.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert("OTP sent successfully!");
          if (data.message.includes('Dev Mode')) {
            alert(data.message);
          }
          startTimer(60);
        } else {
          alert('Error sending OTP: ' + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Failed to resend OTP.');
      });
    }

    function moveToNext(currentInput, index) {
      currentInput.value = currentInput.value.replace(/\D/g, '');
      const boxes = document.querySelectorAll('.otp-box');
      if (currentInput.value.length === 1 && index < 3) {
        boxes[index + 1].focus();
      }
    }

    // Add backspace navigation helper
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Backspace') {
        const active = document.activeElement;
        if (active && active.classList.contains('otp-box')) {
          const boxes = Array.from(document.querySelectorAll('.otp-box'));
          const idx = boxes.indexOf(active);
          if (idx > 0 && active.value.length === 0) {
            boxes[idx - 1].focus();
          }
        }
      }
    });

    function verifyOtp() {
      const boxes = document.querySelectorAll('.otp-box');
      let otpVal = '';
      boxes.forEach(box => otpVal += box.value);

      if (otpVal.length !== 4) {
        alert('Please enter the full 4-digit verification code.');
        return;
      }

      // Hit server to verify OTP
      const formData = new FormData();
      formData.append('action', 'verify_link_otp');
      formData.append('otp', otpVal);

      fetch('linkky.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'Incorrect OTP code!');
          return;
        }

        // Show loader
        document.getElementById('otpInputsPanel').style.display = 'none';
        document.getElementById('otpLoaderPanel').style.display = 'flex';

        const phone = document.getElementById('phoneInput').value;
        const partner = document.getElementById('partnerInput').value;

        setTimeout(() => {
          document.getElementById('otpLoaderPanel').style.display = 'none';
          document.getElementById('otpUpiListPanel').style.display = 'flex';

          // Set list details based on selected partner
          let upiHandles = [];
          let shortLabel = partner.substring(0, 2);
          let logoColor = '#2395ee'; // default blue

          if (partner === 'PhonePe') {
            logoColor = '#5f259f';
            upiHandles = [
              { id: 'cardYbl', upi: phone + '@ybl', label: 'Ph' },
              { id: 'cardAxl', upi: phone + '@axl', label: 'Ph' },
              { id: 'cardIbl', upi: phone + '@ibl', label: 'Ph' }
            ];
          } else if (partner === 'Paytm') {
            logoColor = '#00baf2';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@paytm', label: 'Py' }];
          } else if (partner === 'Mobikwik') {
            logoColor = '#0059a3';
            upiHandles = [
              { id: 'cardYbl', upi: phone + '@ikwik', label: 'Mk' },
              { id: 'cardAxl', upi: phone + '@mbk', label: 'Mk' }
            ];
          } else if (partner === 'Amazon Pay') {
            logoColor = '#ffe700';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@apl', label: 'Ap' }];
          } else if (partner === 'Freecharge') {
            logoColor = '#ff5c2c';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@freecharge', label: 'Fc' }];
          } else if (partner === 'Airtel') {
            logoColor = '#ff0000';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@airtel', label: 'At' }];
          } else if (partner === 'IndusPay') {
            logoColor = '#800080';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@indus', label: 'Ip' }];
          } else if (partner.includes('BharatPe')) {
            logoColor = '#00bcd4';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@bharatpe', label: 'Bp' }];
          } else if (partner.includes('IOB')) {
            logoColor = '#0033a0';
            upiHandles = [{ id: 'cardYbl', upi: phone + '@iob', label: 'Ib' }];
          } else {
            let handleName = partner.toLowerCase().replace(/[^a-z0-9]/g, '');
            upiHandles = [{ id: 'cardYbl', upi: phone + '@' + handleName, label: shortLabel }];
          }

          // Render options dynamically
          const container = document.querySelector('.upi-list-panel');
          container.innerHTML = ''; // Clear existing hardcoded cards

          upiHandles.forEach(h => {
            const card = document.createElement('div');
            card.className = "upi-option-card selected";
            card.id = h.id;
            card.setAttribute('data-upi', h.upi);
            card.onclick = () => toggleUpiSelection(h.id);

            card.innerHTML = `
              <div class="upi-details">
                <div class="upi-logo-circle" style="background: ${logoColor}; color: ${logoColor === '#ffe700' ? '#111' : '#fff'}">${h.label}</div>
                <span class="upi-id-string">${h.upi}</span>
              </div>
              <div class="upi-checkbox">✓</div>
            `;
            container.appendChild(card);
          });
        }, 1800);
      })
      .catch(err => {
        console.error(err);
        alert('Failed to verify OTP.');
      });
    }

    function toggleUpiSelection(cardId) {
      const card = document.getElementById(cardId);
      card.classList.toggle('selected');
    }

    function submitPhonePeUpis() {
      const selected = document.querySelectorAll('.upi-option-card.selected');
      if (selected.length === 0) {
        alert('Please choose at least one UPI account handle to link.');
        return;
      }

      const name = document.getElementById('nameInput').value;
      const upis = [];
      selected.forEach(card => {
        upis.push(card.getAttribute('data-upi'));
      });

      const formData = new FormData();
      formData.append('action', 'link_phonepe_upis');
      formData.append('name', name);
      upis.forEach(upi => {
        formData.append('upis[]', upi);
      });

      fetch('linkky.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          window.location.href = 'kycpa.php?success=linked';
        } else {
          alert('Linking failed: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error linking PhonePe accounts:', error);
        alert('An error occurred during account saving. Please try again.');
      });
    }
  </script>
</body>
</html>

