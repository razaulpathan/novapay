<?php
// agent/index.php - Promoter Dashboard Home
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

// Handle sub-agent registration (only if allow_create_sub_agent is 1)
$sub_agent_error = '';
$sub_agent_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_sub_agent') {
    if (isset($user['allow_create_sub_agent']) && $user['allow_create_sub_agent'] == 1) {
        $sub_phone = isset($_POST['sub_phone']) ? trim($_POST['sub_phone']) : '';
        $sub_password = isset($_POST['sub_password']) ? $_POST['sub_password'] : '';
        
        if (empty($sub_phone) || empty($sub_password)) {
            $sub_agent_error = "Please fill in all fields.";
        } else {
            // Verify if user already exists
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
            $stmtCheck->execute([$sub_phone]);
            if ($stmtCheck->fetchColumn() > 0) {
                $sub_agent_error = "A user with phone number {$sub_phone} already exists.";
            } else {
                $pass_hash = password_hash($sub_password, PASSWORD_DEFAULT);
                $ref_code = 'REF' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                
                $stmtInsert = $pdo->prepare("INSERT INTO users (phone, password, referral_code, referred_by, status, role) VALUES (?, ?, ?, ?, 'Active', 'user')");
                $stmtInsert->execute([$sub_phone, $pass_hash, $ref_code, $user['id']]);
                $sub_agent_success = "Sub-agent {$sub_phone} successfully registered under your team!";
            }
        }
    } else {
        $sub_agent_error = "You do not have permission to register sub-agents.";
    }
}

// Generate promoter referral URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$referral_url = $protocol . $_SERVER['HTTP_HOST'] . "/register.php?ref=" . $user['referral_code'];

// Calculate Direct Subordinates Count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmtCount->execute([$user['id']]);
$direct_sub_cnt = $stmtCount->fetchColumn();

// Handle subordinates search filters
$filter_uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';
$filter_username = isset($_GET['username']) ? trim($_GET['username']) : '';
$filter_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : 'Please Choice';
$filter_parent = isset($_GET['parent_user']) ? trim($_GET['parent_user']) : '';

$where_clauses = ["referred_by = ?"];
$params = [$user['id']];

if ($filter_uid !== '') {
    $where_clauses[] = "id = ?";
    $params[] = intval($filter_uid);
}
if ($filter_username !== '') {
    $where_clauses[] = "phone LIKE ?";
    $params[] = "%" . $filter_username . "%";
}

$query = "SELECT * FROM users WHERE " . implode(" AND ", $where_clauses) . " ORDER BY created_at DESC";
$stmtSubs = $pdo->prepare($query);
$stmtSubs->execute($params);
$raw_subs = $stmtSubs->fetchAll();

$subordinates = [];
foreach ($raw_subs as $sub) {
    $stmtAgentCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmtAgentCheck->execute([$sub['id']]);
    $is_agent = ($stmtAgentCheck->fetchColumn() > 0);
    $sub_type = $is_agent ? 'Agent' : 'Trader';
    
    if ($filter_type !== 'Please Choice' && $filter_type !== $sub_type) continue;
    if ($filter_parent !== '' && strpos($user['phone'], $filter_parent) === false) continue;

    $sub['user_type'] = $sub_type;
    $subordinates[] = $sub;
}

// Calculate Total Profit (Commissions etc.)
$stmtCommTotal = $pdo->prepare("SELECT COALESCE(SUM(price), 0) * 0.003 FROM buy_orders bo JOIN users u ON bo.user_id = u.id WHERE u.referred_by = ? AND bo.status = 'Success'");
$stmtCommTotal->execute([$user['id']]);
$comm_total = $stmtCommTotal->fetchColumn() ?: 0.00;
$total_profit = $user['today_profit'] + $comm_total;

// Include Layout Header
$current_page = 'index.php';
require_once 'header.php';
?>

<!-- Statistics Dashboard widgets -->
<div class="stats-grid">
  <div class="stat-card gradient-primary">
    <div class="stat-card-body">
      <div class="stat-card-info">
        <span class="stat-card-label">Itoken Balance</span>
        <span class="stat-card-value">₹<?= number_format($user['itoken_balance'], 2) ?></span>
      </div>
      <div class="stat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Platform User: NextPay</span>
    </div>
  </div>

  <div class="stat-card gradient-success">
    <div class="stat-card-body">
      <div class="stat-card-info">
        <span class="stat-card-label">Total profit</span>
        <span class="stat-card-value">₹<?= number_format($total_profit, 2) ?></span>
      </div>
      <div class="stat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
          <polyline points="17 6 23 6 23 12"></polyline>
        </svg>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Today profit: ₹<?= number_format($user['today_profit'], 2) ?></span>
    </div>
  </div>

  <div class="stat-card gradient-info">
    <div class="stat-card-body">
      <div class="stat-card-info">
        <span class="stat-card-label">Direct subordinates</span>
        <span class="stat-card-value"><?= intval($direct_sub_cnt) ?> Users</span>
      </div>
      <div class="stat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Reward rate: <?= number_format($user['reward_rate'], 2) ?>%</span>
    </div>
  </div>
