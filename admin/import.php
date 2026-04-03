<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$adminPageTitle = 'Import Matches';
$extraJs = '/assets/js/import.js';

$pdo = getPDO();

// Load servers for dropdown
$servers = $pdo->query('SELECT id, name, code FROM servers ORDER BY id')->fetchAll();

require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-card">
  <h2 class="card-title"><i class="fas fa-download"></i> Import Matches from Server</h2>

  <!-- Step 1: Select Server -->
  <div class="import-step" id="step-1">
    <h3>Step 1: Select Server</h3>
    <div class="form-row">
      <div class="form-group">
        <label for="server_id">Server</label>
        <select id="server_id" name="server_id" class="form-control">
          <option value="">-- Select a server --</option>
          <?php foreach ($servers as $server): ?>
            <option value="<?= (int) $server['id'] ?>" data-code="<?= htmlspecialchars($server['code'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="align-self:flex-end;">
        <button id="btn-fetch" class="btn btn-primary" disabled>
          <i class="fas fa-sync"></i> Fetch Matches
        </button>
      </div>
    </div>
  </div>

  <!-- Step 2: Preview -->
  <div class="import-step" id="step-2" style="display:none;">
    <h3>Step 2: Preview Matches</h3>
    <div id="fetch-status" class="alert" style="display:none;"></div>

    <div style="margin-bottom:1rem; display:flex; gap:1rem; align-items:center;">
      <label>
        <input type="checkbox" id="check-all"> Select All
      </label>
      <span id="selected-count" class="badge">0 selected</span>
    </div>

    <div class="table-responsive">
      <table class="admin-table" id="preview-table">
        <thead>
          <tr>
            <th style="width:40px;"><i class="fas fa-check-square"></i></th>
            <th>Title</th>
            <th>League</th>
            <th>Date / Time</th>
            <th>Streams</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="preview-tbody">
          <!-- Populated by import.js -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- Step 3: Import -->
  <div class="import-step" id="step-3" style="display:none;">
    <h3>Step 3: Import Selected</h3>
    <div id="import-status" class="alert" style="display:none;"></div>
    <button id="btn-import" class="btn btn-success">
      <i class="fas fa-database"></i> Import Selected Matches
    </button>
    <button id="btn-reset" class="btn btn-secondary" style="margin-left:0.5rem;">
      <i class="fas fa-redo"></i> Start Over
    </button>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
