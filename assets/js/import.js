/**
 * import.js — Admin Import Page AJAX Logic
 * Site: https://news.evaulthub.com
 *
 * Element IDs match import.php:
 *   #server_id, #btn-fetch, #step-2, #preview-tbody,
 *   #check-all, #selected-count, #fetch-status,
 *   #step-3, #btn-import, #btn-reset, #import-status
 */
(function () {
  'use strict';

  /* ── Cached DOM references (match import.php IDs) ────────── */
  var serverSelect   = document.getElementById('server_id');
  var fetchBtn       = document.getElementById('btn-fetch');
  var step2          = document.getElementById('step-2');
  var previewTbody   = document.getElementById('preview-tbody');
  var checkAllCb     = document.getElementById('check-all');
  var selectedCount  = document.getElementById('selected-count');
  var fetchStatus    = document.getElementById('fetch-status');
  var step3          = document.getElementById('step-3');
  var importBtn      = document.getElementById('btn-import');
  var resetBtn       = document.getElementById('btn-reset');
  var importStatus   = document.getElementById('import-status');

  // Store parsed matches for the store step
  var currentServerId = null;

  /* ── Helpers ─────────────────────────────────────────────── */

  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#39;');
  }

  function showAlert(el, msg, type) {
    if (!el) return;
    el.style.display = 'block';
    el.className = 'alert alert-' + (type || 'info');
    el.innerHTML = msg;
  }

  function hideAlert(el) {
    if (!el) return;
    el.style.display = 'none';
    el.innerHTML = '';
  }

  function formatDatetime(str) {
    if (!str) return '—';
    // Handle MySQL datetime format "2026-04-03 19:45:00"
    var d = new Date(str.replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return str;
    return d.toLocaleDateString('en-GB', {
      day   : '2-digit',
      month : 'short',
      year  : 'numeric',
      hour  : '2-digit',
      minute: '2-digit',
      hour12: false
    });
  }

  function updateSelectedCount() {
    if (!selectedCount) return;
    var checked = previewTbody ? previewTbody.querySelectorAll('.row-cb:checked').length : 0;
    selectedCount.textContent = checked + ' selected';
  }

  /* ── Enable/disable fetch button based on server selection ── */
  if (serverSelect && fetchBtn) {
    serverSelect.addEventListener('change', function () {
      fetchBtn.disabled = !serverSelect.value;
    });
  }

  /* ── 1. FETCH MATCHES ────────────────────────────────────── */
  if (fetchBtn) {
    fetchBtn.addEventListener('click', function () {
      var serverId = serverSelect ? serverSelect.value : '';
      if (!serverId) return;

      currentServerId = serverId;

      // Show loading state
      fetchBtn.disabled = true;
      fetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching…';
      hideAlert(fetchStatus);
      hideAlert(importStatus);

      // Reset UI
      if (step2) step2.style.display = 'none';
      if (step3) step3.style.display = 'none';
      if (previewTbody) previewTbody.innerHTML = '';

      var body = new FormData();
      body.append('server_id', serverId);

      fetch('/admin/import-fetch.php', {
        method: 'POST',
        body: body
      })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || data.success === false) {
            throw new Error(data.error || data.message || 'Server error ' + res.status);
          }
          return data;
        });
      })
      .then(function (data) {
        var matches = data.matches || [];

        if (matches.length === 0) {
          showAlert(fetchStatus, '<i class="fas fa-info-circle"></i> No matches found from this server.', 'info');
          if (step2) step2.style.display = 'block';
          return;
        }

        renderPreview(matches);

        // Show step 2 and step 3
        if (step2) step2.style.display = 'block';
        if (step3) step3.style.display = 'block';

        // Count new vs existing
        var newCount = matches.filter(function(m) { return m.status === 'new'; }).length;
        var existCount = matches.length - newCount;
        showAlert(fetchStatus,
          '<i class="fas fa-check-circle"></i> Found <strong>' + matches.length + '</strong> matches. ' +
          '<span style="color:#4ade80;">' + newCount + ' new</span>, ' +
          '<span style="color:#f59e0b;">' + existCount + ' already imported</span>.',
          'success'
        );
      })
      .catch(function (err) {
        showAlert(fetchStatus, '<i class="fas fa-exclamation-triangle"></i> ' + escHtml(err.message), 'error');
        if (step2) step2.style.display = 'block';
      })
      .finally(function () {
        fetchBtn.disabled = false;
        fetchBtn.innerHTML = '<i class="fas fa-sync"></i> Fetch Matches';
      });
    });
  }

  /* ── Render preview table ────────────────────────────────── */
  function renderPreview(matches) {
    if (!previewTbody) return;
    previewTbody.innerHTML = '';

    matches.forEach(function (match) {
      var isNew = match.status === 'new';
      var tr = document.createElement('tr');
      if (!isNew) tr.style.opacity = '0.6';

      tr.innerHTML =
        '<td><input type="checkbox" class="row-cb" value="' + escHtml(match.fingerprint) + '"' +
          (isNew ? ' checked' : ' disabled') + '></td>' +
        '<td>' + escHtml(match.title || '—') + '</td>' +
        '<td>' + escHtml(match.league || '—') + '</td>' +
        '<td>' + formatDatetime(match.match_datetime) + '</td>' +
        '<td>' + (match.streams_count || 0) + '</td>' +
        '<td>' + (isNew
          ? '<span style="color:#4ade80;">✅ New</span>'
          : '<span style="color:#f59e0b;">⚠️ Exists</span>') + '</td>';

      previewTbody.appendChild(tr);
    });

    updateSelectedCount();
  }

  /* ── Select All checkbox ─────────────────────────────────── */
  if (checkAllCb && previewTbody) {
    checkAllCb.addEventListener('change', function () {
      var boxes = previewTbody.querySelectorAll('.row-cb:not(:disabled)');
      boxes.forEach(function (cb) {
        cb.checked = checkAllCb.checked;
      });
      updateSelectedCount();
    });

    // Delegate change events from individual checkboxes
    previewTbody.addEventListener('change', function (e) {
      if (!e.target.classList.contains('row-cb')) return;
      var all = previewTbody.querySelectorAll('.row-cb:not(:disabled)');
      var checked = previewTbody.querySelectorAll('.row-cb:not(:disabled):checked');
      checkAllCb.checked = all.length > 0 && all.length === checked.length;
      checkAllCb.indeterminate = checked.length > 0 && checked.length < all.length;
      updateSelectedCount();
    });
  }

  /* ── 2. IMPORT SELECTED ──────────────────────────────────── */
  if (importBtn) {
    importBtn.addEventListener('click', function () {
      hideAlert(importStatus);

      var checkedBoxes = previewTbody ? previewTbody.querySelectorAll('.row-cb:checked') : [];
      if (checkedBoxes.length === 0) {
        showAlert(importStatus, '<i class="fas fa-exclamation-triangle"></i> Please select at least one match.', 'error');
        return;
      }

      var fingerprints = [];
      checkedBoxes.forEach(function (cb) {
        fingerprints.push(cb.value);
      });

      importBtn.disabled = true;
      importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing…';

      // import-store.php expects JSON body
      fetch('/admin/import-store.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          server_id: parseInt(currentServerId, 10),
          fingerprints: fingerprints
        })
      })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || data.success === false) {
            throw new Error(data.error || data.message || 'Import failed');
          }
          return data;
        });
      })
      .then(function (data) {
        var imported = data.imported || 0;
        var skipped  = data.skipped  || 0;

        showAlert(importStatus,
          '<i class="fas fa-check-circle"></i> <strong>' + imported + '</strong> match' +
          (imported !== 1 ? 'es' : '') + ' imported' +
          (skipped > 0 ? ', <strong>' + skipped + '</strong> skipped (already exist)' : '') + '.',
          'success'
        );

        // Disable imported checkboxes and mark as exists
        checkedBoxes.forEach(function (cb) {
          cb.checked = false;
          cb.disabled = true;
          var row = cb.closest('tr');
          if (row) {
            row.style.opacity = '0.6';
            var statusCell = row.querySelector('td:last-child');
            if (statusCell) {
              statusCell.innerHTML = '<span style="color:#f59e0b;">⚠️ Imported</span>';
            }
          }
        });

        checkAllCb && (checkAllCb.checked = false);
        updateSelectedCount();
      })
      .catch(function (err) {
        showAlert(importStatus, '<i class="fas fa-exclamation-triangle"></i> ' + escHtml(err.message), 'error');
      })
      .finally(function () {
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="fas fa-database"></i> Import Selected Matches';
      });
    });
  }

  /* ── 3. RESET / START OVER ───────────────────────────────── */
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (step2) step2.style.display = 'none';
      if (step3) step3.style.display = 'none';
      hideAlert(fetchStatus);
      hideAlert(importStatus);
      if (previewTbody) previewTbody.innerHTML = '';
      if (serverSelect) serverSelect.value = '';
      if (fetchBtn) fetchBtn.disabled = true;
      if (checkAllCb) {
        checkAllCb.checked = false;
        checkAllCb.indeterminate = false;
      }
      currentServerId = null;
      updateSelectedCount();
    });
  }

})();