</div>

<!-- Invitation link widget -->
<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
      </svg>
      Invitation Link Details
    </span>
  </div>
  <div class="card-body-element">
    <div class="invite-flex-container">
      <div class="invite-input-row" style="flex: 1; min-width: 280px;">
        <span class="invite-label-tag">Invite URL</span>
        <div class="invite-field-group">
          <input type="text" id="invite-url-field" class="invite-field-input" value="<?= sanitize($referral_url) ?>" readonly>
          <button onclick="copyInviteUrl()" class="invite-copy-btn" title="Copy Invitation Link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
        </div>
      </div>
      <div class="invite-uid-box">
        <span class="invite-label-tag">Team/Work ID</span>
        <span class="invite-uid-val"><?= intval($user['id']) + 475538000 ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Sub-Agent Registration Card (Visible only if user has permission) -->
<?php if (isset($user['allow_create_sub_agent']) && $user['allow_create_sub_agent'] == 1): ?>
  <div class="card-element highlight-border">
    <div class="card-header-element highlight-header">
      <span class="card-title-element highlight-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <line x1="19" y1="8" x2="19" y2="14"></line>
          <line x1="22" y1="11" x2="16" y2="11"></line>
        </svg>
        Register New Sub-Agent
      </span>
    </div>
    <div class="card-body-element">
      <?php if (!empty($sub_agent_error)): ?>
        <div class="sub-alert danger-alert"><?= sanitize($sub_agent_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($sub_agent_success)): ?>
        <div class="sub-alert success-alert"><?= sanitize($sub_agent_success) ?></div>
      <?php endif; ?>
      
      <form method="POST" class="sub-agent-form">
        <input type="hidden" name="action" value="register_sub_agent">
        
        <div class="form-input-group">
          <label class="form-input-label" for="sub_phone">Sub-Agent Phone Number</label>
          <input type="tel" name="sub_phone" id="sub_phone" class="form-text-input" placeholder="Enter phone number" required>
        </div>
        <div class="form-input-group">
          <label class="form-input-label" for="sub_password">Sub-Agent Password</label>
          <input type="password" name="sub_password" id="sub_password" class="form-text-input" placeholder="Enter password" required>
        </div>
        <button type="submit" class="submit-action-btn">Register Sub-Agent</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Direct Subordinates Ledger Grid -->
<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
      </svg>
      Direct Subordinate Info
    </span>
  </div>
  <div class="card-body-element">
    
    <!-- Filter Search Form -->
    <form method="GET" action="index.php" class="report-filter-form">
      <div class="form-row-filters">
        <div class="filter-box-input">
          <label class="filter-box-label">UID</label>
          <input type="text" name="uid" class="filter-input-element" placeholder="UID" value="<?= sanitize($filter_uid) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">Username</label>
          <input type="text" name="username" class="filter-input-element" placeholder="Username" value="<?= sanitize($filter_username) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">User Type</label>
          <select name="user_type" class="filter-input-element select-input">
            <option value="Please Choice" <?= $filter_type === 'Please Choice' ? 'selected' : '' ?>>Please Choice</option>
            <option value="Agent" <?= $filter_type === 'Agent' ? 'selected' : '' ?>>Agent</option>
            <option value="Trader" <?= $filter_type === 'Trader' ? 'selected' : '' ?>>Trader</option>
          </select>
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">Parent User</label>
          <input type="text" name="parent_user" class="filter-input-element" placeholder="Parent User" value="<?= sanitize($filter_parent) ?>">
        </div>
      </div>
      <div class="filter-actions-row">
        <button type="submit" class="filter-btn btn-primary-action">Query</button>
        <a href="index.php" class="filter-btn btn-secondary-action">Reset</a>
      </div>
    </form>

    <!-- Data Grid Table -->
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>UID</th>
            <th>Username</th>
            <th>User Type</th>
            <th>Pay Type</th>
            <th>Payer Reward</th>
            <th>Payee Reward</th>
            <th>Parent User</th>
            <th>Platform User</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($subordinates)): ?>
            <tr>
              <td colspan="8" class="empty-table-row">No direct subordinate accounts matching your query guidelines.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($subordinates as $sub): ?>
              <tr>
                <td><?= intval($sub['id']) ?></td>
                <td style="font-weight: 700;"><?= sanitize($sub['phone']) ?></td>
                <td>
                  <span class="badge-status <?= $sub['user_type'] === 'Agent' ? 'status-purple' : 'status-blue' ?>">
                    <?= $sub['user_type'] ?>
                  </span>
                </td>
                <td>
                  <span class="badge-status status-green">Payer+Payee</span>
                </td>
                <td style="font-weight: 700; color: var(--primary);">0 + <?= number_format($sub['reward_rate'], 2) ?>%</td>
                <td style="color: var(--text-secondary);">0 + 0.00%</td>
                <td><?= sanitize($user['phone']) ?></td>
                <td>NextPay</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Homepage Specific Styles -->
