<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getPDO();
$adminPageTitle = 'Edit Match';
$error = '';

// Resolve match ID from query string or URI segment
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/matches.php');
    exit;
}

// Fetch match
$matchStmt = $pdo->prepare("SELECT * FROM matches WHERE id = ? LIMIT 1");
$matchStmt->execute([$id]);
$match = $matchStmt->fetch();
if (!$match) {
    header('Location: /admin/matches.php');
    exit;
}

// Fetch existing streams
$streamsStmt = $pdo->prepare(
    "SELECT * FROM match_streams WHERE match_id = ? ORDER BY sort_order ASC"
);
$streamsStmt->execute([$id]);
$existingStreams = $streamsStmt->fetchAll();

$servers = $pdo->query("SELECT * FROM servers ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = sanitizeInput($_POST['title'] ?? '');
    $league   = sanitizeInput($_POST['league'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $teamHome = sanitizeInput($_POST['team_home'] ?? '');
    $teamAway = sanitizeInput($_POST['team_away'] ?? '');
    $datetime = $_POST['match_datetime'] ?? '';
    $serverId = (int)($_POST['server_id'] ?? 0);
    $streams  = $_POST['streams'] ?? [];

    if (!$title || !$datetime || !$serverId) {
        $error = 'Title, date/time, and server are required.';
    } else {
        $dateOnly    = date('Y-m-d', strtotime($datetime));
        $slug        = generateMatchSlug($title, $dateOnly);
        $fingerprint = md5(strtolower($title) . $dateOnly);

        try {
            $pdo->beginTransaction();

            // Update matches row
            $updateStmt = $pdo->prepare(
                "UPDATE matches
                 SET title = ?, slug = ?, league = ?, category = ?,
                     team_home = ?, team_away = ?, match_datetime = ?,
                     server_id = ?, fingerprint = ?
                 WHERE id = ?"
            );
            $updateStmt->execute([
                $title,
                $slug,
                $league ?: null,
                $category ?: null,
                $teamHome ?: null,
                $teamAway ?: null,
                $datetime,
                $serverId,
                $fingerprint,
                $id,
            ]);

            // Replace streams: delete all old, insert new
            $pdo->prepare("DELETE FROM match_streams WHERE match_id = ?")->execute([$id]);

            $streamStmt = $pdo->prepare(
                "INSERT INTO match_streams (match_id, channel_name, iframe_url, lang, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($streams as $i => $s) {
                $iframeUrl = trim($s['iframe_url'] ?? '');
                if ($iframeUrl === '') continue;
                $streamStmt->execute([
                    $id,
                    sanitizeInput($s['channel_name'] ?? '') ?: null,
                    $iframeUrl,
                    sanitizeInput($s['lang'] ?? '') ?: null,
                    (int)$i,
                ]);
            }

            $pdo->commit();
            header('Location: /admin/matches.php?msg=Match+updated+successfully');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }

    // Re-populate for re-display after error
    $match = array_merge($match, [
        'title'          => $_POST['title'] ?? $match['title'],
        'league'         => $_POST['league'] ?? $match['league'],
        'category'       => $_POST['category'] ?? $match['category'],
        'team_home'      => $_POST['team_home'] ?? $match['team_home'],
        'team_away'      => $_POST['team_away'] ?? $match['team_away'],
        'match_datetime' => $_POST['match_datetime'] ?? $match['match_datetime'],
        'server_id'      => $_POST['server_id'] ?? $match['server_id'],
    ]);
    // Use posted streams for re-display
    $existingStreams = [];
    foreach (($_POST['streams'] ?? []) as $s) {
        $existingStreams[] = [
            'channel_name' => $s['channel_name'] ?? '',
            'iframe_url'   => $s['iframe_url']   ?? '',
            'lang'         => $s['lang']          ?? '',
        ];
    }
}

// Format datetime-local value (strip seconds if needed)
$datetimeValue = date('Y-m-d\TH:i', strtotime($match['match_datetime']));

require __DIR__ . '/includes/admin-header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="admin-card">
  <h2 class="card-title"><i class="fas fa-edit"></i> Edit Match #<?= (int)$match['id'] ?></h2>

  <form method="POST" action="/admin/match-edit?id=<?= (int)$match['id'] ?>">
    <div class="form-row">
      <div class="form-group">
        <label for="title">Title *</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($match['title'], ENT_QUOTES, 'UTF-8') ?>"
               required class="form-control">
      </div>
      <div class="form-group">
        <label for="league">League</label>
        <input type="text" id="league" name="league"
               value="<?= htmlspecialchars($match['league'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="form-control">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="team_home">Team Home</label>
        <input type="text" id="team_home" name="team_home"
               value="<?= htmlspecialchars($match['team_home'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="form-control">
      </div>
      <div class="form-group">
        <label for="team_away">Team Away</label>
        <input type="text" id="team_away" name="team_away"
               value="<?= htmlspecialchars($match['team_away'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="form-control">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category" class="form-control">
          <option value="">-- None --</option>
          <?php foreach (['Football', 'Basketball', 'Baseball', 'Tennis', 'MMA', 'Rugby', 'Hockey', 'Other'] as $c): ?>
            <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
              <?= ($match['category'] ?? '') === $c ? 'selected' : '' ?>>
              <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="server_id">Server *</label>
        <select id="server_id" name="server_id" required class="form-control">
          <option value="">-- Select Server --</option>
          <?php foreach ($servers as $s): ?>
            <option value="<?= (int)$s['id'] ?>"
              <?= (int)$match['server_id'] === (int)$s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="match_datetime">Match Date / Time (UTC) *</label>
      <input type="datetime-local" id="match_datetime" name="match_datetime"
             value="<?= htmlspecialchars($datetimeValue, ENT_QUOTES, 'UTF-8') ?>"
             required class="form-control">
    </div>

    <h3 style="margin-top:1.5rem;">
      Streams
      <button type="button" class="btn btn-sm btn-secondary" id="add-stream-btn">
        <i class="fas fa-plus"></i> Add Stream
      </button>
    </h3>
    <div id="streams-container">
      <?php if (empty($existingStreams)): ?>
        <div class="stream-row" style="display:flex;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
          <input type="text" name="streams[0][channel_name]" placeholder="Channel Name" class="form-control">
          <input type="text" name="streams[0][iframe_url]"   placeholder="Iframe URL *" class="form-control" style="flex:2">
          <input type="text" name="streams[0][lang]"         placeholder="Lang (e.g. en)" class="form-control" style="max-width:90px">
        </div>
      <?php else: ?>
        <?php foreach ($existingStreams as $idx => $st): ?>
          <div class="stream-row" style="display:flex;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
            <input type="text" name="streams[<?= $idx ?>][channel_name]"
                   value="<?= htmlspecialchars($st['channel_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Channel Name" class="form-control">
            <input type="text" name="streams[<?= $idx ?>][iframe_url]"
                   value="<?= htmlspecialchars($st['iframe_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Iframe URL *" class="form-control" style="flex:2">
            <input type="text" name="streams[<?= $idx ?>][lang]"
                   value="<?= htmlspecialchars($st['lang'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Lang" class="form-control" style="max-width:90px">
            <?php if ($idx > 0): ?>
              <button type="button" class="btn btn-sm btn-danger remove-stream"><i class="fas fa-times"></i></button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="margin-top:1.5rem; display:flex; gap:1rem; align-items:center;">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
      <a href="/admin/matches" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
(function () {
    let streamCount = <?= max(count($existingStreams), 1) ?>;

    document.getElementById('add-stream-btn').addEventListener('click', function () {
        const container = document.getElementById('streams-container');
        const row = document.createElement('div');
        row.className = 'stream-row';
        row.style.cssText = 'display:flex;gap:0.5rem;margin-bottom:0.5rem;align-items:center;';
        row.innerHTML =
            '<input type="text" name="streams[' + streamCount + '][channel_name]" placeholder="Channel Name" class="form-control">' +
            '<input type="text" name="streams[' + streamCount + '][iframe_url]" placeholder="Iframe URL *" class="form-control" style="flex:2">' +
            '<input type="text" name="streams[' + streamCount + '][lang]" placeholder="Lang" class="form-control" style="max-width:90px">' +
            '<button type="button" class="btn btn-sm btn-danger remove-stream"><i class="fas fa-times"></i></button>';
        container.appendChild(row);
        streamCount++;
    });

    document.getElementById('streams-container').addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-stream');
        if (btn) {
            btn.closest('.stream-row').remove();
        }
    });
}());
</script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
