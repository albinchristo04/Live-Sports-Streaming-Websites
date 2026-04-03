<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pdo = getPDO();
$adminPageTitle = 'Analytics';

// ---- Top-level view counts ----
$today     = (int)$pdo->query("SELECT COUNT(*) FROM match_views WHERE DATE(viewed_at) = CURDATE()")->fetchColumn();
$yesterday = (int)$pdo->query("SELECT COUNT(*) FROM match_views WHERE DATE(viewed_at) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();
$week7     = (int)$pdo->query("SELECT COUNT(*) FROM match_views WHERE viewed_at >= CURDATE() - INTERVAL 7 DAY")->fetchColumn();
$allTime   = (int)$pdo->query("SELECT COUNT(*) FROM match_views")->fetchColumn();

// ---- Per-server breakdown ----
$serverBreakdown = $pdo->query("
    SELECT s.name,
        SUM(CASE WHEN DATE(mv.viewed_at) = CURDATE()                        THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN DATE(mv.viewed_at) = CURDATE() - INTERVAL 1 DAY       THEN 1 ELSE 0 END) AS yesterday,
        SUM(CASE WHEN mv.viewed_at >= CURDATE() - INTERVAL 7 DAY             THEN 1 ELSE 0 END) AS week,
        COUNT(mv.id) AS total
    FROM servers s
    LEFT JOIN match_views mv ON mv.server_id = s.id
    GROUP BY s.id
    ORDER BY s.id
")->fetchAll();

// ---- Top 10 matches last 7 days ----
$topMatches = $pdo->query("
    SELECT match_title, server_id,
        SUM(CASE WHEN viewed_at >= CURDATE() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS views_7d,
        COUNT(*) AS total_views
    FROM match_views
    GROUP BY match_title, server_id
    ORDER BY views_7d DESC
    LIMIT 10
")->fetchAll();

// ---- Chart: daily views last 7 days, grouped by server ----
$chartRaw = $pdo->query("
    SELECT DATE(viewed_at) AS day, server_id, COUNT(*) AS views
    FROM match_views
    WHERE viewed_at >= CURDATE() - INTERVAL 7 DAY
    GROUP BY day, server_id
    ORDER BY day
")->fetchAll();

// Build 7-day label array
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-{$i} days"));
}

// Collect distinct server IDs from breakdown
$serverIds = array_column(
    $pdo->query("SELECT id FROM servers ORDER BY id")->fetchAll(),
    'id'
);

$chartData = [];
foreach ($serverIds as $sid) {
    $chartData[$sid] = array_fill_keys($days, 0);
}

foreach ($chartRaw as $row) {
    $sid = (int)$row['server_id'];
    $day = $row['day'];
    if (isset($chartData[$sid][$day])) {
        $chartData[$sid][$day] = (int)$row['views'];
    }
}

// Palette colours per server (cycles if more than 6 servers)
$palette = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#a855f7', '#ec4899'];

require __DIR__ . '/includes/admin-header.php';
?>

<!-- Summary cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-eye"></i></div>
    <div class="stat-value"><?= number_format($today) ?></div>
    <div class="stat-label">Today</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
    <div class="stat-value"><?= number_format($yesterday) ?></div>
    <div class="stat-label">Yesterday</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
    <div class="stat-value"><?= number_format($week7) ?></div>
    <div class="stat-label">Last 7 Days</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
    <div class="stat-value"><?= number_format($allTime) ?></div>
    <div class="stat-label">All Time</div>
  </div>
</div>

<!-- Server breakdown table -->
<div class="admin-card" style="margin-top:2rem;">
  <h2 class="card-title"><i class="fas fa-server"></i> Server Breakdown</h2>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Server</th>
        <th>Today</th>
        <th>Yesterday</th>
        <th>Last 7 Days</th>
        <th>All Time</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($serverBreakdown)): ?>
        <tr><td colspan="5" style="text-align:center;padding:20px;">No data available.</td></tr>
      <?php else: ?>
        <?php foreach ($serverBreakdown as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int)$r['today']) ?></td>
            <td><?= number_format((int)$r['yesterday']) ?></td>
            <td><?= number_format((int)$r['week']) ?></td>
            <td><?= number_format((int)$r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Chart -->
<div class="admin-card" style="margin-top:2rem;">
  <h2 class="card-title"><i class="fas fa-chart-line"></i> Views — Last 7 Days</h2>
  <canvas id="viewsChart" style="max-height:320px;"></canvas>
</div>

<!-- Top matches -->
<div class="admin-card" style="margin-top:2rem;">
  <h2 class="card-title"><i class="fas fa-trophy"></i> Top 10 Matches (Last 7 Days)</h2>
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Match</th>
        <th>Server ID</th>
        <th>7-Day Views</th>
        <th>Total Views</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($topMatches)): ?>
        <tr><td colspan="5" style="text-align:center;padding:20px;">No view data yet.</td></tr>
      <?php else: ?>
        <?php foreach ($topMatches as $i => $m): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($m['match_title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$m['server_id'] ?></td>
            <td><?= number_format((int)$m['views_7d']) ?></td>
            <td><?= number_format((int)$m['total_views']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var labels   = <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>;
    var palette  = <?= json_encode($palette) ?>;
    var datasets = [];

    <?php foreach ($serverIds as $idx => $sid): ?>
    datasets.push({
        label: <?= json_encode($serverBreakdown[$idx]['name'] ?? 'Server ' . $sid) ?>,
        data:  <?= json_encode(array_values($chartData[$sid])) ?>,
        backgroundColor: palette[<?= $idx ?> % palette.length],
        borderColor:     palette[<?= $idx ?> % palette.length],
        borderWidth: 1,
    });
    <?php endforeach; ?>

    new Chart(document.getElementById('viewsChart'), {
        type: 'bar',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: '#f0f0f0' } }
            },
            scales: {
                x: { ticks: { color: '#a0a0b0' }, grid: { color: '#2a2a3e' } },
                y: { ticks: { color: '#a0a0b0' }, grid: { color: '#2a2a3e' }, beginAtZero: true }
            }
        }
    });
}());
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
