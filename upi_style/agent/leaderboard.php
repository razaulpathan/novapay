<?php
// agent/leaderboard.php - Promoter Subordinates Leaderboard
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

// Fetch top subordinates ranked by success buy orders volume
$queryStr = "
    SELECT u.id, u.phone, u.created_at, COALESCE(SUM(bo.price), 0.00) as total_volume, COUNT(bo.id) as orders_count
    FROM users u
    LEFT JOIN buy_orders bo ON bo.user_id = u.id AND bo.status = 'Success'
    WHERE u.referred_by = ?
    GROUP BY u.id
    ORDER BY total_volume DESC, orders_count DESC
    LIMIT 20
";
$stmtRank = $pdo->prepare($queryStr);
$stmtRank->execute([$user['id']]);
$leaderboard = $stmtRank->fetchAll();

// Include Layout Header
$current_page = 'leaderboard.php';
require_once 'header.php';
?>

<div class="card-element">
  <div class="card-header-element">
    <span class="card-title-element">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
      </svg>
      Subordinate Performance Leaderboard
    </span>
  </div>
  <div class="card-body-element">
    <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.4;">The leaderboard ranks your direct subordinate traders and agents based on the total purchase volume (successful deposit buy orders) they have generated. Track and reward your top contributors to maintain high network activity.</p>
    
    <div class="grid-table-container">
      <table class="grid-data-table leaderboard-table">
        <thead>
          <tr>
            <th style="width: 80px; text-align: center;">Rank</th>
            <th>UID</th>
            <th>Username / Phone</th>
            <th>Total Buy Volume</th>
            <th>Successful Orders</th>
            <th>Registered Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($leaderboard)): ?>
            <tr>
              <td colspan="6" class="empty-table-row">No subordinates found in your network hierarchy yet.</td>
            </tr>
          <?php else: ?>
            <?php 
            $rank = 1;
            foreach ($leaderboard as $row): 
              $rank_class = '';
              $badge_lbl = $rank;
              if ($rank === 1) {
                  $rank_class = 'rank-gold';
                  $badge_lbl = '🥇';
              } elseif ($rank === 2) {
                  $rank_class = 'rank-silver';
                  $badge_lbl = '🥈';
              } elseif ($rank === 3) {
                  $rank_class = 'rank-bronze';
                  $badge_lbl = '🥉';
              }
            ?>
              <tr class="<?= $rank_class ?>">
                <td style="text-align: center; font-weight: 800; font-size: 15px;">
                  <span class="rank-badge-item"><?= $badge_lbl ?></span>
                </td>
                <td><?= intval($row['id']) ?></td>
                <td style="font-weight: 700;"><?= sanitize($row['phone']) ?></td>
                <td style="font-weight: 800; color: var(--primary);">₹<?= number_format($row['total_volume'], 2) ?></td>
                <td style="font-weight: 600;"><?= intval($row['orders_count']) ?> Orders</td>
                <td style="font-size: 12px; color: var(--text-secondary);"><?= $row['created_at'] ?></td>
              </tr>
            <?php 
              $rank++;
            endforeach; 
            ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  /* Leaderboard specifics colors */
  .rank-gold td {
    background-color: rgba(241, 196, 15, 0.04) !important;
  }
  .rank-silver td {
    background-color: rgba(149, 165, 166, 0.04) !important;
  }
  .rank-bronze td {
    background-color: rgba(211, 84, 0, 0.03) !important;
  }

  .rank-badge-item {
    display: inline-flex;
    width: 28px;
    height: 28px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
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
</style>

<?php
// Include Layout Footer
require_once 'footer.php';
?>

