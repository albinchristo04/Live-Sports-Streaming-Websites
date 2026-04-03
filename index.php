<?php
/**
 * index.php — Homepage
 * Lists live and upcoming matches with category filtering and pagination.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ads.php';

$pdo = getPDO();

// ── Inputs ────────────────────────────────────────────────────────────────────
$category = isset($_GET['category']) ? trim(strip_tags($_GET['category'])) : '';
$category = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');  // safe for output
$categoryParam = $category;  // used in SQL bind

$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page    = max(1, $page);
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ── Count total rows for pagination ──────────────────────────────────────────
$countSql = '
    SELECT COUNT(DISTINCT m.id) AS total
    FROM matches m
    JOIN servers s ON s.id = m.server_id
    WHERE m.deleted_at IS NULL
      AND (:category1 = \'\' OR m.category = :category2)
';
$countStmt = $pdo->prepare($countSql);
$countStmt->bindValue(':category1', $categoryParam, PDO::PARAM_STR);
$countStmt->bindValue(':category2', $categoryParam, PDO::PARAM_STR);
$countStmt->execute();
$totalRows  = (int)($countStmt->fetchColumn() ?: 0);
$totalPages = (int)ceil($totalRows / $perPage);

// ── Fetch matches ─────────────────────────────────────────────────────────────
$matchSql = '
    SELECT m.*,
           s.name AS server_name,
           s.code AS server_code,
           COUNT(ms.id) AS stream_count
    FROM matches m
    JOIN servers s ON s.id = m.server_id
    LEFT JOIN match_streams ms ON ms.match_id = m.id AND ms.is_active = 1
    WHERE m.deleted_at IS NULL
      AND (:category3 = \'\' OR m.category = :category4)
    GROUP BY m.id
    ORDER BY m.match_datetime ASC
    LIMIT :limit OFFSET :offset
';
$matchStmt = $pdo->prepare($matchSql);
$matchStmt->bindValue(':category3', $categoryParam, PDO::PARAM_STR);
$matchStmt->bindValue(':category4', $categoryParam, PDO::PARAM_STR);
$matchStmt->bindValue(':limit',    $perPage,        PDO::PARAM_INT);
$matchStmt->bindValue(':offset',   $offset,         PDO::PARAM_INT);
$matchStmt->execute();
$matches = $matchStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch distinct categories ─────────────────────────────────────────────────
$catStmt = $pdo->query('
    SELECT DISTINCT category
    FROM matches
    WHERE deleted_at IS NULL
      AND category IS NOT NULL
    ORDER BY category
');
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Page meta ─────────────────────────────────────────────────────────────────
$pageTitle    = 'Live Sports Streaming';
$pageType     = 'homepage';
$canonicalUrl = SITE_DOMAIN;

require __DIR__ . '/includes/header.php';
?>

  <?= renderAdSlot('div-gpt-hp-header', '/23250651813/homepage_header_banner', [[320,50],[320,100],[728,90],[970,90]]) ?>

  <div class="container">
    <h1 class="page-title">&#128250; Live &amp; Upcoming Matches</h1>

    <!-- Category filter tabs -->
    <div class="filter-tabs">
      <a href="/" class="filter-tab <?= $category === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/?category=<?= urlencode($cat) ?>"
           class="filter-tab <?= $category === $cat ? 'active' : '' ?>">
          <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Match cards grid (infeed ad injected every 4 cards) -->
    <div class="matches-grid">
      <?php $cardCount = 0; foreach ($matches as $match): $cardCount++; ?>

        <a href="/match/<?= htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8') ?>" class="match-card">

          <div class="match-time">
            <?php if (isMatchLive($match['match_datetime'])): ?>
              <span class="live-dot"></span>
              <span class="live-label">LIVE</span>
            <?php else: ?>
              <?= htmlspecialchars(formatMatchTime($match['match_datetime']), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </div>

          <div class="match-teams">
            <span class="team"><?= htmlspecialchars($match['team_home'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="vs">vs</span>
            <span class="team"><?= htmlspecialchars($match['team_away'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></span>
          </div>

          <div class="match-meta">
            <?php if (!empty($match['league'])): ?>
              <span class="league-badge"><?= htmlspecialchars($match['league'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <span class="stream-count">
              <?= (int)$match['stream_count'] ?>
              stream<?= (int)$match['stream_count'] !== 1 ? 's' : '' ?>
            </span>
          </div>

          <?php if (!empty($match['poster_url'])): ?>
            <img src="<?= htmlspecialchars($match['poster_url'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($match['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 class="match-poster"
                 loading="lazy">
          <?php endif; ?>

        </a>

        <?php if ($cardCount % 4 === 0): ?>
        </div><!-- /matches-grid -->
        <?= renderAdSlot(
              'div-gpt-hp-infeed-' . ($cardCount / 4),
              '/23250651813/homepage_infeed_banner',
              [[300,250],[320,100],[336,280]]
            ) ?>
        <div class="matches-grid"><!-- reopen grid -->
        <?php endif; ?>

      <?php endforeach; ?>
    </div><!-- /matches-grid -->

    <!-- SEO / content block -->
    <div class="content-block">
      <h2>Watch Live Sports Online</h2>
      <p>Stream live football, basketball, baseball, tennis, MMA, and more from around the world.
         We aggregate live sports streams from multiple servers so you never miss a match.
         Whether it&rsquo;s Premier League, LaLiga, NBA, NFL, MLB, UFC, or international
         tournaments &mdash; find your match and start watching in HD quality with multiple
         stream options.</p>
      <p>All matches are listed with kickoff times in UTC. Use the category filters above to
         browse by sport. Each match page provides multiple server options for the best viewing
         experience. Streams are updated daily from our sources.</p>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?><?= $category !== '' ? '&amp;category=' . urlencode($category) : '' ?>"
           class="page-link <?= $i === $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <!-- Footer ad -->
    <?= renderAdSlot('div-gpt-hp-footer', '/23250651813/homepage_footer_banner', [[300,250],[320,50],[728,90]]) ?>

  </div><!-- /container -->

  <!-- Mobile sticky ad -->
  <div class="mobile-sticky-ad">
    <?= renderAdSlot('div-gpt-mobile-sticky', '/23250651813/homepage_footer_banner', [[320,50]]) ?>
  </div>

  <script src="/assets/js/ads.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
