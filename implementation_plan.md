# Live Sports Streaming Website — Full Implementation Plan

## Goal

Build a **PHP + MySQL** sports streaming website on a dedicated server, **laser-focused on maximizing Google AdExchange ad revenue**. Three JSON data sources feed matches into a MySQL database via an admin panel. Every page is engineered to maximize ad fill rate, viewability, and eCPM.

---

## User Review Required

> [!IMPORTANT]
> **Domain Name**: What domain will this site run on? Needed for ad tags, meta tags, and CORS.

> [!IMPORTANT]
> **Google Ad Manager Network Code**: The CSV shows network ID `23250651813` and parent `23249651246`. Confirm these are correct for GPT tag generation.

> [!WARNING]
> **S1 Data is Currently Empty**: The live S1 JSON (`rereyano_data.json`) currently has `"events": []` (0 events). The `player_streams` section has 4 channels but no match events. The import will work when events populate, but initially only S2 and S3 will yield matches.

> [!IMPORTANT]
> **Admin Credentials**: What username/password do you want for the admin login? I'll hash it in the DB seed.

---

## Technology Stack

| Layer | Choice | Why |
|-------|--------|-----|
| Backend | **PHP 8.x** (vanilla, no framework) | Fast, direct, your server supports it, no build step |
| Database | **MySQL 8.x** | Dedicated server, full SQL support, good for analytics queries |
| Frontend | **Vanilla HTML/CSS/JS** | No JS framework overhead = faster page loads = better ad viewability |
| Ads | **Google Publisher Tag (GPT)** via `googletag` | Official AdExchange integration, maximizes fill |
| Charts | **Chart.js 4.x CDN** | Admin analytics charts |
| Icons | **Font Awesome 6 CDN** | Share buttons, nav icons |

---

## Ad Units Mapping (from CSV)

### Google AdExchange Units (Network: `23250651813`)

| Ad Unit ID | Code Name | Sizes | Placement |
|-----------|-----------|-------|-----------|
| `23341935627` | `homepage_footer_banner` | 300×250, 320×50, 728×90 | Homepage — bottom of match list |
| `23342996723` | `homepage_header_banner` | 320×50, 320×100, 728×90, 970×90 | Homepage — top of page, below nav |
| `23341935360` | `homepage_infeed_banner` | 300×250, 320×100, 336×280 | Homepage — between every 4th match card |
| `23341936095` | `match_above_player_banner` | 320×50, 728×90, 970×90 | Match page — directly above the player |
| `23341936323` | `match_below_player_banner` | 300×250, 336×280, 728×90 | Match page — directly below the player |
| `23341930107` | `match_footer_banner` | 300×250, 320×50, 728×90 | Match page — page footer area |
| `23342575903` | `match_header_banner` | 320×50, 320×100, 728×90 | Match page — top of page, below nav |
| `23342576122` | `match_sidebar_banner` | 160×600, 300×250, 300×600 | Match page — right sidebar (sticky) |

### Adsterra Popup (on EVERY page)
```html
<script src="https://widthwidowzoology.com/99/19/f6/9919f61cfc44e5526b3b8d954079e2fd.js"></script>
```

---

## GPT (Google Publisher Tag) Integration Strategy

> [!IMPORTANT]
> This is the core revenue engine. Every page loads GPT and defines ad slots.

### How GPT Works (for max fill rate)

```html
<!-- HEAD: Load GPT library -->
<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
<script>
  window.googletag = window.googletag || {cmd: []};
</script>
```

### Ad Slot Definition Pattern
```javascript
googletag.cmd.push(function() {
    // Define each ad slot with ALL supported sizes for responsive fill
    googletag.defineSlot(
        '/23249651246/homepage_header_banner',  // /networkId/adUnitCode
        [[320,50],[320,100],[728,90],[970,90]],  // all sizes from CSV
        'div-gpt-homepage-header'                // div ID on page
    ).addService(googletag.pubads());

    // Enable single-request mode (loads all ads in one call = faster fill)
    googletag.pubads().enableSingleRequest();

    // Enable lazy loading for below-fold ads
    googletag.pubads().enableLazyLoad({
        fetchMarginPercent: 200,   // fetch 200% before viewport
        renderMarginPercent: 100,  // render 100% before viewport
        mobileScaling: 2.0         // more aggressive on mobile
    });

    // Collapse empty divs (don't show blank space if no fill)
    googletag.pubads().collapseEmptyDivs();

    googletag.enableServices();
});
```

