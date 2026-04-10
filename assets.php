<?php
/* ============================================================
   MODULE 1 — ASSET REGISTRY
   All writes go through api.php via AJAX.
   Polling (poll.php) syncs changes from other users every 5s.
   ============================================================ */
require_once 'includes/auth.php';

// Fetch page data — reads only, no writes here
$assets    = dbQuery('SELECT a.*, l.building_name, l.room_number, l.department AS loc_dept
                      FROM assets a LEFT JOIN locations l ON a.location_id = l.id
                      ORDER BY a.created_at DESC');
$locations = dbQuery('SELECT id, location_id, building_name, room_number, department
                      FROM locations ORDER BY building_name');

// Stat counts for the KPI cards
$total   = count($assets);
$active  = count(array_filter($assets, fn($r) => $r['status'] === 'Active'));
$repair  = count(array_filter($assets, fn($r) => $r['status'] === 'Under Repair'));
$retired = count(array_filter($assets, fn($r) => $r['status'] === 'Retired'));

// Helper: status badge HTML
function statusBadge(string $s): string {
    $map = ['Active' => 'badge-green', 'Under Repair' => 'badge-yellow', 'Retired' => 'badge-gray'];
    $cls = $map[$s] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($s) . '</span>';
}

// Helper: device type badge HTML
function typeBadge(string $t): string {
    $map = ['PC' => 'badge-blue', 'Laptop' => 'badge-purple', 'Printer' => 'badge-yellow',
            'Router' => 'badge-green', 'Switch' => 'badge-green', 'Monitor' => 'badge-gray'];
    $cls = $map[$t] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($t) . '</span>';
}

$page_title  = 'Asset Registry';
$active_page = 'assets';
include 'includes/layout.php';
?>

<!-- KPI Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Assets</div>
    <div class="stat-value" id="stat-total"><?= $total ?></div>
    <div class="stat-meta">All registered devices</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Active</div>
    <div class="stat-value" id="stat-active"><?= $active ?></div>
    <div class="stat-meta">Currently in use</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-label">Under Repair</div>
    <div class="stat-value" id="stat-repair"><?= $repair ?></div>
    <div class="stat-meta">Being serviced</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Retired</div>
    <div class="stat-value" id="stat-retired"><?= $retired ?></div>
    <div class="stat-meta">Decommissioned</div>
  </div>
</div>

<!-- Main Table Card -->
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Device Inventory</div>
      <!-- <div class="card-subtitle" id="asset-count-label"><?= $total ?> assets registered</div> // Remove ambiguous subtexts -->
    </div>
    <div class="ml-auto">
      <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Asset
      </button>
    </div>
  </div>

  <!-- Search and filter bar -->
  <div style="padding:14px 22px 0">
    <div class="filter-bar">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="search-input" placeholder="Search by name, brand, serial, IP…">
      </div>
      <select id="filter-type" class="filter-select">
        <option value="">All Types</option>
        <option>PC</option><option>Laptop</option><option>Printer</option>
        <option>Router</option><option>Switch</option><option>Monitor</option><option>Other</option>
      </select>
      <select id="filter-status" class="filter-select">
        <option value="">All Status</option>
        <option>Active</option><option>Under Repair</option><option>Retired</option>
      </select>
    </div>
  </div>

  <!-- Asset table -->
  <div class="table-wrap">
    <table id="assets-table">
      <thead>
        <tr>
          <th>Asset ID</th><th>Device</th><th>Type</th><th>Brand / Model</th>
          <th>Serial #</th><th>IP Address</th><th>Location</th>
          <th>Warranty</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="assets-tbody">
        <?php if (empty($assets)): ?>
        <tr id="empty-row"><td colspan="10">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.2">
              <rect x="2" y="3" width="20" height="14" rx="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            <h3>No assets found</h3>
            <p>Click "Add Asset" to register your first device.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($assets as $a):
            // Determine warranty colour
            $wStyle = '';
            if ($a['warranty_expiry']) {
                $daysLeft = (strtotime($a['warranty_expiry']) - time()) / 86400;
                if ($daysLeft < 0)  $wStyle = 'style="color:var(--red)"';
                elseif ($daysLeft < 90) $wStyle = 'style="color:var(--yellow)"';
            }
            // Location display: department (primary) + building (secondary)
            $locHtml = $a['loc_dept']
                ? htmlspecialchars($a['loc_dept']) . '<div class="text-muted">' . htmlspecialchars($a['building_name']) . '</div>'
                : ($a['building_name'] ? htmlspecialchars($a['building_name']) : '—');
        ?>
        <tr data-id="<?= $a['id'] ?>"
            data-row='<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>'>
          <td><span class="asset-id"><?= htmlspecialchars($a['asset_id']) ?></span></td>
          <td><span class="cell-primary"><?= htmlspecialchars($a['device_name']) ?></span></td>
          <td><?= typeBadge($a['device_type']) ?></td>
          <td>
            <span class="cell-primary"><?= htmlspecialchars($a['brand']) ?></span>
            <div class="text-muted"><?= htmlspecialchars($a['model']) ?></div>
          </td>
          <td class="cell-mono"><?= htmlspecialchars($a['serial_number']) ?></td>
          <td class="cell-mono"><?= htmlspecialchars($a['ip_address'] ?? '—') ?></td>
          <td><?= $locHtml ?></td>
          <td class="cell-mono" <?= $wStyle ?>>
            <?= $a['warranty_expiry'] ? date('d M Y', strtotime($a['warranty_expiry'])) : '—' ?>
          </td>
          <td><?= statusBadge($a['status']) ?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
              <button class="btn btn-danger btn-xs del-btn">Delete</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ ADD MODAL ═══ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      <span class="modal-title">Add New Asset</span>
      <button class="modal-close" onclick="Modal.close('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="form-add">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Asset ID <span class="req">*</span></label>
            <input type="text" name="asset_id" required placeholder="e.g. PC-ER-0001">
            <span class="form-hint">Prefix-DEPT-XXXX format recommended</span>
          </div>
          <div class="form-group">
            <label>Device Name <span class="req">*</span></label>
            <input type="text" name="device_name" required placeholder="e.g. PC-ER-01">
          </div>
          <div class="form-group">
            <label>Device Type <span class="req">*</span></label>
            <select name="device_type">
              <option>PC</option><option>Laptop</option><option>Printer</option>
              <option>Router</option><option>Switch</option><option>Monitor</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Brand <span class="req">*</span></label>
            <input type="text" name="brand" required placeholder="Dell, HP, Lenovo…">
          </div>
          <div class="form-group">
            <label>Model <span class="req">*</span></label>
            <input type="text" name="model" required placeholder="e.g. OptiPlex 7090">
          </div>
          <div class="form-group">
            <label>Serial Number <span class="req">*</span></label>
            <input type="text" name="serial_number" required placeholder="Manufacturer serial">
          </div>
          <div class="form-group">
            <label>IP Address</label>
            <input type="text" name="ip_address" placeholder="192.168.1.10">
          </div>
          <div class="form-group">
            <label>Purchase Date</label>
            <input type="date" name="purchase_date">
          </div>
          <div class="form-group">
            <label>Warranty Expiry</label>
            <input type="date" name="warranty_expiry">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option>Active</option><option>Under Repair</option><option>Retired</option>
            </select>
          </div>
          <div class="form-group">
            <label>Location</label>
            <select name="location_id">
              <option value="">— None —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>">
                <?= htmlspecialchars("{$loc['department']} — {$loc['building_name']}, Rm.{$loc['room_number']}") ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Asset</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ EDIT MODAL ═══ -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="var(--yellow)" stroke-width="2">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
      </svg>
      <span class="modal-title">Edit Asset</span>
      <button class="modal-close" onclick="Modal.close('modal-edit')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="form-edit">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="record_id" id="edit-record-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Asset ID <span class="req">*</span></label>
            <input type="text" name="asset_id" id="edit-asset-id" required>
          </div>
          <div class="form-group">
            <label>Device Name <span class="req">*</span></label>
            <input type="text" name="device_name" id="edit-device-name" required>
          </div>
          <div class="form-group">
            <label>Device Type</label>
            <select name="device_type" id="edit-device-type">
              <option>PC</option><option>Laptop</option><option>Printer</option>
              <option>Router</option><option>Switch</option><option>Monitor</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Brand</label>
            <input type="text" name="brand" id="edit-brand">
          </div>
          <div class="form-group">
            <label>Model</label>
            <input type="text" name="model" id="edit-model">
          </div>
          <div class="form-group">
            <label>Serial Number</label>
            <input type="text" name="serial_number" id="edit-serial">
          </div>
          <div class="form-group">
            <label>IP Address</label>
            <input type="text" name="ip_address" id="edit-ip">
          </div>
          <div class="form-group">
            <label>Purchase Date</label>
            <input type="date" name="purchase_date" id="edit-purchase">
          </div>
          <div class="form-group">
            <label>Warranty Expiry</label>
            <input type="date" name="warranty_expiry" id="edit-warranty">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit-status">
              <option>Active</option><option>Under Repair</option><option>Retired</option>
            </select>
          </div>
          <div class="form-group">
            <label>Location</label>
            <select name="location_id" id="edit-location">
              <option value="">— None —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>">
                <?= htmlspecialchars("{$loc['department']} — {$loc['building_name']}, Rm.{$loc['room_number']}") ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Asset</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   ASSETS MODULE — CLIENT SCRIPT
   Uses AjaxForm (app.js) for saves and Poller (app.js) for
   live sync. No page reloads at any point.
   ============================================================ */

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Safely escape HTML special characters
function esc(value) {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Format a YYYY-MM-DD date string to "01 Jan 2026"
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const [year, month, day] = dateStr.split('-');
    return `${day} ${MONTHS[parseInt(month) - 1]} ${year}`;
}

// Return inline style string for warranty date colouring
function warrantyStyle(dateStr) {
    if (!dateStr) return '';
    const daysLeft = (new Date(dateStr) - new Date()) / 86400000;
    if (daysLeft < 0)  return ' style="color:var(--red)"';
    if (daysLeft < 90) return ' style="color:var(--yellow)"';
    return '';
}

// Build a complete <tr> HTML string from an asset data object.
// Used both by AJAX callbacks (instant insert) and the Poller (live sync).
function buildRow(asset) {
    const TYPE_BADGES   = { PC:'badge-blue', Laptop:'badge-purple', Printer:'badge-yellow',
                             Router:'badge-green', Switch:'badge-green', Monitor:'badge-gray' };
    const STATUS_BADGES = { Active:'badge-green', 'Under Repair':'badge-yellow', Retired:'badge-gray' };

    const location = asset.loc_dept
        ? esc(asset.loc_dept) + '<div class="text-muted">' + esc(asset.building_name) + '</div>'
        : (asset.building_name ? esc(asset.building_name) : '—');

    // Store the full data object on the row for the edit modal
    const rowData = JSON.stringify(asset).replace(/'/g, '&#39;');

    return `
        <tr data-id="${asset.id}" data-row='${rowData}'>
            <td><span class="asset-id">${esc(asset.asset_id)}</span></td>
            <td><span class="cell-primary">${esc(asset.device_name)}</span></td>
            <td><span class="badge ${TYPE_BADGES[asset.device_type] || 'badge-gray'}">${esc(asset.device_type)}</span></td>
            <td>
                <span class="cell-primary">${esc(asset.brand)}</span>
                <div class="text-muted">${esc(asset.model)}</div>
            </td>
            <td class="cell-mono">${esc(asset.serial_number)}</td>
            <td class="cell-mono">${esc(asset.ip_address || '—')}</td>
            <td>${location}</td>
            <td class="cell-mono"${warrantyStyle(asset.warranty_expiry)}>${formatDate(asset.warranty_expiry)}</td>
            <td><span class="badge ${STATUS_BADGES[asset.status] || 'badge-gray'}">${esc(asset.status)}</span></td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-danger btn-xs del-btn">Delete</button>
                </div>
            </td>
        </tr>`;
}

// Recalculate and update the KPI stat cards from current DOM rows
function updateStats() {
    const rows  = document.querySelectorAll('#assets-tbody tr[data-id]');
    let active  = 0, repair = 0, retired = 0;

    rows.forEach(row => {
        const badge = row.querySelector('.badge');
        if (!badge) return;
        const status = badge.textContent.trim();
        if (status === 'Active')            active++;
        else if (status === 'Under Repair') repair++;
        else if (status === 'Retired')      retired++;
    });

    document.getElementById('stat-total').textContent   = rows.length;
    document.getElementById('stat-active').textContent  = active;
    document.getElementById('stat-repair').textContent  = repair;
    document.getElementById('stat-retired').textContent = retired;
    document.getElementById('asset-count-label').textContent =
        `${rows.length} asset${rows.length !== 1 ? 's' : ''} registered`;
}

// Populate the edit modal fields with data from the clicked row
function openEditModal(asset) {
    document.getElementById('edit-record-id').value   = asset.id;
    document.getElementById('edit-asset-id').value    = asset.asset_id;
    document.getElementById('edit-device-name').value = asset.device_name;
    document.getElementById('edit-brand').value       = asset.brand;
    document.getElementById('edit-model').value       = asset.model;
    document.getElementById('edit-serial').value      = asset.serial_number;
    document.getElementById('edit-ip').value          = asset.ip_address    || '';
    document.getElementById('edit-purchase').value    = asset.purchase_date  || '';
    document.getElementById('edit-warranty').value    = asset.warranty_expiry || '';

    setSelectValue('edit-device-type', asset.device_type);
    setSelectValue('edit-status',      asset.status);
    setSelectValue('edit-location',    asset.location_id || '');

    Modal.open('modal-edit');
}

// Set a <select> element's selected option by value
function setSelectValue(elementId, value) {
    const select = document.getElementById(elementId);
    if (!select) return;
    Array.from(select.options).forEach(opt => {
        opt.selected = (opt.value == value);
    });
}

// Insert a new row at the top of the table, removing the empty-state row if present
function insertRow(asset) {
    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();
    document.getElementById('assets-tbody').insertAdjacentHTML('afterbegin', buildRow(asset));
}

// Replace an existing row in place (after edit)
function replaceRow(asset) {
    const existing = document.querySelector(`#assets-tbody tr[data-id="${asset.id}"]`);
    if (!existing) return;
    const temp = document.createElement('tbody');
    temp.innerHTML = buildRow(asset);
    existing.replaceWith(temp.firstElementChild);
}

// Remove a row with a fade-out animation
function removeRow(id) {
    const row = document.querySelector(`#assets-tbody tr[data-id="${id}"]`);
    if (!row) return;
    row.style.transition = 'opacity .25s';
    row.style.opacity    = '0';
    setTimeout(() => { row.remove(); updateStats(); }, 250);
}

/* ── Form Handlers ── */

document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'assets', function(data) {
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        if (!data.row) return;
        insertRow(data.row);
        updateStats();
    });
});

