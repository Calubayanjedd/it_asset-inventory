<?php
/* ============================================================
   MODULE 6 — USER ROLES
   File: users.php
   Depends on: includes/db.php, includes/layout.php
   Completely standalone — no FK to other tables
   ============================================================ */

require_once 'includes/auth.php';


$success = $error = '';


$currentUser = $authUser ?? ['id' => 0];


/* ── FETCH ── */
$users = dbQuery('SELECT * FROM sys_users ORDER BY role ASC, username ASC');

try {
    $logs = dbQuery(
        'SELECT l.*, u.username
         FROM activity_log l
         LEFT JOIN sys_users u ON l.user_id = u.id
         ORDER BY l.created_at DESC LIMIT 100'
    );
} catch (Throwable $e) {
    $logs = [];
}

/* ── STATS ── */
$total    = count($users);
$admins   = count(array_filter($users, fn($r) => $r['role'] === 'Admin'));
$staff    = count(array_filter($users, fn($r) => $r['role'] === 'IT Staff'));
$viewers  = count(array_filter($users, fn($r) => $r['role'] === 'Viewer'));
$active   = count(array_filter($users, fn($r) => $r['status'] === 'Active'));

/* ── ROLE BADGE ── */
function roleBadge(string $r): string {
    return match($r) {
        'Admin'    => '<span class="badge badge-red">Admin</span>',
        'IT Staff' => '<span class="badge badge-blue">IT Staff</span>',
        'Viewer'   => '<span class="badge badge-gray">Viewer</span>',
        default    => '<span class="badge badge-gray">' . htmlspecialchars($r) . '</span>',
    };
}

function statusBadgeU(string $s): string {
    return $s === 'Active'
        ? '<span class="badge badge-green">Active</span>'
        : '<span class="badge badge-gray">Inactive</span>';
}

function roleIcon(string $r): string {
    return match($r) {
        'Admin'    => '🔴',
        'IT Staff' => '🔵',
        default    => '⚪',
    };
}

/* ── ROLE PERMISSIONS TABLE ── */
$permissions = [
    ['Module',          'Admin', 'IT Staff', 'Viewer'],
    ['Asset Registry',  '✓ Full','✓ Full',   '👁 View'],
    ['Assignment',      '✓ Full','✓ Full',   '👁 View'],
    ['Locations',       '✓ Full','✓ Full',   '👁 View'],
    ['Stock',           '✓ Full','✓ Full',   '👁 View'],
    ['Dashboard',       '✓ Full','✓ Full',   '✓ Full'],
    ['User Roles',      '✓ Full','✗ None',   '✗ None'],
];

$page_title  = 'User Roles';
$active_page = 'users';
include 'includes/layout.php';
?>

<?php if ($success): ?>
<div class="alert alert-success">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-meta"><?= $active ?> active accounts</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Administrators</div>
    <div class="stat-value"><?= $admins ?></div>
    <div class="stat-meta">Full system access</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">IT Staff</div>
    <div class="stat-value"><?= $staff ?></div>
    <div class="stat-meta">Operational access</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Viewers</div>
    <div class="stat-value"><?= $viewers ?></div>
    <div class="stat-meta">Read-only access</div>
  </div>
</div>

