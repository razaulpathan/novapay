<?php
// admin/_sidebar.php - Shared Admin Sidebar with all features organized
// Include this file in every admin page: include '_sidebar.php';

// Get badge counts (safe - only runs if tables exist)
$_sb_pendingBuy = 0;
$_sb_pendingSell = 0;
$_sb_pendingTransfers = 0;
$_sb_openTickets = 0;
try {
    $_sb_pendingBuy       = intval($pdo->query("SELECT COUNT(*) FROM buy_orders WHERE status='Pending'")->fetchColumn());
    $_sb_pendingSell      = intval($pdo->query("SELECT COUNT(*) FROM sell_orders WHERE status='Paying'")->fetchColumn());
    $_sb_pendingTransfers = intval($pdo->query("SELECT COUNT(*) FROM token_transfers WHERE status='Pending'")->fetchColumn());
    $_sb_openTickets      = intval($pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='Open'")->fetchColumn());
} catch (Exception $e) {}

$_sb_current = basename($_SERVER['PHP_SELF']);

function _sb_item($href, $label, $badge = 0, $icon = '') {
    global $_sb_current;
    $active = (basename($href) === $_sb_current) ? ' active' : '';
    $badgeHtml = $badge > 0 ? "<span style='margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;font-weight:700;'>$badge</span>" : '';
    $svg = $icon ?: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"></circle></svg>';
    return "<li class='menu-item{$active}'><a href='{$href}'>{$svg}<span>{$label}</span>{$badgeHtml}</a></li>";
}
?>
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
            <line x1="6" y1="6" x2="6.01" y2="6"></line>
            <line x1="6" y1="18" x2="6.01" y2="18"></line>
        </svg>
        <span class="sidebar-title">NextPay Admin</span>
    </div>

    <div style="overflow-y: auto; flex: 1; padding-bottom: 16px;">

    <!-- ── MAIN ──────────────────────────────────── -->
    <ul class="sidebar-menu">
        <?= _sb_item('dashboard.php', 'Dashboard', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>') ?>
        <?= _sb_item('users.php', 'Users', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>') ?>
        <?= _sb_item('buy_orders.php',   'Buy Orders',      $_sb_pendingBuy,       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>') ?>
        <?= _sb_item('sell_orders.php',  'Sell Orders',     $_sb_pendingSell,      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>') ?>
        <?= _sb_item('token_transfers.php', 'Token Transfers', $_sb_pendingTransfers, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>') ?>
        <?= _sb_item('support_tickets.php', 'Support Tickets', $_sb_openTickets, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>') ?>
        <?= _sb_item('settings.php', 'Settings', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>') ?>
        <?= _sb_item('announcements.php', 'News/Announcements', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><path d="M22 6l-10 7L2 6"></path></svg>') ?>
        <?= _sb_item('partners.php', 'Payment Partners', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>') ?>
        <?= _sb_item('telegram_settings.php', 'Telegram Bot', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>') ?>
        <?= _sb_item('banners.php', 'Banners & Tutorials', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="m3 9 6-6 6 6 3-3 3 3"/><circle cx="15" cy="15" r="2"/></svg>') ?>
        <?= _sb_item('transfer_user.php', 'Transfer Referrer', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M12 12L21 3"></path><path d="M12 12L3 21"></path></svg>') ?>
        <?= _sb_item('backup.php', 'DB Backup', 0, '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>') ?>
    </ul>

    <!-- ── BOTTOM ───────────────────────────────── -->
    <ul class="sidebar-menu" style="margin-top: 8px;">
        <li class="menu-item" style="border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 6px;">
            <a href="../home.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Return to Site</span>
            </a>
        </li>
    </ul>

    </div><!-- end scroll div -->

    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-signout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<style>
/* Section titles in sidebar */
.sb-section-title {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #4a5568;
    padding: 14px 20px 4px;
    margin-top: 4px;
}
/* Make sidebar scrollable */
.sidebar {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
}
</style>