### Ad Container HTML Pattern
```html
<div id="div-gpt-homepage-header" class="ad-container ad-header">
    <script>
        googletag.cmd.push(function() {
            googletag.display('div-gpt-homepage-header');
        });
    </script>
</div>
```

### Key Strategies to Maximize Fill & eCPM

1. **Single Request Architecture (SRA)**: All ad slots on a page fetched in one HTTP request
2. **Lazy Loading**: Below-fold ads loaded as user scrolls (saves bandwidth, improves viewability score)
3. **Collapse Empty Divs**: If an ad doesn't fill, div collapses so page doesn't look broken
4. **Size Mapping** for responsive: Use `googletag.sizeMapping()` to serve appropriate sizes by viewport
5. **Refresh on long sessions**: Auto-refresh ads every 60s for users watching streams (more impressions)
6. **Content around player**: Add match info, related matches, league table snippets around the player to increase dwell time and ad viewability

---

## Database Schema (MySQL)

```sql
-- ============================================
-- DATABASE SCHEMA
-- ============================================

CREATE DATABASE IF NOT EXISTS live_sports CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE live_sports;

-- 1. SERVERS TABLE
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,              -- "Server 1", "Server 2", "Server 3"
    code VARCHAR(10) NOT NULL UNIQUE,       -- "s1", "s2", "s3"
    json_url VARCHAR(500) NOT NULL,         -- source JSON URL
    timezone VARCHAR(30) NOT NULL,          -- "CET", "UTC", "UNIX"
    embed_pattern VARCHAR(500) NULL,        -- URL pattern for S1, NULL for S2/S3
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed servers
INSERT INTO servers (name, code, json_url, timezone, embed_pattern) VALUES
('Server 1', 's1', 'https://raw.githubusercontent.com/albinchristo04/arda/refs/heads/main/rereyano_data.json', 'CET', 'https://cartelive.club/player/{channelId}/1'),
('Server 2', 's2', 'https://raw.githubusercontent.com/albinchristo04/mayiru/refs/heads/main/sports_events.json', 'UTC', NULL),
('Server 3', 's3', 'https://raw.githubusercontent.com/albinchristo04/ptv/refs/heads/main/events.json', 'UNIX', NULL);

-- 2. MATCHES TABLE
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,            -- "Los Angeles Dodgers vs Cleveland Guardians"
    slug VARCHAR(350) NOT NULL UNIQUE,      -- URL-friendly slug
    league VARCHAR(150) NULL,               -- "MLB", "LaLiga", "Premier League"
    category VARCHAR(100) NULL,             -- "Football", "Basketball", "Baseball"
    team_home VARCHAR(150) NULL,
    team_away VARCHAR(150) NULL,
    match_datetime DATETIME NOT NULL,       -- normalized to UTC
    display_datetime DATETIME NULL,         -- original timezone display
    country VARCHAR(100) NULL,
    poster_url VARCHAR(500) NULL,           -- from S3 (has posters)
    viewers VARCHAR(20) NULL,               -- from S3
    server_id INT NOT NULL,
    fingerprint VARCHAR(32) NOT NULL,       -- MD5 for dedup
    is_featured TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,               -- soft delete
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_match_datetime (match_datetime),
    INDEX idx_server_id (server_id),
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_slug (slug),
    INDEX idx_deleted (deleted_at),
    INDEX idx_league (league),
    FOREIGN KEY (server_id) REFERENCES servers(id)
);

-- 3. MATCH_STREAMS TABLE
CREATE TABLE match_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    channel_name VARCHAR(100) NULL,         -- "HD 1", "Sport TV", channel label
    iframe_url VARCHAR(500) NOT NULL,       -- the actual embed URL
    stream_type ENUM('iframe', 'm3u8') DEFAULT 'iframe',
    lang VARCHAR(10) NULL,                  -- "fr", "us", "en"
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_match_id (match_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

-- 4. MATCH_VIEWS TABLE (survives match deletion)
CREATE TABLE match_views (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NULL,                      -- NULL if match deleted
    match_title VARCHAR(300) NOT NULL,      -- denormalized for survival
    match_slug VARCHAR(350) NOT NULL,
    server_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    referer VARCHAR(500) NULL,
    country_code VARCHAR(5) NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_viewed_at (viewed_at),
    INDEX idx_match_id (match_id),
    INDEX idx_server_id (server_id),
    INDEX idx_date (viewed_at)
);

-- 5. AD_SLOTS TABLE (manage ad units from admin)
CREATE TABLE ad_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,             -- "homepage_header_banner"
    ad_unit_path VARCHAR(200) NOT NULL,     -- "/23249651246/homepage_header_banner"
    sizes JSON NOT NULL,                    -- [[320,50],[728,90],...]
    page_type ENUM('homepage', 'match', 'all') NOT NULL,
    position VARCHAR(50) NOT NULL,          -- "header", "above_player", "sidebar", etc.
    is_active TINYINT(1) DEFAULT 1,
    div_id VARCHAR(100) NOT NULL,           -- "div-gpt-homepage-header"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed ad slots from CSV
INSERT INTO ad_slots (name, ad_unit_path, sizes, page_type, position, div_id) VALUES
('homepage_header_banner',  '/23249651246/homepage_header_banner',  '[[320,50],[320,100],[728,90],[970,90]]',  'homepage', 'header',       'div-gpt-hp-header'),
('homepage_infeed_banner',  '/23249651246/homepage_infeed_banner',  '[[300,250],[320,100],[336,280]]',         'homepage', 'infeed',       'div-gpt-hp-infeed'),
('homepage_footer_banner',  '/23249651246/homepage_footer_banner',  '[[300,250],[320,50],[728,90]]',           'homepage', 'footer',       'div-gpt-hp-footer'),
('match_header_banner',     '/23249651246/match_header_banner',     '[[320,50],[320,100],[728,90]]',           'match',    'header',       'div-gpt-match-header'),
('match_above_player_banner','/23249651246/match_above_player_banner','[[320,50],[728,90],[970,90]]',          'match',    'above_player', 'div-gpt-match-above'),
('match_below_player_banner','/23249651246/match_below_player_banner','[[300,250],[336,280],[728,90]]',        'match',    'below_player', 'div-gpt-match-below'),
('match_sidebar_banner',    '/23249651246/match_sidebar_banner',    '[[160,600],[300,250],[300,600]]',         'match',    'sidebar',      'div-gpt-match-sidebar'),
('match_footer_banner',     '/23249651246/match_footer_banner',     '[[300,250],[320,50],[728,90]]',           'match',    'footer',       'div-gpt-match-footer');

-- 6. USERS TABLE (admin auth)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,    -- password_hash() output
    role ENUM('admin', 'editor') DEFAULT 'admin',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. SETTINGS TABLE (key-value store)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Live Sports Streaming'),
('adsterra_popup_script', '<script src="https://widthwidowzoology.com/99/19/f6/9919f61cfc44e5526b3b8d954079e2fd.js"></script>'),
('ad_refresh_interval', '60'),
('gpt_network_id', '23249651246');
```

