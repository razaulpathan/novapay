<?php
// agent/header.php - Modular Header Layout
require_once __DIR__ . '/../db.php';

// Auth checks
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Fetch promoter/agent details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'Blocked') {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (!in_array($user['role'], ['Agent', 'Admin', 'SubAdmin'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Determine active items for navigation
if (!isset($current_page)) {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
}

// Retrieve promoter/agent allowed menu options
$allowed_pages = json_decode(getSetting('agent_allowed_pages'), true);
if ($allowed_pages === null) {
    $allowed_pages = [
        'team_daily.php', 'token_stmt.php', 'token_stmt_his.php', 
        'daily_stat.php', 'transfer_token.php', 'index.php', 
        'sub_agent.php', 'leaderboard.php', 'security.php', 
        'support_tickets.php', 'security_logs.php'
    ];
}

// Block access to disabled pages
if (!in_array($current_page, $allowed_pages) && $current_page !== 'index.php' && $current_page !== 'verify_2fa.php') {
    header("Location: index.php");
    exit;
}

// Identify menu folders
$biz_pages = ['team_daily.php', 'token_stmt.php', 'token_stmt_his.php', 'daily_stat.php', 'transfer_token.php'];
$current_menu = in_array($current_page, $biz_pages) ? 'biz' : 'home';

// Page Title Mapping
$page_titles = [
    'index.php' => 'Home',
    'team_daily.php' => 'Team Daily',
    'token_stmt.php' => 'Token STMT',
    'token_stmt_his.php' => 'Token STMT HIS',
    'daily_stat.php' => 'Daily Statistic',
    'sub_agent.php' => 'Register Sub-Agent',
    'transfer_token.php' => 'Transfer Token',
    'security.php' => 'Security (2FA)',
    'support_tickets.php' => 'Support Desk',
    'security_logs.php' => 'Security Logs',
    'leaderboard.php' => 'Leaderboard'
];
$active_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Dashboard';

// Generate promoter referral URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$referral_url = $protocol . $_SERVER['HTTP_HOST'] . "/register.php?ref=" . $user['referral_code'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - <?php echo $active_title; ?></title>
  
  <!-- Modern Outfit Font -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- CSS Styles -->
  <style>
    :root {
      /* Default Theme Color: Emerald Green */
      --primary: #00b578;
      --primary-rgb: 0, 181, 120;
      
      /* Light Mode Palette */
      --bg-body: #f5f7fa;
      --bg-sidebar-primary: #001529;
      --bg-sidebar-secondary: #ffffff;
      --bg-card: #ffffff;
      --bg-header: #ffffff;
      
      --text-main: #2c3e50;
      --text-secondary: #8c9cb5;
      --text-sidebar-primary: #a6adb4;
      --text-sidebar-secondary: #555555;
      
      --border-color: #ebedf2;
      --table-header-bg: #f8f9fa;
      --table-hover-bg: #fafbfc;
      
      --success: #2ecc71;
      --danger: #e74c3c;
      --warning: #f39c12;
      --info: #3498db;
      
      --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.04);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    /* Dark Mode Override */
    html.dark {
      --bg-body: #10141d;
      --bg-sidebar-primary: #090c12;
      --bg-sidebar-secondary: #161b26;
      --bg-card: #161b26;
      --bg-header: #161b26;
      
      --text-main: #f3f6f9;
      --text-secondary: #6e7c91;
      --text-sidebar-primary: #6e7c91;
      --text-sidebar-secondary: #abb4be;
      
      --border-color: #242b38;
      --table-header-bg: #1c222f;
      --table-hover-bg: #1f2735;
      
      --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.2);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: var(--bg-body);
      color: var(--text-main);
      font-family: 'Outfit', sans-serif;
      display: flex;
      min-height: 100vh;
      overflow-x: hidden;
      transition: background-color 0.2s, color 0.2s;
    }

    /* Double Sidebar Container */
    .sidebar-container {
      display: flex;
      height: 100vh;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: var(--shadow-md);
      transition: transform 0.3s ease, width 0.3s ease;
    }

    /* Primary Sidebar: Icon navigation */
    .primary-sidebar {
      width: 72px;
      background: var(--bg-sidebar-primary);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px 0;
      flex-shrink: 0;
      border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    .sidebar-brand-wrapper {
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .sidebar-logo {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      object-fit: cover;
      border: 1.5px solid rgba(255, 255, 255, 0.1);
    }

    .primary-menu {
      display: flex;
      flex-direction: column;
      gap: 16px;
      width: 100%;
      align-items: center;
    }

    .primary-menu-item {
      width: 56px;
      height: 56px;
      border-radius: 10px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--text-sidebar-primary);
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }

    .primary-menu-item:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.08);
    }

    .primary-menu-item.active {
      color: #ffffff;
      background: var(--primary);
      box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.3);
    }

    .primary-menu-item .menu-icon {
      width: 20px;
      height: 20px;
      stroke-width: 2.2;
    }

    .primary-menu-item .menu-label {
      font-size: 9px;
      font-weight: 600;
      margin-top: 4px;
      text-align: center;
    }

    /* Secondary Sidebar: Submenu options */
    .secondary-sidebar {
      width: 160px;
      background: var(--bg-sidebar-secondary);
      border-right: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
      transition: width 0.2s ease, opacity 0.2s ease;
      overflow: hidden;
    }

    .secondary-sidebar-header {
      height: 64px;
      display: flex;
      align-items: center;
      padding: 0 18px;
      font-size: 13px;
      font-weight: 800;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      border-bottom: 1px solid var(--border-color);
    }

    .secondary-menu {
      display: flex;
      flex-direction: column;
      padding: 16px 8px;
      gap: 4px;
      flex: 1;
    }

    .secondary-menu-item {
      display: block;
      padding: 11px 14px;
      border-radius: 6px;
      color: var(--text-sidebar-secondary);
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 600;
      transition: all 0.2s;
    }

    .secondary-menu-item:hover {
      background: var(--table-header-bg);
      color: var(--primary);
    }

    .secondary-menu-item.active {
      background: rgba(var(--primary-rgb), 0.08);
      color: var(--primary);
      font-weight: 700;
    }

    /* Main Container layout */
    .main-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow-y: auto;
      min-width: 0;
    }

    /* Header Top Bar */
    .top-header {
      height: 60px;
      background: var(--bg-header);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      position: sticky;
      top: 0;
      z-index: 90;
      flex-shrink: 0;
    }

    .top-header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .sidebar-toggle-btn {
      background: transparent;
      border: 0;
      color: var(--text-secondary);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      transition: background 0.2s;
    }

    .sidebar-toggle-btn:hover {
      background: var(--table-header-bg);
      color: var(--text-main);
    }

    .sidebar-toggle-btn svg {
      width: 20px;
      height: 20px;
    }

    /* Breadcrumbs path navigation */
    .breadcrumbs-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13.5px;
      font-weight: 500;
      color: var(--text-secondary);
    }

    .breadcrumb-item {
      text-decoration: none;
      color: inherit;
      transition: color 0.2s;
    }

    .breadcrumb-item:hover {
      color: var(--primary);
    }

    .breadcrumb-item.active {
      color: var(--text-main);
      font-weight: 600;
    }

    .breadcrumb-separator {
      color: var(--text-secondary);
      opacity: 0.6;
    }

    .top-header-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    /* Promoter details & configurations settings */
    .user-profile-status {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--table-header-bg);
      padding: 6px 14px;
      border-radius: 30px;
      border: 1px solid var(--border-color);
      cursor: pointer;
      position: relative;
    }

    .status-indicator {
      width: 8px;
      height: 8px;
      background: var(--success);
      border-radius: 50%;
      position: relative;
    }

    .status-indicator::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      background: var(--success);
      border-radius: 50%;
      animation: pulse 1.8s infinite ease-in-out;
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 1; }
      100% { transform: scale(2.4); opacity: 0; }
    }

    .user-profile-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-main);
    }

    .layout-setting-trigger {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: var(--table-header-bg);
      border: 1px solid var(--border-color);
      color: var(--text-secondary);
      cursor: pointer;
      transition: all 0.2s;
    }

    .layout-setting-trigger:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
      transform: rotate(30deg);
    }

    .layout-setting-trigger svg {
      width: 18px;
      height: 18px;
    }

    /* Tabbar navigation */
    .tabbar-container {
      height: 40px;
      background: var(--bg-header);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      padding: 0 16px;
      overflow-x: auto;
      gap: 6px;
      flex-shrink: 0;
      scrollbar-width: none; /* Hide scrollbar Firefox */
    }

    .tabbar-container::-webkit-scrollbar {
      display: none; /* Hide scrollbar Webkit */
    }

    .workspace-tab {
      display: flex;
      align-items: center;
      gap: 8px;
      height: 28px;
      padding: 0 12px;
      background: var(--table-header-bg);
      border: 1px solid var(--border-color);
      border-radius: 4px;
      text-decoration: none;
      font-size: 12.5px;
      font-weight: 600;
      color: var(--text-sidebar-secondary);
      transition: all 0.2s;
      white-space: nowrap;
      position: relative;
    }

    .workspace-tab:hover {
      background: var(--bg-body);
      color: var(--primary);
    }

    .workspace-tab.active {
      background: #ffffff;
      border-color: var(--primary);
      color: var(--primary);
    }

    html.dark .workspace-tab.active {
      background: var(--bg-sidebar-secondary);
    }

    .workspace-tab-dot {
      width: 6px;
      height: 6px;
      background: var(--primary);
      border-radius: 50%;
      display: inline-block;
    }

    .workspace-tab-close {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      border-radius: 50%;
      font-size: 10px;
      color: var(--text-secondary);
      transition: background 0.2s, color 0.2s;
      cursor: pointer;
    }

    .workspace-tab-close:hover {
      background: #e74c3c;
      color: white;
    }

    /* Content Body Area */
    .content-body {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      flex: 1;
      overflow-y: auto;
    }

    /* Styled Container Cards */
    .card-element {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s;
    }

    .card-header-element {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title-element {
      font-size: 14.5px;
      font-weight: 800;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .card-title-element svg {
      width: 16px;
      height: 16px;
      color: var(--primary);
      stroke-width: 2.2;
    }

    .card-body-element {
      padding: 20px;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
      .secondary-sidebar {
        width: 0;
        opacity: 0;
        border-right: 0;
      }
    }

    @media (max-width: 768px) {
      .sidebar-container {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        transform: translateX(-100%);
      }
      .sidebar-container.open {
        transform: translateX(0);
      }
      .breadcrumbs-bar {
        display: none !important;
      }
    }

    /* Collapsed state adjustments */
    .sidebar-container.collapsed .secondary-sidebar {
      width: 0;
      opacity: 0;
      border-right: 0;
    }
  </style>

  <!-- Pre-applied theme variables configuration check -->
  <script>
    (function() {
      const themeColor = localStorage.getItem('agent-theme-color') || '#00b578';
      const enableDark = localStorage.getItem('agent-enable-dark') === 'true';
      const enableBreadcrumbs = localStorage.getItem('agent-enable-breadcrumbs') !== 'false';
      const enableTabs = localStorage.getItem('agent-enable-tabs') !== 'false';
      const enableLogo = localStorage.getItem('agent-enable-logo') !== 'false';

      // Set CSS Variables
      document.documentElement.style.setProperty('--primary', themeColor);
      
      const hexToRgb = hex => {
        let c = hex.substring(1);
        let rgb = parseInt(c, 16);
        let r = (rgb >> 16) & 0xff;
        let g = (rgb >> 8) & 0xff;
        let b = rgb & 0xff;
        return `${r}, ${g}, ${b}`;
      };
      document.documentElement.style.setProperty('--primary-rgb', hexToRgb(themeColor));

      if (enableDark) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }

      // Inject dynamically computed settings styles
      let style = document.createElement('style');
      style.id = 'theme-setting-styles';
      style.innerHTML = `
        .breadcrumbs-bar { display: ${enableBreadcrumbs ? 'flex' : 'none'} !important; }
        .tabbar-container { display: ${enableTabs ? 'flex' : 'none'} !important; }
        .sidebar-brand-wrapper { display: ${enableLogo ? 'flex' : 'none'} !important; }
      `;
      document.head.appendChild(style);
    })();
  </script>
</head>
<body>

  <!-- Dynamic Double Sidebar Navigation -->
  <aside class="sidebar-container" id="sidebar-container">
    <!-- Primary Sidebar -->
    <div class="primary-sidebar">
      <div class="sidebar-brand-wrapper">
        <img src="../images/Login_Logo.png" alt="NextPay Logo" class="sidebar-logo">
      </div>
      <div class="primary-menu">
        <?php if (in_array('index.php', $allowed_pages) || in_array('leaderboard.php', $allowed_pages) || in_array('security.php', $allowed_pages) || in_array('support_tickets.php', $allowed_pages) || in_array('security_logs.php', $allowed_pages) || in_array('sub_agent.php', $allowed_pages)): ?>
          <a href="index.php" class="primary-menu-item <?php echo ($current_menu === 'home') ? 'active' : ''; ?>" title="Home">
            <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span class="menu-label">Home</span>
          </a>
        <?php endif; ?>
        <?php 
        $biz_allowed = false;
        foreach ($biz_pages as $bp) {
            if (in_array($bp, $allowed_pages)) {
                $biz_allowed = true;
                break;
            }
        }
        if ($biz_allowed): ?>
          <a href="team_daily.php" class="primary-menu-item <?php echo ($current_menu === 'biz') ? 'active' : ''; ?>" title="BIZ Manage">
            <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span class="menu-label">BIZ Manage</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Secondary Sidebar -->
    <div class="secondary-sidebar" id="secondary-sidebar">
      <div class="secondary-sidebar-header">
        <?php echo ($current_menu === 'biz') ? 'BIZ Manage' : 'Home'; ?>
      </div>
      <div class="secondary-menu">
        <?php if ($current_menu === 'biz'): ?>
          <?php if (in_array('team_daily.php', $allowed_pages)): ?>
            <a href="team_daily.php" class="secondary-menu-item <?php echo ($current_page === 'team_daily.php') ? 'active' : ''; ?>">Team Daily</a>
          <?php endif; ?>
          <?php if (in_array('token_stmt.php', $allowed_pages)): ?>
            <a href="token_stmt.php" class="secondary-menu-item <?php echo ($current_page === 'token_stmt.php') ? 'active' : ''; ?>">Token STMT</a>
          <?php endif; ?>
          <?php if (in_array('token_stmt_his.php', $allowed_pages)): ?>
            <a href="token_stmt_his.php" class="secondary-menu-item <?php echo ($current_page === 'token_stmt_his.php') ? 'active' : ''; ?>">Token STMT HIS</a>
          <?php endif; ?>
          <?php if (in_array('daily_stat.php', $allowed_pages)): ?>
            <a href="daily_stat.php" class="secondary-menu-item <?php echo ($current_page === 'daily_stat.php') ? 'active' : ''; ?>">Daily Statistic</a>
          <?php endif; ?>
          <?php if (in_array('transfer_token.php', $allowed_pages)): ?>
            <a href="transfer_token.php" class="secondary-menu-item <?php echo ($current_page === 'transfer_token.php') ? 'active' : ''; ?>">Transfer Token</a>
          <?php endif; ?>
        <?php else: ?>
          <?php if (in_array('index.php', $allowed_pages)): ?>
            <a href="index.php" class="secondary-menu-item <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">Home</a>
          <?php endif; ?>
          <?php if (in_array('sub_agent.php', $allowed_pages) && isset($user['allow_create_sub_agent']) && $user['allow_create_sub_agent'] == 1): ?>
            <a href="sub_agent.php" class="secondary-menu-item <?php echo ($current_page === 'sub_agent.php') ? 'active' : ''; ?>">Sub-Agent</a>
          <?php endif; ?>
          <?php if (in_array('leaderboard.php', $allowed_pages)): ?>
            <a href="leaderboard.php" class="secondary-menu-item <?php echo ($current_page === 'leaderboard.php') ? 'active' : ''; ?>">Leaderboard</a>
          <?php endif; ?>
          <?php if (in_array('security.php', $allowed_pages)): ?>
            <a href="security.php" class="secondary-menu-item <?php echo ($current_page === 'security.php') ? 'active' : ''; ?>">Security (2FA)</a>
          <?php endif; ?>
          <?php if (in_array('support_tickets.php', $allowed_pages)): ?>
            <a href="support_tickets.php" class="secondary-menu-item <?php echo ($current_page === 'support_tickets.php') ? 'active' : ''; ?>">Support Desk</a>
          <?php endif; ?>
          <?php if (in_array('security_logs.php', $allowed_pages)): ?>
            <a href="security_logs.php" class="secondary-menu-item <?php echo ($current_page === 'security_logs.php') ? 'active' : ''; ?>">Security Logs</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </aside>

  <!-- Main View Container -->
  <div class="main-container">
    
    <!-- Top Header Navigation Bar -->
    <header class="top-header">
      <div class="top-header-left">
        <button class="sidebar-toggle-btn" id="sidebar-collapse-toggle" onclick="toggleSidebarCollapse()" title="Toggle Sidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>

        <!-- Breadcrumbs Navigation -->
        <nav class="breadcrumbs-bar">
          <a href="index.php" class="breadcrumb-item">Home</a>
          <?php if ($current_menu === 'biz'): ?>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item">BIZ Manage</span>
          <?php endif; ?>
          <span class="breadcrumb-separator">/</span>
          <span class="breadcrumb-item active"><?php echo $active_title; ?></span>
        </nav>
      </div>

      <div class="top-header-right">
        <!-- User Status badge -->
        <div class="user-profile-status" onclick="location.href='index.php'">
          <div class="status-indicator"></div>
          <span class="user-profile-name">Promoter (<?php echo sanitize($user['phone']); ?>)</span>
        </div>

        <!-- Layout configurations trigger button -->
        <button class="layout-setting-trigger" onclick="toggleSettingsDrawer()" title="Settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
        </button>
      </div>
    </header>

    <!-- Workspace Tag Tabbar -->
    <div class="tabbar-container" id="tabbar-container">
      <!-- Generated via JS for exact client-side tab lists synchronization -->
    </div>

    <!-- Active view contents wrapper -->
    <main class="content-body">

