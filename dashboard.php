<?php
/* ============================================================
   MODULE 5 — DASHBOARD / REPORTS
   File: dashboard.php
   Depends on: includes/db.php, includes/layout.php
   Reads from all tables but writes to none.
   Gracefully handles missing tables.
   ============================================================ */

require_once 'includes/auth.php';

/* ── Safe query helper — returns [] if table doesn't exist ── */
function safeQuery(string $sql, array $p = []): array {
    try { return dbQuery($sql, $p); } catch (Throwable $e) { return []; }
}
function safeRow(string $sql, array $p = []): ?array {
    try { return dbRow($sql, $p); } catch (Throwable $e) { return null; }
}

/* ── ASSET STATS ── */
$assetTotal   = (int)(safeRow('SELECT COUNT(*) AS n FROM assets')['n'] ?? 0);
$assetActive  = (int)(safeRow('SELECT COUNT(*) AS n FROM assets WHERE status="Active"')['n'] ?? 0);
$assetRepair  = (int)(safeRow('SELECT COUNT(*) AS n FROM assets WHERE status="Under Repair"')['n'] ?? 0);
$assetRetired = (int)(safeRow('SELECT COUNT(*) AS n FROM assets WHERE status="Retired"')['n'] ?? 0);

/* ── ASSIGNMENT STATS ── */
$assignTotal    = (int)(safeRow('SELECT COUNT(*) AS n FROM assignments')['n'] ?? 0);
$assignActive   = (int)(safeRow('SELECT COUNT(*) AS n FROM assignments WHERE status="Assigned"')['n'] ?? 0);

/* ── STOCK STATS ── */
$stockTotal   = (int)(safeRow('SELECT COUNT(*) AS n FROM stock_items')['n'] ?? 0);
$stockLow     = (int)(safeRow('SELECT COUNT(*) AS n FROM stock_items WHERE status="Low Stock"')['n'] ?? 0);
$stockOut     = (int)(safeRow('SELECT COUNT(*) AS n FROM stock_items WHERE status="Out of Stock"')['n'] ?? 0);

/* ── LOCATION STATS ── */
$locTotal = (int)(safeRow('SELECT COUNT(*) AS n FROM locations')['n'] ?? 0);

/* ── ASSETS BY TYPE ── */
$byType = safeQuery(
    'SELECT device_type, COUNT(*) AS total,
            SUM(status="Active") AS active,
            SUM(status="Under Repair") AS repair,
            SUM(status="Retired") AS retired
     FROM assets GROUP BY device_type ORDER BY total DESC'
);

/* ── ASSETS BY DEPARTMENT (via location) ── */
$byDept = safeQuery(
    'SELECT COALESCE(l.department,"Unassigned") AS dept, COUNT(a.id) AS total
     FROM assets a
     LEFT JOIN locations l ON a.location_id = l.id
     GROUP BY dept ORDER BY total DESC LIMIT 8'
);

/* ── WARRANTY EXPIRING SOON (next 90 days) ── */
$expiringSoon = safeQuery(
    'SELECT asset_id, device_name, brand, model, warranty_expiry,
            DATEDIFF(warranty_expiry, CURDATE()) AS days_left
     FROM assets
     WHERE warranty_expiry IS NOT NULL
       AND warranty_expiry >= CURDATE()
       AND warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY warranty_expiry ASC LIMIT 10'
);

/* ── ALREADY EXPIRED ── */
$expired = safeQuery(
    'SELECT asset_id, device_name, brand, warranty_expiry,
            ABS(DATEDIFF(warranty_expiry, CURDATE())) AS days_ago
     FROM assets WHERE warranty_expiry < CURDATE()
     ORDER BY warranty_expiry DESC LIMIT 6'
);

/* ── ASSET UTILIZATION RATE ── */
$assetUtilization = $assetTotal > 0 ? round(($assignActive / $assetTotal) * 100) : 0;

/* ── RECENTLY ADDED (last 7 days) ── */
$recentAssets = safeQuery(
    'SELECT asset_id, device_name, device_type, brand, status, created_at
     FROM assets ORDER BY created_at DESC LIMIT 8'
);

/* ── LOW / OUT STOCK ITEMS ── */
$lowStockItems = safeQuery(
    'SELECT * FROM stock_items WHERE status IN ("Low Stock","Out of Stock")
     ORDER BY status DESC, quantity ASC LIMIT 10'
);

