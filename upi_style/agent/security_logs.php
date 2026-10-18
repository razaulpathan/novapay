<?php
// agent/security_logs.php - Promoter Security Audit Logs
require_once '../db.php';

// Auth check
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Fetch agent details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'Blocked') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch recent audit logs for the promoter
$stmtLogs = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmtLogs->execute([$user['id']]);
$logs = $stmtLogs->fetchAll();

// Include Layout Header
$current_page = 'security_logs.php';
require_once 'header.php';
?>

<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
      </svg>
      Security Audit Log History
    </span>
  </div>
  <div class="card-body-element">
    <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.4;">The security audit log displays the login attempts and session history related to your promoter profile. If you notice any suspicious IP address or browser agents, modify your password immediately and contact your platform administrator.</p>
    
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>Log ID</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Browser / User Agent</th>
            <th>Action Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" class="empty-table-row">No security logs recorded for this account.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td>#<?= intval($log['id']) ?></td>
                <td>
                  <span class="badge-status status-green"><?= sanitize($log['action']) ?></span>
                </td>
                <td style="font-family: monospace; font-weight: 700;"><?= sanitize($log['ip_address']) ?></td>
                <td style="font-size: 12px; color: var(--text-secondary); max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= sanitize($log['user_agent']) ?>">
                  <?= sanitize($log['user_agent']) ?>
                </td>
                <td style="font-size: 12px; color: var(--text-secondary);"><?= $log['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  /* Data Table Layouts */
  .grid-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
  }

  .grid-data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
  }

  .grid-data-table th {
    background: var(--table-header-bg);
    color: var(--text-main);
    font-weight: 700;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
  }

  .grid-data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-main);
    background: var(--bg-card);
  }

  .grid-data-table tr:hover td {
    background: var(--table-hover-bg);
  }

  .grid-data-table tr:last-child td {
    border-bottom: 0;
  }

  .empty-table-row {
    text-align: center;
    color: var(--text-secondary) !important;
    padding: 40px 0 !important;
    font-weight: 500;
  }

  /* Badge status chips */
  .badge-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
  }

  .status-green {
    background: rgba(46, 204, 113, 0.08);
    color: var(--success);
  }
</style>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