document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'assets', function(data) {
        Modal.close('modal-edit');
        if (!data.row) return;
        replaceRow(data.row);
        updateStats();
    });
});

/* ── Event Delegation ──
   Handles Edit and Delete for ALL rows — including those added
   by AJAX or polling (which don't have inline onclick handlers).
─────────────────────────────────────────────────────────────── */
document.getElementById('assets-tbody').addEventListener('click', function(e) {
    const editBtn = e.target.closest('.edit-btn');
    const delBtn  = e.target.closest('.del-btn');

    if (editBtn) {
        const row   = editBtn.closest('tr[data-row]');
        const asset = JSON.parse(row.dataset.row.replace(/&#39;/g, "'"));
        openEditModal(asset);
    }

    if (delBtn) {
        if (!confirm('Delete this asset? This cannot be undone.')) return;
        const row = delBtn.closest('tr[data-id]');
        AjaxForm.delete('assets', row.dataset.id, () => removeRow(row.dataset.id));
    }
});

/* ── Live Polling ──
   Called by Poller every 5 seconds with fresh data from poll.php.
   Only updates rows that actually changed — no full re-renders.
─────────────────────────────────────────────────────────────── */
Poller.init('assets', function(freshRows) {
    const tbody     = document.getElementById('assets-tbody');
    const domIds    = new Set([...tbody.querySelectorAll('tr[data-id]')].map(r => String(r.dataset.id)));
    const serverIds = new Set(freshRows.map(r => String(r.id)));

    // Add rows that appeared from another user's action
    freshRows.forEach(asset => {
        if (!domIds.has(String(asset.id))) insertRow(asset);
    });

    // Fade out rows deleted by another user
    domIds.forEach(id => {
        if (!serverIds.has(id)) removeRow(id);
    });

    // Update rows whose data changed
    freshRows.forEach(asset => {
        const existing = tbody.querySelector(`tr[data-id="${asset.id}"]`);
        if (!existing) return;
        const temp = document.createElement('tbody');
        temp.innerHTML = buildRow(asset);
        const newRow = temp.firstElementChild;
        if (existing.innerHTML !== newRow.innerHTML) existing.replaceWith(newRow);
    });

    updateStats();
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    initTableSearch('search-input', 'assets-table', [0, 1, 3, 4, 5]);
    initSelectFilter('filter-type',   'assets-table', 2);
    initSelectFilter('filter-status', 'assets-table', 8);
});
</script>
<?php // end ?>
