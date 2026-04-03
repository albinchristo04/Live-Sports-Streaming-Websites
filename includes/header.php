<?php
// Expected variables from calling page:
// $pageTitle    (string) - page-specific title
// $pageType     (string) - 'homepage' or 'match'
// $canonicalUrl (string) - full canonical URL for this page

if (!isset($pageTitle))    $pageTitle    = defined('SITE_NAME') ? SITE_NAME : 'Live Sports Streaming';
if (!isset($pageType))     $pageType     = 'homepage';
if (!isset($canonicalUrl)) $canonicalUrl = (defined('SITE_DOMAIN') ? SITE_DOMAIN : 'https://news.evaulthub.com') . '/';

// Pull ad slots for this page type
$gptSlots = getGPTSlotDefinitions($pageType);

// Build JSON for inline GPT setup script
$slotsJson = json_encode($gptSlots, JSON_UNESCAPED_SLASHES);

$siteName    = defined('SITE_NAME')   ? SITE_NAME   : 'Live Sports Streaming';
$siteDomain  = defined('SITE_DOMAIN') ? SITE_DOMAIN : 'https://news.evaulthub.com';
$refreshInt  = defined('AD_REFRESH_INTERVAL') ? (int)AD_REFRESH_INTERVAL : 60;

$safeTitle    = htmlspecialchars($pageTitle,    ENT_QUOTES, 'UTF-8');
$safeCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
$safeSiteName  = htmlspecialchars($siteName,     ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $safeTitle ?> - <?= $safeSiteName ?></title>
  <meta name="description" content="Watch live sports streaming online. <?= $safeTitle ?> - free live stream coverage on <?= $safeSiteName ?>.">
  <link rel="canonical" href="<?= $safeCanonical ?>">

  <!-- Open Graph -->
  <meta property="og:type"        content="website">
  <meta property="og:title"       content="<?= $safeTitle ?> - <?= $safeSiteName ?>">
  <meta property="og:description" content="Watch <?= $safeTitle ?> live online. Free sports streaming coverage.">
  <meta property="og:url"         content="<?= $safeCanonical ?>">
  <meta property="og:site_name"   content="<?= $safeSiteName ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= $safeTitle ?> - <?= $safeSiteName ?>">
  <meta name="twitter:description" content="Watch <?= $safeTitle ?> live online. Free sports streaming coverage.">

  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

  <!-- Google Publisher Tag (async) -->
  <script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js" crossorigin="anonymous"></script>
  <script>window.googletag = window.googletag || {cmd: []};</script>

  <script>
  (function() {
    var slots = <?= $slotsJson ?>;
    var refreshInterval = <?= $refreshInt ?>;

    googletag.cmd.push(function() {
      var definedSlots = [];

      for (var i = 0; i < slots.length; i++) {
        var slot = slots[i];
        var gptSlot = googletag.defineSlot(slot.path, slot.sizes, slot.div_id);
        if (gptSlot) {
          gptSlot.addService(googletag.pubads());
          definedSlots.push(gptSlot);
        }
      }

      googletag.pubads().enableSingleRequest();

      googletag.pubads().enableLazyLoad({
        fetchMarginPercent:  200,
        renderMarginPercent: 100,
        mobileScaling:       2.0
      });

      googletag.pubads().collapseEmptyDivs();

      googletag.enableServices();
    });
  })();
  </script>
</head>
<body>
  <nav class="navbar">
    <a href="/" class="nav-brand">&#128250; Live Sports</a>
    <div class="nav-links">
      <a href="/"><i class="fas fa-home"></i> Home</a>
    </div>
  </nav>
  <main class="site-main">
