<?php
/* ============================================================
   MODULE — MAINTENANCE
   Tracks maintenance done on assets.
   Monthly printable report via maintenance_print.php.
   ============================================================ */
require_once 'includes/auth.php';

// Filters
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterYear  = substr($filterMonth, 0, 4);
$filterMon   = substr($filterMonth, 5, 2);

// All records for the selected month
$records = dbQuery(
    'SELECT m.*, a.asset_id AS asset_code, a.model, a.device_type, a.brand
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     WHERE YEAR(m.maintenance_date) = ? AND MONTH(m.maintenance_date) = ?
     ORDER BY m.maintenance_date DESC',
    [(int)$filterYear, (int)$filterMon]
);

// All assets for the dropdown
$allAssets = dbQuery('SELECT id, asset_id, model, brand FROM assets ORDER BY asset_id');

// All users for the performed by dropdown
$allUsers = dbQuery('SELECT full_name FROM sys_users WHERE status = "Active" ORDER BY full_name');

// Stats for this month
$total     = count($records);
$completed = count(array_filter($records, fn($r) => $r['status'] === 'Completed'));
$pending   = count(array_filter($records, fn($r) => $r['status'] === 'Pending'));
$totalCost = array_sum(array_column($records, 'cost'));

// Month label for display
$monthLabel = date('F Y', mktime(0,0,0,(int)$filterMon,1,(int)$filterYear));

// Build list of available months that have records (for navigation)
$availableMonths = dbQuery(
    'SELECT DISTINCT DATE_FORMAT(maintenance_date, "%Y-%m") AS ym,
            DATE_FORMAT(maintenance_date, "%M %Y") AS label
     FROM maintenance_logs ORDER BY ym DESC LIMIT 24'
);

$page_title  = 'Maintenance';
$active_page = 'maintenance';
include 'includes/layout.php';
?> 

