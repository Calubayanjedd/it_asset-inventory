<?php
/* ============================================================
   MODULE 4 — STOCK / CONSUMABLES
   File: stock.php
   Depends on: includes/db.php, includes/layout.php
   Completely standalone — no FK to assets or assignments
   ============================================================ */

require_once 'includes/auth.php';

$success = $error = '';


/* ── FETCH ── */
$items = dbQuery('SELECT * FROM stock_items ORDER BY status ASC, item_name ASC');

/* Stock movements log (last 30) */
$movements = dbQuery(
    'SELECT m.*, s.item_name, s.unit FROM stock_movements m
     LEFT JOIN stock_items s ON m.stock_item_id = s.id
     ORDER BY m.created_at DESC LIMIT 30'
);

/* ── STATS ── */
$total    = count($items);
$ok       = count(array_filter($items, fn($r) => $r['status'] === 'OK'));
$low      = count(array_filter($items, fn($r) => $r['status'] === 'Low Stock'));
$out      = count(array_filter($items, fn($r) => $r['status'] === 'Out of Stock'));

/* ── CATEGORIES for filter ── */
$categories = array_unique(array_column($items, 'category'));
sort($categories);

/* ── NEXT ITEM ID ── */
$nextNum    = $total + 1;
$suggestedId = 'ITM-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

/* ── STATUS BADGE ── */
function stockBadge(string $s): string {
    return match($s) {
        'OK'           => '<span class="badge badge-green">OK</span>',
        'Low Stock'    => '<span class="badge badge-yellow">Low Stock</span>',
        'Out of Stock' => '<span class="badge badge-red">Out of Stock</span>',
        default        => '<span class="badge badge-gray">' . htmlspecialchars($s) . '</span>',
    };
}

$page_title  = 'Stock / Consumables';
$active_page = 'stock';
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
    <div class="stat-label">Total Items</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-meta">Tracked consumables</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Well Stocked</div>
    <div class="stat-value"><?= $ok ?></div>
    <div class="stat-meta">Above minimum level</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-label">Low Stock</div>
    <div class="stat-value"><?= $low ?></div>
    <div class="stat-meta">Needs restocking</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Out of Stock</div>
    <div class="stat-value"><?= $out ?></div>
    <div class="stat-meta">Immediate action needed</div>
  </div>
</div>

