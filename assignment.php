<?php
/* ============================================================
   MODULE 2 — ASSIGNMENT  (AJAX — no page reload)
   ============================================================ */
require_once 'includes/auth.php';

/* Page only reads — all writes go through api.php */
$assignments = dbQuery(
    'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
     FROM assignments asn
     LEFT JOIN assets a ON asn.asset_id = a.id
     ORDER BY asn.created_at DESC'
);
$allAssets = dbQuery('SELECT id, asset_id, device_name, brand, device_type FROM assets WHERE id NOT IN (SELECT asset_id FROM assignments WHERE status = \'Assigned\') ORDER BY asset_id');
$allAssetsForEdit = dbQuery('SELECT id, asset_id, device_name, brand, device_type FROM assets ORDER BY asset_id');

/* Departments pulled from locations — no more free-text */
$departments = dbQuery('SELECT DISTINCT department FROM locations ORDER BY department');

$total    = count($assignments);
$assigned = count(array_filter($assignments, fn($r) => $r['status'] === 'Assigned'));
$returned = count(array_filter($assignments, fn($r) => $r['status'] === 'Returned'));
$available= count(array_filter($assignments, fn($r) => $r['status'] === 'Available'));

$page_title  = 'Assignment';
$active_page = 'assignment';
include 'includes/layout.php';
?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-label">Total Records</div><div class="stat-value" id="stat-total"><?= $total ?></div><div class="stat-meta">All assignment logs</div></div>
  <div class="stat-card"><div class="stat-label">Currently Assigned</div><div class="stat-value" id="stat-assigned"><?= $assigned ?></div><div class="stat-meta">Active assignments</div></div>
  <div class="stat-card green"><div class="stat-label">Returned</div><div class="stat-value" id="stat-returned"><?= $returned ?></div><div class="stat-meta">Assets returned</div></div>
  <div class="stat-card purple"><div class="stat-label">Available</div><div class="stat-value" id="stat-available"><?= $available ?></div><div class="stat-meta">Unassigned</div></div>
</div>

