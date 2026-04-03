/**
 * admin.js — Admin Panel UI Logic
 * Site: https://news.evaulthub.com
 */
(function () {
  'use strict';

  /* ── 1. Sidebar active link highlighting ──────────────────
     Compares each nav link's pathname to the current URL.
     Exact match → active; partial prefix match for sub-pages.
  ──────────────────────────────────────────────────────── */
  function highlightActiveNavLink() {
    var currentPath = window.location.pathname;

    var navLinks = document.querySelectorAll('.sidebar-nav a');
    navLinks.forEach(function (link) {
      var linkPath = link.pathname || '';

      if (!linkPath) return;

      // Exact match takes priority
      if (currentPath === linkPath) {
        link.classList.add('active');
        return;
      }

      // Prefix match for sub-sections (e.g. /admin/matches/edit matches /admin/matches)
      // Guard: don't match "/" or "/admin" against everything
      if (linkPath.length > 7 && currentPath.startsWith(linkPath)) {
        link.classList.add('active');
      }
    });
  }

  /* ── 2. Delete confirmation ───────────────────────────────
     Intercepts any form with data-confirm or class delete-form
     and shows a browser confirm dialog before submitting.
  ──────────────────────────────────────────────────────── */
  function setupDeleteConfirmation() {
    document.addEventListener('submit', function (e) {
      var form = e.target;

      var needsConfirm =
        form.classList.contains('delete-form') ||
        form.hasAttribute('data-confirm');

      if (!needsConfirm) return;

      var message = form.getAttribute('data-confirm') ||
                    'Are you sure you want to delete this item? This action cannot be undone.';

      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });

    // Also handle standalone delete links/buttons with data-confirm
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el || el.tagName === 'FORM') return;           // forms handled above

      var message = el.getAttribute('data-confirm') ||
                    'Are you sure?';

      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });
  }

  /* ── 3. Select-all checkbox for import preview table ──────
     Looks for #select-all and toggles all .row-checkbox inputs.
  ──────────────────────────────────────────────────────── */
  function setupSelectAll() {
    var selectAll = document.getElementById('select-all');
    if (!selectAll) return;

    selectAll.addEventListener('change', function () {
      var checkboxes = document.querySelectorAll('.row-checkbox');
      checkboxes.forEach(function (cb) {
        cb.checked = selectAll.checked;
      });
    });

    // If all row checkboxes are individually ticked, tick select-all too
    document.addEventListener('change', function (e) {
      if (!e.target.classList.contains('row-checkbox')) return;

      var all  = document.querySelectorAll('.row-checkbox');
      var checked = document.querySelectorAll('.row-checkbox:checked');
      selectAll.checked = all.length === checked.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    });
  }

  /* ── 4. Auto-dismiss flash messages after 4 seconds ───────
     Targets elements with class .alert.auto-dismiss or
     any .alert inside a .flash-messages wrapper.
  ──────────────────────────────────────────────────────── */
  function setupFlashMessages() {
    var alerts = document.querySelectorAll(
      '.flash-messages .alert, .alert.auto-dismiss'
    );

    alerts.forEach(function (alert) {
      // Fade out then remove
      setTimeout(function () {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';

        setTimeout(function () {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 500);
      }, 4000);
    });
  }

  /* ── 5. Mobile sidebar toggle (optional enhancement) ──────
     If there's a hamburger button #sidebar-toggle, wire it up.
  ──────────────────────────────────────────────────────── */
  function setupMobileSidebarToggle() {
    var toggle  = document.getElementById('sidebar-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (e) {
      if (
        sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        !toggle.contains(e.target)
      ) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* ── Bootstrap ───────────────────────────────────────────── */
  function init() {
    highlightActiveNavLink();
    setupDeleteConfirmation();
    setupSelectAll();
    setupFlashMessages();
    setupMobileSidebarToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