<!-- MAIN SPLIT LAYOUT -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

  <!-- LEFT: ITEMS TABLE -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Consumables Inventory</div>
        <div class="card-subtitle">Ink, toner, cables, peripherals &amp; more</div>
      </div>
      <div class="ml-auto flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="Modal.open('modal-add')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Item
        </button>
      </div>
    </div>

    <div style="padding:14px 22px 0">
      <div class="filter-bar">
        <div class="search-box">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="search-input" placeholder="Search item, supplier…">
        </div>
        <select id="filter-cat" class="filter-select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c) echo "<option>" . htmlspecialchars($c) . "</option>"; ?>
        </select>
        <select id="filter-status" class="filter-select">
          <option value="">All Status</option>
          <option>OK</option><option>Low Stock</option><option>Out of Stock</option>
        </select>
      </div>
    </div>

    <div class="table-wrap">
      <table id="stock-table">
        <thead>
          <tr>
            <th>Item ID</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Qty</th>
            <th>Min Level</th>
            <th>Unit Cost</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <h3>No items tracked yet</h3>
              <p>Click "Add Item" to start tracking consumables.</p>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach ($items as $item): ?>
          <?php
            $pct = $item['min_stock_level'] > 0
                 ? min(100, round($item['quantity'] / $item['min_stock_level'] * 100))
                 : 100;
            $barColor = $item['status'] === 'OK' ? 'var(--green)' : ($item['status'] === 'Low Stock' ? 'var(--yellow)' : 'var(--red)');
          ?>
          <tr data-id="<?= $item['id'] ?>">
            <td><span class="asset-id"><?= htmlspecialchars($item['item_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($item['item_name']) ?></span>
              <?php if ($item['supplier']): ?>
              <div class="text-muted"><?= htmlspecialchars($item['supplier']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($item['category']) ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="cell-primary" style="font-family:var(--font-mono);min-width:32px"><?= $item['quantity'] ?> <span style="color:var(--text-muted);font-size:11px"><?= htmlspecialchars($item['unit']) ?></span></span>
              </div>
              <div style="background:var(--bg-base);border-radius:99px;height:3px;width:80px;margin-top:5px;overflow:hidden">
                <div style="height:3px;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:99px;transition:width .4s"></div>
              </div>
            </td>
            <td class="cell-mono"><?= $item['min_stock_level'] ?> <?= htmlspecialchars($item['unit']) ?></td>
            <td class="cell-mono"><?= $item['unit_cost'] !== null ? '₱' . number_format($item['unit_cost'], 2) : '—' ?></td>
            <td><?= stockBadge($item['status']) ?></td>
            <td>
              <div class="flex gap-2" style="flex-wrap:wrap">
                <button class="btn btn-secondary btn-xs" onclick='openRestockModal(<?= json_encode($item) ?>)' title="Restock">+In</button>
                <button class="btn btn-ghost btn-xs" onclick='openIssueModal(<?= json_encode($item) ?>)' title="Issue">−Out</button>
                <button class="btn btn-ghost btn-xs" onclick='openEditModal(<?= json_encode($item) ?>)'>Edit</button>
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

  <!-- RIGHT: MOVEMENT LOG -->
  <div class="card" style="position:sticky;top:78px">
    <div class="card-header">
      <div>
        <div class="card-title">Movement Log</div>
        <div class="card-subtitle">Last 30 transactions</div>
      </div>
    </div>
    <div style="max-height:520px;overflow-y:auto">
      <?php if (empty($movements)): ?>
      <div class="empty-state" style="padding:32px">
        <p>No movements recorded yet.</p>
      </div>
      <?php else: ?>
      <?php foreach ($movements as $mv): ?>
      <div style="display:flex;align-items:flex-start;gap:10px;padding:11px 18px;border-bottom:1px solid var(--border)">
        <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;
             background:<?= $mv['movement_type'] === 'IN' ? 'var(--green-bg)' : 'var(--red-bg)' ?>;
             color:<?= $mv['movement_type'] === 'IN' ? 'var(--green)' : 'var(--red)' ?>;
             font-size:14px;font-weight:700">
          <?= $mv['movement_type'] === 'IN' ? '+' : '−' ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;color:var(--text-primary);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($mv['item_name'] ?? '—') ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            <?= $mv['notes'] ?: ($mv['movement_type'] === 'IN' ? 'Restocked' : 'Issued') ?>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-family:var(--font-mono);font-size:12px;font-weight:600;
               color:<?= $mv['movement_type'] === 'IN' ? 'var(--green)' : 'var(--red)' ?>">
            <?= $mv['movement_type'] === 'IN' ? '+' : '−' ?><?= $mv['quantity'] ?>
            <span style="color:var(--text-muted);font-size:10px"><?= htmlspecialchars($mv['unit'] ?? '') ?></span>
          </div>
          <div style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono)">
            <?= date('d M H:i', strtotime($mv['created_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div><!-- /grid -->

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span class="modal-title">Add Stock Item</span>
      <button class="modal-close" onclick="Modal.close('modal-add')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-add" method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Item ID <span class="req">*</span></label>
            <input type="text" name="item_id" required value="<?= htmlspecialchars($suggestedId) ?>">
          </div>
          <div class="form-group">
            <label>Item Name <span class="req">*</span></label>
            <input type="text" name="item_name" required placeholder="e.g. HP 680 Black Ink">
          </div>
          <div class="form-group">
            <label>Category <span class="req">*</span></label>
            <select name="category">
              <option>Ink</option><option>Toner</option><option>LAN Cable</option>
              <option>RJ45 Connector</option><option>USB Cable</option><option>HDMI Cable</option>
              <option>Mouse</option><option>Keyboard</option><option>Patch Panel</option>
              <option>Power Strip</option><option>Battery</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Unit</label>
            <select name="unit">
              <option value="pcs">pcs</option><option value="box">box</option>
              <option value="ream">ream</option><option value="meters">meters</option>
              <option value="rolls">rolls</option><option value="sets">sets</option>
            </select>
          </div>
          <div class="form-group">
            <label>Quantity <span class="req">*</span></label>
            <input type="number" name="quantity" required min="0" value="0">
          </div>
          <div class="form-group">
            <label>Minimum Stock Level <span class="req">*</span></label>
            <input type="number" name="min_stock_level" required min="0" value="5">
            <span class="form-hint">Alert triggers below this</span>
          </div>
          <div class="form-group">
            <label>Unit Cost (₱)</label>
            <input type="number" name="unit_cost" step="0.01" min="0" placeholder="0.00">
          </div>
          <div class="form-group">
            <label>Supplier</label>
            <input type="text" name="supplier" placeholder="Supplier name">
          </div>
          <div class="form-group full-span">
            <label>Storage Location</label>
            <input type="text" name="location_ref" placeholder="e.g. Cabinet A, Shelf 2">
          </div>
          <div class="form-group full-span">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional remarks…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      <span class="modal-title">Edit Item</span>
      <button class="modal-close" onclick="Modal.close('modal-edit')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-edit" method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="record_id" id="edit-id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Item ID</label><input type="text" name="item_id" id="edit-item-id" required></div>
          <div class="form-group"><label>Item Name</label><input type="text" name="item_name" id="edit-item-name" required></div>
          <div class="form-group">
            <label>Category</label>
            <select name="category" id="edit-category">
              <option>Ink</option><option>Toner</option><option>LAN Cable</option>
              <option>RJ45 Connector</option><option>USB Cable</option><option>HDMI Cable</option>
              <option>Mouse</option><option>Keyboard</option><option>Patch Panel</option>
              <option>Power Strip</option><option>Battery</option><option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Unit</label>
            <select name="unit" id="edit-unit">
              <option value="pcs">pcs</option><option value="box">box</option>
              <option value="ream">ream</option><option value="meters">meters</option>
              <option value="rolls">rolls</option><option value="sets">sets</option>
            </select>
          </div>
          <div class="form-group"><label>Quantity</label><input type="number" name="quantity" id="edit-qty" min="0"></div>
          <div class="form-group"><label>Min Stock Level</label><input type="number" name="min_stock_level" id="edit-min" min="0"></div>
          <div class="form-group"><label>Unit Cost (₱)</label><input type="number" name="unit_cost" id="edit-cost" step="0.01" min="0"></div>
          <div class="form-group"><label>Supplier</label><input type="text" name="supplier" id="edit-supplier"></div>
          <div class="form-group full-span"><label>Storage Location</label><input type="text" name="location_ref" id="edit-locref"></div>
          <div class="form-group full-span"><label>Notes</label><textarea name="notes" id="edit-notes"></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ RESTOCK MODAL ══ -->
<div class="modal-overlay" id="modal-restock">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      <span class="modal-title">Restock Item</span>
      <button class="modal-close" onclick="Modal.close('modal-restock')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-restock" method="POST">
      <input type="hidden" name="action" value="restock">
      <input type="hidden" name="record_id" id="restock-id">
      <div class="modal-body">
        <p id="restock-name" style="color:var(--text-primary);font-weight:600;margin-bottom:16px"></p>
        <div class="form-group">
          <label>Quantity to Add <span class="req">*</span></label>
          <input type="number" name="add_qty" id="restock-qty" required min="1" value="1">
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:10px">
          Current stock: <strong id="restock-current" style="color:var(--text-primary)"></strong>
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-restock')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:var(--green)">+ Restock</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ ISSUE MODAL ══ -->
<div class="modal-overlay" id="modal-issue">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><polyline points="8 8 12 4 16 8"/><line x1="12" y1="4" x2="12" y2="13"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      <span class="modal-title">Issue / Consume</span>
      <button class="modal-close" onclick="Modal.close('modal-issue')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-issue" method="POST">
      <input type="hidden" name="action" value="issue">
      <input type="hidden" name="record_id" id="issue-id">
      <div class="modal-body">
        <p id="issue-name" style="color:var(--text-primary);font-weight:600;margin-bottom:16px"></p>
        <div class="form-group">
          <label>Quantity to Issue <span class="req">*</span></label>
          <input type="number" name="sub_qty" required min="1" value="1">
        </div>
        <div class="form-group" style="margin-top:14px">
          <label>Purpose / Notes</label>
          <input type="text" name="issue_notes" placeholder="Who requested / purpose">
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:10px">
          Available: <strong id="issue-current" style="color:var(--text-primary)"></strong>
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="Modal.close('modal-issue')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:var(--yellow);color:#000">− Issue</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   STOCK MODULE — CLIENT SCRIPT
   Handles: add, edit, restock (+In), issue (−Out), delete
   Live sync via Poller every 5 seconds
   ============================================================ */

/** Safely escape a value for HTML output */
function esc(value) {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Build a <tr> HTML string from a stock item data object */
function buildRow(item) {
    const STATUS_BADGES = {
        'OK':           'badge-green',
        'Low Stock':    'badge-yellow',
        'Out of Stock': 'badge-red'
    };

    // Calculate stock level bar percentage (capped at 100%)
    const barPercent = item.min_stock_level > 0
        ? Math.min(100, Math.round(item.quantity / item.min_stock_level * 100))
        : 100;

    const barColors = {
        'OK':           'var(--green)',
        'Low Stock':    'var(--yellow)',
        'Out of Stock': 'var(--red)'
    };

    const unitCost = item.unit_cost != null
        ? '&#8369;' + parseFloat(item.unit_cost).toFixed(2)
        : '—';

    const rowData = JSON.stringify(item).replace(/'/g, '&#39;');

    return `
        <tr data-id="${item.id}" data-row='${rowData}'>
            <td><span class="asset-id">${esc(item.item_id)}</span></td>
            <td>
                <span class="cell-primary">${esc(item.item_name)}</span>
                ${item.supplier ? `<div class="text-muted">${esc(item.supplier)}</div>` : ''}
            </td>
            <td><span class="badge badge-gray">${esc(item.category)}</span></td>
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="cell-primary" style="font-family:var(--font-mono);min-width:32px">
                        ${item.quantity}
                        <span style="color:var(--text-muted);font-size:11px">${esc(item.unit)}</span>
                    </span>
                </div>
                <div style="background:var(--bg-base);border-radius:99px;height:3px;
                            width:80px;margin-top:5px;overflow:hidden">
                    <div style="height:3px;width:${barPercent}%;
                                background:${barColors[item.status] || 'var(--text-muted)'};
                                border-radius:99px"></div>
                </div>
            </td>
            <td class="cell-mono">${item.min_stock_level} ${esc(item.unit)}</td>
            <td class="cell-mono">${unitCost}</td>
            <td><span class="badge ${STATUS_BADGES[item.status] || 'badge-gray'}">${esc(item.status)}</span></td>
            <td>
                <div class="flex gap-2" style="flex-wrap:wrap">
                    <button class="btn btn-secondary btn-xs rst-btn" title="Restock">+In</button>
                    <button class="btn btn-ghost btn-xs iss-btn"     title="Issue">−Out</button>
                    <button class="btn btn-ghost btn-xs edit-btn">Edit</button>
                    <button class="btn btn-danger btn-xs del-btn">Del</button>
                </div>
            </td>
        </tr>`;
}

/** Populate the edit modal from a stock item data object */
function openEditModal(item) {
    document.getElementById('edit-id').value        = item.id;
    document.getElementById('edit-item-id').value   = item.item_id;
    document.getElementById('edit-item-name').value = item.item_name;
    document.getElementById('edit-qty').value        = item.quantity;
    document.getElementById('edit-min').value        = item.min_stock_level;
    document.getElementById('edit-cost').value       = item.unit_cost || '';
    document.getElementById('edit-supplier').value   = item.supplier || '';
    document.getElementById('edit-locref').value     = item.location_ref || '';
    document.getElementById('edit-notes').value      = item.notes || '';

    setSelectValue('edit-category', item.category);
    setSelectValue('edit-unit',     item.unit);

    Modal.open('modal-edit');
}

/** Populate the Restock modal */
function openRestockModal(item) {
    document.getElementById('restock-id').value          = item.id;
    document.getElementById('restock-name').textContent  = item.item_name;
    document.getElementById('restock-current').textContent = `${item.quantity} ${item.unit}`;
    document.getElementById('restock-qty').value          = 1;
    Modal.open('modal-restock');
}

/** Populate the Issue modal */
function openIssueModal(item) {
    document.getElementById('issue-id').value            = item.id;
    document.getElementById('issue-name').textContent    = item.item_name;
    document.getElementById('issue-current').textContent = `${item.quantity} ${item.unit}`;
    Modal.open('modal-issue');
}

/** Set a <select> to the option matching the given value */
function setSelectValue(elementId, value) {
    const select = document.getElementById(elementId);
    if (!select) return;
    Array.from(select.options).forEach(opt => { opt.selected = opt.value === value; });
}

/** Read data-row from the nearest ancestor element */
function getRowData(element) {
    const container = element.closest('[data-row]');
    if (!container) return null;
    return JSON.parse(container.dataset.row.replace(/&#39;/g, "'"));
}

/** Insert a new row at the top, removing the empty-state row if present */
function insertRow(item) {
    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();
    document.querySelector('#stock-table tbody').insertAdjacentHTML('afterbegin', buildRow(item));
}

/** Replace an existing row in place */
function replaceRow(item) {
    const existing = document.querySelector(`#stock-table tr[data-id="${item.id}"]`);
    if (!existing) return;
    const temp = document.createElement('tbody');
    temp.innerHTML = buildRow(item);
    existing.replaceWith(temp.firstElementChild);
}

/** Fade out and remove a row */
function removeRow(id) {
    const row = document.querySelector(`#stock-table tr[data-id="${id}"]`);
    if (!row) return;
    row.style.transition = 'opacity .25s';
    row.style.opacity    = '0';
    setTimeout(() => row.remove(), 250);
}

/* ── Form: Add Item ── */
document.getElementById('form-add').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'stock', function(data) {
        if (!data.row) return;
        Modal.close('modal-add');
        document.getElementById('form-add').reset();
        insertRow(data.row);
    });
});

/* ── Form: Edit Item ── */
document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'stock', function(data) {
        if (!data.row) return;
        Modal.close('modal-edit');
        replaceRow(data.row);
    });
});

/* ── Form: Restock ── */
document.getElementById('form-restock').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'stock', function(data) {
        if (!data.row) return;
        Modal.close('modal-restock');
        replaceRow(data.row);
    });
});

