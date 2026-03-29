<?php
/* ============================================================
   MODULE 3 — LOCATION
   All writes go through api.php via AJAX.
   Polling (poll.php) syncs changes from other users every 5s.
   ============================================================ */
require_once 'includes/auth.php';

// Fetch page data — reads only, no writes here
$locations = dbQuery(
    'SELECT l.*, COUNT(a.id) AS asset_count
     FROM locations l
     LEFT JOIN assets a ON a.location_id = l.id
     GROUP BY l.id
     ORDER BY l.building_name, l.floor, l.room_number'
);

// Stat counts for KPI cards
$total      = count($locations);
$buildings  = count(array_unique(array_column($locations, 'building_name')));
$depts      = count(array_unique(array_column($locations, 'department')));
$withAssets = count(array_filter($locations, fn($r) => $r['asset_count'] > 0));

// Suggest next Location ID
$suggestedId = 'LOC-' . str_pad($total + 1, 4, '0', STR_PAD_LEFT);

// Building list for filter dropdown
$buildingList = array_unique(array_column($locations, 'building_name'));
sort($buildingList);

$page_title  = 'Locations';
$active_page = 'location';
include 'includes/layout.php';
?>

<!-- KPI Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Locations</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-meta">Registered rooms</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Buildings</div>
    <div class="stat-value"><?= $buildings ?></div>
    <div class="stat-meta">Distinct buildings</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Departments</div>
    <div class="stat-value"><?= $depts ?></div>
    <div class="stat-meta">Unique departments</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">With Assets</div>
    <div class="stat-value"><?= $withAssets ?></div>
    <div class="stat-meta">Locations w/ devices</div>
  </div>
</div>

