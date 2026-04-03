<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$adminPageTitle = 'Dashboard';
$pdo = getPDO();

// 1. Total Matches
$stmt = $pdo->query('SELECT COUNT(*) FROM matches WHERE deleted_at IS NULL');
$totalMatches = (int) $stmt->fetchColumn();

// 2. Views Today
$stmt = $pdo->query('SELECT COUNT(*) FROM match_views WHERE DATE(viewed_at) = CURDATE()');
$viewsToday = (int) $stmt->fetchColumn();

// 3. Active / Live Streams
$stmt = $pdo->query(
    'SELECT COUNT(*) FROM matches
     WHERE deleted_at IS NULL
       AND match_datetime BETWEEN NOW() - INTERVAL 3 HOUR AND NOW() + INTERVAL 1 HOUR'
);
$activeStreams = (int) $stmt->fetchColumn();

// 4. Per-server breakdown
$stmt = $pdo->query(
    'SELECT s.name, COUNT(m.id) as cnt
     FROM servers s
     LEFT JOIN matches m ON m.server_id = s.id AND m.deleted_at IS NULL
     GROUP BY s.id
     ORDER BY s.id'
);
$serverBreakdown = $stmt->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-futbol"></i></div>
    <div class="stat-value"><?= number_format($totalMatches) ?></div>
    <div class="stat-label">Total Matches</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-eye"></i></div>
    <div class="stat-value"><?= number_format($viewsToday) ?></div>
    <div class="stat-label">Views Today</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-broadcast-tower"></i></div>
    <div class="stat-value"><?= number_format($activeStreams) ?></div>
    <div class="stat-label">Active Streams (Live)</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-server"></i></div>
    <div class="stat-value"><?= count($serverBreakdown) ?></div>
    <div class="stat-label">Active Servers</div>
  </div>
</div>

<div class="admin-card" style="margin-top: 2rem;">
  <h2 class="card-title"><i class="fas fa-server"></i> Matches by Server</h2>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Server</th>
        <th>Matches</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($serverBreakdown)): ?>
        <tr><td colspan="2" style="text-align:center;">No servers found.</td></tr>
      <?php else: ?>
        <?php foreach ($serverBreakdown as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int) $row['cnt']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="admin-card" style="margin-top: 1.5rem;">
  <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
  <div style="display:flex; gap:1rem; flex-wrap:wrap;">
    <a href="/admin/import" class="btn btn-primary"><i class="fas fa-download"></i> Import Matches</a>
    <a href="/admin/match-create" class="btn btn-success"><i class="fas fa-plus"></i> Add Match</a>
    <a href="/admin/matches" class="btn btn-secondary"><i class="fas fa-list"></i> View All Matches</a>
    <a href="/admin/analytics" class="btn btn-secondary"><i class="fas fa-chart-line"></i> Analytics</a>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