<style>
  /* Stats Cards grids */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
  }

  .stat-card {
    border-radius: 12px;
    padding: 20px;
    color: white;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 130px;
    position: relative;
    overflow: hidden;
  }

  .gradient-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #00875a 100%);
  }

  .gradient-success {
    background: linear-gradient(135deg, var(--success) 0%, #219653 100%);
  }

  .gradient-info {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
  }

  .stat-card-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }

  .stat-card-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .stat-card-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    opacity: 0.85;
    letter-spacing: 0.5px;
  }

  .stat-card-value {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.5px;
  }

  .stat-card-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .stat-card-icon svg {
    width: 24px;
    height: 24px;
  }

  .stat-card-footer {
    font-size: 12.5px;
    opacity: 0.8;
    font-weight: 500;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 10px;
    margin-top: 12px;
  }

  /* Invitation link Layout */
  .invite-flex-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
  }

  .invite-label-tag {
    display: block;
    font-size: 11px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
  }

  .invite-field-group {
    display: flex;
    border: 1px solid var(--border-color);
    background: var(--table-header-bg);
    border-radius: 8px;
    overflow: hidden;
    height: 44px;
    max-width: 500px;
    width: 100%;
  }

  .invite-field-input {
    flex: 1;
    border: 0;
    background: transparent;
    padding: 0 16px;
    font-size: 13.5px;
    color: var(--text-main);
    font-weight: 500;
    outline: 0;
    font-family: inherit;
  }

  .invite-copy-btn {
    background: transparent;
    border: 0;
    padding: 0 16px;
    color: var(--primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
  }

  .invite-copy-btn:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
  }

  .invite-copy-btn svg {
    width: 18px;
    height: 18px;
  }

  .invite-uid-box {
    display: flex;
    flex-direction: column;
    min-width: 120px;
  }

  .invite-uid-val {
    font-size: 18px;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: 0.5px;
    line-height: 1.4;
  }

  /* Sub-Agent registration card border highlight */
  .card-element.highlight-border {
    border-color: rgba(255, 45, 85, 0.25);
  }

  .card-header-element.highlight-header {
    background: rgba(255, 45, 85, 0.03);
    border-bottom-color: rgba(255, 45, 85, 0.1);
  }

  .card-title-element.highlight-title {
    color: #ff2d55;
  }

  .card-title-element.highlight-title svg {
    color: #ff2d55;
  }

  .sub-alert {
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
  }

  .danger-alert {
    background: #fdf2f2;
    border: 1px solid #fde8e8;
    color: #e74c3c;
  }

  .success-alert {
    background: #f3faf6;
    border: 1px solid #def7ec;
    color: #0e6251;
  }

  .sub-agent-form {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
  }

  .form-input-group {
    flex: 1;
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .form-input-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
  }

  .form-text-input {
    height: 42px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    border-radius: 8px;
    padding: 0 14px;
    font-size: 13.5px;
    color: var(--text-main);
    outline: 0;
    transition: border-color 0.2s;
    font-family: inherit;
    width: 100%;
  }

  .form-text-input:focus {
    border-color: var(--primary);
  }

  .submit-action-btn {
    height: 42px;
    background: var(--primary);
    color: white;
    font-weight: 700;
    border: 0;
    border-radius: 8px;
    padding: 0 24px;
    font-size: 13.5px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.25);
    transition: opacity 0.2s;
  }

  .submit-action-btn:hover {
    opacity: 0.95;
  }

  /* Filters styling */
  .report-filter-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 20px;
    border-bottom: 1px dashed var(--border-color);
    padding-bottom: 20px;
  }

  .form-row-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
  }

  .filter-box-input {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .filter-box-label {
    font-size: 11px;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .filter-input-element {
    height: 40px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    border-radius: 6px;
    padding: 0 12px;
    font-size: 13px;
    color: var(--text-main);
    outline: 0;
    font-family: inherit;
    transition: border-color 0.2s;
    width: 100%;
  }

  .filter-input-element:focus {
    border-color: var(--primary);
  }

  .filter-input-element.select-input {
    cursor: pointer;
  }

  .filter-actions-row {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
  }

  .filter-btn {
    height: 38px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 20px;
    text-decoration: none;
    font-family: inherit;
    transition: all 0.2s;
  }

  .btn-primary-action {
    background: var(--primary);
    color: white;
    border: 0;
  }

  .btn-primary-action:hover {
    opacity: 0.9;
  }

  .btn-secondary-action {
    background: var(--table-header-bg);
    color: var(--text-main);
    border: 1px solid var(--border-color);
  }

  .btn-secondary-action:hover {
    background: var(--border-color);
  }

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

  .status-purple {
    background: rgba(114, 46, 209, 0.08);
    color: #722ed1;
  }

  .status-blue {
    background: rgba(24, 144, 255, 0.08);
    color: #1890ff;
  }

  .status-green {
    background: rgba(0, 181, 120, 0.08);
    color: var(--primary);
  }
</style>

<script>
  function copyInviteUrl() {
    const field = document.getElementById('invite-url-field');
    field.select();
    field.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(field.value)
      .then(() => {
        showGlobalToast("Invitation link copied successfully!");
      })
      .catch(() => {
        showGlobalToast("Failed to copy automatically. Please copy manually.");
      });
  }
</script>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