<!-- Stats for current month -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">This Month</div>
    <div class="stat-value" id="stat-total"><?= $total ?></div>
    <div class="stat-meta"><?= htmlspecialchars($monthLabel) ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Completed</div>
    <div class="stat-value" id="stat-completed"><?= $completed ?></div>
    <div class="stat-meta">Finished this month</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-label">Pending</div>
    <div class="stat-value" id="stat-pending"><?= $pending ?></div>
    <div class="stat-meta">Still in progress</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Total Cost</div>
    <div class="stat-value">₱<?= number_format($totalCost, 2) ?></div>
    <div class="stat-meta">This month's expenses</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Maintenance Log</div>
      <div class="card-subtitle"><?= htmlspecialchars($monthLabel) ?></div> <!-- Remove this from the div "- <?= $total ?> record<?= $total !== 1 ? 's' : '' ?>" -->
    </div>
    <div class="ml-auto flex gap-2">
      <!-- Month picker -->
      <input type="month" id="month-picker" value="<?= htmlspecialchars($filterMonth) ?>"
             class="filter-select" style="font-family:var(--font-mono);font-size:12px"
             onchange="window.location='maintenance.php?month='+this.value">
      <!-- Export button -->
      <button class="btn btn-ghost btn-sm" onclick="showExportPreview()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export to Excel
      </button>
      <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Log Maintenance
      </button>
    </div>
  </div>

  <!-- Filter bar -->
  <div style="padding:14px 22px 0">
    <div class="filter-bar">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="search-input" placeholder="Search asset, type, performed by…">
      </div>
      <select id="filter-type" class="filter-select">
        <option value="">All Types</option>
        <option>Preventive</option><option>Corrective</option>
        <option>Hardware Repair</option><option>Software</option>
        <option>Cleaning</option><option>Inspection</option><option>Other</option>
      </select>
      <select id="filter-status" class="filter-select">
        <option value="">All Status</option>
        <option>Completed</option><option>Pending</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <table id="maint-table">
      <thead>
        <tr>
          <th>#</th><th>Date</th><th>Asset</th><th>Type</th>
          <th>Description</th><th>Performed By</th>
          <th>Cost</th><th>Next Schedule</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="maint-tbody">
        <?php if (empty($records)): ?>
        <tr id="empty-row"><td colspan="10">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.2">
              <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
            <h3>No maintenance records for <?= htmlspecialchars($monthLabel) ?></h3>
            <p>Click "Log Maintenance" to add a record.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($records as $i => $r):
            $rowData   = htmlspecialchars(json_encode($r), ENT_QUOTES);
            $statusCls = $r['status'] === 'Completed' ? 'badge-green' : 'badge-yellow';
        ?>
        <tr data-id="<?= $r['id'] ?>" data-row='<?= $rowData ?>'>
          <td class="cell-mono"><?= $i + 1 ?></td>
          <td class="cell-mono"><?= date('d M Y', strtotime($r['maintenance_date'])) ?></td>
          <td>
            <?php if ($r['asset_code']): ?>
            <span class="asset-id"><?= htmlspecialchars($r['device_type']) ?> / <?= htmlspecialchars($r['brand']) ?></span>
            <div class="text-muted"><?= htmlspecialchars($r['model'] ?? '') ?></div>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($r['maintenance_type']) ?></span></td>
          <td style="max-width:200px">
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                         display:block;max-width:200px;color:var(--text-secondary);font-size:12.5px">
              <?= htmlspecialchars($r['description'] ?? '—') ?>
            </span>
          </td>
          <td><span class="cell-primary"><?= htmlspecialchars($r['performed_by']) ?></span></td>
          <td class="cell-mono">
            <?= $r['cost'] ? '₱' . number_format((float)$r['cost'], 2) : '—' ?>
          </td>
          <td class="cell-mono">
            <?= $r['next_schedule'] ? date('d M Y', strtotime($r['next_schedule'])) : '—' ?>
          </td>
          <td><span class="badge <?= $statusCls ?>"><?= htmlspecialchars($r['status']) ?></span></td>
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

  <!-- Monthly cost footer -->
  <?php if ($total > 0): ?>
  <div style="padding:14px 22px;border-top:1px solid var(--border);
              display:flex;justify-content:flex-end;gap:24px;font-size:13px">
    <span style="color:var(--text-muted)"><?= $total ?> records</span>
    <span style="font-weight:600;color:var(--text-primary)">
      Total cost: <span style="font-family:var(--font-mono);color:var(--accent)">
        ₱<?= number_format($totalCost, 2) ?>
      </span>
    </span>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ ADD MODAL ═══ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
      </svg>
      <span class="modal-title">Log Maintenance</span>
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
          <div class="form-group full-span">
            <label>Asset</label>
            <select name="asset_id">
              <option value="">— General / No specific asset —</option>
              <?php foreach ($allAssets as $a): ?>
              <option value="<?= $a['id'] ?>">
                [<?= htmlspecialchars($a['asset_id']) ?>] <?= htmlspecialchars($a['device_name']) ?> — <?= htmlspecialchars($a['brand']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Maintenance Type <span class="req">*</span></label>
            <select name="maintenance_type" required>
              <option value="">— Select type —</option>
              <option>Preventive</option><option>Corrective</option>
              <option>Hardware Repair</option><option>Software</option>
              <option>Cleaning</option><option>Inspection</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option>Completed</option><option>Pending</option>
            </select>
          </div>
          <div class="form-group">
            <label>Maintenance Date <span class="req">*</span></label>
            <input type="date" name="maintenance_date" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Performed By <span class="req">*</span></label>
            <select name="performed_by" required>
              <option value="">— Select technician —</option>
              <?php foreach ($allUsers as $u): ?>
              <option value="<?= htmlspecialchars($u['full_name']) ?>"><?= htmlspecialchars($u['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Cost (₱)</label>
            <input type="number" name="cost" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="form-group">
            <label>Next Schedule</label>
            <input type="date" name="next_schedule">
          </div>
          <div class="form-group full-span">
            <label>Description</label>
            <textarea name="description" rows="3"
                      placeholder="What was done? Any findings or notes…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Record</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ EXPORT PREVIEW MODAL ═══ -->
<div class="modal-overlay" id="modal-export-preview">
  <div class="modal" style="max-width:900px">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      <span class="modal-title">Export Preview — <?= htmlspecialchars($monthLabel) ?></span>
      <button class="modal-close" onclick="Modal.close('modal-export-preview')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:var(--text-muted);font-size:13px">
        Preview of the data that will be exported to Excel. Click "Download CSV" to export.
      </p>
      <div style="max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:6px">
        <table id="export-preview-table" style="width:100%;border-collapse:collapse;font-size:12px">
          <thead style="background:var(--bg-elevated);position:sticky;top:0">
            <tr>
              <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-weight:600">Date</th>
              <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-weight:600">Asset</th>
              <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-weight:600">Description</th>
              <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-weight:600">Cost</th>
              <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-weight:600">Status</th>
            </tr>
          </thead>
          <tbody id="export-preview-tbody">
            <?php if (empty($records)): ?>
            <tr><td colspan="5" style="padding:20px;text-align:center;color:var(--text-muted)">No records to export</td></tr>
            <?php else: ?>
            <?php foreach ($records as $r): ?>
            <tr>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);font-family:var(--font-mono)">
                <?= date('m/d/Y', strtotime($r['maintenance_date'])) ?>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light)">
                <?= htmlspecialchars($r['asset_code'] ? $r['device_type'] . ' / ' . $r['brand'] . ', ' . $r['model'] : 'General') ?>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);max-width:200px;word-wrap:break-word">
                <?= htmlspecialchars($r['description'] ?? '') ?>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);font-family:var(--font-mono);text-align:right">
                <?= $r['cost'] ? number_format((float)$r['cost'], 2) : '' ?>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light)">
                <?= htmlspecialchars($r['status']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-export-preview')">Cancel</button>
      <a href="maintenance_export.php?month=<?= urlencode($filterMonth) ?>" class="btn btn-primary">Download CSV</a>
    </div>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   MAINTENANCE MODULE — CLIENT SCRIPT
   ============================================================ */

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function esc(v) {
    if (v == null) return '';
    return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatDate(d) {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day} ${MONTHS[parseInt(m) - 1]} ${y}`;
}

function buildRow(r, idx) {
    const statusCls = r.status === 'Completed' ? 'badge-green' : 'badge-yellow';
    const cost      = r.cost ? '₱' + parseFloat(r.cost).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '—';
    const asset     = r.asset_code
        ? `<span class="asset-id">${esc(r.device_type)} / ${esc(r.brand)}</span><div class="text-muted">${esc(r.model || '')}</div>`
        : '<span class="text-muted">—</span>';
    const rowData   = JSON.stringify(r).replace(/'/g, '&#39;');

    return `
        <tr data-id="${r.id}" data-row='${rowData}'>
            <td class="cell-mono">${idx || ''}</td>
            <td class="cell-mono">${formatDate(r.maintenance_date)}</td>
            <td>${asset}</td>
            <td><span class="badge badge-gray">${esc(r.maintenance_type)}</span></td>
            <td style="max-width:200px">
                <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                             display:block;max-width:200px;color:var(--text-secondary);font-size:12.5px">
                    ${esc(r.description || '—')}
                </span>
            </td>
            <td><span class="cell-primary">${esc(r.performed_by)}</span></td>
            <td class="cell-mono">${cost}</td>
            <td class="cell-mono">${formatDate(r.next_schedule)}</td>
            <td><span class="badge ${statusCls}">${esc(r.status)}</span></td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-danger btn-xs del-btn">Del</button>
                </div>
            </td>
        </tr>`;
}

function updateStats() {
    const rows = document.querySelectorAll('#maint-tbody tr[data-id]');
    let completed = 0, pending = 0, totalCost = 0;

    rows.forEach(row => {
        // Status badge is in cell 8 (Status column), not cell 3 (Type column)
        const statusBadge = row.cells[8]?.querySelector('.badge');
        if (!statusBadge) return;
        if (statusBadge.textContent.trim() === 'Completed') completed++;
        else pending++;

        // Cost is in cell 6 (Cost column)
        const costCell = row.cells[6];
        if (costCell) {
            const raw = costCell.textContent.replace(/[₱,]/g, '').trim();
            if (raw && raw !== '—') totalCost += parseFloat(raw) || 0;
        }
    });

    document.getElementById('stat-total').textContent    = rows.length;
    document.getElementById('stat-completed').textContent = completed;
    document.getElementById('stat-pending').textContent   = pending;
}

function openEditModal(r) {
    document.getElementById('edit-id').value             = r.id;
    document.getElementById('edit-date').value           = r.maintenance_date || '';
    setSelectValue('edit-performed-by', r.performed_by || '');
    document.getElementById('edit-cost').value           = r.cost             || '';
    document.getElementById('edit-next-schedule').value  = r.next_schedule    || '';
    document.getElementById('edit-description').value    = r.description      || '';
    setSelectValue('edit-asset-id', r.asset_id || '');
    setSelectValue('edit-type',     r.maintenance_type);
    setSelectValue('edit-status',   r.status);
    Modal.open('modal-edit');
}

function setSelectValue(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    Array.from(el.options).forEach(o => { o.selected = (String(o.value) === String(val)); });
}

function insertRow(r) {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('maint-tbody');
    const count = tbody.querySelectorAll('tr[data-id]').length + 1;
    tbody.insertAdjacentHTML('afterbegin', buildRow(r, count));
    updateStats();
}

function replaceRow(r) {
    const existing = document.querySelector(`#maint-tbody tr[data-id="${r.id}"]`);
    if (!existing) return;
    const idx  = existing.querySelector('td')?.textContent || '';
    const temp = document.createElement('tbody');
    temp.innerHTML = buildRow(r, idx);
    existing.replaceWith(temp.firstElementChild);
    updateStats();
}

function removeRow(id) {
    const row = document.querySelector(`#maint-tbody tr[data-id="${id}"]`);
    if (!row) return;
    row.style.transition = 'opacity .25s';
    row.style.opacity    = '0';
    setTimeout(() => { row.remove(); updateStats(); }, 250);
}

/* ── Form Handlers ── */

document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'maintenance', function(data) {
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        // Reset date to today after form reset
        document.querySelector('#form-add [name="maintenance_date"]').value =
            new Date().toISOString().split('T')[0];
        if (!data.row) return;
        // Only show if the record belongs to the current month filter
        const recMonth = data.row.maintenance_date?.substring(0, 7);
        const curMonth = document.getElementById('month-picker').value;
        if (recMonth === curMonth) insertRow(data.row);
    });
});