/* ── RECENT ASSIGNMENTS ── */
$recentAssign = safeQuery(
    'SELECT asn.assigned_to, asn.department, asn.date_assigned, asn.status,
            a.asset_id, a.device_name
     FROM assignments asn
     LEFT JOIN assets a ON asn.asset_id = a.id
     ORDER BY asn.created_at DESC LIMIT 8'
);

/* ── FOR CHARTS (JSON) ── */
$typeLabels  = json_encode(array_column($byType, 'device_type'));
$typeTotals  = json_encode(array_map('intval', array_column($byType, 'total')));
$deptLabels  = json_encode(array_column($byDept, 'dept'));
$deptTotals  = json_encode(array_map('intval', array_column($byDept, 'total')));

$statusLabels = json_encode(['Active', 'Under Repair', 'Retired']);
$statusData   = json_encode([$assetActive, $assetRepair, $assetRetired]);

$page_title  = 'Dashboard';
$active_page = 'dashboard';
$extra_css   = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
include 'includes/layout.php';
?>

<!-- ── TOP KPI STRIP ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px">
  <?php
  $kpis = [
    ['Total Assets',      $assetTotal,   'monitor',   '',        'All registered devices'],
    ['Active Devices',    $assetActive,  'check',     'green',   "{$assetRepair} under repair · {$assetRetired} retired"],
    ['Low / Out Stock',   $stockLow + $stockOut, 'alert', 'yellow', "{$stockLow} low · {$stockOut} out of stock"],
    ['Assigned Devices',  $assignActive, 'users',     'purple',  "Out of {$assignTotal} total assignment records"],
  ];
  foreach ($kpis as [$label, $val, $icon, $color, $meta]):
  ?>
  <div class="stat-card <?= $color ?>" style="cursor:pointer;transition:all 0.2s">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <div class="stat-label"><?= $label ?></div>
      <div style="width:30px;height:30px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--text-muted)">
        <?php if($icon==='monitor'): ?><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        <?php elseif($icon==='check'): ?><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <?php elseif($icon==='alert'): ?><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php else: ?><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-value"><?= $val ?></div>
    <div class="stat-meta"><?= $meta ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── SECTION HEADER: Analytics ── -->
<div style="display:flex;align-items:center;gap:12px;margin:36px 0 20px;padding-bottom:12px;border-bottom:2px solid var(--border)">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  <h2 style="font-size:16px;font-weight:600;color:var(--text-primary);margin:0">System Analytics</h2>
  <div style="flex:1"></div>
  <a href="assets.php" class="btn btn-ghost btn-xs" style="font-size:11px">View All Assets →</a>
</div>

<!-- ── ROW 1: CHARTS ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:20px">

  <!-- Donut: Asset Status -->
  <div class="card" style="position:relative;overflow:hidden">
    <div class="card-header">
      <div class="card-title">Asset Status</div>
      <div class="card-subtitle">Current distribution</div>
    </div>
    <div class="card-body" style="display:flex;justify-content:center;align-items:center;min-height:220px">
      <canvas id="chart-status" style="max-height:220px"></canvas>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--border);background:var(--bg-elevated);font-size:11px;display:flex;justify-content:space-around;text-align:center">
      <div><div style="font-weight:600;color:var(--green)"><?= $assetActive ?></div><div style="color:var(--text-muted)">Active</div></div>
      <div><div style="font-weight:600;color:var(--yellow)"><?= $assetRepair ?></div><div style="color:var(--text-muted)">Repair</div></div>
      <div><div style="font-weight:600;color:var(--text-muted)"><?= $assetRetired ?></div><div style="color:var(--text-muted)">Retired</div></div>
    </div>
  </div>

  <!-- Bar: By Type -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Devices by Type</div>
      <div class="card-subtitle">All registered assets</div>
    </div>
    <div class="card-body" style="min-height:200px">
      <canvas id="chart-types" style="max-height:200px"></canvas>
    </div>
  </div>

  <!-- Horizontal Bar: By Department -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Assets by Department</div>
      <div class="card-subtitle">Via location mapping</div>
    </div>
    <div class="card-body" style="min-height:200px">
      <canvas id="chart-dept" style="max-height:200px"></canvas>
    </div>
  </div>
</div>

<!-- ── SECTION HEADER: Alerts & Warnings ── -->
<div style="display:flex;align-items:center;gap:12px;margin:36px 0 20px;padding-bottom:12px;border-bottom:2px solid var(--border)">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <h2 style="font-size:16px;font-weight:600;color:var(--text-primary);margin:0">Alerts & Warnings</h2>
  <div style="flex:1"></div>
  <span style="font-size:11px;color:var(--text-muted)"><?= count($expiringSoon) + count($expired) + count($lowStockItems) ?> total alerts</span>
