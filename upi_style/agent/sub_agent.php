<?php
// agent/sub_agent.php - Sub-Agent Registration and Management
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

// Restrict to agents who have permission
if (!isset($user['allow_create_sub_agent']) || $user['allow_create_sub_agent'] != 1) {
    header("Location: index.php");
    exit;
}

$sub_agent_error = '';
$sub_agent_success = '';

// Handle sub-agent registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_sub_agent') {
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
            
            // Register as sub-agent
            $stmtInsert = $pdo->prepare("INSERT INTO users (phone, password, referral_code, referred_by, status, role, allow_create_sub_agent) VALUES (?, ?, ?, ?, 'Active', 'user', 1)");
            $stmtInsert->execute([$sub_phone, $pass_hash, $ref_code, $user['id']]);
            $sub_agent_success = "Sub-agent {$sub_phone} successfully registered under your team!";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_sub_permission') {
    $sub_id = isset($_POST['sub_id']) ? intval($_POST['sub_id']) : 0;
    
    // Verify subordinate belongs to agent
    $stmtVerify = $pdo->prepare("SELECT * FROM users WHERE id = ? AND referred_by = ?");
    $stmtVerify->execute([$sub_id, $user['id']]);
    $sub = $stmtVerify->fetch();
    
    if (!$sub) {
        $sub_agent_error = "Sub-agent not found.";
    } else {
        $new_val = ($sub['allow_create_sub_agent'] == 1) ? 0 : 1;
        $stmtUp = $pdo->prepare("UPDATE users SET allow_create_sub_agent = ? WHERE id = ?");
        $stmtUp->execute([$new_val, $sub_id]);
        $sub_agent_success = "Creation permission for sub-agent " . sanitize($sub['phone']) . " updated.";
    }
}

// Fetch list of created sub-agents and their team sizes
$stmtSubList = $pdo->prepare("
    SELECT s.id, s.phone, s.created_at, s.status, s.reward_rate, s.allow_create_sub_agent, COUNT(u.id) as team_size
    FROM users s
    LEFT JOIN users u ON u.referred_by = s.id
    WHERE s.referred_by = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmtSubList->execute([$user['id']]);
$sub_agents = $stmtSubList->fetchAll();

// Include Layout Header
$current_page = 'sub_agent.php';
require_once 'header.php';
?>

<!-- Register Sub-Agent Form Card -->
<div class="card-element highlight-border">
  <div class="card-header-element highlight-header">
    <span class="card-title-element highlight-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <line x1="19" y1="8" x2="19" y2="14"></line>
        <line x1="22" y1="11" x2="16" y2="11"></line>
      </svg>
      Create Sub-Agent Credentials
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

<!-- Sub-Agent List Table Card -->
<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
      </svg>
      Sub-Agent Accounts Directory
    </span>
  </div>
  <div class="card-body-element">
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>UID</th>
            <th>Phone / Username</th>
            <th>Reward Rate</th>
            <th>Referred Team Size</th>
            <th>Registered Time</th>
            <th>Permission</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sub_agents)): ?>
            <tr>
              <td colspan="8" class="empty-table-row">No sub-agent accounts registered under your hierarchy yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($sub_agents as $sa): ?>
              <tr>
                <td><?= intval($sa['id']) ?></td>
                <td style="font-weight: 700;"><?= sanitize($sa['phone']) ?></td>
                <td style="font-weight: 700; color: var(--primary);"><?= number_format($sa['reward_rate'], 2) ?>%</td>
                <td>
                  <span class="badge-status status-blue"><?= intval($sa['team_size']) ?> Users</span>
                </td>
                <td style="font-size: 12px; color: var(--text-secondary);"><?= $sa['created_at'] ?></td>
                <td>
                  <span class="badge-status <?= ($sa['allow_create_sub_agent'] == 1) ? 'status-green' : 'status-purple' ?>">
                    <?= ($sa['allow_create_sub_agent'] == 1) ? 'Allowed' : 'Disallowed' ?>
                  </span>
                </td>
                <td>
                  <span class="badge-status <?= $sa['status'] === 'Active' ? 'status-green' : 'status-purple' ?>">
                    <?= sanitize($sa['status']) ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <form method="POST" style="margin: 0; display: inline-block;">
                    <input type="hidden" name="action" value="toggle_sub_permission">
                    <input type="hidden" name="sub_id" value="<?= $sa['id'] ?>">
                    <button type="submit" class="submit-action-btn" style="height: 28px; padding: 0 12px; font-size: 11px; font-weight: bold; border-radius: 4px; box-shadow: none; background: <?= ($sa['allow_create_sub_agent'] == 1) ? 'var(--danger)' : 'var(--primary)' ?>;">
                      <?= ($sa['allow_create_sub_agent'] == 1) ? 'Revoke Create' : 'Grant Create' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  /* sub_agent specifics styles */
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

<?php
// Include Layout Footer
require_once 'footer.php';
?>

