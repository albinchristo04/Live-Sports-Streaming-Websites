/**
 * ads.js — GPT Ad Management
 * Network ID : 23250651813
 * Site       : https://news.evaulthub.com
 * Refresh    : 60 seconds (match / player pages only)
 */
(function () {
  'use strict';

  var NETWORK_ID      = '23250651813';
  var REFRESH_INTERVAL = 60000; // 60 seconds
  var refreshTimer    = null;

  /* ── Slot definitions ─────────────────────────────────────
     Each entry maps a div ID to an ad unit path + sizes.
     Sizes are passed straight to googletag.defineSlot().
  ──────────────────────────────────────────────────────── */
  var slotConfigs = [
    {
      id       : 'div-gpt-ad-header',
      unit     : '/' + NETWORK_ID + '/header',
      sizes    : [[970, 90], [728, 90], [320, 50]],
      mapping  : [
        { viewport: [970, 0], sizes: [[970, 90], [728, 90]] },
        { viewport: [728, 0], sizes: [[728, 90]] },
        { viewport: [0,   0], sizes: [[320, 50]] }
      ]
    },
    {
      id       : 'div-gpt-ad-sidebar',
      unit     : '/' + NETWORK_ID + '/sidebar',
      sizes    : [[300, 600], [300, 250]],
      mapping  : [
        { viewport: [992, 0], sizes: [[300, 600]] },
        { viewport: [500, 0], sizes: [[300, 250]] },
        { viewport: [0,   0], sizes: [] }           // hidden on mobile
      ]
    },
    {
      id       : 'div-gpt-ad-infeed',
      unit     : '/' + NETWORK_ID + '/infeed',
      sizes    : [[336, 280], [300, 250]],
      mapping  : [
        { viewport: [500, 0], sizes: [[336, 280]] },
        { viewport: [0,   0], sizes: [[300, 250]] }
      ]
    },
    {
      id       : 'div-gpt-ad-below-player',
      unit     : '/' + NETWORK_ID + '/below-player',
      sizes    : [[336, 280], [300, 250]],
      mapping  : [
        { viewport: [500, 0], sizes: [[336, 280]] },
        { viewport: [0,   0], sizes: [[300, 250]] }
      ]
    },
    {
      id       : 'div-gpt-ad-sticky-mobile',
      unit     : '/' + NETWORK_ID + '/sticky-mobile',
      sizes    : [[320, 50]],
      mapping  : [
        { viewport: [768, 0], sizes: [] },          // hidden on desktop
        { viewport: [0,   0], sizes: [[320, 50]] }
      ]
    }
  ];

  /* Track which slots have been defined so we can refresh them */
  var definedSlots = [];

  /* ── Build a size mapping object from our config ─────────── */
  function buildSizeMapping(mappingConfig) {
    var sm = googletag.sizeMapping();
    mappingConfig.forEach(function (entry) {
      sm.addSize(entry.viewport, entry.sizes);
    });
    return sm.build();
  }

  /* ── Define a single slot (only if its div exists in DOM) ─── */
  function defineSlot(config) {
    var el = document.getElementById(config.id);
    if (!el) return null;

    var slot = googletag
      .defineSlot(config.unit, config.sizes, config.id)
      .defineSizeMapping(buildSizeMapping(config.mapping))
      .addService(googletag.pubads());

    return slot;
  }

  /* ── Initialise GPT ──────────────────────────────────────── */
  function initGPT() {
    googletag.cmd.push(function () {
      // Enable services
      googletag.pubads().enableSingleRequest();
      googletag.pubads().collapseEmptyDivs(true);
      googletag.enableServices();

      // Define all slots present on this page
      slotConfigs.forEach(function (config) {
        var slot = defineSlot(config);
        if (slot) {
          definedSlots.push(slot);
        }
      });

      // Initial display
      definedSlots.forEach(function (slot) {
        googletag.display(slot.getSlotElementId());
      });
    });
  }

  /* ── Ad Refresh (match/player pages only) ────────────────── */
  function isMatchPage() {
    // Refresh on any URL that contains /match/ or /watch/
    return /\/(match|watch)\//.test(window.location.pathname);
  }

  function startAdRefresh() {
    if (!isMatchPage()) return;

    refreshTimer = setInterval(function () {
      if (typeof googletag === 'undefined' || !googletag.pubads) return;

      googletag.cmd.push(function () {
        if (definedSlots.length > 0) {
          googletag.pubads().refresh(definedSlots);
        }
      });
    }, REFRESH_INTERVAL);
  }

  function stopAdRefresh() {
    if (refreshTimer !== null) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
  }

  /* ── Mobile Sticky Ad ────────────────────────────────────── */
  function handleMobileStickyAd() {
    var stickyEl = document.querySelector('.mobile-sticky-ad');
    if (!stickyEl) return;

    if (window.innerWidth < 768) {
      stickyEl.style.display = 'block';
    } else {
      stickyEl.style.display = 'none';
    }
  }

  /* ── Bootstrap ───────────────────────────────────────────── */
  function init() {
    // Ensure googletag command queue exists
    window.googletag = window.googletag || { cmd: [] };

    initGPT();
    handleMobileStickyAd();
    startAdRefresh();

    window.addEventListener('resize', handleMobileStickyAd);
    window.addEventListener('beforeunload', stopAdRefresh);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