<!-- TWO COLUMN LAYOUT -->
<div style="display:grid;grid-template-columns:1fr;gap:20px;align-items:start">

  <!-- LEFT: USERS TABLE -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <div>
          <div class="card-title">System Users</div>
          <div class="card-subtitle">Manage access and roles</div>
        </div>
        <div class="ml-auto flex gap-2">
          <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add User
          </button>
        </div>
      </div>

      <div style="padding:14px 22px 0">
        <div class="filter-bar">
          <div class="search-box">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" placeholder="Search username, name…">
          </div>
          <select id="filter-role" class="filter-select">
            <option value="">All Roles</option>
            <option>Admin</option><option>IT Staff</option><option>Viewer</option>
          </select>
          <select id="filter-status" class="filter-select">
            <option value="">All Status</option>
            <option>Active</option><option>Inactive</option>
          </select>
        </div>
      </div>

      <div class="table-wrap">
        <table id="users-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Username</th>
              <th>Role</th>
              <th>Email</th>
              <th>Last Login</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7">
              <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <h3>No users found</h3>
                <p>Add the first system user above.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr data-id="<?= $u['id'] ?>" data-row='<?= htmlspecialchars(json_encode(array_diff_key($u, ['password_hash'=>1])), ENT_QUOTES) ?>'>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-elevated);border:1px solid var(--border);
                       display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;
                       color:<?= $u['role']==='Admin'?'var(--red)':($u['role']==='IT Staff'?'var(--accent)':'var(--text-muted)') ?>">
                    <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
                  </div>
                  <span class="cell-primary"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></span>
                </div>
              </td>
              <td class="cell-mono" style="color:var(--text-secondary)"><?= htmlspecialchars($u['username']) ?></td>
              <td><?= roleBadge($u['role']) ?></td>
              <td class="text-muted"><?= $u['email'] ? htmlspecialchars($u['email']) : '—' ?></td>
              <td class="cell-mono text-muted"><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
              <td><?= statusBadgeU($u['status']) ?></td>
              <td>
                <div class="flex gap-2" style="flex-wrap:wrap">
                  <button class="btn btn-ghost btn-xs" onclick='openEditModal(<?= json_encode($u) ?>)'>Edit</button>
                  <button class="btn btn-ghost btn-xs" onclick="toggleStatus(<?= $u['id'] ?>)"><?= $u['status']==="Active"?"Disable":"Enable" ?></button>
                  <?php if ($u['id'] != ($currentUser['id'] ?? 0)): ?>
                  <button class="btn btn-danger btn-xs del-btn">Del</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ACTIVITY LOG -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Activity Log</div>
          <div class="card-subtitle">Recent system actions</div>
        </div>
        <form method="POST" class="ml-auto">
          <input type="hidden" name="action" value="clear_log">
          <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Clear old log entries?')">
            Clear Old Entries
          </button>
        </form>
      </div>
      <div class="table-wrap" style="max-height:450px;overflow-y:auto">
        <?php if (empty($logs)): ?>
        <div class="empty-state" style="padding:28px">
          <p>No activity recorded yet.</p>
        </div>
        <?php else: ?>
        <table>
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
              <td class="cell-mono text-muted"><?= date('d M H:i', strtotime($l['created_at'])) ?></td>
              <td class="cell-primary"><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
              <td>
                <?php
                  $cls = match(true) {
                      str_contains($l['action'], 'DELETE') => 'badge-red',
                      str_contains($l['action'], 'UPDATE') => 'badge-yellow',
                      str_contains($l['action'], 'INSERT') => 'badge-green',
                      default                              => 'badge-gray',
                  };
                ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($l['action']) ?></span>
              </td>
              <td style="font-size:11px;color:var(--text-secondary);line-height:1.6">
                <?php
                  $details = $l['details'] ?? '';
                  $action = $l['action'];

                  // Parse details and create readable descriptions
                  $parsed = [];
                  $pairs = array_map('trim', explode(',', $details));
                  foreach ($pairs as $pair) {
                    if (empty($pair)) continue;
                    [$key, $value] = array_pad(explode(':', $pair, 2), 2, '');
                    $parsed[trim($key)] = trim($value);
                  }

                  // Resolve human-readable names if available
                  $assetLookup = null;
                  $assignedLookup = null;
                  $locationLookup = null;

                  if (!empty($parsed['Asset ID'])) {
                    // Support both asset_id code and numeric ID values in details
                    $assetLookup = dbRow('SELECT device_name FROM assets WHERE asset_id = ?', [$parsed['Asset ID']]);
                    if (!$assetLookup && is_numeric($parsed['Asset ID'])) {
                      $assetLookup = dbRow('SELECT device_name FROM assets WHERE id = ?', [(int)$parsed['Asset ID']]);
                    }
                  }

                  if (!empty($parsed['Assigned to'])) {
                    $assignedLookup = dbRow('SELECT full_name FROM sys_users WHERE username = ?', [$parsed['Assigned to']]);
                  }

                  if (!empty($parsed['Location ID'])) {
                    $locationLookup = dbRow('SELECT building_name FROM locations WHERE location_id = ?', [$parsed['Location ID']]);
                  }

                  $assetName = $assetLookup['device_name'] ?? $parsed['Asset ID'] ?? null;
                  $assignedName = $assignedLookup['full_name'] ?? $parsed['Assigned to'] ?? null;
                  $locationName = $locationLookup['building_name'] ?? $parsed['Location ID'] ?? null;

                  // Handle assignment ID lookup if present; provide asset/user context from assignment
                  if (!empty($parsed['Assignment ID'])) {
                    $assignment = dbRow('SELECT asset_id, assigned_to FROM assignments WHERE id = ?', [$parsed['Assignment ID']]);
                    if ($assignment) {
                      if (empty($assetName)) {
                        $assetLookup = dbRow('SELECT device_name FROM assets WHERE id = ?', [$assignment['asset_id']]);
                        $assetName = $assetLookup['device_name'] ?? $assetName;
                      }
                      if (empty($assignedName) && !empty($assignment['assigned_to'])) {
                        $assignedLookup = dbRow('SELECT full_name FROM sys_users WHERE username = ?', [$assignment['assigned_to']]);
                        $assignedName = $assignedLookup['full_name'] ?? $assignment['assigned_to'];
                      }
                    }
                  }

                  // Create readable descriptions with names
                  $description = match(true) {
                    str_contains($action, 'ADD ASSET') =>
                      "Asset <strong>" . ($assetName ?: 'unknown') . "</strong> added",

                    str_contains($action, 'UPDATE ASSET') =>
                      "Asset <strong>" . ($assetName ?: 'unknown') . "</strong> updated",

                    str_contains($action, 'ADD ASSIGNMENT') =>
                      "<strong>" . ($assetName ?: 'unknown') . "</strong> assigned to <strong>" . ($assignedName ?: 'unknown') . "</strong>",

                    str_contains($action, 'UPDATE ASSIGNMENT') =>
                      "<strong>" . ($assetName ?: 'unknown') . "</strong> assignment updated",

                    str_contains($action, 'RETURN ASSET') =>
                      "<strong>" . ($assetName ?: 'unknown') . "</strong> returned",

                    str_contains($action, 'ASSIGN ASSET') =>
                      "<strong>" . ($assetName ?: 'unknown') . "</strong> reassigned",

                    str_contains($action, 'DELETE ASSIGNMENT') =>
                      "<strong>" . ($assetName ?: 'unknown') . "</strong> assignment removed",

                    str_contains($action, 'ADD LOCATION') =>
                      "Location <strong>" . ($locationName ?: 'unknown') . "</strong> added",

                    str_contains($action, 'UPDATE LOCATION') =>
                      "Location <strong>" . ($locationName ?: 'unknown') . "</strong> updated",

                    str_contains($action, 'DELETE LOCATION') =>
                      "Location <strong>" . ($locationName ?: 'unknown') . "</strong> removed",

                    str_contains($action, 'ADD USER') =>
                      "User account created",

                    str_contains($action, 'UPDATE USER') =>
                      "User account updated",

                    str_contains($action, 'DELETE USER') =>
                      "User account removed",

                    str_contains($action, 'TOGGLE USER') =>
                      "User status changed",

                    default => htmlspecialchars($details)
                  };

                  echo $description;
                ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /grid -->

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="11" x2="19" y2="17"/><line x1="16" y1="14" x2="22" y2="14"/></svg>
      <span class="modal-title">Add New User</span>
      <button class="modal-close" onclick="Modal.close('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-add" method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="Juan dela Cruz">
          </div>
          <div class="form-group">
            <label>Username <span class="req">*</span></label>
            <input type="text" name="username" required placeholder="jdelacruz" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="user@company.com">
          </div>
          <div class="form-group">
            <label>Role <span class="req">*</span></label>
            <select name="role">
              <option>Viewer</option>
              <option>IT Staff</option>
              <option>Admin</option>
            </select>
          </div>
          <div class="form-group full-span">
            <label>Password <span class="req">*</span></label>
            <input type="password" name="password" required autocomplete="new-password" placeholder="Minimum 6 characters">
            <span class="form-hint">Stored as bcrypt hash — never in plain text</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      <span class="modal-title">Edit User</span>
      <button class="modal-close" onclick="Modal.close('modal-edit')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-edit" method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="record_id" id="edit-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" id="edit-fullname">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" id="edit-username" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="edit-email">
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" id="edit-role">
              <option>Viewer</option><option>IT Staff</option><option>Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit-status">
              <option>Active</option><option>Inactive</option>
            </select>
          </div>
          <div class="form-group full-span">
            <label>New Password <span style="color:var(--text-muted);font-weight:400">(leave blank to keep current)</span></label>
            <input type="password" name="new_password" autocomplete="new-password" placeholder="Enter new password to change">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update User</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   USERS MODULE — CLIENT SCRIPT
   Handles: add, edit, enable/disable, delete
   Live sync via Poller every 5 seconds
   ============================================================ */