<div class="card">
  <div class="card-header">
    <div><div class="card-title">Assignment Records</div><div class="card-subtitle">Track who has what device</div></div>
    <div class="ml-auto flex gap-2">
      <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Assignment
      </button>
    </div>
  </div>
  <div style="padding:14px 22px 0">
    <div class="filter-bar">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="search-input" placeholder="Search by name, department, asset…">
      </div>
      <select id="filter-status" class="filter-select">
        <option value="">All Status</option>
        <option>Assigned</option><option>Returned</option><option>Available</option>
      </select>
      <select id="filter-dept" class="filter-select">
        <option value="">All Departments</option>
        <?php foreach ($departments as $d): ?>
        <option><?= htmlspecialchars($d['department']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="table-wrap">
    <table id="assign-table">
      <thead>
        <tr><th>#</th><th>Asset</th><th>Type</th><th>Assigned To</th><th>Department</th><th>Date Assigned</th><th>Date Returned</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody id="assign-tbody">
        <?php if (empty($assignments)): ?>
        <tr id="empty-row"><td colspan="9">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <h3>No assignments yet</h3><p>Create an assignment to track device allocation.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($assignments as $i => $r):
            $rj = htmlspecialchars(json_encode($r), ENT_QUOTES);
        ?>
        <tr data-id="<?= $r['id'] ?>">
          <td class="text-mono"><?= $i+1 ?></td>
          <td><span class="asset-id"><?= htmlspecialchars($r['asset_code'] ?? 'N/A') ?></span><div class="text-muted"><?= htmlspecialchars($r['device_name'] ?? '—') ?></div></td>
          <td><?= htmlspecialchars($r['device_type'] ?? '—') ?></td>
          <td><span class="cell-primary"><?= htmlspecialchars($r['assigned_to']) ?></span></td>
          <td><?= htmlspecialchars($r['department']) ?></td>
          <td class="cell-mono"><?= date('d M Y', strtotime($r['date_assigned'])) ?></td>
          <td class="cell-mono"><?= $r['date_returned'] ? date('d M Y', strtotime($r['date_returned'])) : '—' ?></td>
          <td><?php
            $sc = match($r['status']) { 'Assigned'=>'badge-blue','Returned'=>'badge-green',default=>'badge-gray' };
            echo '<span class="badge '.$sc.'">'.htmlspecialchars($r['status']).'</span>';
          ?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-xs" onclick='openEditModal(<?= $rj ?>)'>Edit</button>
              <?php if ($r['status'] === 'Assigned'): ?>
              <button class="btn btn-secondary btn-xs" onclick="returnAsset(<?= $r['id'] ?>)">Return</button>
              <?php endif; ?>
              <button class="btn btn-danger btn-xs" onclick="deleteAssignment(<?= $r['id'] ?>)">Del</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="11" x2="19" y2="17"/><line x1="16" y1="14" x2="22" y2="14"/></svg>
      <span class="modal-title">New Assignment</span>
      <button class="modal-close" onclick="Modal.close('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-add">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full-span">
            <label>Asset <span class="req">*</span></label>
            <select name="asset_id" required>
              <option value="">— Select asset —</option>
              <?php foreach ($allAssets as $a): ?>
              <option value="<?= $a['id'] ?>">[<?= htmlspecialchars($a['asset_id']) ?>] <?= htmlspecialchars($a['device_name']) ?> (<?= htmlspecialchars($a['device_type']) ?>) — <?= htmlspecialchars($a['brand']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Assigned To <span class="req">*</span></label>
            <input type="text" name="assigned_to" required placeholder="Full name of person">
          </div>
          <div class="form-group">
            <label>Department <span class="req">*</span></label>
            <select name="department" required>
              <option value="">— Select department —</option>
              <?php foreach ($departments as $d): ?>
              <option><?= htmlspecialchars($d['department']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date Assigned <span class="req">*</span></label>
            <input type="date" name="date_assigned" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Date Returned</label>
            <input type="date" name="date_returned">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status"><option>Assigned</option><option>Available</option><option>Returned</option></select>
          </div>
          <div class="form-group full-span">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional remarks…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Assignment</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      <span class="modal-title">Edit Assignment</span>
      <button class="modal-close" onclick="Modal.close('modal-edit')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-edit">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="record_id" id="edit-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full-span">
            <label>Asset</label>
            <select name="asset_id" id="edit-asset-id">
              <option value="">— Select asset —</option>
              <?php foreach ($allAssetsForEdit as $a): ?>
              <option value="<?= $a['id'] ?>">[<?= htmlspecialchars($a['asset_id']) ?>] <?= htmlspecialchars($a['device_name']) ?> (<?= htmlspecialchars($a['device_type']) ?>) — <?= htmlspecialchars($a['brand']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Assigned To</label>
            <input type="text" name="assigned_to" id="edit-assigned-to">
          </div>
          <div class="form-group">
            <label>Department</label>
            <select name="department" id="edit-dept">
              <option value="">— Select department —</option>
              <?php foreach ($departments as $d): ?>
              <option><?= htmlspecialchars($d['department']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Date Assigned</label><input type="date" name="date_assigned" id="edit-date-assigned"></div>
          <div class="form-group"><label>Date Returned</label><input type="date" name="date_returned" id="edit-date-returned"></div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit-status"><option>Assigned</option><option>Available</option><option>Returned</option></select>
          </div>
          <div class="form-group full-span"><label>Notes</label><textarea name="notes" id="edit-notes"></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   ASSIGNMENT MODULE — CLIENT SCRIPT
   ============================================================ */

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function esc(v) {
    if (v == null) return '';
    return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatDate(d) {
    if (!d || d === '—') return '—';
    const [y, m, day] = d.split('-');
    return `${day} ${MONTHS[parseInt(m) - 1]} ${y}`;
}

// Build a <tr> for an assignment row
function buildRow(r, idx) {
    const STATUS_BADGES = { Assigned:'badge-blue', Returned:'badge-green', Available:'badge-gray' };
    const rowData = JSON.stringify(r).replace(/'/g, '&#39;');
    const returnBtn = r.status === 'Assigned'
        ? '<button class="btn btn-secondary btn-xs ret-btn">Return</button>' : '';

    return `
        <tr data-id="${r.id}" data-row='${rowData}'>
            <td class="text-mono">${idx || ''}</td>
            <td>
                <span class="asset-id">${esc(r.asset_code || 'N/A')}</span>
                <div class="text-muted">${esc(r.device_name || '—')}</div>
            </td>
            <td>${esc(r.device_type || '—')}</td>
            <td><span class="cell-primary">${esc(r.assigned_to)}</span></td>
            <td>${esc(r.department)}</td>
            <td class="cell-mono">${formatDate(r.date_assigned)}</td>
            <td class="cell-mono">${formatDate(r.date_returned)}</td>
            <td><span class="badge ${STATUS_BADGES[r.status] || 'badge-gray'}">${esc(r.status)}</span></td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    ${returnBtn}
                    <button class="btn btn-danger btn-xs del-btn">Del</button>
                </div>
            </td>
        </tr>`;
}

// Update assignment KPI stat cards
function updateStats() {
    const rows = document.querySelectorAll('#assign-tbody tr[data-id]');
    let assigned = 0, returned = 0, available = 0;

    rows.forEach(row => {
        const badge = row.querySelector('.badge');
        if (!badge) return;
        const s = badge.textContent.trim();
        if (s === 'Assigned')       assigned++;
        else if (s === 'Returned')  returned++;
        else if (s === 'Available') available++;
    });

    document.getElementById('stat-total').textContent    = rows.length;
    document.getElementById('stat-assigned').textContent = assigned;
    document.getElementById('stat-returned').textContent = returned;
    document.getElementById('stat-available').textContent= available;
}

function openEditModal(r) {
    document.getElementById('edit-id').value             = r.id;
    document.getElementById('edit-assigned-to').value    = r.assigned_to  || '';
    document.getElementById('edit-date-assigned').value  = r.date_assigned || '';
    document.getElementById('edit-date-returned').value  = r.date_returned || '';
    document.getElementById('edit-notes').value          = r.notes         || '';
    setSelectValue('edit-asset-id', r.asset_id);
    setSelectValue('edit-status',   r.status);
    setSelectValue('edit-dept',     r.department);
    Modal.open('modal-edit');
}

function setSelectValue(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    Array.from(el.options).forEach(o => {
        o.selected = (String(o.value) === String(val) || o.text === String(val));
    });
}

function insertRow(r) {
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();
    const tbody = document.getElementById('assign-tbody');
    const count = tbody.querySelectorAll('tr[data-id]').length + 1;
    tbody.insertAdjacentHTML('afterbegin', buildRow(r, count));
}

function replaceRow(r) {
    const existing = document.querySelector(`#assign-tbody tr[data-id="${r.id}"]`);
    if (!existing) return;
    const idx  = existing.querySelector('td') ? existing.querySelector('td').textContent : '';
    const temp = document.createElement('tbody');
    temp.innerHTML = buildRow(r, idx);
    existing.replaceWith(temp.firstElementChild);
}

function removeRow(id) {
    const row = document.querySelector(`#assign-tbody tr[data-id="${id}"]`);
    if (!row) return;
    row.style.transition = 'opacity .25s';
    row.style.opacity    = '0';
    setTimeout(() => { row.remove(); updateStats(); }, 250);
}

/* ── Form Handlers ── */

document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'assignments', function(data) {
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        if (!data.row) return;
        insertRow(data.row);
        updateStats();
    });
});

document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'assignments', function(data) {
        Modal.close('modal-edit');
        if (!data.row) return;
        replaceRow(data.row);
        updateStats();
    });
});

/* ── Event Delegation ── */
document.getElementById('assign-tbody').addEventListener('click', function(e) {
    const editBtn   = e.target.closest('.edit-btn');
    const retBtn    = e.target.closest('.ret-btn');
    const delBtn    = e.target.closest('.del-btn');

    if (editBtn) {
        const row = editBtn.closest('tr[data-row]');
        openEditModal(JSON.parse(row.dataset.row.replace(/&#39;/g, "'")));
    }

    if (retBtn) {
        if (!confirm('Mark this asset as returned?')) return;
        const row = retBtn.closest('tr[data-id]');
        AjaxForm.action('assignments', { action:'return', record_id: row.dataset.id },
            data => { if (data.row) replaceRow(data.row); updateStats(); }
        );
    }

    if (delBtn) {
        if (!confirm('Delete this assignment record?')) return;
        const row = delBtn.closest('tr[data-id]');
        AjaxForm.delete('assignments', row.dataset.id, () => removeRow(row.dataset.id));
    }
});

/* ── Live Polling ── */
Poller.init('assignments', function(freshRows) {
    const tbody     = document.getElementById('assign-tbody');
    const domIds    = new Set([...tbody.querySelectorAll('tr[data-id]')].map(r => String(r.dataset.id)));
    const serverIds = new Set(freshRows.map(r => String(r.id)));

    freshRows.forEach(r => { if (!domIds.has(String(r.id))) insertRow(r); });
    domIds.forEach(id  => { if (!serverIds.has(id)) removeRow(id); });
    freshRows.forEach(r => {
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
    initTableSearch('search-input', 'assign-table', [3, 4, 1]);
    initSelectFilter('filter-status', 'assign-table', 7);
    initSelectFilter('filter-dept',   'assign-table', 4);
});
</script>
<?php // end ?>