<!-- Main card -->
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Location Directory</div>
      <div class="card-subtitle"><?= $total ?> locations registered</div>
    </div>
    <div class="ml-auto flex gap-2">
      <!-- View toggle -->
      <button class="btn btn-ghost btn-sm btn-secondary" id="btn-card-view"
              onclick="setView('card')" title="Card view">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
      </button>
      <button class="btn btn-ghost btn-sm" id="btn-table-view"
              onclick="setView('table')" title="Table view">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
          <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
          <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
      </button>
      <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Location
      </button>
    </div>
  </div>

  <!-- Search and filter -->
  <div style="padding:14px 22px 0">
    <div class="filter-bar">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="search-input" placeholder="Search building, room, department…">
      </div>
      <select id="filter-building" class="filter-select">
        <option value="">All Buildings</option>
        <?php foreach ($buildingList as $b): ?>
        <option><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Card View -->
  <div id="view-card" style="padding:20px 22px">
    <!--
      cards-grid always exists in the DOM even when empty.
      This is important — the JS event delegation listener
      attaches to it on DOMContentLoaded and must find it.
    -->
    <div id="cards-grid"
         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
      <?php if (empty($locations)): ?>
      <div id="cards-empty" style="grid-column:1/-1">
        <div class="empty-state">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="1.2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
          <h3>No locations found</h3>
          <p>Add a location to map your assets.</p>
        </div>
      </div>
      <?php else: ?>
      <?php foreach ($locations as $loc):
          // Store full row data in data-row attribute — used by JS edit modal
          $rowData = htmlspecialchars(json_encode($loc), ENT_QUOTES);
      ?>
      <div class="loc-card"
           data-id="<?= $loc['id'] ?>"
           data-row='<?= $rowData ?>'
           data-search="<?= strtolower($loc['building_name'].' '.$loc['floor'].' '.$loc['room_number'].' '.$loc['department']) ?>"
           data-building="<?= strtolower($loc['building_name']) ?>"
           style="background:var(--bg-elevated);border:1px solid var(--border);
                  border-radius:var(--radius-md);padding:18px;transition:border-color .2s">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
          <div style="width:36px;height:36px;background:var(--accent-glow);
                      border:1px solid rgba(79,142,247,.3);border-radius:var(--radius-sm);
                      display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="var(--accent)" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;color:var(--text-primary);font-size:13.5px;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= htmlspecialchars($loc['building_name']) ?>
            </div>
            <div style="font-family:var(--font-mono);font-size:11px;color:var(--accent);margin-top:1px">
              <?= htmlspecialchars($loc['location_id']) ?>
            </div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-bottom:12px">
          <div>
            <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                        letter-spacing:.05em;margin-bottom:2px">Floor</div>
            <div style="color:var(--text-secondary)"><?= htmlspecialchars($loc['floor']) ?></div>
          </div>
          <div>
            <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                        letter-spacing:.05em;margin-bottom:2px">Room</div>
            <div style="color:var(--text-secondary)"><?= htmlspecialchars($loc['room_number']) ?></div>
          </div>
          <div style="grid-column:1/-1">
            <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                        letter-spacing:.05em;margin-bottom:2px">Department</div>
            <div style="color:var(--text-secondary)"><?= htmlspecialchars($loc['department']) ?></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding-top:10px;border-top:1px solid var(--border)">
          <span style="font-size:11px;color:var(--text-muted)">
            <span class="loc-asset-count"
                  style="color:var(--accent);font-family:var(--font-mono);font-weight:600">
              <?= $loc['asset_count'] ?>
            </span> asset<?= $loc['asset_count'] != 1 ? 's' : '' ?>
          </span>
          <div style="display:flex;gap:6px">
            <!-- No inline onclick — event delegation handles these -->
            <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
            <button class="btn btn-danger btn-xs del-btn">Del</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Table View -->
  <div id="view-table" style="display:none">
    <div class="table-wrap">
      <table id="loc-table">
        <thead>
          <tr>
            <th>Location ID</th><th>Building</th><th>Floor</th><th>Room</th>
            <th>Department</th><th>Assets</th><th>Notes</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="loc-tbody">
          <?php if (empty($locations)): ?>
          <tr id="table-empty"><td colspan="8">
            <div class="empty-state" style="padding:40px 0">
              <h3>No locations found</h3>
              <p>Add a location to get started.</p>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach ($locations as $loc):
              $rowData = htmlspecialchars(json_encode($loc), ENT_QUOTES);
          ?>
          <tr data-id="<?= $loc['id'] ?>" data-row='<?= $rowData ?>'>
            <td><span class="asset-id"><?= htmlspecialchars($loc['location_id']) ?></span></td>
            <td><span class="cell-primary"><?= htmlspecialchars($loc['building_name']) ?></span></td>
            <td><?= htmlspecialchars($loc['floor']) ?></td>
            <td><?= htmlspecialchars($loc['room_number']) ?></td>
            <td><?= htmlspecialchars($loc['department']) ?></td>
            <td>
              <span style="background:var(--accent-light);color:var(--accent);
                           font-family:var(--font-mono);font-size:12px;
                           padding:2px 8px;border-radius:99px;
                           border:1px solid rgba(59,110,240,.2)">
                <?= $loc['asset_count'] ?>
              </span>
            </td>
            <td class="text-muted"><?= htmlspecialchars($loc['notes'] ?? '—') ?></td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                <button class="btn btn-danger btn-xs del-btn">Del</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ ADD MODAL ═══ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
        <circle cx="12" cy="10" r="3"/>
      </svg>
      <span class="modal-title">Add Location</span>
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
            <label>Location ID <span class="req">*</span></label>
            <input type="text" name="location_id" required
                   value="<?= htmlspecialchars($suggestedId) ?>"
                   placeholder="LOC-0001">
            <span class="form-hint">Auto-suggested · can be changed</span>
          </div>
          <div class="form-group">
            <label>Building Name <span class="req">*</span></label>
            <input type="text" name="building_name" required placeholder="e.g. Main Building">
          </div>
          <div class="form-group">
            <label>Floor <span class="req">*</span></label>
            <input type="text" name="floor" required placeholder="Ground Floor, 2nd Floor…">
          </div>
          <div class="form-group">
            <label>Room Number <span class="req">*</span></label>
            <input type="text" name="room_number" required placeholder="e.g. 201">
          </div>
          <div class="form-group full-span">
            <label>Department <span class="req">*</span></label>
            <input type="text" name="department" required placeholder="e.g. Accounting Department">
          </div>
          <div class="form-group full-span">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional description…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Location</button>
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
      <span class="modal-title">Edit Location</span>
      <button class="modal-close" onclick="Modal.close('modal-edit')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form id="form-edit">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="record_id" id="edit-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Location ID</label>
            <input type="text" name="location_id" id="edit-loc-id" required>
          </div>
          <div class="form-group">
            <label>Building Name</label>
            <input type="text" name="building_name" id="edit-building" required>
          </div>
          <div class="form-group">
            <label>Floor</label>
            <input type="text" name="floor" id="edit-floor" required>
          </div>
          <div class="form-group">
            <label>Room Number</label>
            <input type="text" name="room_number" id="edit-room" required>
          </div>
          <div class="form-group full-span">
            <label>Department</label>
            <input type="text" name="department" id="edit-dept" required>
          </div>
          <div class="form-group full-span">
            <label>Notes</label>
            <textarea name="notes" id="edit-notes"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Location</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   LOCATION MODULE — CLIENT SCRIPT
   Two views: Card (default) and Table.
   Both use data-row attributes — no inline onclick JSON.
   Event delegation handles all button clicks in both views.
   ============================================================ */