// Current logged-in user ID — prevents self-deletion/self-disable
const CURRENT_USER_ID = <?php echo (int)$authUser['id']; ?>;

/** Safely escape a value for HTML output */
function esc(value) {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Build a <tr> HTML string from a user data object */
function buildRow(user) {
    const ROLE_COLORS = { Admin: '#dc2626', 'IT Staff': '#3b6ef0', Viewer: '#8b91a8' };
    const ROLE_BADGES = { Admin: 'badge-red', 'IT Staff': 'badge-blue', Viewer: 'badge-gray' };

    const avatarColor  = ROLE_COLORS[user.role] || '#8b91a8';
    const roleBadge    = ROLE_BADGES[user.role]  || 'badge-gray';
    const initial      = (user.full_name || user.username || '?')[0].toUpperCase();
    const isSelf       = user.id == CURRENT_USER_ID;
    const displayName  = user.full_name || user.username;
    const statusBadge  = user.status === 'Active' ? 'badge-green' : 'badge-gray';
    const toggleLabel  = user.status === 'Active' ? 'Disable' : 'Enable';
    const deleteButton = isSelf ? '' : '<button class="btn btn-danger btn-xs del-btn">Del</button>';
    const rowData      = JSON.stringify(user).replace(/'/g, '&#39;');

    return `
        <tr data-id="${user.id}" data-row='${rowData}'>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:32px;height:32px;border-radius:50%;
                                background:${avatarColor};display:flex;align-items:center;
                                justify-content:center;font-size:12px;font-weight:600;color:#fff">
                        ${initial}
                    </div>
                    <span class="cell-primary">${esc(displayName)}</span>
                </div>
            </td>
            <td class="cell-mono" style="color:var(--text-secondary)">${esc(user.username)}</td>
            <td><span class="badge ${roleBadge}">${esc(user.role)}</span></td>
            <td class="text-muted">${esc(user.email || '—')}</td>
            <td class="cell-mono text-muted">${user.last_login || 'Never'}</td>
            <td><span class="badge ${statusBadge}">${esc(user.status)}</span></td>
            <td>
                <div class="flex gap-2" style="flex-wrap:wrap">
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-ghost btn-xs tog-btn">${toggleLabel}</button>
                    ${deleteButton}
                </div>
            </td>
        </tr>`;
}

/** Populate the edit modal from a user data object */
function openEditModal(user) {
    document.getElementById('edit-id').value       = user.id;
    document.getElementById('edit-fullname').value = user.full_name || '';
    document.getElementById('edit-username').value = user.username;
    document.getElementById('edit-email').value    = user.email || '';

    setSelectValue('edit-role',   user.role);
    setSelectValue('edit-status', user.status);

    Modal.open('modal-edit');
}

/** Set a <select> to the option matching the given value */
function setSelectValue(elementId, value) {
    const select = document.getElementById(elementId);
    if (!select) return;
    Array.from(select.options).forEach(opt => { opt.selected = opt.value === value; });
}

/** Insert a new row at the top of the table */
function insertRow(user) {
    document.querySelector('#users-table tbody').insertAdjacentHTML('afterbegin', buildRow(user));
}

/** Replace an existing row in place */
function replaceRow(user) {
    const existing = document.querySelector(`#users-table tr[data-id="${user.id}"]`);
    if (!existing) return;
    const temp = document.createElement('tbody');
    temp.innerHTML = buildRow(user);
    existing.replaceWith(temp.firstElementChild);
}

/** Fade out and remove a row */
function removeRow(id) {
    const row = document.querySelector(`#users-table tr[data-id="${id}"]`);
    if (!row) return;
    row.style.transition = 'opacity .25s';
    row.style.opacity    = '0';
    setTimeout(() => row.remove(), 250);
}

/* ── Form: Add User ── */
document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'users', function(data) {
        if (!data.row) return;
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        insertRow(data.row);
    });
});

