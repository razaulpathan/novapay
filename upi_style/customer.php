<?php
// customer.php - Official Customer Service Handles
require_once 'db.php';
requireLogin();

$telegram_channel = getSetting('support_telegram_channel') ?: 'https://t.me/NextPay_channel';
$telegram_vip     = getSetting('support_telegram_vip') ?: 'https://t.me/NextPay_vip';
$telegram_csr     = getSetting('support_telegram_csr') ?: 'https://t.me/NextPay_csr';
$whatsapp_support = getSetting('support_whatsapp') ?: 'https://wa.me/919999999999';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Customer Service</title>
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
      padding: 12px 0;
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }

    /* Service List row */
    .service-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 16px;
      border-bottom: 1.5px solid #f2f5f8;
      gap: 16px;
    }

    .service-left {
      display: flex;
      align-items: center;
      gap: 14px;
      flex: 1;
    }

    .icon-wrapper {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .icon-wrapper.tg {
      background: #2ea6de;
      color: #ffffff;
    }

    .icon-wrapper.wa {
      background: #25d366;
      color: #ffffff;
    }

    .icon-wrapper svg {
      width: 24px;
      height: 24px;
    }

    .service-info {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .service-title {
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
    }

    .service-subtitle {
      font-size: 12px;
      color: #95a5a6;
      font-weight: bold;
    }

    /* Action button */
    .go-btn {
      background: #2395ee;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 8px 24px;
      font-size: 14px;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      box-shadow: 0 3px 8px rgba(35, 149, 238, 0.2);
      transition: background-color 0.2s, transform 0.1s;
    }

    .go-btn:hover {
      background: #0d7dd4;
    }

    .go-btn:active {
      transform: scale(0.96);
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
        <!-- Back chevron SVG -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Customer Service</h1>
    </header>

    <div class="content">
      
      <!-- TG 1 -->
      <div class="service-row">
        <div class="service-left">
          <div class="icon-wrapper tg">
            <!-- Telegram Icon SVG -->
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
            </svg>
          </div>
          <div class="service-info">
            <span class="service-title">NextPay Online Service</span>
            <span class="service-subtitle">TG - Subscription Channel</span>
          </div>
        </div>
        <button class="go-btn" onclick="window.open('<?= sanitize($telegram_channel) ?>', '_blank')">Go</button>
      </div>

      <!-- TG 2 -->
      <div class="service-row">
        <div class="service-left">
          <div class="icon-wrapper tg">
            <!-- Telegram Icon SVG -->
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
            </svg>
          </div>
          <div class="service-info">
            <span class="service-title">Personal Customer Service</span>
            <span class="service-subtitle">TG - VIP Service</span>
          </div>
        </div>
        <button class="go-btn" onclick="window.open('<?= sanitize($telegram_vip) ?>', '_blank')">Go</button>
      </div>

      <!-- TG 3 -->
      <div class="service-row">
        <div class="service-left">
          <div class="icon-wrapper tg">
            <!-- Telegram Icon SVG -->
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.02 5.75-.14.75-.41 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.35-.49.97-.74 3.79-1.65 6.32-2.73 7.57-3.26 3.61-1.51 4.36-1.77 4.85-1.78.11 0 .35.03.5.16.13.12.17.28.18.4z"/>
            </svg>
          </div>
          <div class="service-info">
            <span class="service-title">NextPay Online CSR</span>
            <span class="service-subtitle">Official Customer Service</span>
          </div>
        </div>
        <button class="go-btn" onclick="window.open('<?= sanitize($telegram_csr) ?>', '_blank')">Go</button>
      </div>

      <!-- WA -->
      <div class="service-row">
        <div class="service-left">
          <div class="icon-wrapper wa">
            <!-- WhatsApp Icon SVG -->
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm5.82 14c-.26.74-1.54 1.37-2.12 1.43-.57.06-1.12.3-3.66-.7-3.25-1.28-5.35-4.59-5.51-4.8-.16-.22-1.32-1.76-1.32-3.36 0-1.6.84-2.39 1.14-2.72.3-.33.66-.41.88-.41.22 0 .44.01.62.02.2.01.46-.08.72.54.26.63.88 2.14.96 2.3.08.16.14.35.03.57-.11.22-.24.36-.38.52-.14.16-.3.36-.43.48-.15.14-.3.29-.13.58.17.29.76 1.25 1.63 2.03.87.78 1.61 1.02 1.9 1.17.29.15.46.12.63-.07.17-.19.74-.86.94-1.15.2-.29.4-.25.67-.15s1.72.81 2.02.96c.3.15.5.22.57.35.07.12.07.72-.19 1.46z"/>
            </svg>
          </div>
          <div class="service-info">
            <span class="service-title">Online CSR</span>
            <span class="service-subtitle">Official Customer Service</span>
          </div>
        </div>
        <button class="go-btn" onclick="window.open('<?= sanitize($whatsapp_support) ?>', '_blank')">Go</button>
      </div>

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
</body>
</html>