/* ── Form: Issue ── */
document.getElementById('form-issue').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'stock', function(data) {
        if (!data.row) return;
        Modal.close('modal-issue');
        replaceRow(data.row);
    });
});

/* ── Event Delegation ──
   Handles all button clicks for every row, including polling-injected rows.
─────────────────────────────────────────────────────────────── */
document.querySelector('#stock-table tbody').addEventListener('click', function(e) {
    const editBtn    = e.target.closest('.edit-btn');
    const restockBtn = e.target.closest('.rst-btn');
    const issueBtn   = e.target.closest('.iss-btn');
    const deleteBtn  = e.target.closest('.del-btn');

    if (editBtn)    openEditModal(getRowData(editBtn));
    if (restockBtn) openRestockModal(getRowData(restockBtn));
    if (issueBtn)   openIssueModal(getRowData(issueBtn));

    if (deleteBtn) {
        const row = deleteBtn.closest('tr[data-id]');
        if (row && confirm('Delete this stock item?')) {
            AjaxForm.delete('stock', row.dataset.id, () => removeRow(row.dataset.id));
        }
    }
});

/* ── Live Polling ── */
Poller.init('stock', function(freshRows) {
    const tbody     = document.querySelector('#stock-table tbody');
    const domIds    = new Set([...tbody.querySelectorAll('tr[data-id]')].map(r => String(r.dataset.id)));
    const serverIds = new Set(freshRows.map(r => String(r.id)));

    freshRows.forEach(item => {
        if (!domIds.has(String(item.id))) insertRow(item);
    });

    domIds.forEach(id => {
        if (!serverIds.has(id)) removeRow(id);
    });

    freshRows.forEach(item => {
        const existing = tbody.querySelector(`tr[data-id="${item.id}"]`);
        if (!existing) return;
        const temp   = document.createElement('tbody');
        temp.innerHTML = buildRow(item);
        const newRow = temp.firstElementChild;
        if (existing.innerHTML !== newRow.innerHTML) existing.replaceWith(newRow);
    });
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    initTableSearch('search-input', 'stock-table', [0, 1, 2]);
    initSelectFilter('filter-cat',    'stock-table', 2);
    initSelectFilter('filter-status', 'stock-table', 6);
});
</script>
<?php // end ?>
