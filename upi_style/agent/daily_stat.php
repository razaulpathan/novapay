<?php
// agent/daily_stat.php - Daily statistics performance metrics
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
$filter_date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : date('Y-m-d');
$filter_date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : date('Y-m-d');
$filter_stat_agent = isset($_GET['stat_agent']) ? trim($_GET['stat_agent']) : '';
$filter_sub_tab = isset($_GET['sub_tab']) ? trim($_GET['sub_tab']) : 'Agent';

$daily_stats_data = [];

// Fetch list of direct subordinates
$stmtSubs = $pdo->prepare("SELECT id, phone FROM users WHERE referred_by = ?");
$stmtSubs->execute([$user['id']]);
$direct_subs = $stmtSubs->fetchAll();

foreach ($direct_subs as $ds) {
    if ($filter_stat_agent !== '' && strpos($ds['phone'], $filter_stat_agent) === false) continue;

    // Registers count
    $stmtReg = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmtReg->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $reg_count = $stmtReg->fetchColumn() ?: 0;

    // First Buy counts
    $stmtFirstBuy = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM buy_orders bo JOIN users u ON bo.user_id = u.id WHERE u.referred_by = ? AND bo.status = 'Success' AND DATE(bo.created_at) BETWEEN ? AND ?");
    $stmtFirstBuy->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $first_buy_count = $stmtFirstBuy->fetchColumn() ?: 0;

    // PayIn stats (Buy orders)
    $stmtPayinTotal = $pdo->prepare("SELECT COUNT(*) FROM buy_orders WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayinTotal->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payin_total_orders = $stmtPayinTotal->fetchColumn() ?: 0;

    $stmtPayinSuccess = $pdo->prepare("SELECT COUNT(*) FROM buy_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayinSuccess->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payin_success_orders = $stmtPayinSuccess->fetchColumn() ?: 0;

    $stmtPayinSum = $pdo->prepare("SELECT SUM(price) FROM buy_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayinSum->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payin_success_sum = $stmtPayinSum->fetchColumn() ?: 0.00;

    $payin_success_rate = $payin_total_orders > 0 ? ($payin_success_orders / $payin_total_orders * 100) : 0;

    // PayOut stats (Sell orders)
    $stmtPayoutTotal = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayoutTotal->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payout_total_orders = $stmtPayoutTotal->fetchColumn() ?: 0;

    $stmtPayoutSuccess = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayoutSuccess->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payout_success_orders = $stmtPayoutSuccess->fetchColumn() ?: 0;

    $stmtPayoutSum = $pdo->prepare("SELECT SUM(amount) FROM sell_orders WHERE user_id = ? AND status = 'Success' AND DATE(created_at) BETWEEN ? AND ?");
    $stmtPayoutSum->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $payout_success_sum = $stmtPayoutSum->fetchColumn() ?: 0.00;

    $payout_success_rate = $payout_total_orders > 0 ? ($payout_success_orders / $payout_total_orders * 100) : 0;

    $daily_stats_data[] = [
        'agent' => $ds['phone'],
        'uid' => $ds['id'],
        'register' => $reg_count,
        'direct_register' => $reg_count,
        'first_buy' => $first_buy_count,
        'first_buy_direct' => $first_buy_count,
        
        // PayIn variables
        'payin_total' => $payin_total_orders,
        'payin_success' => $payin_success_orders,
        'payin_sum' => $payin_success_sum,
        'payin_rate' => $payin_success_rate,
        
        // PayOut variables
        'payout_total' => $payout_total_orders,
        'payout_success' => $payout_success_orders,
        'payout_sum' => $payout_success_sum,
        'payout_rate' => $payout_success_rate
    ];
}

// Include Layout Header
$current_page = 'daily_stat.php';
require_once 'header.php';
?>

<!-- Daily Statistics view wrapper -->
<div class="card-element">
  <!-- Sub-Tabs menu list under Daily Statistics -->
  <div class="sub-tabs-header-bar">
    <a href="daily_stat.php?sub_tab=Runner" class="sub-tab-item <?= $filter_sub_tab === 'Runner' ? 'active' : '' ?>">Runner</a>
    <a href="daily_stat.php?sub_tab=UPI" class="sub-tab-item <?= $filter_sub_tab === 'UPI' ? 'active' : '' ?>">UPI</a>
    <a href="daily_stat.php?sub_tab=wallet" class="sub-tab-item <?= $filter_sub_tab === 'wallet' ? 'active' : '' ?>">wallet</a>
    <a href="daily_stat.php?sub_tab=Agent" class="sub-tab-item <?= $filter_sub_tab === 'Agent' ? 'active' : '' ?>">Agent</a>
    <a href="daily_stat.php?sub_tab=RegisterQuery" class="sub-tab-item <?= $filter_sub_tab === 'RegisterQuery' ? 'active' : '' ?>">Register Query</a>
    <a href="daily_stat.php?sub_tab=AgentIn" class="sub-tab-item <?= $filter_sub_tab === 'AgentIn' ? 'active' : '' ?>">Agent In</a>
  </div>

  <div class="card-body-element">
    
    <!-- Filters search row -->
    <form method="GET" action="daily_stat.php" class="report-filter-form">
      <input type="hidden" name="sub_tab" value="<?= sanitize($filter_sub_tab) ?>">
      
      <div class="form-row-filters">
        <div class="filter-box-input">
          <label class="filter-box-label">Date Start</label>
          <input type="date" name="date_start" class="filter-input-element" value="<?= sanitize($filter_date_start) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">Date End</label>
          <input type="date" name="date_end" class="filter-input-element" value="<?= sanitize($filter_date_end) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">Agent Username</label>
          <input type="text" name="stat_agent" class="filter-input-element" placeholder="Agent Username" value="<?= sanitize($filter_stat_agent) ?>">
        </div>
      </div>
      <div class="filter-actions-row">
        <button type="submit" class="filter-btn btn-primary-action">Query</button>
        <button type="button" class="filter-btn btn-success-action" onclick="exportStatsCSV()">Export</button>
        <a href="daily_stat.php?sub_tab=<?= urlencode($filter_sub_tab) ?>" class="filter-btn btn-secondary-action">Reset</a>
      </div>
    </form>

    <!-- Table Grid with Spanned Header columns (matching Screenshot 5) -->
    <div class="grid-table-container">
      <table class="grid-data-table double-header">
        <thead>
          <tr>
            <th rowspan="2">Agent</th>
            <th rowspan="2">UID</th>
            <th rowspan="2">Register</th>
            <th rowspan="2">Direct Register</th>
            <th rowspan="2">First Buy</th>
            <th rowspan="2">First Buy(Direct)</th>
            <th rowspan="2">Active User</th>
            <th rowspan="2">Total Order</th>
            <th rowspan="2">Total Success</th>
            <th colspan="3" class="header-group-title header-group-payin">PayIn</th>
            <th colspan="6" class="header-group-title header-group-payout">PayOut</th>
          </tr>
          <tr>
            <th class="sub-header">Total Sum</th>
            <th class="sub-header">Success Sum</th>
            <th class="sub-header">Success Rate</th>
            
            <th class="sub-header">Active User</th>
            <th class="sub-header">Total Order</th>
            <th class="sub-header">Total Success</th>
            <th class="sub-header">Total Sum</th>
            <th class="sub-header">Success Sum</th>
            <th class="sub-header">Success Rate</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($filter_sub_tab !== 'Agent'): ?>
            <tr>
              <td colspan="18" class="empty-table-row">No records to load under sub-tab: <?= sanitize($filter_sub_tab) ?>. Select "Agent" sub-tab to view metrics.</td>
            </tr>
          <?php elseif (empty($daily_stats_data)): ?>
            <tr>
              <td colspan="18" class="empty-table-row">No statistical records matching the search criteria.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($daily_stats_data as $stat): ?>
              <tr>
                <td style="font-weight: 700;"><?= sanitize($stat['agent']) ?></td>
                <td><?= intval($stat['uid']) ?></td>
                <td><?= intval($stat['register']) ?></td>
                <td><?= intval($stat['direct_register']) ?></td>
                <td><?= intval($stat['first_buy']) ?></td>
                <td><?= intval($stat['first_buy_direct']) ?></td>
                
                <!-- Active User & order counts -->
                <td><?= intval($stat['payin_success'] > 0 ? 1 : 0) ?></td>
                <td><?= intval($stat['payin_total']) ?></td>
                <td><?= intval($stat['payin_success']) ?></td>
                
                <!-- PayIn details -->
                <td style="font-weight: 600;">₹<?= number_format($stat['payin_sum'], 2) ?></td>
                <td style="font-weight: 700; color: var(--success);">₹<?= number_format($stat['payin_sum'], 2) ?></td>
                <td style="font-weight: 700; color: var(--primary);"><?= number_format($stat['payin_rate'], 1) ?>%</td>
                
                <!-- PayOut details -->
                <td><?= intval($stat['payout_success'] > 0 ? 1 : 0) ?></td>
                <td><?= intval($stat['payout_total']) ?></td>
                <td><?= intval($stat['payout_success']) ?></td>
                <td style="font-weight: 600;">₹<?= number_format($stat['payout_sum'], 2) ?></td>
                <td style="font-weight: 700; color: var(--danger);">₹<?= number_format($stat['payout_sum'], 2) ?></td>
                <td style="font-weight: 700; color: var(--warning);"><?= number_format($stat['payout_rate'], 1) ?>%</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<style>
  /* Sub-tabs header bar */
  .sub-tabs-header-bar {
    display: flex;
    background: var(--table-header-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 0 16px;
    gap: 20px;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .sub-tabs-header-bar::-webkit-scrollbar {
    display: none;
  }
  .sub-tab-item {
    display: block;
    height: 46px;
    line-height: 46px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-sidebar-secondary);
    text-decoration: none;
    position: relative;
    white-space: nowrap;
    transition: color 0.2s;
  }
  .sub-tab-item:hover {
    color: var(--primary);
  }
  .sub-tab-item.active {
    color: var(--primary);
    font-weight: 700;
  }
  .sub-tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary);
    border-radius: 3px 3px 0 0;
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

  /* Double Header Table adjustments */
  .grid-data-table.double-header th {
    text-align: center;
    vertical-align: middle;
    border-right: 1px solid var(--border-color);
  }

  .grid-data-table.double-header th.header-group-title {
    font-weight: 800;
    font-size: 13.5px;
    border-bottom: 1px solid var(--border-color);
  }

  .grid-data-table.double-header th.header-group-payin {
    background: rgba(0, 181, 120, 0.05);
    color: var(--primary);
  }

  .grid-data-table.double-header th.header-group-payout {
    background: rgba(255, 45, 85, 0.05);
    color: #ff2d55;
  }

  .grid-data-table.double-header th.sub-header {
    font-size: 11.5px;
    padding: 10px;
    text-transform: uppercase;
    font-weight: 700;
  }

  .grid-data-table.double-header td {
    text-align: center;
    border-right: 1px solid var(--border-color);
  }

  .grid-data-table.double-header td:first-child {
    text-align: left;
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
    font-size: 13px;
  }

  .grid-data-table th {
    background: var(--table-header-bg);
    color: var(--text-main);
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
</style>

<script>
  function exportStatsCSV() {
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
    downloadLink.download = `Daily_Statistics_Report_${new Date().toISOString().slice(0, 10)}.csv`;
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

