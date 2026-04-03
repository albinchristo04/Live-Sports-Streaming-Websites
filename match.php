<?php
/**
 * match.php — Individual Match Page
 * Displays the video player, stream selector, related matches, and ads.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ads.php';

$pdo = getPDO();

// ── Slug ──────────────────────────────────────────────────────────────────────
$slug = isset($_GET['slug']) ? trim(strip_tags($_GET['slug'])) : '';
$slug = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

if ($slug === '') {
    header('Location: /');
    exit;
}

// ── Fetch match ───────────────────────────────────────────────────────────────
$matchStmt = $pdo->prepare('
    SELECT m.*, s.name AS server_name, s.code AS server_code, s.id AS server_id_val
    FROM matches m
    JOIN servers s ON s.id = m.server_id
    WHERE m.slug = :slug AND m.deleted_at IS NULL
    LIMIT 1
');
$matchStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
$matchStmt->execute();
$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    $pageTitle    = 'Match Not Found';
    $pageType     = 'match';
    $canonicalUrl = SITE_DOMAIN . '/match/' . $slug;
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="padding:4rem 1rem;text-align:center;">
      <h1>Match Not Found</h1>
      <p>The match you are looking for does not exist or has been removed.</p>
      <a href="/" class="btn-primary">Back to Home</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ── Fetch streams ─────────────────────────────────────────────────────────────
$streamsStmt = $pdo->prepare('
    SELECT *
    FROM match_streams
    WHERE match_id = :id AND is_active = 1
    ORDER BY sort_order ASC
');
$streamsStmt->bindValue(':id', (int)$match['id'], PDO::PARAM_INT);
$streamsStmt->execute();
$streams = $streamsStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch related matches ─────────────────────────────────────────────────────
$relatedStmt = $pdo->prepare('
    SELECT m.slug, m.title, m.team_home, m.team_away, m.match_datetime, m.league
    FROM matches m
    WHERE m.deleted_at IS NULL
      AND m.id != :id
      AND (m.league = :league OR DATE(m.match_datetime) = DATE(:datetime))
    ORDER BY ABS(TIMESTAMPDIFF(MINUTE, m.match_datetime, :datetime2)) ASC
    LIMIT 4
');
$relatedStmt->bindValue(':id',        (int)$match['id'],          PDO::PARAM_INT);
$relatedStmt->bindValue(':league',    (string)($match['league'] ?? ''), PDO::PARAM_STR);
$relatedStmt->bindValue(':datetime',  (string)$match['match_datetime'], PDO::PARAM_STR);
$relatedStmt->bindValue(':datetime2', (string)$match['match_datetime'], PDO::PARAM_STR);
$relatedStmt->execute();
$relatedMatches = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Group streams by server_id ────────────────────────────────────────────────
$streamsByServer = [];
foreach ($streams as $stream) {
    $sid = (int)$match['server_id'];
    if (!isset($streamsByServer[$sid])) {
        $streamsByServer[$sid] = [];
    }
    $streamsByServer[$sid][] = $stream;
}
$serverIds      = array_keys($streamsByServer);
$firstStreamUrl = !empty($streams) ? $streams[0]['iframe_url'] : '';

// S1/S2 iframes get sandboxed (blocks their popup ads); S3 must NOT be sandboxed
$useIframeSandbox = in_array($match['server_code'], ['s1', 's2'], true);
$iframeSandbox    = $useIframeSandbox
    ? 'sandbox="allow-scripts allow-same-origin allow-forms allow-presentation allow-orientation-lock"'
    : '';

// ── Auto-generated match info ─────────────────────────────────────────────────
$matchInfo = htmlspecialchars($match['team_home'] ?? 'TBD', ENT_QUOTES, 'UTF-8')
           . ' takes on '
           . htmlspecialchars($match['team_away'] ?? 'TBD', ENT_QUOTES, 'UTF-8')
           . (!empty($match['league'])
               ? ' in ' . htmlspecialchars($match['league'], ENT_QUOTES, 'UTF-8') . ' action'
               : '')
           . ' on ' . date('F j, Y', strtotime($match['match_datetime']))
           . '. Watch the full match live stream online for free with multiple server options.';

// ── Share URL ─────────────────────────────────────────────────────────────────
$shareUrl   = SITE_DOMAIN . '/match/' . htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8');
$shareTitle = htmlspecialchars($match['title'] ?? '', ENT_QUOTES, 'UTF-8');

// ── Page meta ─────────────────────────────────────────────────────────────────
$pageTitle    = $match['title'] ?? 'Live Match';
$pageType     = 'match';
$canonicalUrl = SITE_DOMAIN . '/match/' . $match['slug'];

require __DIR__ . '/includes/header.php';
?>

  <!-- Match header ad -->
  <?= renderAdSlot('div-gpt-match-header', '/23250651813/match_header_banner', [[320,50],[320,100],[728,90]]) ?>

  <div class="container">
    <div class="match-page-layout">

      <!-- ── Main column ─────────────────────────────────────────────────── -->
      <div class="match-main">

        <h1 class="match-title"><?= htmlspecialchars($match['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>

        <!-- Match meta -->
        <div class="match-meta-bar">
          <?php if (!empty($match['league'])): ?>
            <span class="league-badge"><?= htmlspecialchars($match['league'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <span class="match-server">
            <i class="fas fa-server"></i>
            <?= htmlspecialchars($match['server_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </span>
          <span class="match-time-label">
            <i class="fas fa-clock"></i>
            <?php if (isMatchLive($match['match_datetime'])): ?>
              <span class="live-dot"></span> <strong>LIVE NOW</strong>
            <?php else: ?>
              <?= htmlspecialchars(formatMatchTime($match['match_datetime']), ENT_QUOTES, 'UTF-8') ?>
              &mdash; <?= htmlspecialchars(getRelativeTime($match['match_datetime']), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </span>
        </div>

        <!-- Above-player ad -->
        <?= renderAdSlot('div-gpt-match-above', '/23250651813/match_above_player_banner', [[320,50],[728,90],[970,90]]) ?>

        <!-- Video player -->
        <div class="player-wrapper">
          <?php if ($firstStreamUrl !== ''): ?>
            <iframe id="match-player"
                    src="<?= htmlspecialchars($firstStreamUrl, ENT_QUOTES, 'UTF-8') ?>"
                    frameborder="0"
                    allowfullscreen
                    allow="autoplay; encrypted-media; picture-in-picture"
                    scrolling="no"
                    <?= $iframeSandbox ?>>
            </iframe>
          <?php else: ?>
            <div class="no-stream-placeholder">
              <p>No streams are currently available for this match. Please check back later.</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Server tabs (only shown when multiple servers exist) -->
        <?php if (count($streamsByServer) > 0): ?>
        <div class="server-tabs" id="server-tabs">
          <?php $tabIndex = 1; foreach ($serverIds as $sid): ?>
            <button class="server-tab <?= $tabIndex === 1 ? 'active' : '' ?>"
                    data-server="<?= (int)$sid ?>"
                    onclick="switchServer(<?= (int)$sid ?>, this)">
              Server <?= $tabIndex ?>
            </button>
          <?php $tabIndex++; endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Stream buttons -->
        <?php if (!empty($streams)): ?>
        <div class="stream-buttons" id="stream-buttons">
          <?php foreach ($streams as $idx => $stream): ?>
            <button class="stream-btn <?= $idx === 0 ? 'active' : '' ?>"
                    data-server="<?= (int)$match['server_id'] ?>"
                    data-url="<?= htmlspecialchars($stream['iframe_url'], ENT_QUOTES, 'UTF-8') ?>"
                    data-sandbox="<?= $useIframeSandbox ? '1' : '0' ?>"
                    onclick="loadStream(<?= htmlspecialchars(json_encode($stream['iframe_url']), ENT_QUOTES, 'UTF-8') ?>, this)">
              <i class="fas fa-play-circle"></i>
              <?= htmlspecialchars($stream['channel_name'] ?? ('Stream ' . ($idx + 1)), ENT_QUOTES, 'UTF-8') ?>
              <?php if (!empty($stream['lang'])): ?>
                <span class="quality-badge"><?= htmlspecialchars(strtoupper($stream['lang']), ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Below-player ad -->
        <?= renderAdSlot('div-gpt-match-below', '/23250651813/match_below_player_banner', [[300,250],[336,280],[728,90]]) ?>

        <!-- Auto-generated match info -->
        <div class="content-block match-info">
          <h2>About This Match</h2>
          <p><?= $matchInfo ?></p>
          <?php if (!empty($match['description'])): ?>
            <p><?= nl2br(htmlspecialchars($match['description'], ENT_QUOTES, 'UTF-8')) ?></p>
          <?php endif; ?>
        </div>

        <!-- Related matches -->
        <?php if (!empty($relatedMatches)): ?>
        <div class="related-matches">
          <h2>Related Matches</h2>
          <div class="related-grid">
            <?php foreach ($relatedMatches as $rel): ?>
              <a href="/match/<?= htmlspecialchars($rel['slug'], ENT_QUOTES, 'UTF-8') ?>"
                 class="match-card related-card">
                <div class="match-time">
                  <?php if (isMatchLive($rel['match_datetime'])): ?>
                    <span class="live-dot"></span> <span class="live-label">LIVE</span>
                  <?php else: ?>
                    <?= htmlspecialchars(formatMatchTime($rel['match_datetime']), ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </div>
                <div class="match-teams">
                  <span class="team"><?= htmlspecialchars($rel['team_home'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="vs">vs</span>
                  <span class="team"><?= htmlspecialchars($rel['team_away'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($rel['league'])): ?>
                  <div class="match-meta">
                    <span class="league-badge"><?= htmlspecialchars($rel['league'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Share buttons -->
        <div class="share-row">
          <span class="share-label"><i class="fas fa-share-alt"></i> Share:</span>
          <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($shareTitle) ?>"
             class="share-btn share-twitter" target="_blank" rel="noopener noreferrer"
             aria-label="Share on Twitter">
            <i class="fab fa-twitter"></i> Twitter
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>"
             class="share-btn share-facebook" target="_blank" rel="noopener noreferrer"
             aria-label="Share on Facebook">
            <i class="fab fa-facebook-f"></i> Facebook
          </a>
          <a href="https://api.whatsapp.com/send?text=<?= urlencode($shareTitle . ' ' . $shareUrl) ?>"
             class="share-btn share-whatsapp" target="_blank" rel="noopener noreferrer"
             aria-label="Share on WhatsApp">
            <i class="fab fa-whatsapp"></i> WhatsApp
          </a>
          <button class="share-btn share-copy"
                  onclick="navigator.clipboard && navigator.clipboard.writeText('<?= $shareUrl ?>').then(function(){this.textContent='Copied!';}.bind(this))"
                  aria-label="Copy link">
            <i class="fas fa-link"></i> Copy Link
          </button>
        </div>

        <!-- Match footer ad -->
        <?= renderAdSlot('div-gpt-match-footer', '/23250651813/match_footer_banner', [[300,250],[320,50],[728,90]]) ?>

      </div><!-- /match-main -->

      <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
      <aside class="match-sidebar">
        <div class="sidebar-ad-sticky">
          <?= renderAdSlot('div-gpt-match-sidebar', '/23250651813/match_sidebar_banner', [[160,600],[300,250],[300,600]]) ?>
        </div>
      </aside>

    </div><!-- /match-page-layout -->
  </div><!-- /container -->

  <script src="/assets/js/ads.js"></script>

  <script>
  // ── Stream / server switching ───────────────────────────────────────────────
  // useSandbox: set from PHP — true for S1/S2 (blocks iframe popups), false for S3
  var _useSandbox = <?= $useIframeSandbox ? 'true' : 'false' ?>;
  var _sandboxAttr = 'allow-scripts allow-same-origin allow-forms allow-presentation allow-orientation-lock';

  function loadStream(url, btn) {
    var player = document.getElementById('match-player');
    if (player) {
      player.src = url;
      // Apply or remove sandbox depending on server type
      if (_useSandbox) {
        player.setAttribute('sandbox', _sandboxAttr);
      } else {
        player.removeAttribute('sandbox');
      }
    }
    document.querySelectorAll('.stream-btn').forEach(function(b) {
      b.classList.remove('active');
    });
    if (btn) { btn.classList.add('active'); }
  }

  function switchServer(serverId, tab) {
    // Highlight active tab
    document.querySelectorAll('.server-tab').forEach(function(t) {
      t.classList.remove('active');
    });
    if (tab) { tab.classList.add('active'); }

    // Show only buttons belonging to this server
    var buttons = document.querySelectorAll('.stream-btn');
    var firstVisible = null;
    buttons.forEach(function(btn) {
      if (parseInt(btn.dataset.server, 10) === serverId) {
        btn.style.display = '';
        if (!firstVisible) { firstVisible = btn; }
      } else {
        btn.style.display = 'none';
        btn.classList.remove('active');
      }
    });

    // Auto-load first stream of the selected server
    if (firstVisible) {
      firstVisible.classList.add('active');
      loadStream(firstVisible.dataset.url, firstVisible);
    }
  }

  // ── View tracking ───────────────────────────────────────────────────────────
  (function() {
    try {
      fetch('/api/track-view', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({
          match_id  : <?= (int)$match['id'] ?>,
          match_slug: <?= json_encode($match['slug']) ?>,
          server_id : <?= (int)$match['server_id_val'] ?>
        })
      });
    } catch(e) { /* silently ignore */ }
  })();

  // ── Ad refresh every 60 s (match pages only) ────────────────────────────────
  var _adRefreshInterval = setInterval(function() {
    if (typeof googletag !== 'undefined') {
      googletag.cmd.push(function() { googletag.pubads().refresh(); });
    }
  }, 60000);
  </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