---

## File Structure

```
/var/www/html/                          (or your web root)
│
├── index.php                           # Homepage — match listing + ads
├── match.php                           # Match page — player + ads + content
├── .htaccess                           # Pretty URLs, security headers
│
├── config/
│   └── database.php                    # DB connection (PDO), constants
│
├── includes/
│   ├── header.php                      # HTML head, GPT script, nav
│   ├── footer.php                      # Footer, Adsterra popup, closing tags
│   ├── ads.php                         # GPT slot definitions, ad render functions
│   ├── functions.php                   # Utility functions (slugify, time format, etc.)
│   └── auth.php                        # Session check, login guard
│
├── api/
│   └── track-view.php                  # POST endpoint to log match_views (AJAX)
│
├── admin/
│   ├── index.php                       # Redirect to dashboard
│   ├── login.php                       # Admin login form
│   ├── logout.php                      # Destroy session
│   ├── dashboard.php                   # Stats cards
│   ├── import.php                      # Import matches (fetch + preview + store)
│   ├── import-fetch.php                # AJAX: fetch from JSON source
│   ├── import-store.php                # POST: store selected matches
│   ├── matches.php                     # Manage matches list
│   ├── match-create.php                # Create new match form
│   ├── match-edit.php                  # Edit match form
│   ├── match-delete.php                # Soft delete handler
│   ├── match-restore.php               # Restore soft-deleted match
│   ├── analytics.php                   # Analytics dashboard
│   └── includes/
│       ├── admin-header.php            # Dark sidebar layout start
│       └── admin-footer.php            # Layout end + scripts
│
├── assets/
│   ├── css/
│   │   ├── style.css                   # Public site styles
│   │   └── admin.css                   # Admin panel styles
│   └── js/
│       ├── ads.js                      # GPT init, refresh logic, size mapping
│       ├── share.js                    # Share buttons + copy link
│       ├── admin.js                    # Admin panel JS
│       └── import.js                   # Import page JS (fetch preview, select, store)
│
├── importers/
│   ├── ImporterBase.php                # Abstract base class
│   ├── S1Importer.php                  # Parse S1 (CET events array)
│   ├── S2Importer.php                  # Parse S2 (UTC day-named events)
│   └── S3Importer.php                  # Parse S3 (Unix timestamps, has posters)
│
└── sql/
    └── schema.sql                      # Full DB schema (copy-paste to run)
```