// Safely escape a value for HTML output
function esc(value) {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Build a table <tr> from a location data object
function buildTableRow(loc) {
    const rowData = JSON.stringify(loc).replace(/'/g, '&#39;');
    return `
        <tr data-id="${loc.id}" data-row='${rowData}'>
            <td><span class="asset-id">${esc(loc.location_id)}</span></td>
            <td><span class="cell-primary">${esc(loc.building_name)}</span></td>
            <td>${esc(loc.floor)}</td>
            <td>${esc(loc.room_number)}</td>
            <td>${esc(loc.department)}</td>
            <td>
                <span style="background:var(--accent-light);color:var(--accent);
                             font-family:var(--font-mono);font-size:12px;
                             padding:2px 8px;border-radius:99px;
                             border:1px solid rgba(59,110,240,.2)">
                    ${loc.asset_count || 0}
                </span>
            </td>
            <td class="text-muted">${esc(loc.notes || '—')}</td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-danger btn-xs del-btn">Del</button>
                </div>
            </td>
        </tr>`;
}

// Build a card <div> from a location data object
function buildCard(loc) {
    const rowData    = JSON.stringify(loc).replace(/'/g, '&#39;');
    const searchData = `${loc.building_name} ${loc.floor} ${loc.room_number} ${loc.department}`.toLowerCase();
    const buildingLC = (loc.building_name || '').toLowerCase();
    const assetCount = loc.asset_count || 0;
    const assetLabel = assetCount === 1 ? 'asset' : 'assets';

    return `
        <div class="loc-card"
             data-id="${loc.id}"
             data-row='${rowData}'
             data-search="${esc(searchData)}"
             data-building="${esc(buildingLC)}"
             style="background:var(--bg-elevated);border:1px solid var(--border);
                    border-radius:var(--radius-md);padding:18px;transition:border-color .2s">
            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
                <div style="width:36px;height:36px;background:var(--accent-glow);
                            border:1px solid rgba(79,142,247,.3);border-radius:var(--radius-sm);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                         viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;color:var(--text-primary);font-size:13.5px;
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        ${esc(loc.building_name)}
                    </div>
                    <div style="font-family:var(--font-mono);font-size:11px;color:var(--accent);margin-top:1px">
                        ${esc(loc.location_id)}
                    </div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-bottom:12px">
                <div>
                    <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                                letter-spacing:.05em;margin-bottom:2px">Floor</div>
                    <div style="color:var(--text-secondary)">${esc(loc.floor)}</div>
                </div>
                <div>
                    <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                                letter-spacing:.05em;margin-bottom:2px">Room</div>
                    <div style="color:var(--text-secondary)">${esc(loc.room_number)}</div>
                </div>
                <div style="grid-column:1/-1">
                    <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;
                                letter-spacing:.05em;margin-bottom:2px">Department</div>
                    <div style="color:var(--text-secondary)">${esc(loc.department)}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding-top:10px;border-top:1px solid var(--border)">
                <span style="font-size:11px;color:var(--text-muted)">
                    <span class="loc-asset-count"
                          style="color:var(--accent);font-family:var(--font-mono);font-weight:600">
                        ${assetCount}
                    </span> ${assetLabel}
                </span>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-danger btn-xs del-btn">Del</button>
                </div>
            </div>
        </div>`;
}

// Populate the edit modal fields from a location data object
function openEditModal(loc) {
    document.getElementById('edit-id').value       = loc.id;
    document.getElementById('edit-loc-id').value   = loc.location_id;
    document.getElementById('edit-building').value = loc.building_name;
    document.getElementById('edit-floor').value    = loc.floor;
    document.getElementById('edit-room').value     = loc.room_number;
    document.getElementById('edit-dept').value     = loc.department;
    document.getElementById('edit-notes').value    = loc.notes || '';
    Modal.open('modal-edit');
}

// Parse the data-row attribute from the nearest ancestor that has one
function getRowData(element) {
    const container = element.closest('[data-row]');
    if (!container) return null;
    return JSON.parse(container.dataset.row.replace(/&#39;/g, "'"));
}

// Insert a new location into both views
function insertLocation(loc) {
    // Remove empty-state placeholders if present
    const cardsEmpty = document.getElementById('cards-empty');
    const tableEmpty = document.getElementById('table-empty');
    if (cardsEmpty) cardsEmpty.remove();
    if (tableEmpty) tableEmpty.remove();

    // Append to end (not prepend) so order matches server sort order
    document.getElementById('cards-grid').insertAdjacentHTML('beforeend', buildCard(loc));

    const tbody = document.getElementById('loc-tbody');
    if (tbody) tbody.insertAdjacentHTML('beforeend', buildTableRow(loc));
}

// Replace a location in both views
function replaceLocation(loc) {
    const existingCard = document.querySelector(`.loc-card[data-id="${loc.id}"]`);
    const existingRow  = document.querySelector(`#loc-tbody tr[data-id="${loc.id}"]`);

    if (existingCard) {
        const temp = document.createElement('div');
        temp.innerHTML = buildCard(loc);
        existingCard.replaceWith(temp.firstElementChild);
    }
    if (existingRow) {
        const temp = document.createElement('tbody');
        temp.innerHTML = buildTableRow(loc);
        existingRow.replaceWith(temp.firstElementChild);
    }
}

// Fade out and remove a location from both views
function removeLocation(id) {
    [
        document.querySelector(`.loc-card[data-id="${id}"]`),
        document.querySelector(`#loc-tbody tr[data-id="${id}"]`)
    ].forEach(el => {
        if (!el) return;
        el.style.transition = 'opacity .25s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 250);
    });
}

/* ── Form: Add Location ── */
document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'locations', function(data) {
        if (!data.row) return;
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        insertLocation(data.row);
    });
});

/* ── Form: Edit Location ── */
document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'locations', function(data) {
        if (!data.row) return;
        Modal.close('modal-edit');
        replaceLocation(data.row);
    });
});

