<?php
// home.php - User Home Dashboard
require_once 'db.php';
requireLogin();

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch exchange rate and night-time ratio
$exchange_rate_usdt = getSetting('exchange_rate_usdt') ?: '106.7';
$buy_ratio_nighttime = getSetting('buy_ratio_nighttime') ?: '3.0';

// Fetch active banners from DB
$home_banners = [];
try {
    $bStmt = $pdo->query("SELECT * FROM home_banners WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $home_banners = $bStmt->fetchAll();
} catch (PDOException $e) { /* table may not exist yet */ }

// Fetch latest active announcement
$news_item = null;
try {
    $newsStmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $news_item = $newsStmt->fetch();
} catch (PDOException $e) { /* table may not exist yet */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay Home</title>
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
      --accent-orange: #ff9933;
      --accent-green: #138808;
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
      background: var(--bg-color);
      position: relative;
      padding-bottom: 80px; /* Space for fixed bottom nav */
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Scrollable content area */
    .content {
      padding: 16px;
      flex: 1;
      overflow-y: auto;
    }

    /* Banner Slider Card */
    .banner {
      position: relative;
      width: 100%;
      height: 200px;
      background: linear-gradient(135deg, #0b5cd3 0%, #053b92 100%);
      border-radius: 16px;
      color: white;
      padding: 16px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 4px 15px rgba(11, 92, 211, 0.2);
    }

    /* Tricolor flag accent on the right of the banner */
    .banner-flag-accent {
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      width: 45%;
      pointer-events: none;
      opacity: 0.95;
    }

    .banner-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 2;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .logo-mark {
      width: 24px;
      height: 28px;
    }

    .brand-name {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 18px;
      font-weight: 900;
      letter-spacing: 0.5px;
    }

    .upi-brand {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      line-height: 1;
    }

    .upi-logo-text {
      font-size: 16px;
      font-weight: 900;
      font-style: italic;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 2px;
    }

    .upi-logo-text span {
      color: #ff9933;
    }

    .upi-logo-text span:last-child {
      color: #138808;
    }

    .upi-sub {
      font-size: 6px;
      font-weight: bold;
      opacity: 0.8;
      letter-spacing: 0.2px;
    }

    .banner-body {
      max-width: 60%;
      z-index: 2;
      margin-top: 10px;
    }

    .banner-title {
      font-size: 16px;
      font-weight: bold;
      line-height: 1.2;
      margin: 0 0 6px 0;
      text-shadow: 0 1px 3px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .banner-title svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }

    .banner-subtitle {
      font-size: 10px;
      opacity: 0.9;
      line-height: 1.3;
      margin: 0;
    }

    .banner-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      z-index: 2;
    }

    /* Gold coins stacking */
    .coins-stack {
      display: flex;
      align-items: flex-end;
      gap: 2px;
      margin-bottom: -4px;
    }

    .coin-single {
      width: 16px;
      height: 16px;
      background: radial-gradient(circle, #ffe066 30%, #f5b041 90%);
      border-radius: 50%;
      border: 1px solid #d35400;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      font-weight: bold;
      color: #d35400;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .coin-pile {
      position: relative;
      width: 24px;
      height: 16px;
    }

    .coin-pile .coin {
      position: absolute;
      width: 14px;
      height: 14px;
      background: radial-gradient(circle, #ffe066 30%, #f5b041 90%);
      border-radius: 50%;
      border: 1px solid #d35400;
      box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .coin-pile .coin:nth-child(1) { bottom: 0; left: 0; }
    .coin-pile .coin:nth-child(2) { bottom: 0; left: 8px; }
    .coin-pile .coin:nth-child(3) { bottom: 4px; left: 4px; z-index: 2; }

    /* Slider Dots Indicators */
    .slider-dots {
      display: flex;
      gap: 4px;
      margin-left: 20px;
      margin-bottom: 2px;
    }

    .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
    }

    .dot.active {
      width: 18px;
      border-radius: 3px;
      background: #ffffff;
    }

    /* IToken Balance Card Section */
    .balance-card {
      margin-top: 16px;
      background: #fffdf4 url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="%23f9ebd2" stroke-width="1.5" stroke-dasharray="4,4"/><path d="M42 35 h16 M42 45 h16 M50 35 v30 M42 65 h16" fill="none" stroke="%23f9ebd2" stroke-width="2"/></svg>') repeat;
      background-size: 120px;
      border-radius: 16px;
      padding: 16px;
      border: 1px solid #f2e6cb;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    }

    .balance-header {
      display: flex;
      align-items: baseline;
      gap: 8px;
    }

    .balance-title {
      font-size: 20px;
      font-weight: 800;
      color: #3f3d32;
      margin: 0;
    }

    .exchange-rate {
      font-size: 11px;
      color: #8c8877;
      font-family: Arial, sans-serif;
    }

    .balance-display-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 14px;
      margin-bottom: 18px;
    }

    .balance-amount-wrapper {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .flag-circle {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      overflow: hidden;
      border: 2px solid #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
    }

    .flag-stripe {
      height: 14px;
      width: 100%;
    }

    .flag-stripe.orange { background: #ff9933; }
    .flag-stripe.white {
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .flag-stripe.green { background: #138808; }
    .flag-wheel {
      width: 8px;
      height: 8px;
      border: 1px solid #000080;
      border-radius: 50%;
      position: absolute;
    }

    .flag-wheel::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, #000080 20%, transparent 30%);
    }

    .balance-value {
      font-size: 40px;
      font-weight: 900;
      color: #2b2b2b;
      line-height: 1;
    }

    /* Buy button */
    .buy-btn {
      background: #1c82e6;
      color: white;
      border: none;
      border-radius: 12px;
      padding: 12px 20px;
      font-size: 16px;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(28, 130, 230, 0.3);
      font-family: inherit;
      transition: background 0.2s, transform 0.1s;
    }

    .buy-btn:hover {
      background: #0f6fcd;
    }

    .buy-btn:active {
      transform: scale(0.97);
    }

    .rupee-circle {
      width: 22px;
      height: 22px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: bold;
    }

    /* Statistics Grid Layout */
    .stats-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .stats-left-col {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .stat-box {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .stat-label {
      font-size: 14px;
      color: #7d7d7d;
      font-weight: bold;
    }

    .stat-value {
      font-size: 18px;
      color: #111111;
      font-weight: 900;
    }

    .stat-value.link {
      color: #1c82e6;
      cursor: pointer;
      text-decoration: underline;
    }

    /* History Button Styles */
    .history-card {
      background: #ffffff;
      border: 1px solid #e1e8ed;
      border-radius: 12px;
      padding: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 800;
      color: #2c3e50;
      cursor: pointer;
      text-align: center;
      transition: background-color 0.2s, box-shadow 0.2s;
      min-height: 52px;
    }

    .history-card:hover {
      background-color: #fafbfc;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    /* Notice Bar section */
    .notice-bar {
      margin-top: 14px;
      background: #ffffff;
      border-radius: 8px;
      padding: 8px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    }

    .notice-left {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: bold;
      color: #555555;
    }

    .notice-left svg {
      color: #888888;
      width: 16px;
      height: 16px;
      flex-shrink: 0;
    }

    .notice-info-btn {
      color: #b8c0c8;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .notice-info-btn svg {
      width: 16px;
      height: 16px;
    }

    /* News Feed Section */
    .news-card {
      margin-top: 14px;
      background: #ffffff;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    }

    .news-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      border-bottom: 1px solid #f5f7fa;
      padding-bottom: 8px;
    }

    .news-title {
      font-size: 16px;
      font-weight: bold;
      color: #111111;
      margin: 0;
    }

    .news-more {
      font-size: 13px;
      color: #95a5a6;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 4px;
      font-weight: bold;
    }

    .news-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
      cursor: pointer;
    }

    .news-item-title {
      font-size: 15px;
      font-weight: bold;
      color: #333333;
    }

    .news-item-date {
      font-size: 11px;
      color: #bdc3c7;
      font-family: Arial, sans-serif;
    }

    /* Floating Chat Action Button */
    .fab {
      position: absolute;
      bottom: 96px;
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
      transition: transform 0.2s;
    }

    .fab:hover {
      transform: scale(1.05);
    }

    .fab svg {
      width: 24px;
      height: 24px;
    }

    /* Bottom Tab Navigation Bar */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: min(100%, 574px);
      height: 64px;
      background: #ffffff;
      border-top: 1px solid var(--line);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 100;
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      text-decoration: none;
      color: var(--muted);
      font-size: 12px;
      font-weight: bold;
      width: 20%;
      height: 100%;
      transition: color 0.2s;
    }

    .nav-item.active {
      color: var(--blue);
    }

    .nav-item svg {
      width: 22px;
      height: 22px;
    }

    /* Media query styles */
    @media (max-width: 430px) {
      .balance-value {
        font-size: 32px;
      }
      .flag-circle {
        width: 36px;
        height: 36px;
      }
      .flag-stripe {
        height: 12px;
      }
      .buy-btn {
        padding: 10px 16px;
        font-size: 14px;
      }
      .stats-layout {
        gap: 8px;
      }
      .history-card {
        padding: 12px;
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="screen">
    <div class="content">
      
      <!-- Top banner slider -->
      <?php if (!empty($home_banners)): ?>
      <!-- ── Dynamic DB Banners ── -->
      <div class="banner" id="bannerSlider" style="padding:0; overflow:hidden; position:relative; cursor:pointer;">
        <div id="bannerTrack" style="display:flex; transition: transform 0.45s ease; height:100%;">
          <?php foreach ($home_banners as $bn): ?>
          <div style="min-width:100%; height:200px; position:relative; overflow:hidden; flex-shrink:0;
               background: linear-gradient(135deg, #0b5cd3 0%, #053b92 100%);">
            <?php if ($bn['image_url']): ?>
              <img src="<?= htmlspecialchars($bn['image_url']) ?>" alt="banner"
                   style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0.85;">
            <?php else: ?>
              <!-- Default SVG decoration when no image -->
              <svg style="position:absolute;right:0;top:0;bottom:0;width:45%;opacity:0.95;pointer-events:none;" viewBox="0 0 180 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M120 0 C135 40, 110 80, 130 130 C145 170, 160 190, 180 200 L180 0 Z" fill="#138808" fill-opacity="0.25"/>
                <path d="M90 0 C110 50, 95 90, 110 140 C125 180, 150 190, 180 200 L180 0 Z" fill="#ffffff" fill-opacity="0.15"/>
                <path d="M60 0 C80 40, 70 80, 90 130 C105 175, 130 190, 180 200 L180 0 Z" fill="#ff9933" fill-opacity="0.2"/>
              </svg>
            <?php endif; ?>
            <!-- Gradient overlay for text readability -->
            <div style="position:absolute;inset:0;background:linear-gradient(to right, rgba(5,59,146,0.9) 0%, rgba(5,59,146,0.3) 70%, transparent 100%);"></div>
            <div style="position:relative;z-index:2;padding:18px 20px;height:100%;display:flex;flex-direction:column;justify-content:space-between;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:6px;">
                  <img src="images/Login_Logo.png" alt="logo" style="width:26px;height:26px;border-radius:5px;object-fit:cover;">
                  <span style="font-size:17px;font-weight:900;color:#fff;letter-spacing:0.5px;">NextPay</span>
                </div>
                <div style="text-align:right;line-height:1;">
                  <div style="font-size:15px;font-weight:900;font-style:italic;color:#fff;">U<span style="color:#ff9933">P</span>I</div>
                  <div style="font-size:6px;font-weight:bold;opacity:0.8;color:#fff;letter-spacing:0.2px;">NATIVE THIRD PARTY PAYMENT</div>
                </div>
              </div>
              <div style="max-width:70%;">
                <?php if ($bn['link_url']): ?><a href="<?= htmlspecialchars($bn['link_url']) ?>" style="text-decoration:none;"><?php endif; ?>
                <h2 style="font-size:16px;font-weight:bold;line-height:1.25;margin:0 0 6px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.4);">
                  <?= htmlspecialchars($bn['title']) ?>
                </h2>
                <?php if ($bn['link_url']): ?></a><?php endif; ?>
                <?php if ($bn['subtitle']): ?>
                <p style="font-size:10px;opacity:0.9;line-height:1.3;margin:0;color:#fff;"><?= htmlspecialchars($bn['subtitle']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Dots indicator -->
        <div id="bannerDots" style="position:absolute;bottom:10px;right:16px;display:flex;gap:4px;z-index:5;">
          <?php foreach ($home_banners as $i => $bn): ?>
          <div class="dot <?= $i===0?'active':'' ?>" data-idx="<?= $i ?>" onclick="goToBanner(<?= $i ?>)" style="cursor:pointer;"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <script>
        (function(){
          var cur = 0, total = <?= count($home_banners) ?>;
          var track = document.getElementById('bannerTrack');
          var dots  = document.querySelectorAll('#bannerDots .dot');
          function goToBanner(idx) {
            cur = (idx + total) % total;
            track.style.transform = 'translateX(-' + (cur * 100) + '%)';
            dots.forEach(function(d,i){ d.classList.toggle('active', i===cur); });
          }
          window.goToBanner = goToBanner;
          if (total > 1) { setInterval(function(){ goToBanner(cur+1); }, 4000); }
        })();
      </script>

      <?php else: ?>
      <!-- ── Fallback Static Banner ── -->
      <div class="banner">
        <svg class="banner-flag-accent" viewBox="0 0 180 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M120 0 C135 40, 110 80, 130 130 C145 170, 160 190, 180 200 L180 0 Z" fill="#138808" fill-opacity="0.25"/>
          <path d="M90 0 C110 50, 95 90, 110 140 C125 180, 150 190, 180 200 L180 0 Z" fill="#ffffff" fill-opacity="0.15"/>
          <path d="M60 0 C80 40, 70 80, 90 130 C105 175, 130 190, 180 200 L180 0 Z" fill="#ff9933" fill-opacity="0.2"/>
          <circle cx="160" cy="80" r="30" fill="none" stroke="#ff9933" stroke-width="3" opacity="0.3"/>
          <circle cx="160" cy="80" r="24" fill="none" stroke="#ffffff" stroke-width="3" opacity="0.4"/>
          <circle cx="160" cy="80" r="18" fill="none" stroke="#138808" stroke-width="3" opacity="0.3"/>
          <circle cx="160" cy="80" r="12" fill="none" stroke="#000080" stroke-width="1.5" opacity="0.3"/>
        </svg>
        <div class="banner-header">
          <div class="logo-container">
            <img class="logo-mark" src="images/Login_Logo.png" alt="NextPay logo" style="width:28px;height:28px;border-radius:6px;object-fit:cover;">
            <div class="brand-name">NextPay</div>
          </div>
          <div class="upi-brand">
            <div class="upi-logo-text">U<span>P</span>I</div>
            <div class="upi-sub">NATIVE THIRD PARTY PAYMENT</div>
          </div>
        </div>
        <div class="banner-body">
          <h2 class="banner-title">Invite your friends with your referral link
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
          </h2>
          <p class="banner-subtitle">Earn referral rewards and profit on first sign-up</p>
        </div>
        <div class="banner-footer">
          <div class="coins-stack">
            <div class="coin-single">₹</div>
            <div class="coin-pile"><div class="coin"></div><div class="coin"></div><div class="coin"></div></div>
          </div>
          <div class="slider-dots">
            <div class="dot active"></div>
            <div class="dot"></div><div class="dot"></div><div class="dot"></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Balance Card -->

      <div class="balance-card">
        <div class="balance-header">
          <h3 class="balance-title">My IToken</h3>
          <span class="exchange-rate">1 Rs = 1 IToken, 1 USDT &approx; <?= sanitize($exchange_rate_usdt) ?> IToken</span>
        </div>

        <div class="balance-display-row">
          <div class="balance-amount-wrapper">
            <!-- Round Indian Flag -->
            <div class="flag-circle">
              <div class="flag-stripe orange"></div>
              <div class="flag-stripe white">
                <div class="flag-wheel"></div>
              </div>
              <div class="flag-stripe green"></div>
            </div>
            <div class="balance-value"><?= number_format($user['itoken_balance'], 2, '.', '') ?></div>
          </div>

          <a href="upibuyplus.php" class="buy-btn" style="text-decoration: none;">
            <span class="rupee-circle">₹</span>
            Buy
          </a>
        </div>

        <!-- Details and History Action Grid -->
        <div class="stats-layout">
          <!-- Column 1: Info Labels -->
          <div class="stats-left-col">
            <div class="stat-box">
              <span class="stat-label">Today Profit</span>
              <span class="stat-value"><?= number_format($user['today_profit'], 2, '.', '') ?></span>
            </div>
            
            <div class="stat-box">
              <span class="stat-label">Reward</span>
              <span class="stat-value"><?= number_format($user['reward_rate'], 2, '.', '') ?>%</span>
            </div>

            <div class="stat-box">
              <span class="stat-label">Auto Selling</span>
              <span class="stat-value"><?= $user['auto_selling'] ? 'Sell Set' : 'Inactive' ?></span>
            </div>

            <div class="stat-box" onclick="location.href='link_upi.php'">
              <span class="stat-label">Sell Faster</span>
              <span class="stat-value stat-value link">Link Upi</span>
            </div>
          </div>

          <!-- Column 2: History Buttons -->
          <div class="stats-left-col">
            <div class="history-card" onclick="location.href='depo.php'" style="margin-bottom: 8px; flex: 1;">
              Buy History
            </div>
            <div class="history-card" onclick="location.href='sellhis.php'" style="flex: 1;">
              Sell History
            </div>
          </div>
        </div>
      </div>

      <!-- Notice Bar -->
      <div class="notice-bar">
        <div class="notice-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span>Night-Time Buy Ratio Increased to <?= sanitize($buy_ratio_nighttime) ?>%</span>
        </div>
        <div class="notice-info-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
        </div>
      </div>

      <!-- News Section -->
      <?php if ($news_item): ?>
      <div class="news-card">
        <div class="news-header">
          <h4 class="news-title">News</h4>
        </div>
        <div class="news-item" onclick="showNewsModal(<?= htmlspecialchars(json_encode($news_item['title'])) ?>, <?= htmlspecialchars(json_encode($news_item['body'])) ?>)">
          <div class="news-item-title"><?= sanitize($news_item['title']) ?></div>
          <div class="news-item-date"><?= $news_item['created_at'] ?></div>
        </div>
      </div>
      <?php else: ?>
      <div class="news-card">
        <div class="news-header">
          <h4 class="news-title">News</h4>
        </div>
        <div class="news-item">
          <div class="news-item-title">Welcome to NextPay!</div>
          <div class="news-item-date">2026-07-01</div>
        </div>
      </div>
      <?php endif; ?>

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

    <!-- Bottom navigation tabs -->
    <nav class="bottom-nav">
      <a href="home.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Home
      </a>
      <a href="upibuyplus.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="2" y1="10" x2="22" y2="10"></line>
        </svg>
        Buy
      </a>
      <a href="kycpa.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        UPI
      </a>
      <a href="agent.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Team
      </a>
      <a href="mine.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        Mine
      </a>
    </nav>
  </div>

  <?php
  // Load dynamic popup headers and images
  $header1 = getSetting('site_popup_header_1') ?: 'Night-Time Buy Ratio Increased to 3.0%';
  $image1  = getSetting('site_popup_image_1') ?: 'images/banner_nighttime.png';
  $header2 = getSetting('site_popup_header_2') ?: 'GUIDANCE REWARDS';
  $image2  = getSetting('site_popup_image_2') ?: 'images/banner_guidance.png';
  $header3 = getSetting('site_popup_header_3') ?: 'BUY ORDER REWARDS';
  $image3  = getSetting('site_popup_image_3') ?: 'images/banner_latest_event.png';
  
  if (!empty($image1) || !empty($image2) || !empty($image3)):
  ?>
    <!-- Global Announcement Popup Modal -->
    <div id="announcement-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
      <div class="modal-content" style="background: #ffffff; width: 100%; max-width: 420px; max-height: 80vh; border-radius: 16px; padding: 28px 16px 20px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35); display: flex; flex-direction: column; position: relative; color: #333333; text-align: center; overflow: hidden;">
        
        <!-- Close button -->
        <button onclick="closeAnnouncement()" style="position: absolute; top: 12px; right: 12px; background: #f5f7fa; border: none; border-radius: 50%; color: #8c9cb5; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 6px; z-index: 10;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 16px; height: 16px;">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <!-- Scrollable Event Items List -->
        <div style="overflow-y: auto; flex: 1; padding: 4px; display: flex; flex-direction: column; gap: 24px; text-align: center; -webkit-overflow-scrolling: touch;">
          
          <!-- Event 1 -->
          <?php if (!empty($image1)): ?>
            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
              <h4 style="font-size: 13px; font-weight: 800; color: #2c3e50; margin: 0; text-transform: uppercase; font-family: inherit;"><?= sanitize($header1) ?></h4>
              <img src="<?= sanitize($image1) ?>" alt="Banner 1" style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.06); object-fit: cover;">
            </div>
          <?php endif; ?>

          <!-- Event 2 -->
          <?php if (!empty($image2)): ?>
            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
              <h4 style="font-size: 13px; font-weight: 800; color: #2c3e50; margin: 0; text-transform: uppercase; font-family: inherit;"><?= sanitize($header2) ?></h4>
              <img src="<?= sanitize($image2) ?>" alt="Banner 2" style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.06); object-fit: cover;">
            </div>
          <?php endif; ?>

          <!-- Event 3 -->
          <?php if (!empty($image3)): ?>
            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
              <h4 style="font-size: 13px; font-weight: 800; color: #2c3e50; margin: 0; text-transform: uppercase; font-family: inherit;"><?= sanitize($header3) ?></h4>
              <img src="<?= sanitize($image3) ?>" alt="Banner 3" style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.06); object-fit: cover;">
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", function() {
        // Check if popup was already shown in this session
        if (!sessionStorage.getItem("announcement_shown")) {
          const modal = document.getElementById("announcement-modal");
          if (modal) {
            modal.style.display = "flex";
          }
        }
      });

      function closeAnnouncement() {
        const modal = document.getElementById("announcement-modal");
        if (modal) {
          modal.style.display = "none";
        }
        sessionStorage.setItem("announcement_shown", "true");
      }

      function showNewsModal(title, body) {
        document.getElementById('news-modal-title').textContent = title;
        document.getElementById('news-modal-body').innerHTML = body.replace(/\n/g, '<br>');
        document.getElementById('news-modal').style.display = 'flex';
      }

      function closeNewsModal() {
        document.getElementById('news-modal').style.display = 'none';
      }
    </script>
  <?php endif; ?>

  <!-- Dynamic News Detail Modal -->
  <div id="news-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 20px;">
    <div class="modal-content" style="background: #ffffff; width: 100%; max-width: 420px; max-height: 80vh; border-radius: 16px; padding: 28px 16px 20px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35); display: flex; flex-direction: column; position: relative; color: #333333; text-align: center; overflow: hidden;">
      <button onclick="closeNewsModal()" style="position: absolute; top: 12px; right: 12px; background: #f5f7fa; border: none; border-radius: 50%; color: #8c9cb5; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 6px; z-index: 10;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 16px; height: 16px;">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
      <h3 id="news-modal-title" style="margin-top: 10px; margin-bottom: 16px; color: #2c3e50; font-size: 18px; font-weight: 800;"></h3>
      <div id="news-modal-body" style="overflow-y: auto; flex: 1; font-size: 14px; line-height: 1.6; color: #555555; text-align: left; padding: 0 8px; -webkit-overflow-scrolling: touch;"></div>
    </div>
  </div>

  <!-- Fallback JS when no announcement popup modal wraps the script -->
  <script>
    if (typeof showNewsModal === 'undefined') {
      function showNewsModal(title, body) {
        document.getElementById('news-modal-title').textContent = title;
        document.getElementById('news-modal-body').innerHTML = body.replace(/\n/g, '<br>');
        document.getElementById('news-modal').style.display = 'flex';
      }
      function closeNewsModal() {
        document.getElementById('news-modal').style.display = 'none';
      }
    }
  </script>
</body>
</html>