---

## Page-by-Page Breakdown

### Homepage (`index.php`)

**Layout (top to bottom):**

```
┌─────────────────────────────────────────────────────────────┐
│  NAV BAR (site name, minimal links)                        │
├─────────────────────────────────────────────────────────────┤
│  🔶 AD: homepage_header_banner (728×90 desktop / 320×50 mob)│
├─────────────────────────────────────────────────────────────┤
│  "📺 Live & Upcoming Matches"  (H1)                        │
│  Filter tabs: All | Football | Basketball | Baseball | ...  │
├─────────────────────────────────────────────────────────────┤
│  MATCH CARD 1                                               │
│  MATCH CARD 2                                               │
│  MATCH CARD 3                                               │
│  MATCH CARD 4                                               │
├─────────────────────────────────────────────────────────────┤
│  🔶 AD: homepage_infeed_banner (300×250 / 336×280)          │
├─────────────────────────────────────────────────────────────┤
│  MATCH CARD 5                                               │
│  MATCH CARD 6                                               │
│  MATCH CARD 7                                               │
│  MATCH CARD 8                                               │
├─────────────────────────────────────────────────────────────┤
│  🔶 AD: homepage_infeed_banner #2  (repeat infeed slot)     │
├─────────────────────────────────────────────────────────────┤
│  ... more matches ...                                       │
├─────────────────────────────────────────────────────────────┤
│  📝 CONTENT BLOCK: "About Live Sports Streaming"           │
│  (200-300 words of keyword-rich content for eCPM boost)    │
├─────────────────────────────────────────────────────────────┤
│  🔶 AD: homepage_footer_banner (728×90 / 300×250)           │
├─────────────────────────────────────────────────────────────┤
│  FOOTER + Adsterra popup script                             │
└─────────────────────────────────────────────────────────────┘
```

**Match Card HTML:**
```html
<a href="/match/manchester-city-vs-liverpool-12345" class="match-card">
    <div class="match-time">
        <span class="live-dot"></span> 15:00 UTC
    </div>
    <div class="match-teams">
        <span>Manchester City</span>
        <span class="vs">vs</span>
        <span>Liverpool</span>
    </div>
    <div class="match-meta">
        <span class="league">Premier League</span>
        <span class="servers">3 streams</span>
    </div>
</a>
```

**Content Block** (below match list, for eCPM boost):
- ~250 words about live sports streaming
- Mention popular leagues, how to watch, schedule info
- This gives Google's ad system page context = higher eCPM