/* ── Event Delegation: Cards Grid ──
   Handles Edit and Delete for all cards — PHP-rendered and JS-injected.
   Attached to cards-grid which always exists in the DOM.
─────────────────────────────────────────────────────────────────── */
document.getElementById('cards-grid').addEventListener('click', function(e) {
    const editBtn = e.target.closest('.edit-btn');
    const delBtn  = e.target.closest('.del-btn');

    if (editBtn) {
        const loc = getRowData(editBtn);
        if (loc) openEditModal(loc);
    }
    if (delBtn) {
        const card = delBtn.closest('[data-id]');
        if (card && confirm('Delete this location? Assets linked here will be unlinked.')) {
            AjaxForm.delete('locations', card.dataset.id, () => removeLocation(card.dataset.id));
        }
    }
});

/* ── Event Delegation: Table Body ── */
document.getElementById('loc-tbody').addEventListener('click', function(e) {
    const editBtn = e.target.closest('.edit-btn');
    const delBtn  = e.target.closest('.del-btn');

    if (editBtn) {
        const loc = getRowData(editBtn);
        if (loc) openEditModal(loc);
    }
    if (delBtn) {
        const row = delBtn.closest('tr[data-id]');
        if (row && confirm('Delete this location? Assets linked here will be unlinked.')) {
            AjaxForm.delete('locations', row.dataset.id, () => removeLocation(row.dataset.id));
        }
    }
});

