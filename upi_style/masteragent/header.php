<?php
// masteragent/header.php - Head Agent Panel Modular Header
require_once __DIR__ . '/../db.php';

// Auth checks
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Fetch master agent details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'Blocked' || $user['role'] !== 'masteragent') {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

if (!isset($current_page)) {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
}

$page_titles = [
    'index.php' => 'Master Dashboard',
    'transfer_token.php' => 'Transfer Tokens',
    'transactions.php' => 'Team Transactions & UPIs'
];
$active_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Master Agent Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - <?php echo $active_title; ?></title>
  
  <!-- Modern Outfit Font -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary: #4f46e5;
      --primary-hover: #4338ca;
      --primary-rgb: 79, 70, 229;
      
      --bg-body: #f3f4f6;
      --bg-sidebar: #0f172a;
      --bg-card: #ffffff;
      --bg-header: #ffffff;
      
      --text-main: #1e293b;
      --text-secondary: #64748b;
      --text-sidebar: #94a3b8;
      
      --border-color: #e2e8f0;
      --table-header-bg: #f8fafc;
      --table-hover-bg: #f1f5f9;
      
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    }

    /* Sidebar Layout */
    .sidebar {
      width: 260px;
      background: var(--bg-sidebar);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      min-height: 100vh;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .sidebar-brand-wrapper {
      padding: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-logo {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      object-fit: cover;
    }

    .brand-name {
      color: white;
      font-weight: 800;
      font-size: 18px;
      letter-spacing: 0.5px;
    }

    .sidebar-menu {
      display: flex;
      flex-direction: column;
      padding: 20px 12px;
      gap: 6px;
      flex: 1;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      border-radius: 8px;
      color: var(--text-sidebar);
      text-decoration: none;
      font-size: 14.5px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .menu-item:hover {
      color: white;
      background: rgba(255, 255, 255, 0.05);
    }

    .menu-item.active {
      color: white;
      background: var(--primary);
      box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
      font-weight: 600;
    }

    .menu-icon {
      width: 20px;
      height: 20px;
    }

    /* Main Container Layout */
    .main-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .top-header {
      height: 70px;
      background: var(--bg-header);
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 32px;
      position: sticky;
      top: 0;
      z-index: 90;
    }

    .page-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-main);
    }

    .user-profile-status {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f8fafc;
      padding: 8px 16px;
      border-radius: 30px;
      border: 1px solid var(--border-color);
    }

    .status-indicator {
      width: 8px;
      height: 8px;
      background: var(--success);
      border-radius: 50%;
    }

    .user-profile-name {
      font-size: 13.5px;
      font-weight: 600;
      color: var(--text-main);
    }

    /* Content Area */
    .content-body {
      padding: 32px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      flex: 1;
    }

    /* Cards System */
    .card-element {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .card-header-element {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title-element {
      font-size: 15px;
      font-weight: 700;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .card-title-element svg {
      width: 18px;
      height: 18px;
      color: var(--primary);
    }

    .card-body-element {
      padding: 24px;
    }

    /* Alerts System */
    .alert {
      padding: 14px 20px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-success {
      background: #ecfdf5;
      border: 1px solid #d1fae5;
      color: #065f46;
    }

    .alert-error {
      background: #fef2f2;
      border: 1px solid #fee2e2;
      color: #991b1b;
    }

    /* Forms */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-secondary);
    }

    .form-control {
      height: 42px;
      border: 1px solid var(--border-color);
      background: #ffffff;
      border-radius: 8px;
      padding: 0 14px;
      font-size: 14px;
      color: var(--text-main);
      outline: 0;
      transition: all 0.2s;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    /* Buttons */
    .btn {
      height: 42px;
      padding: 0 20px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      cursor: pointer;
      border: 0;
      transition: all 0.2s;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--primary-hover);
    }

    .btn-secondary {
      background: #f1f5f9;
      color: var(--text-main);
      border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
      background: #e2e8f0;
    }

    /* Badge */
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 30px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef3c7; color: #92400e; }

    /* Tables */
    .table-responsive {
      overflow-x: auto;
      border: 1px solid var(--border-color);
      border-radius: 8px;
    }

    .table-custom {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 13.5px;
    }

    .table-custom th {
      background: var(--table-header-bg);
      color: var(--text-secondary);
      font-weight: 600;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border-color);
    }

    .table-custom td {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border-color);
      color: var(--text-main);
    }

    .table-custom tr:hover td {
      background: var(--table-hover-bg);
    }
  </style>
</head>
<body>

  <!-- Head Agent Sidebar Navigation -->
  <aside class="sidebar">
    <div class="sidebar-brand-wrapper">
      <img src="../images/nextpay.jpeg" alt="NextPay" class="sidebar-logo" style="border-radius: 6px;">
      <span class="brand-name">NextPay Head</span>
    </div>
    
    <div class="sidebar-menu">
      <a href="index.php" class="menu-item <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
        <svg class="menu-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
        </svg>
        <span>Dashboard</span>
      </a>
      
      <a href="transfer_token.php" class="menu-item <?php echo ($current_page === 'transfer_token.php') ? 'active' : ''; ?>">
        <svg class="menu-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Transfer Tokens</span>
      </a>

      <a href="transactions.php" class="menu-item <?php echo ($current_page === 'transactions.php') ? 'active' : ''; ?>">
        <svg class="menu-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
        <span>Team Status &amp; UPIs</span>
      </a>
    </div>

    <!-- Sidebar Footer Signout -->
    <div style="padding: 16px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
      <a href="logout.php" class="menu-item" style="color: #f87171;">
        <svg class="menu-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Main Container -->
  <div class="main-container">
    <header class="top-header">
      <h1 class="page-title"><?php echo $active_title; ?></h1>
      <div class="user-profile-status">
        <div class="status-indicator"></div>
        <span class="user-profile-name">Head Agent (<?php echo sanitize($user['phone']); ?>)</span>
      </div>
    </header>
    
    <main class="content-body">