---

### Match Page (`match.php?slug=xxx`)

**This is the most important page for revenue.** User spends the most time here.

**Layout:**

```
┌───────────────────────────────────────────────┬──────────────┐
│  NAV BAR                                      │              │
├───────────────────────────────────────────────┤              │
│  🔶 AD: match_header_banner (728×90)           │              │
├───────────────────────────────────────────────┤   SIDEBAR    │
│                                               │              │
│  MATCH TITLE (H1)                             │  🔶 AD:       │
│  League • Date • Time                         │  sidebar     │
│                                               │  (300×600)   │
├───────────────────────────────────────────────┤  STICKY      │
│  🔶 AD: match_above_player_banner (728×90)     │              │
├───────────────────────────────────────────────┤              │
│                                               │              │
│  ┌───────────────────────────────────────┐    │              │
│  │                                       │    │              │
│  │         VIDEO PLAYER (iframe)         │    │              │
│  │         16:9 aspect ratio             │    │              │
│  │                                       │    │              │
│  └───────────────────────────────────────┘    │              │
│                                               │              │
│  SERVER TABS: [Server 1] [Server 2] [Server 3]│              │
│  Stream buttons within selected server tab    │              │
│                                               │              │
├───────────────────────────────────────────────┤              │
│  🔶 AD: match_below_player_banner (336×280)    │              │
├───────────────────────────────────────────────┤              │
│                                               │              │
│  📝 MATCH INFO CONTENT BLOCK:                  │              │
│  • Match preview / teams info (auto-generated)│              │
│  • "How to watch {team} vs {team} live"       │              │
│  • League standings snippet                   │              │
│  • Related upcoming matches (4 cards)         │              │
│                                               │              │
├───────────────────────────────────────────────┤              │
│  SHARE BUTTONS ROW                            │              │
│  [WhatsApp] [Telegram] [Twitter] [FB] [Copy]  │              │
├───────────────────────────────────────────────┤              │
│  🔶 AD: match_footer_banner (728×90)           │              │
├───────────────────────────────────────────────┤              │
│  FOOTER + Adsterra popup                      │              │
└───────────────────────────────────────────────┴──────────────┘

MOBILE: Sidebar collapses below player, sticky ad at bottom
```

**Content Around Player** (critical for eCPM):
1. **Match Info Box**: Auto-generated text — "{Team A} takes on {Team B} in {League} action on {Date}. Watch the full match live stream..."
2. **Related Matches**: 4 cards of other matches from same league/day
3. **"How to Watch" section**: 2-3 sentences about the match
4. This surrounding content gives Google's crawler context = better ad targeting = higher CPMs

**Server Tabs Logic:**
- Each match can have streams from multiple servers (S1, S2, S3)
- Show tabs only for servers that have streams for this match
- First available stream auto-loads in iframe
- Clicking a stream button swaps the iframe src (no page reload)

**View Tracking:**
- On page load, fire AJAX POST to `/api/track-view.php`
- Record: match_id, match_title (denormalized), server_id, IP, user_agent, referrer

**Ad Refresh (for long viewing sessions):**
```javascript
// Every 60 seconds, refresh visible ads (while player is active)
setInterval(function() {
    googletag.cmd.push(function() {
        googletag.pubads().refresh();
    });
}, 60000);
```

---

### Share Buttons

```html
<div class="share-row">
    <h3>Share this Match</h3>
    <a href="https://wa.me/?text=..." target="_blank" rel="noopener noreferrer"
       class="share-btn share-whatsapp">
        <i class="fab fa-whatsapp"></i> WhatsApp
    </a>
    <a href="https://t.me/share/url?url=...&text=..." target="_blank" rel="noopener noreferrer"
       class="share-btn share-telegram">
        <i class="fab fa-telegram"></i> Telegram
    </a>
    <a href="https://twitter.com/intent/tweet?url=...&text=..." target="_blank" rel="noopener noreferrer"
       class="share-btn share-twitter">
        <i class="fab fa-x-twitter"></i> Twitter/X
    </a>
    <a href="https://www.facebook.com/sharer/sharer.php?u=..." target="_blank" rel="noopener noreferrer"
       class="share-btn share-facebook">
        <i class="fab fa-facebook"></i> Facebook
    </a>
    <button class="share-btn share-copy" onclick="copyLink(this)">
        <i class="fas fa-link"></i> Copy Link
    </button>
</div>
```

