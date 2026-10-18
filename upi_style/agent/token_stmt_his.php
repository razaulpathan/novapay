<?php
// agent/token_stmt_his.php - Token Statement History ledger
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

// Filters calculations
$filter_rpt_no = isset($_GET['rpt_no']) ? trim($_GET['rpt_no']) : '';
$filter_stmt_username = isset($_GET['stmt_username']) ? trim($_GET['stmt_username']) : '';
$filter_stmt_uid = isset($_GET['stmt_uid']) ? trim($_GET['stmt_uid']) : '';

$stmt_records = [];

// Fetch historical ledger data (created before today)
$queryStr = "SELECT 'PayIn' as direction, b.order_no, b.price as amount, b.created_at, b.status, u.phone, u.id as user_id, u.itoken_balance, u.reward_rate
             FROM buy_orders b
             JOIN users u ON b.user_id = u.id
             WHERE u.referred_by = ? AND DATE(b.created_at) < CURRENT_DATE()
             ORDER BY b.created_at DESC LIMIT 50";
$stmtLedger = $pdo->prepare($queryStr);
$stmtLedger->execute([$user['id']]);
$ledger_orders = $stmtLedger->fetchAll();

foreach ($ledger_orders as $lo) {
    if ($filter_rpt_no !== '' && strpos($lo['order_no'], $filter_rpt_no) === false) continue;
    if ($filter_stmt_username !== '' && strpos($lo['phone'], $filter_stmt_username) === false) continue;
    if ($filter_stmt_uid !== '' && intval($lo['user_id']) !== intval($filter_stmt_uid)) continue;

    $tran_amt = $lo['amount'] * ($lo['reward_rate'] / 100);
    $after_amt = $lo['itoken_balance'];
    $before_amt = $after_amt - $tran_amt;
    $rpt_no = 'RPT' . str_pad($lo['user_id'], 5, '0', STR_PAD_LEFT) . strtotime($lo['created_at']);

    if ($filter_rpt_no !== '' && strpos($rpt_no, $filter_rpt_no) === false) continue;

    $stmt_records[] = [
        'rpt_no' => $rpt_no,
        'order_no' => $lo['order_no'],
        'username' => $lo['phone'],
        'uid' => $lo['user_id'],
        'fee_type' => 'Payment Share',
        'user_type' => 'Agent',
        'before_amt' => $before_amt,
        'tran_amt' => $tran_amt,
        'after_amt' => $after_amt,
        'order_typ' => 'PayOut',
        'profit_loss' => 'Profit',
        'create_time' => $lo['created_at']
    ];
}

// Include Layout Header
$current_page = 'token_stmt_his.php';
require_once 'header.php';
?>

<!-- Token STMT History view wrapper -->
<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      Token Statement History Ledger
    </span>
  </div>
  <div class="card-body-element">
    
    <!-- Filters form -->
    <form method="GET" action="token_stmt_his.php" class="report-filter-form">
      <div class="form-row-filters">
        <div class="filter-box-input">
          <label class="filter-box-label">Report No</label>
          <input type="text" name="rpt_no" class="filter-input-element" placeholder="Report number" value="<?= sanitize($filter_rpt_no) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">Username</label>
          <input type="text" name="stmt_username" class="filter-input-element" placeholder="Username" value="<?= sanitize($filter_stmt_username) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">UID</label>
          <input type="text" name="stmt_uid" class="filter-input-element" placeholder="UID" value="<?= sanitize($filter_stmt_uid) ?>">
        </div>
      </div>
      <div class="filter-actions-row">
        <button type="submit" class="filter-btn btn-primary-action">Query</button>
        <button type="button" class="filter-btn btn-success-action" onclick="exportHistoryCSV()">Export</button>
        <a href="token_stmt_his.php" class="filter-btn btn-secondary-action">Reset</a>
      </div>
    </form>

    <!-- Data Grid Table -->
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>RptNo</th>
            <th>OrderNo</th>
            <th>Username</th>
            <th>UID</th>
            <th>Fee Type</th>
            <th>User Type</th>
            <th>BeforeAmt</th>
            <th>TranAmt</th>
            <th>AfterAmt</th>
            <th>OrderTyp</th>
            <th>Profit/Loss</th>
            <th>Create Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($stmt_records)): ?>
            <tr>
              <td colspan="12" class="empty-table-row">No historical statement ledger logs matching your filters.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($stmt_records as $rec): ?>
              <tr>
                <td style="font-family: monospace; font-size: 11px;"><?= $rec['rpt_no'] ?></td>
                <td style="font-family: monospace; font-size: 11px;"><?= $rec['order_no'] ?></td>
                <td style="font-weight: 700;"><?= sanitize($rec['username']) ?></td>
                <td><?= intval($rec['uid']) ?></td>
                <td>
                  <span class="badge-status status-green"><?= $rec['fee_type'] ?></span>
                </td>
                <td>
                  <span class="badge-status status-purple"><?= $rec['user_type'] ?></span>
                </td>
                <td>₹<?= number_format($rec['before_amt'], 2) ?></td>
                <td style="font-weight: 700; color: var(--primary);">+₹<?= number_format($rec['tran_amt'], 2) ?></td>
                <td>₹<?= number_format($rec['after_amt'], 2) ?></td>
                <td>
                  <span class="badge-status status-blue"><?= $rec['order_typ'] ?></span>
                </td>
                <td>
                  <span class="badge-status status-green"><?= $rec['profit_loss'] ?></span>
                </td>
                <td style="font-size: 11.5px; color: var(--text-secondary);"><?= $rec['create_time'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<style>
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

  .btn-success-action {
    background: #00b578;
    color: white;
    border: 0;
  }

  .btn-success-action:hover {
    opacity: 0.9;
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
    white-space: nowrap;
  }

  .grid-data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-main);
    background: var(--bg-card);
    white-space: nowrap;
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
  function exportHistoryCSV() {
    const csv = [];
    const rows = document.querySelectorAll(".grid-data-table tr");
    
    for (let i = 0; i < rows.length; i++) {
      const row = [], cols = rows[i].querySelectorAll("td, th");
      
      for (let j = 0; j < cols.length; j++) {
        let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s+)/gm, ' ');
        data = data.replace(/"/g, '""');
        row.push('"' + data + '"');
      }
      csv.push(row.join(","));
    }

    const csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    const downloadLink = document.createElement("a");
    downloadLink.download = `Token_Statement_History_Ledger_${new Date().toISOString().slice(0, 10)}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    showGlobalToast("CSV exported successfully!");
  }
</script>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