/* ── Form: Edit User ── */
document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'users', function(data) {
        if (!data.row) return;
        Modal.close('modal-edit');
        replaceRow(data.row);
    });
});

/* ── Action: Toggle Active/Inactive ── */
function toggleStatus(id) {
    AjaxForm.action('users', { action: 'toggle_status', record_id: id }, function(data) {
        if (data.row) replaceRow(data.row);
    });
}

/* ── Action: Delete ── */
function deleteUser(id) {
    AjaxForm.delete('users', id, () => removeRow(id));
}

/* ── Event Delegation ──
   Handles all button clicks for every row, including polling-injected rows.
─────────────────────────────────────────────────────────────── */
document.querySelector('#users-table tbody').addEventListener('click', function(e) {
    const editBtn   = e.target.closest('.edit-btn');
    const toggleBtn = e.target.closest('.tog-btn');
    const deleteBtn = e.target.closest('.del-btn');

    if (editBtn) {
        const row  = editBtn.closest('tr[data-row]');
        const user = JSON.parse(row.dataset.row.replace(/&#39;/g, "'"));
        openEditModal(user);
    }

    if (toggleBtn) {
        const row = toggleBtn.closest('tr[data-id]');
        if (row) toggleStatus(row.dataset.id);
    }

    if (deleteBtn) {
        const row = deleteBtn.closest('tr[data-id]');
        if (row && confirm(`Delete user account?`)) deleteUser(row.dataset.id);
    }
});

/* ── Live Polling ── */
Poller.init('users', function(freshRows) {
    const tbody     = document.querySelector('#users-table tbody');
    const domIds    = new Set([...tbody.querySelectorAll('tr[data-id]')].map(r => String(r.dataset.id)));
    const serverIds = new Set(freshRows.map(r => String(r.id)));

    freshRows.forEach(user => {
        if (!domIds.has(String(user.id))) insertRow(user);
    });

    domIds.forEach(id => {
        if (!serverIds.has(id)) removeRow(id);
    });

    freshRows.forEach(user => {
        const existing = tbody.querySelector(`tr[data-id="${user.id}"]`);
        if (!existing) return;
        const temp   = document.createElement('tbody');
        temp.innerHTML = buildRow(user);
        const newRow = temp.firstElementChild;
        if (existing.innerHTML !== newRow.innerHTML) existing.replaceWith(newRow);
    });
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    initTableSearch('search-input', 'users-table', [0, 1, 3]);
    initSelectFilter('filter-role',   'users-table', 2);
    initSelectFilter('filter-status', 'users-table', 5);
});
</script>
<?php // end ?>