---

## Importer Logic (Per Server)

### S1 Importer (`S1Importer.php`)

```
Source: rereyano_data.json
Timezone: CET (UTC+2)
Events path: $.events[]

For each event:
  title = event.teams  (e.g. "Los Angeles Dodgers - Cleveland Guardians")
  league = event.league
  date = event.date (DD-MM-YYYY) → parse to Y-m-d
  time = event.time (HH:MM CET) → convert to UTC (subtract 2 hours)
  team_home = split teams by " - " → [0]
  team_away = split teams by " - " → [1]
  fingerprint = MD5(strtolower(title) + date)

  For each channel in event.channels:
    iframe_url = "https://cartelive.club/player/{channel.id}/1"
    lang = channel.lang
    channel_name = "Server 1 - {lang.toUpper()}"
```

### S2 Importer (`S2Importer.php`)

```
Source: sports_events.json
Timezone: UTC
Events path: $.events.{DAYNAME}[]

Critical: Day names (THURSDAY, FRIDAY, etc.) → resolve to actual dates
  - Use last_updated from JSON to determine the current week
  - Map day names to dates relative to that week

For each event:
  title = event.event (e.g. "Palmeiras x Grêmio")
  league = extract from title if contains "NBA:", "UFC", "Tennis -", etc.
         else = NULL (no league field in S2)
  time = event.time (HH:MM UTC) → already UTC
  team_home = split by " x " → [0]  (S2 uses " x " separator)
  team_away = split by " x " → [1]
  fingerprint = MD5(strtolower(title) + resolved_date)

  For each stream URL in event.streams:
    iframe_url = stream URL directly
    channel_name = extract from URL path (e.g. "hd1", "sporttv1", "br3")
```

### S3 Importer (`S3Importer.php`)

```
Source: events.json
Timezone: Unix timestamps
Events path: $.events.streams[].streams[]

For each category group → for each stream:
  title = stream.name (e.g. "Brazil vs. Croatia")
  league = stream.tag (e.g. "Ligue 1", "LaLiga")
  category = stream.category_name (e.g. "Football")
  poster = stream.poster
  viewers = stream.viewers
  starts_at = stream.starts_at → convert to DateTime UTC
  ends_at = stream.ends_at → used for "is live" check
  team_home = split by " vs. " → [0]
  team_away = split by " vs. " → [1]
  fingerprint = MD5(strtolower(title) + date_from_timestamp)

  iframe_url = stream.iframe directly
  channel_name = "Server 3"
```

---

## Admin Panel

### Admin Layout (`admin/includes/admin-header.php`)

```
┌──────────────┬──────────────────────────────────────────────┐
│              │  TOP BAR: "Admin Panel"    Welcome, admin ▼  │
│   SIDEBAR    ├──────────────────────────────────────────────┤
│   (dark)     │                                              │
│              │                                              │
│  📊 Dashboard│           MAIN CONTENT AREA                  │
│  📥 Import   │                                              │
│  📋 Matches  │    (each admin page renders here)            │
│  📈 Analytics│                                              │
│  🚪 Logout   │                                              │
│              │                                              │
│              │                                              │
└──────────────┴──────────────────────────────────────────────┘
```

### 6A. Dashboard (`/admin/dashboard.php`)

**Stats cards (single SQL queries each):**

| Card | Query |
|------|-------|
| Total Matches | `SELECT COUNT(*) FROM matches WHERE deleted_at IS NULL` |
| Views Today | `SELECT COUNT(*) FROM match_views WHERE DATE(viewed_at) = CURDATE()` |
| Per Server | `SELECT s.name, COUNT(m.id) FROM servers s LEFT JOIN matches m ON m.server_id = s.id WHERE m.deleted_at IS NULL GROUP BY s.id` |
| Active Streams | `SELECT COUNT(*) FROM matches WHERE deleted_at IS NULL AND match_datetime BETWEEN NOW() - INTERVAL 3 HOUR AND NOW() + INTERVAL 1 HOUR` |