</div>

<!-- ── ROW 2: TABLES ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:20px">

  <!-- Warranty Alerts -->
  <div class="card">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <div class="card-title">Warranty Expiring Soon</div>
        <div class="card-subtitle">Within 90 days</div>
      </div>
      <?php if ($expiringSoon): ?>
      <span class="badge badge-yellow ml-auto"><?= count($expiringSoon) ?></span>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <?php if (empty($expiringSoon) && empty($expired)): ?>
      <div class="empty-state" style="padding:32px">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="width:36px;height:36px"><polyline points="20 6 9 17 4 12"/></svg>
        <h3>All warranties are current</h3>
        <p>No expiries within 90 days.</p>
      </div>
      <?php else: ?>
      <table style="position:relative">
        <thead style="position:sticky;top:0;background:var(--bg-elevated);z-index:10"><tr><th>Asset ID</th><th>Device</th><th>Expiry</th><th>Days Left</th></tr></thead>
        <tbody>
          <?php foreach ($expiringSoon as $w): ?>
          <tr>
            <td><span class="asset-id"><?= htmlspecialchars($w['asset_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($w['device_name']) ?></span>
              <div class="text-muted"><?= htmlspecialchars($w['brand']) ?></div>
            </td>
            <td class="cell-mono"><?= date('d M Y', strtotime($w['warranty_expiry'])) ?></td>
            <td>
              <?php $d = (int)$w['days_left']; $cls = $d <= 30 ? 'badge-red' : 'badge-yellow'; ?>
              <span class="badge <?= $cls ?>"><?= $d ?> days</span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php foreach ($expired as $w): ?>
          <tr>
            <td><span class="asset-id"><?= htmlspecialchars($w['asset_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($w['device_name']) ?></span>
              <div class="text-muted"><?= htmlspecialchars($w['brand']) ?></div>
            </td>
            <td class="cell-mono" style="color:var(--red)"><?= date('d M Y', strtotime($w['warranty_expiry'])) ?></td>
            <td><span class="badge badge-red">Expired <?= $w['days_ago'] ?>d ago</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Low Stock Alerts -->
  <div class="card">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      <div>
        <div class="card-title">Stock Alerts</div>
        <div class="card-subtitle">Items needing attention</div>
      </div>
      <?php if ($lowStockItems): ?>
      <span class="badge badge-red ml-auto"><?= count($lowStockItems) ?></span>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <?php if (empty($lowStockItems)): ?>
      <div class="empty-state" style="padding:32px">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="width:36px;height:36px"><polyline points="20 6 9 17 4 12"/></svg>
        <h3>All items well-stocked</h3>
        <p>No low stock alerts at this time.</p>
      </div>
      <?php else: ?>
      <table style="position:relative">
        <thead style="position:sticky;top:0;background:var(--bg-elevated);z-index:10"><tr><th>Item</th><th>Category</th><th>Qty</th><th>Min</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($lowStockItems as $s): ?>
          <tr>
            <td><span class="cell-primary"><?= htmlspecialchars($s['item_name']) ?></span></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($s['category']) ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div class="cell-mono" style="color:<?= $s['quantity'] == 0 ? 'var(--red)' : 'var(--yellow)' ?>;font-weight:600;min-width:50px"><?= $s['quantity'] ?> <?= htmlspecialchars($s['unit']) ?></div>
              </div>
              <div style="width:60px;height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden">
                <div style="height:100%;background:<?= $s['quantity'] == 0 ? 'var(--red)' : 'var(--yellow)' ?>;width:<?= min(($s['quantity'] / max($s['min_stock_level'], 1)) * 100, 100) ?>%"></div>
              </div>
            </td>
            <td class="cell-mono text-muted"><?= $s['min_stock_level'] ?></td>
            <td>
              <?php if ($s['status'] === 'Out of Stock'): ?>
              <span class="badge badge-red">Out of Stock</span>
              <?php else: ?>
              <span class="badge badge-yellow">Low Stock</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── MINI CARDS: Key Metrics ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:36px 0 20px">
  <!-- Asset Utilization -->
  <div class="card">
    <div class="card-body" style="text-align:center">
      <div style="font-size:32px;font-weight:700;color:var(--accent);margin-bottom:6px"><?= $assetUtilization ?>%</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Asset Utilization</div>
      <div style="height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden;margin-bottom:6px">
        <div style="height:100%;background:var(--accent);width:<?= $assetUtilization ?>%"></div>
      </div>
      <div style="font-size:10px;color:var(--text-secondary)"><?= $assignActive ?> of <?= $assetTotal ?> devices</div>
    </div>
  </div>
  <!-- Stock Health -->
  <div class="card">
    <div class="card-body" style="text-align:center">
      <div style="font-size:32px;font-weight:700;color:var(--green);margin-bottom:6px"><?= $stockTotal - ($stockLow + $stockOut) ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Well-Stocked Items</div>
      <div style="height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden;margin-bottom:6px">
        <div style="height:100%;background:var(--green);width:<?= $stockTotal > 0 ? round((($stockTotal - ($stockLow + $stockOut)) / $stockTotal) * 100) : 0 ?>%"></div>
      </div>
      <div style="font-size:10px;color:var(--text-secondary)"><?= $stockTotal ?> total items</div>
    </div>
  </div>
  <!-- Active Assignments -->
  <div class="card">
    <div class="card-body" style="text-align:center">
      <div style="font-size:32px;font-weight:700;color:var(--purple);margin-bottom:6px"><?= $assignActive ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Active Assignments</div>
      <div style="height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden;margin-bottom:6px">
        <div style="height:100%;background:var(--purple);width:<?= $assignTotal > 0 ? round(($assignActive / $assignTotal) * 100) : 0 ?>%"></div>
      </div>
      <div style="font-size:10px;color:var(--text-secondary)"><?= $assignTotal ?> total records</div>
    </div>
  </div>
  <!-- Alert Load -->
  <div class="card">
    <div class="card-body" style="text-align:center">
      <div style="font-size:32px;font-weight:700;color:var(--red);margin-bottom:6px"><?= count($expiringSoon) + count($expired) + count($lowStockItems) ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Pending Alerts</div>
      <div style="height:4px;background:var(--bg-elevated);border-radius:2px;overflow:hidden;margin-bottom:6px">
        <div style="height:100%;background:var(--red);width:<?= (count($expiringSoon) + count($expired) + count($lowStockItems)) > 0 ? 100 : 0 ?>%"></div>
      </div>
      <div style="font-size:10px;color:var(--text-secondary)">Needs attention</div>
    </div>
  </div>
</div>

<!-- ── SECTION HEADER: Recent Activity ── -->
<div style="display:flex;align-items:center;gap:12px;margin:36px 0 20px;padding-bottom:12px;border-bottom:2px solid var(--border)">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-primary)" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>
  <h2 style="font-size:16px;font-weight:600;color:var(--text-primary);margin:0">Recent Activity</h2>
  <div style="flex:1"></div>
  <a href="assignment.php" class="btn btn-ghost btn-xs" style="font-size:11px">View All →</a>
</div>

<!-- ── ROW 3: RECENT LISTS ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:20px">

  <!-- Recently Added Assets -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Recently Added Assets</div>
      <div class="card-subtitle">Latest registrations</div>
    </div>
    <div class="table-wrap">
      <?php if (empty($recentAssets)): ?>
      <div class="empty-state" style="padding:32px"><p>No assets yet.</p></div>
      <?php else: ?>
      <table style="position:relative">
        <thead style="position:sticky;top:0;background:var(--bg-elevated);z-index:10"><tr><th>Asset ID</th><th>Device</th><th>Type</th><th>Status</th><th>Added</th></tr></thead>
        <tbody>
          <?php foreach ($recentAssets as $a): ?>
          <tr>
            <td><span class="asset-id"><?= htmlspecialchars($a['asset_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($a['device_name']) ?></span>
              <div class="text-muted"><?= htmlspecialchars($a['brand']) ?></div>
            </td>
            <td><?= htmlspecialchars($a['device_type']) ?></td>
            <td><?php
              echo match($a['status']) {
                'Active'       => '<span class="badge badge-green">Active</span>',
                'Under Repair' => '<span class="badge badge-yellow">Under Repair</span>',
                default        => '<span class="badge badge-gray">Retired</span>',
              };
            ?></td>
            <td class="text-muted cell-mono"><?= date('d M', strtotime($a['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Assignments -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Recent Assignments</div>
      <div class="card-subtitle">Latest device allocations</div>
    </div>
    <div class="table-wrap">
      <?php if (empty($recentAssign)): ?>
      <div class="empty-state" style="padding:32px"><p>No assignments yet.</p></div>
      <?php else: ?>
      <table style="position:relative">
        <thead style="position:sticky;top:0;background:var(--bg-elevated);z-index:10"><tr><th>Asset</th><th>Assigned To</th><th>Dept</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentAssign as $r): ?>
          <tr>
            <td>
              <span class="asset-id"><?= htmlspecialchars($r['asset_id'] ?? 'N/A') ?></span>
              <div class="text-muted"><?= htmlspecialchars($r['device_name'] ?? '') ?></div>
            </td>
            <td class="cell-primary"><?= htmlspecialchars($r['assigned_to']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($r['department']) ?></td>
            <td class="cell-mono text-muted"><?= date('d M Y', strtotime($r['date_assigned'])) ?></td>
            <td><?php
              echo match($r['status']) {
                'Assigned'  => '<span class="badge badge-blue">Assigned</span>',
                'Returned'  => '<span class="badge badge-green">Returned</span>',
                default     => '<span class="badge badge-gray">Available</span>',
              };
            ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── QUICK SUMMARY FOOTER ── -->
<div class="card" style="margin-bottom:8px">
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;text-align:center">
      <?php
        $summary = [
          ['Total Assets',    $assetTotal,   'var(--text-primary)',  'assets.php'],
          ['Locations',       $locTotal,     'var(--accent)',        'location.php'],
          ['Assignments',     $assignTotal,  'var(--purple)',        'assignment.php'],
          ['Stock Items',     $stockTotal,   'var(--green)',         'stock.php'],
          ['Low/Out Stock',   $stockLow+$stockOut, 'var(--yellow)', 'stock.php'],
          ['Warranty Alerts', count($expiringSoon)+count($expired), 'var(--red)', 'assets.php'],
        ];
        foreach ($summary as [$l, $v, $c, $link]):
      ?>
      <a href="<?= $link ?>" style="text-decoration:none;cursor:pointer;transition:all 0.2s;border-radius:var(--radius-sm);padding:12px;display:block;background:var(--bg-elevated);border:1px solid transparent;hover:border-color:<?= $c ?>" onmouseover="this.style.borderColor='<?= $c ?>'" onmouseout="this.style.borderColor='transparent'">
        <div style="font-size:22px;font-weight:700;font-family:var(--font-mono);color:<?= $c ?>"><?= $v ?></div>
        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-top:4px"><?= $l ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$extra_js = <<<JSBLOCK
<script>
document.addEventListener('DOMContentLoaded', () => {
  const theme = {
    grid:   'rgba(255,255,255,.06)',
    text:   '#8a91a8',
    accent: '#4f8ef7',
    green:  '#34d399',
    yellow: '#fbbf24',
    red:    '#f87171',
    purple: '#a78bfa',
  };

  Chart.defaults.color      = theme.text;
  Chart.defaults.font.family = "'IBM Plex Mono', monospace";
  Chart.defaults.font.size   = 11;

  /* ── Donut: Status ── */
  new Chart(document.getElementById('chart-status'), {
    type: 'doughnut',
    data: {
      labels: {$statusLabels},
      datasets: [{
        data: {$statusData},
        backgroundColor: [theme.green, theme.yellow, theme.text],
        borderColor: '#141720',
        borderWidth: 3,
        hoverOffset: 6,
      }]
    },
    options: {
      cutout: '68%',
      plugins: {
        legend: { position: 'bottom', labels: { padding: 16, boxWidth: 10, borderRadius: 3 } }
      }
    }
  });

  /* ── Bar: Device Types ── */
  new Chart(document.getElementById('chart-types'), {
    type: 'bar',
    data: {
      labels: {$typeLabels},
      datasets: [{
        label: 'Devices',
        data: {$typeTotals},
        backgroundColor: theme.accent + '99',
        borderColor: theme.accent,
        borderWidth: 1,
        borderRadius: 4,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: theme.grid } },
        y: { grid: { color: theme.grid }, beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  /* ── Horizontal Bar: By Department ── */
  new Chart(document.getElementById('chart-dept'), {
    type: 'bar',
    data: {
      labels: {$deptLabels},
      datasets: [{
        label: 'Assets',
        data: {$deptTotals},
        backgroundColor: theme.purple + '99',
        borderColor: theme.purple,
        borderWidth: 1,
        borderRadius: 4,
      }]
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: theme.grid }, beginAtZero: true, ticks: { stepSize: 1 } },
        y: { grid: { color: theme.grid } }
      }
    }
  });
});
</script>
JSBLOCK;
include 'includes/layout_end.php';
?>
