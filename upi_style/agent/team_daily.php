<?php
// agent/team_daily.php - Team Daily report view
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

// Filter calculations
$filter_date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : date('Y-m-d');
$filter_date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : date('Y-m-d');
$filter_sub_phone = isset($_GET['sub_phone']) ? trim($_GET['sub_phone']) : '';
$filter_sub_uid = isset($_GET['sub_uid']) ? trim($_GET['sub_uid']) : '';

$team_daily_data = [];

$stmtSubs = $pdo->prepare("SELECT id, phone, reward_rate FROM users WHERE referred_by = ?");
$stmtSubs->execute([$user['id']]);
$direct_subs = $stmtSubs->fetchAll();

foreach ($direct_subs as $ds) {
    if ($filter_sub_phone !== '' && strpos($ds['phone'], $filter_sub_phone) === false) continue;
    if ($filter_sub_uid !== '' && intval($ds['id']) !== intval($filter_sub_uid)) continue;
    
    $stmtOrders = $pdo->prepare("SELECT DATE(created_at) as order_date, 
                                        SUM(price) as total_price, 
                                        COUNT(*) as total_count,
                                        SUM(reward) as total_reward 
                                 FROM buy_orders 
                                 WHERE user_id = ? AND status = 'Success'
                                   AND DATE(created_at) BETWEEN ? AND ?
                                 GROUP BY DATE(created_at)");
    $stmtOrders->execute([$ds['id'], $filter_date_start, $filter_date_end]);
    $days = $stmtOrders->fetchAll();
    
    foreach ($days as $day) {
        $stmtNewTeamers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ? AND DATE(created_at) = ?");
        $stmtNewTeamers->execute([$ds['id'], $day['order_date']]);
        $new_teamers = $stmtNewTeamers->fetchColumn();

        $team_daily_data[] = [
            'date' => date('Ymd', strtotime($day['order_date'])),
            'username' => $ds['phone'],
            'uid' => $ds['id'],
            'buy_inr' => $day['total_price'],
            'buy_times' => $day['total_count'],
            'reward_inr' => $day['total_price'] * ($ds['reward_rate'] / 100),
            'new_teamer' => $new_teamers
        ];
    }
}

// Include Layout Header
$current_page = 'team_daily.php';
require_once 'header.php';
?>

<!-- Team Daily report view wrapper -->
<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      Team Daily Aggregates
    </span>
  </div>
  <div class="card-body-element">
    
    <!-- Filters form -->
    <form method="GET" action="team_daily.php" class="report-filter-form">
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
          <label class="filter-box-label">Username</label>
          <input type="text" name="sub_phone" class="filter-input-element" placeholder="Subphone number" value="<?= sanitize($filter_sub_phone) ?>">
        </div>
        <div class="filter-box-input">
          <label class="filter-box-label">UID</label>
          <input type="text" name="sub_uid" class="filter-input-element" placeholder="Sub UID" value="<?= sanitize($filter_sub_uid) ?>">
        </div>
      </div>
      <div class="filter-actions-row">
        <button type="submit" class="filter-btn btn-primary-action">Query</button>
        <button type="button" class="filter-btn btn-success-action" onclick="exportTeamDailyCSV()">Export</button>
        <a href="team_daily.php" class="filter-btn btn-secondary-action">Reset</a>
      </div>
    </form>

    <!-- Table Grid -->
    <div class="grid-table-container">
      <table class="grid-data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Username</th>
            <th>UID</th>
            <th>Buy (INR)</th>
            <th>Buy Times</th>
            <th>Reward (INR)</th>
            <th>Dividend</th>
            <th>Payment Share</th>
            <th>Activity Reward</th>
            <th>Buy (USDT)</th>
            <th>Reward (USDT)</th>
            <th>Sell Times</th>
            <th>Sell Token</th>
            <th>New Wallet</th>
            <th>New Teamer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($team_daily_data)): ?>
            <tr>
              <td colspan="15" class="empty-table-row">No team aggregated records found within the criteria bounds.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($team_daily_data as $td): ?>
              <tr>
                <td><?= $td['date'] ?></td>
                <td style="font-weight: 700;"><?= sanitize($td['username']) ?></td>
                <td><?= intval($td['uid']) ?></td>
                <td style="font-weight: 700; color: var(--primary);">₹<?= number_format($td['buy_inr'], 2) ?></td>
                <td><?= intval($td['buy_times']) ?></td>
                <td style="color: var(--success); font-weight: 700;">₹<?= number_format($td['reward_inr'], 2) ?></td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0</td>
                <td>0.00</td>
                <td><?= intval($td['buy_times'] > 0 ? 1 : 0) ?></td>
                <td><?= intval($td['new_teamer']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<style>
  /* Extra button style for Export */
  .btn-success-action {
    background: #00b578;
    color: white;
    border: 0;
  }
  .btn-success-action:hover {
    opacity: 0.9;
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
</style>

<script>
  function exportTeamDailyCSV() {
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
    downloadLink.download = `Team_Daily_Report_${new Date().toISOString().slice(0, 10)}.csv`;
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