### 6B. Import (`/admin/import.php`)

**Step 1: Select Server**
- Dropdown populated from `servers` table
- "Fetch Matches" button → AJAX POST to `import-fetch.php`

**Step 2: Preview (AJAX response)**
- `import-fetch.php` fetches the JSON from the selected server's URL
- Runs the appropriate importer (S1/S2/S3) to parse events
- For each parsed event, checks fingerprint against DB
- Returns JSON: `{ matches: [{ title, league, datetime, streams_count, status: "new"|"exists", fingerprint }] }`
- JS renders preview table with checkboxes

**Step 3: Import Selected**
- User checks desired matches → POST to `import-store.php`
- Server inserts into `matches` + `match_streams`
- Returns: `{ imported: X, skipped: Y }`

### 6C. Manage Matches (`/admin/matches.php`)

**Filter bar:**
- Server dropdown, League dropdown, Title search, Date picker
- All use GET params for bookmarkable/refreshable URLs

**Table (20 per page):**
- Columns: ID | Title | League | Server | DateTime | Streams | Status | Actions
- Soft-deleted rows: muted red background, show `[Restore]` instead of `[Delete]`
- Actions: `[Edit]` → `match-edit.php?id=X`, `[Delete]` → POST to `match-delete.php`

**Create Match (`match-create.php`):**
- Form fields as specified in requirements
- Dynamic stream repeater (JS: add/remove rows)
- POST → validate → INSERT into `matches` + `match_streams`

**Edit Match (`match-edit.php?id=X`):**
- Pre-filled form, edit streams (add/remove/modify iframe URLs)
- POST → UPDATE `matches`, DELETE old streams, INSERT new streams

### 6D. Analytics (`/admin/analytics.php`)

**Stats cards:**

| Card | Query |
|------|-------|
| Today | `SELECT COUNT(*) FROM match_views WHERE DATE(viewed_at) = CURDATE()` |
| Yesterday | `WHERE DATE(viewed_at) = CURDATE() - INTERVAL 1 DAY` |
| Last 7 Days | `WHERE viewed_at >= CURDATE() - INTERVAL 7 DAY` |
| All Time | `SELECT COUNT(*) FROM match_views` |

**Server Breakdown Table:**
```sql
SELECT s.name,
    SUM(CASE WHEN DATE(mv.viewed_at) = CURDATE() THEN 1 ELSE 0 END) as today,
    SUM(CASE WHEN DATE(mv.viewed_at) = CURDATE() - INTERVAL 1 DAY THEN 1 ELSE 0 END) as yesterday,
    SUM(CASE WHEN mv.viewed_at >= CURDATE() - INTERVAL 7 DAY THEN 1 ELSE 0 END) as week,
    COUNT(*) as total
FROM servers s
LEFT JOIN match_views mv ON mv.server_id = s.id
GROUP BY s.id
```

**Top 10 Matches (last 7 days):**
```sql
SELECT match_title, server_id,
    SUM(CASE WHEN viewed_at >= CURDATE() - INTERVAL 7 DAY THEN 1 ELSE 0 END) as views_7d,
    COUNT(*) as total_views,
    MAX(CASE WHEN match_id IS NULL THEN 1 ELSE 0 END) as is_deleted
FROM match_views
GROUP BY match_title, server_id
ORDER BY views_7d DESC
LIMIT 10
```

**Chart.js Bar Chart:**
```sql
SELECT DATE(viewed_at) as day, server_id, COUNT(*) as views
FROM match_views
WHERE viewed_at >= CURDATE() - INTERVAL 7 DAY
GROUP BY day, server_id
ORDER BY day
```
- Two datasets: Server 1 (blue), Server 2 (red), Server 3 (green)

---

## .htaccess (URL Routing)