/* ── View Toggle ── */
let currentView = 'card';

function setView(view) {
    currentView = view;
    document.getElementById('view-card').style.display  = view === 'card'  ? '' : 'none';
    document.getElementById('view-table').style.display = view === 'table' ? '' : 'none';
    document.getElementById('btn-card-view').classList.toggle('btn-secondary',  view === 'card');
    document.getElementById('btn-table-view').classList.toggle('btn-secondary', view === 'table');
}

/* ── Live Polling ──
   Syncs card view and table view with fresh data from poll.php.
   Insert: new locations added by another user appear immediately.
   Update: changed locations refresh in place.
   Remove: deleted locations fade out automatically.
─────────────────────────────────────────────────────────────────── */
Poller.init('locations', function(freshRows) {
    const serverIds = new Set(freshRows.map(r => String(r.id)));

    // Build set of IDs currently in the card grid
    const cardIds = new Set(
        [...document.querySelectorAll('.loc-card[data-id]')].map(c => String(c.dataset.id))
    );

    // Insert locations added by another user
    freshRows.forEach(loc => {
        if (!cardIds.has(String(loc.id))) insertLocation(loc);
    });

    // Remove locations deleted by another user
    cardIds.forEach(id => {
        if (!serverIds.has(id)) removeLocation(id);
    });

    // Update locations changed by another user
    freshRows.forEach(loc => {
        const existingCard = document.querySelector(`.loc-card[data-id="${loc.id}"]`);
        const existingRow  = document.querySelector(`#loc-tbody tr[data-id="${loc.id}"]`);

        if (existingCard) {
            const temp = document.createElement('div');
            temp.innerHTML = buildCard(loc);
            const newCard = temp.firstElementChild;
            // Only replace if content actually differs (prevents pointless DOM churn)
            if (existingCard.innerHTML !== newCard.innerHTML) {
                existingCard.replaceWith(newCard);
            }
        }
        if (existingRow) {
            const temp = document.createElement('tbody');
            temp.innerHTML = buildTableRow(loc);
            const newRow = temp.firstElementChild;
            if (existingRow.innerHTML !== newRow.innerHTML) {
                existingRow.replaceWith(newRow);
            }
        }
    });
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    initTableSearch('search-input', 'loc-table', [0, 1, 2, 3, 4]);
    initSelectFilter('filter-building', 'loc-table', 1);

    // Card view filter — uses data-search and data-building attributes
    const searchInput    = document.getElementById('search-input');
    const buildingFilter = document.getElementById('filter-building');

    function filterCards() {
        const query    = searchInput.value.toLowerCase();
        const building = buildingFilter.value.toLowerCase();

        document.querySelectorAll('.loc-card').forEach(card => {
            const matchesSearch   = !query    || (card.dataset.search   || '').includes(query);
            const matchesBuilding = !building || (card.dataset.building || '').includes(building);
            card.style.display    = matchesSearch && matchesBuilding ? '' : 'none';
        });
    }

    searchInput.addEventListener('input',    filterCards);
    buildingFilter.addEventListener('change', filterCards);
});
</script>
<?php // end ?>