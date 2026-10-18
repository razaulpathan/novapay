<?php
// agent/support_tickets.php - Promoter Support Tickets desk
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

$error_msg = '';
$success_msg = '';

// Handle ticket creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (empty($subject) || empty($message)) {
        $error_msg = "Please fill in all fields.";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'Open')");
        $stmtInsert->execute([$user['id'], $subject, $message]);
        
        $success_msg = "Your support ticket has been opened successfully! Our admin team will reply shortly.";
    }
}

// Fetch tickets raised by the agent
$stmtTickets = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmtTickets->execute([$user['id']]);
$tickets = $stmtTickets->fetchAll();

// Include Layout Header
$current_page = 'support_tickets.php';
require_once 'header.php';
?>

<div class="transfer-grid-container">
  <!-- Card 1: Submit Ticket -->
  <div class="card-element flex-form-card">
    <div class="card-header-element">
      <span class="card-title-element">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        Open a New Support Ticket
      </span>
    </div>
    <div class="card-body-element">
      <?php if (!empty($error_msg)): ?>
        <div class="transfer-alert alert-danger"><?= sanitize($error_msg) ?></div>
      <?php endif; ?>
      <?php if (!empty($success_msg)): ?>
        <div class="transfer-alert alert-success"><?= sanitize($success_msg) ?></div>
      <?php endif; ?>

      <form method="POST" class="transfer-input-form">
        <input type="hidden" name="action" value="create_ticket">
        
        <div class="form-input-group">
          <label class="form-input-label" for="subject">Subject / Issue Summary</label>
          <input type="text" name="subject" id="subject" class="form-text-input" placeholder="e.g. Settlement issue, sub-agent rate query" required>
        </div>
        <div class="form-input-group">
          <label class="form-input-label" for="message">Detailed Message</label>
          <textarea name="message" id="message" class="form-text-input textarea-input" placeholder="Describe your issue or request in detail..." required style="height: 120px; padding: 12px; resize: none;"></textarea>
        </div>
        <button type="submit" class="submit-action-btn">Submit Support Ticket</button>
      </form>
    </div>
  </div>

  <!-- Card 2: Help Summary -->
  <div class="card-element balance-summary-card">
    <div class="card-header-element">
      <span class="card-title-element">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        Support Channels
      </span>
    </div>
    <div class="card-body-element balance-summary-body">
      <div class="balance-alert-tip">
        <strong>Need Assistance?</strong><br><br>
        Create a support ticket, and our backend administrator group will review your case. Typical response time is under 1-2 hours during active business cycles.
      </div>
      <div class="balance-alert-tip" style="border-top: 1px solid var(--border-color); padding-top: 16px;">
        For urgent payment gateway integrations, connect directly via Telegram support handles listed in the main client dashboard.
      </div>
    </div>
  </div>
</div>