document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'maintenance', function(data) {
        Modal.close('modal-edit');
        if (!data.row) return;
        replaceRow(data.row);
    });
});

/* ── Event Delegation ── */
document.getElementById('maint-tbody').addEventListener('click', function(e) {
    const editBtn = e.target.closest('.edit-btn');
    const delBtn  = e.target.closest('.del-btn');

    if (editBtn) {
        const row = editBtn.closest('tr[data-row]');
        openEditModal(JSON.parse(row.dataset.row.replace(/&#39;/g, "'")));
    }

    if (delBtn) {
        if (!confirm('Delete this maintenance record?')) return;
        const row = delBtn.closest('tr[data-id]');
        AjaxForm.delete('maintenance', row.dataset.id, () => removeRow(row.dataset.id));
    }
});

/* ── Live Polling ── */
Poller.init('maintenance', function(freshRows) {
    // Only show rows matching the current month filter
    const curMonth = document.getElementById('month-picker').value;
    const filtered = freshRows.filter(r => r.maintenance_date?.startsWith(curMonth));

    const tbody     = document.getElementById('maint-tbody');
    const domIds    = new Set([...tbody.querySelectorAll('tr[data-id]')].map(r => String(r.dataset.id)));
    const serverIds = new Set(filtered.map(r => String(r.id)));

    filtered.forEach(r => { if (!domIds.has(String(r.id))) insertRow(r); });
    domIds.forEach(id  => { if (!serverIds.has(id)) removeRow(id); });
    filtered.forEach(r => {
        const existing = tbody.querySelector(`tr[data-id="${r.id}"]`);
        if (!existing) return;
        const idx  = existing.querySelector('td')?.textContent || '';
        const temp = document.createElement('tbody');
        temp.innerHTML = buildRow(r, idx);
        const newRow = temp.firstElementChild;
        if (existing.innerHTML !== newRow.innerHTML) existing.replaceWith(newRow);
    });

    updateStats();
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    initTableSearch('search-input', 'maint-table', [2, 3, 4, 5]);
    initSelectFilter('filter-type',   'maint-table', 3);
    initSelectFilter('filter-status', 'maint-table', 8);
});

/* ── Export Preview ── */
function showExportPreview() {
    Modal.open('modal-export-preview');
}
</script>
<?php // end ?>