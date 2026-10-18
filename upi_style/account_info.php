<?php
// account_info.php - User Account Details Page
require_once 'db.php';
requireLogin();

// Fetch fresh user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Account Info</title>
  
  <!-- Modern Outfit / Inter Font -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --blue: #3a6bf6;
      --text-dark: #2c3e50;
      --text-light: #7f8c8d;
      --text-gray: #8c9cb5;
      --line: #f2f5f8;
      --bg-color: #ffffff;
      --muted: #b8c0c8;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #eef2f5;
      color: var(--text-dark);
      font-family: 'Outfit', sans-serif;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .screen {
      width: min(100%, 574px);
      min-height: 100vh;
      background: var(--bg-color);
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
      padding: 0 20px;
      border-bottom: 1px solid var(--line);
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 10;
    }

    .back-btn {
      background: transparent;
      border: 0;
      cursor: pointer;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      margin-left: -8px;
    }

    .back-btn svg {
      width: 24px;
      height: 24px;
    }

    .header-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
      margin: 0 auto;
      transform: translateX(-16px); /* Align center compensated back button */
    }

    /* Content Area */
    .content {
      padding: 24px 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
    }

    /* Avatar Illustration Circle */
    .avatar-container {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 1.5px solid #d4e6f1;
      overflow: hidden;
      margin-bottom: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fcff;
    }

    /* Data Rows Container */
    .info-list {
      width: 100%;
      display: flex;
      flex-direction: column;
    }

    .info-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 0;
      border-bottom: 1px solid var(--line);
    }

    .info-label {
      font-size: 18px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .info-right-group {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .info-value {
      font-size: 17px;
      color: var(--text-gray);
      font-weight: 500;
      font-family: Arial, sans-serif;
    }

    .action-icon {
      background: transparent;
      border: 0;
      cursor: pointer;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 4px;
      transition: opacity 0.2s;
    }

    .action-icon:hover {
      opacity: 0.7;
    }

    .action-icon svg {
      width: 20px;
      height: 20px;
    }

    /* Custom Toast Alert */
    .toast-alert {
      position: absolute;
      bottom: 100px;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: rgba(0, 0, 0, 0.8);
      color: #ffffff;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: bold;
      opacity: 0;
      transition: opacity 0.3s, transform 0.3s;
      z-index: 300;
      pointer-events: none;
    }

    .toast-alert.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    /* Floating Support Button */
    .fab {
      position: absolute;
      bottom: 24px;
      right: 20px;
      width: 52px;
      height: 52px;
      background: var(--blue);
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
    
    <!-- Top Header -->
    <header class="header">
      <button class="back-btn" onclick="location.href='mine.php'" title="Back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </button>
      <h1 class="header-title">Account Info</h1>
    </header>

    <!-- Content Area -->
    <div class="content">
      
      <!-- Avatar Section -->
      <div class="avatar-container">
        <!-- Exact Illustration Match from Screenshot -->
        <svg viewBox="0 0 120 120" style="width: 100%; height: 100%;">
          <circle cx="60" cy="60" r="60" fill="#e2eefc"/>
          <path d="M60 70 A 24 24 0 0 0 36 94 A 8 8 0 0 0 44 102 L 76 102 A 8 8 0 0 0 84 94 A 24 24 0 0 0 60 70 Z" fill="#07588f"/>
          <circle cx="60" cy="46" r="16" fill="#fbb0ac" />
          <path d="M44 46 Q60 54 76 46 Z" fill="#07588f" opacity="0.3"/>
          <rect x="58" y="58" width="4" height="14" rx="2" fill="#fbb0ac"/>
          <path d="M44 38 Q60 30 76 38" fill="none" stroke="#07588f" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>

      <!-- Information List -->
      <div class="info-list">
        
        <!-- UID Row -->
        <div class="info-row">
          <span class="info-label">ID:</span>
          <div class="info-right-group">
            <span class="info-value" id="val-uid"><?= sanitize($user['id']) ?></span>
            <button class="action-icon" onclick="copyToClipboard('val-uid', 'UID copied successfully!')" title="Copy ID">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Phone Row -->
        <div class="info-row">
          <span class="info-label">Phone:</span>
          <div class="info-right-group">
            <span class="info-value" id="val-phone"><?= sanitize($user['phone']) ?></span>
            <button class="action-icon" onclick="copyToClipboard('val-phone', 'Phone number copied successfully!')" title="Copy Phone">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Security Password Row -->
        <div class="info-row" style="border-bottom: 0;">
          <span class="info-label">Security</span>
          <div class="info-right-group">
            <span class="info-value">********</span>
            <button class="action-icon" onclick="location.href='editpass.php'" title="Edit Password">
              <!-- Edit/Arrow up-right icon from screenshot -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- Floating Support Chat -->
    <div class="fab" onclick="location.href='customer.php'">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <circle cx="9" cy="10" r="1"></circle>
        <circle cx="13" cy="10" r="1"></circle>
        <circle cx="17" cy="10" r="1"></circle>
      </svg>
    </div>

    <!-- Toast Notification -->
    <div class="toast-alert" id="toastAlert">Copied!</div>

  </div>

  <script>
    function copyToClipboard(elementId, successMessage) {
      const textToCopy = document.getElementById(elementId).textContent;
      const tempInput = document.createElement('input');
      tempInput.value = textToCopy;
      document.body.appendChild(tempInput);
      tempInput.select();
      document.execCommand('copy');
      document.body.removeChild(tempInput);

      const toast = document.getElementById('toastAlert');
      toast.textContent = successMessage;
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
      }, 1500);
    }
  </script>
</body>
</html>