```apache
RewriteEngine On

# Pretty URLs for match pages
RewriteRule ^match/([a-z0-9\-]+)$ match.php?slug=$1 [L,QSA]

# Admin routes
RewriteRule ^admin/?$ admin/dashboard.php [L]
RewriteRule ^admin/dashboard$ admin/dashboard.php [L]
RewriteRule ^admin/import$ admin/import.php [L]
RewriteRule ^admin/import/fetch$ admin/import-fetch.php [L]
RewriteRule ^admin/import/store$ admin/import-store.php [L]
RewriteRule ^admin/matches$ admin/matches.php [L]
RewriteRule ^admin/matches/create$ admin/match-create.php [L]
RewriteRule ^admin/matches/([0-9]+)/edit$ admin/match-edit.php?id=$1 [L]
RewriteRule ^admin/matches/([0-9]+)/delete$ admin/match-delete.php?id=$1 [L]
RewriteRule ^admin/matches/([0-9]+)/restore$ admin/match-restore.php?id=$1 [L]
RewriteRule ^admin/analytics$ admin/analytics.php [L]
RewriteRule ^admin/login$ admin/login.php [L]
RewriteRule ^admin/logout$ admin/logout.php [L]

# API
RewriteRule ^api/track-view$ api/track-view.php [L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
```

---

## Mobile Sticky Ad

```css
/* Mobile sticky ad at bottom */
@media (max-width: 768px) {
    body {
        padding-bottom: 60px; /* prevent content from hiding under sticky ad */
    }
    .mobile-sticky-ad {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 9999;
        background: #000;
        text-align: center;
        padding: 5px 0;
    }
}
```

The mobile sticky ad will use the `homepage_footer_banner` or `match_footer_banner` ad unit (320×50 size) rendered inside a fixed-bottom container.

---

## Ad Revenue Optimization Checklist

| Strategy | Implementation |
|----------|---------------|
| ✅ GPT Single Request | `enableSingleRequest()` — one HTTP call for all ads |
| ✅ Lazy Load | `enableLazyLoad()` — below-fold ads load on scroll |
| ✅ Collapse Empty | `collapseEmptyDivs()` — no blank ad spaces |
| ✅ Size Mapping | Responsive sizes per viewport (mobile vs desktop) |
| ✅ Auto Refresh | 60-second ad refresh on match pages (active viewing) |
| ✅ Content Keywords | Contextual text around player for better ad targeting |
| ✅ Multiple Ad Slots | 4 ads on match page, 3+ on homepage |
| ✅ Sidebar Sticky | 300×600 sidebar ad stays visible while scrolling |
| ✅ Infeed Ads | Ads between match listings (native feel = higher CTR) |
| ✅ Mobile Sticky | 320×50 fixed ad at bottom on mobile |
| ✅ Adsterra Popup | Fallback/bonus revenue on every page |
| ✅ View Tracking | Analytics to optimize ad placement over time |

---

## Open Questions

> [!IMPORTANT]
> 1. **What is the site domain?** Needed for ad tags, Open Graph, share URLs, and canonical links.

> [!IMPORTANT]
> 2. **Admin credentials** — what username/password do you want seeded?

> [!NOTE]
> 3. **S1 data is empty today** — The S1 JSON has 0 events right now. Does it populate during match days only? The importer will handle it either way, but initially only S2 (~100+ events) and S3 (~50+ events) will yield data.

> [!NOTE]
> 4. **ads.txt** — Do you have an `ads.txt` file for your domain? Google requires it. If not, I'll generate one based on your Ad Manager network ID.

---

## Verification Plan

### After Building
1. **Schema test**: Run `schema.sql` on MySQL, verify all tables created
2. **Import test**: Import from S2 and S3 (S1 is empty), verify matches in DB
3. **Ad test**: Open homepage in browser, verify GPT loads and ad divs exist (check DevTools Network tab for `securepubads.g.doubleclick.net`)
4. **View tracking**: Click a match, verify `match_views` row created
5. **Admin flow**: Login → Dashboard → Import → Manage → Analytics full walkthrough
6. **Mobile test**: Verify sticky ad + body padding, responsive ad sizes
7. **Ad refresh**: Stay on match page 60+ seconds, verify ad refresh in Network tab
