<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$adminPageTitle = 'Matches';
$pdo = getPDO();

// ---- Flash message ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Filters ----
$filterServerId = (int)   ($_GET['server_id'] ?? 0);
$filterLeague   = trim(   $_GET['league']     ?? '');
$filterSearch   = trim(   $_GET['search']     ?? '');
$filterDeleted  = (int)   ($_GET['deleted']   ?? 0);
$page           = max(1, (int) ($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

// ---- Servers for filter dropdown ----
$servers = $pdo->query('SELECT id, name FROM servers ORDER BY id')->fetchAll();

// ---- Distinct leagues ----
$leaguesStmt = $pdo->query('SELECT DISTINCT league FROM matches WHERE league IS NOT NULL ORDER BY league');
$leagues     = $leaguesStmt->fetchAll(PDO::FETCH_COLUMN);

// ---- Count query ----
$countSql = '
    SELECT COUNT(DISTINCT m.id)
    FROM matches m
    JOIN servers s ON s.id = m.server_id
    WHERE (:deleted = 1 OR m.deleted_at IS NULL)
      AND (:server_id = 0 OR m.server_id = :server_id2)
      AND (:league = \'\' OR m.league = :league2)
      AND (:search = \'\' OR m.title LIKE :search_like)
';

$countStmt = $pdo->prepare($countSql);
$countStmt->execute([
    ':deleted'     => $filterDeleted,
    ':server_id'   => $filterServerId,
    ':server_id2'  => $filterServerId,
    ':league'      => $filterLeague,
    ':league2'     => $filterLeague,
    ':search'      => $filterSearch,
    ':search_like' => '%' . $filterSearch . '%',
]);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));

// ---- Main query ----
$sql = '
    SELECT m.id, m.title, m.league, m.match_datetime, m.deleted_at, m.slug,
           s.name AS server_name,
           COUNT(ms.id) AS stream_count
    FROM matches m
    JOIN servers s ON s.id = m.server_id
    LEFT JOIN match_streams ms ON ms.match_id = m.id AND ms.is_active = 1
    WHERE (:deleted = 1 OR m.deleted_at IS NULL)
      AND (:server_id = 0 OR m.server_id = :server_id2)
      AND (:league = \'\' OR m.league = :league2)
      AND (:search = \'\' OR m.title LIKE :search_like)
    GROUP BY m.id
    ORDER BY m.match_datetime DESC
    LIMIT :limit OFFSET :offset
';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':deleted',     $filterDeleted, PDO::PARAM_INT);
$stmt->bindValue(':server_id',   $filterServerId, PDO::PARAM_INT);
$stmt->bindValue(':server_id2',  $filterServerId, PDO::PARAM_INT);
$stmt->bindValue(':league',      $filterLeague);
$stmt->bindValue(':league2',     $filterLeague);
$stmt->bindValue(':search',      $filterSearch);
$stmt->bindValue(':search_like', '%' . $filterSearch . '%');
$stmt->bindValue(':limit',       $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset',      $offset,  PDO::PARAM_INT);
$stmt->execute();
$matches = $stmt->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<!-- Filters -->
<div class="admin-card">
  <form method="GET" action="/admin/matches.php" class="filter-form">
    <div class="form-row">
      <div class="form-group">
        <label>Server</label>
        <select name="server_id" class="form-control">
          <option value="0">All Servers</option>
          <?php foreach ($servers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= $filterServerId === (int) $s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>League</label>
        <select name="league" class="form-control">
          <option value="">All Leagues</option>
          <?php foreach ($leagues as $lg): ?>
            <option value="<?= htmlspecialchars($lg, ENT_QUOTES, 'UTF-8') ?>" <?= $filterLeague === $lg ? 'selected' : '' ?>>
              <?= htmlspecialchars($lg, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Search Title</label>
        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="deleted" class="form-control">
          <option value="0" <?= $filterDeleted === 0 ? 'selected' : '' ?>>Active</option>
          <option value="1" <?= $filterDeleted === 1 ? 'selected' : '' ?>>Include Deleted</option>
        </select>
      </div>
      <div class="form-group" style="align-self:flex-end;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
        <a href="/admin/matches.php" class="btn btn-secondary">Reset</a>
      </div>
    </div>
  </form>
</div>

<!-- Match List -->
<div class="admin-card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
    <h2 class="card-title" style="margin:0;">
      <i class="fas fa-list"></i> Matches
      <span class="badge"><?= number_format($totalCount) ?> total</span>
    </h2>
    <a href="/admin/match-create.php" class="btn btn-success">
      <i class="fas fa-plus"></i> Add Match
    </a>
  </div>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>League</th>
          <th>Server</th>
          <th>Date / Time</th>
          <th>Streams</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($matches)): ?>
          <tr><td colspan="8" style="text-align:center;">No matches found.</td></tr>
        <?php else: ?>
          <?php foreach ($matches as $m): ?>
            <?php $isDeleted = $m['deleted_at'] !== null; ?>
            <tr class="<?= $isDeleted ? 'row-deleted' : '' ?>">
              <td><?= (int) $m['id'] ?></td>
              <td>
                <?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?>
                <?php if ($isDeleted): ?>
                  <span class="badge badge-danger" style="font-size:0.7em;">Deleted</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($m['league'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($m['server_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($m['match_datetime'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $m['stream_count'] ?></td>
              <td>
                <?php if (isMatchLive($m['match_datetime'])): ?>
                  <span class="badge badge-live">LIVE</span>
                <?php elseif ($isDeleted): ?>
                  <span class="badge badge-danger">Deleted</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Scheduled</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <?php if (!$isDeleted): ?>
                  <a href="/admin/match-edit.php?id=<?= (int) $m['id'] ?>" class="btn btn-sm btn-secondary">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <form method="POST" action="/admin/match-delete.php?id=<?= (int) $m['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete this match?')">
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="/admin/match-restore.php?id=<?= (int) $m['id'] ?>" style="display:inline;">
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="fas fa-undo"></i> Restore
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $queryBase = http_build_query(array_filter([
          'server_id' => $filterServerId ?: null,
          'league'    => $filterLeague ?: null,
          'search'    => $filterSearch ?: null,
          'deleted'   => $filterDeleted ?: null,
      ]));
      $queryBase = $queryBase ? '&' . $queryBase : '';
      ?>
      <?php if ($page > 1): ?>
        <a href="/admin/matches.php?page=<?= $page - 1 ?><?= $queryBase ?>" class="btn btn-sm btn-secondary">&laquo; Prev</a>
      <?php endif; ?>
      <span>Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/admin/matches.php?page=<?= $page + 1 ?><?= $queryBase ?>" class="btn btn-sm btn-secondary">Next &raquo;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