<!-- List of active/resolved tickets -->
<div class="card-element" style="margin-top: 20px;">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
      </svg>
      Your Ticket Log
    </span>
  </div>
  <div class="card-body-element">
    <div class="tickets-accordion-list">
      <?php if (empty($tickets)): ?>
        <div class="empty-tickets-view">You have not submitted any support tickets yet.</div>
      <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
          <div class="ticket-collapse-box">
            <div class="ticket-collapse-header" onclick="toggleAccordion('ticket-<?= $ticket['id'] ?>')">
              <div class="ticket-header-left">
                <span class="ticket-id-tag">#<?= intval($ticket['id']) ?></span>
                <span class="ticket-subject-title"><?= sanitize($ticket['subject']) ?></span>
              </div>
              <div class="ticket-header-right">
                <span class="ticket-date-lbl"><?= $ticket['created_at'] ?></span>
                <?php if ($ticket['status'] === 'Open'): ?>
                  <span class="badge-status status-warning">Open</span>
                <?php elseif ($ticket['status'] === 'Replied'): ?>
                  <span class="badge-status status-success">Replied</span>
                <?php else: ?>
                  <span class="badge-status status-blue">Closed</span>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="ticket-collapse-body" id="ticket-<?= $ticket['id'] ?>" style="display: none;">
              <div class="ticket-message-section">
                <div class="author-label-row">
                  <span class="author-title">You:</span>
                </div>
                <div class="message-content-text"><?= nl2br(sanitize($ticket['message'])) ?></div>
              </div>
              
              <?php if (!empty($ticket['reply'])): ?>
                <div class="ticket-reply-section">
                  <div class="author-label-row">
                    <span class="author-title admin-author">Support Administrator:</span>
                  </div>
                  <div class="message-content-text reply-text"><?= nl2br(sanitize($ticket['reply'])) ?></div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
  .transfer-grid-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
  }

  .flex-form-card {
    display: flex;
    flex-direction: column;
  }

  .balance-summary-card {
    display: flex;
    flex-direction: column;
  }

  .balance-summary-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
    justify-content: center;
  }

  .balance-alert-tip {
    font-size: 13.5px;
    color: var(--text-secondary);
    line-height: 1.5;
  }

  .transfer-alert {
    padding: 11px 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 600;
  }

  .alert-danger {
    background: #fdf2f2;
    border: 1px solid #fde8e8;
    color: #e74c3c;
  }

  .alert-success {
    background: #f3faf6;
    border: 1px solid #def7ec;
    color: #0e6251;
  }

  .transfer-input-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .form-input-group {
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

  .form-text-input.textarea-input {
    height: 100px;
    padding-top: 10px;
    padding-bottom: 10px;
  }

  .submit-action-btn {
    height: 44px;
    background: var(--primary);
    color: white;
    font-weight: 700;
    border: 0;
    border-radius: 8px;
    font-size: 13.5px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.2);
    transition: opacity 0.2s;
    margin-top: 10px;
  }

  .submit-action-btn:hover {
    opacity: 0.95;
  }

  /* Accordion styling */
  .tickets-accordion-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .empty-tickets-view {
    text-align: center;
    color: var(--text-secondary);
    font-weight: 500;
    padding: 40px 0;
  }

  .ticket-collapse-box {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
  }

  .ticket-collapse-header {
    background: var(--table-header-bg);
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    flex-wrap: wrap;
    gap: 12px;
  }

  .ticket-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .ticket-id-tag {
    font-size: 12px;
    font-family: monospace;
    background: var(--border-color);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--text-main);
    font-weight: 700;
  }

  .ticket-subject-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
  }

  .ticket-header-right {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .ticket-date-lbl {
    font-size: 12px;
    color: var(--text-secondary);
  }

  .ticket-collapse-body {
    padding: 18px;
    background: var(--bg-card);
    border-top: 1px dashed var(--border-color);
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .ticket-message-section {
    background: var(--bg-body);
    padding: 12px 16px;
    border-radius: 8px;
    border-left: 3px solid var(--text-secondary);
  }

  .ticket-reply-section {
    background: rgba(var(--primary-rgb), 0.04);
    padding: 12px 16px;
    border-radius: 8px;
    border-left: 3px solid var(--primary);
    align-self: flex-end;
    width: 90%;
  }

  .author-label-row {
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
  }

  .author-title {
    color: var(--text-secondary);
  }

  .author-title.admin-author {
    color: var(--primary);
  }

  .message-content-text {
    font-size: 13.5px;
    color: var(--text-main);
    line-height: 1.4;
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

  .status-warning {
    background: rgba(243, 156, 18, 0.08);
    color: var(--warning);
  }

  .status-success {
    background: rgba(46, 204, 113, 0.08);
    color: var(--success);
  }

  .status-blue {
    background: rgba(24, 144, 255, 0.08);
    color: #1890ff;
  }

  @media (max-width: 900px) {
    .transfer-grid-container {
      grid-template-columns: 1fr;
    }
    .ticket-reply-section {
      width: 100%;
    }
  }
</style>

<script>
  function toggleAccordion(id) {
    const el = document.getElementById(id);
    if (el.style.display === "none" || el.style.display === "") {
      el.style.display = "flex";
    } else {
      el.style.display = "none";
    }
  }
</script>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

