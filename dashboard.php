<?php
/* ============================================================
   MODULE 5 — DASHBOARD
   Read-only. All data fetched fresh on page load.
   Charts auto-refresh every 30s via a lightweight AJAX call.
   ============================================================ */
require_once 'includes/auth.php';

/* Safe query helpers — return empty results if a table is missing */
function safeQuery(string $sql, array $p = []): array {
    try { return dbQuery($sql, $p); } catch (Throwable $e) { return []; }
}
function safeVal(string $sql, array $p = []): int {
    try { return (int)(dbRow($sql, $p)['n'] ?? 0); } catch (Throwable $e) { return 0; }
}

/* ── Asset counts ── */
$assetTotal   = safeVal('SELECT COUNT(*) AS n FROM assets');
$assetActive  = safeVal('SELECT COUNT(*) AS n FROM assets WHERE status="Active"');
$assetRepair  = safeVal('SELECT COUNT(*) AS n FROM assets WHERE status="Under Repair"');
$assetRetired = safeVal('SELECT COUNT(*) AS n FROM assets WHERE status="Retired"');

/* ── Assignment counts ── */
$assignTotal  = safeVal('SELECT COUNT(*) AS n FROM assignments');
$assignActive = safeVal('SELECT COUNT(*) AS n FROM assignments WHERE status="Assigned"');

/* ── Stock counts ── */
$stockTotal = safeVal('SELECT COUNT(*) AS n FROM stock_items');
$stockLow   = safeVal('SELECT COUNT(*) AS n FROM stock_items WHERE status="Low Stock"');
$stockOut   = safeVal('SELECT COUNT(*) AS n FROM stock_items WHERE status="Out of Stock"');

/* ── Location count ── */
$locTotal = safeVal('SELECT COUNT(*) AS n FROM locations');

/* ── Maintenance stats ── */
$maintTotal     = safeVal('SELECT COUNT(*) AS n FROM maintenance_logs');
$maintThisMonth = safeVal('SELECT COUNT(*) AS n FROM maintenance_logs WHERE MONTH(maintenance_date)=MONTH(CURDATE()) AND YEAR(maintenance_date)=YEAR(CURDATE())');
$maintPending   = safeVal('SELECT COUNT(*) AS n FROM maintenance_logs WHERE status="Pending"');
$maintCostMonth = (float)(dbRow('SELECT COALESCE(SUM(cost),0) AS n FROM maintenance_logs WHERE MONTH(maintenance_date)=MONTH(CURDATE()) AND YEAR(maintenance_date)=YEAR(CURDATE())') ?? ['n'=>0])['n'];

/* ── Maintenance by type (this month) ── */
$maintByType = safeQuery(
    'SELECT maintenance_type, COUNT(*) AS total
     FROM maintenance_logs
     WHERE MONTH(maintenance_date)=MONTH(CURDATE()) AND YEAR(maintenance_date)=YEAR(CURDATE())
     GROUP BY maintenance_type ORDER BY total DESC'
);

/* ── Recent maintenance (last 5) ── */
$recentMaint = safeQuery(
    'SELECT m.maintenance_type, m.performed_by, m.maintenance_date, m.status, m.cost,
            a.asset_id AS asset_code, a.device_name
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     ORDER BY m.maintenance_date DESC, m.created_at DESC LIMIT 5'
);

/* ── Upcoming scheduled maintenance ── */
$upcomingMaint = safeQuery(
    'SELECT m.next_schedule, m.performed_by, m.description,
            a.asset_id AS asset_code, a.device_name
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     WHERE m.next_schedule IS NOT NULL AND m.next_schedule >= CURDATE()
     ORDER BY m.next_schedule ASC LIMIT 5'
);

$dueSoonCount = safeVal(
    'SELECT COUNT(*) AS n
     FROM maintenance_logs
     WHERE next_schedule BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
);
$nextDueMaintenance = safeQuery(
    'SELECT m.next_schedule, a.asset_id AS asset_code, a.device_name, m.description
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     WHERE m.next_schedule BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY m.next_schedule ASC LIMIT 1'
);
$nextDueText = '';
if (!empty($nextDueMaintenance)) {
    $item = $nextDueMaintenance[0];
    $assetLabel = $item['asset_code'] ? htmlspecialchars($item['asset_code']) : 'Maintenance';
    $deviceLabel = $item['device_name'] ? ' — ' . htmlspecialchars($item['device_name']) : '';
    $nextDueText = sprintf('%s%s on %s', $assetLabel, $deviceLabel, date('d M Y', strtotime($item['next_schedule'])));
}

$maintenanceTrend = safeQuery(
    'SELECT DATE_FORMAT(maintenance_date, "%Y-%m") AS ym,
            DATE_FORMAT(maintenance_date, "%b") AS label,
            COUNT(*) AS total_jobs,
            COALESCE(SUM(cost),0) AS total_cost
     FROM maintenance_logs
     GROUP BY ym
     ORDER BY ym DESC
     LIMIT 12'
);
$maintenanceTrend = array_reverse($maintenanceTrend);
$maintenanceTrendYears = array_values(array_unique(array_map(fn($row) => date('Y', strtotime($row['ym'] . '-01')), $maintenanceTrend)));
$maintenanceTrendYearLabel = '';
if (!empty($maintenanceTrendYears)) {
    $maintenanceTrendYearLabel = count($maintenanceTrendYears) === 1
        ? $maintenanceTrendYears[0]
        : $maintenanceTrendYears[0] . '–' . end($maintenanceTrendYears);
}
$maintenanceTrendLabels = json_encode(array_column($maintenanceTrend, 'label'));
$maintenanceTrendJobs   = json_encode(array_map('intval', array_column($maintenanceTrend, 'total_jobs')));
$maintenanceTrendCost   = json_encode(array_map('floatval', array_column($maintenanceTrend, 'total_cost')));
$maintenanceTrendYear   = json_encode($maintenanceTrendYearLabel);

$maintTypeChart = json_encode($maintByType);

/* ── Chart data ── */
$byType = safeQuery(
    'SELECT device_type, COUNT(*) AS total
     FROM assets GROUP BY device_type ORDER BY total DESC'
);
$byDept = safeQuery(
    'SELECT COALESCE(l.department, "Unassigned") AS dept, COUNT(a.id) AS total
     FROM assets a LEFT JOIN locations l ON a.location_id = l.id
     GROUP BY dept ORDER BY total DESC LIMIT 8'
);
$byStatus = [
    ['label' => 'Active',       'value' => $assetActive,  'color' => '#16a34a'],
    ['label' => 'Under Repair', 'value' => $assetRepair,  'color' => '#b45309'],
    ['label' => 'Retired',      'value' => $assetRetired, 'color' => '#8b91a8'],
];

/* ── Warranty alerts ── */
$expiringSoon = safeQuery(
    'SELECT asset_id, device_name, brand, warranty_expiry,
            DATEDIFF(warranty_expiry, CURDATE()) AS days_left
     FROM assets
     WHERE warranty_expiry IS NOT NULL
       AND warranty_expiry >= CURDATE()
       AND warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY warranty_expiry ASC LIMIT 8'
);
$expired = safeQuery(
    'SELECT asset_id, device_name, brand, warranty_expiry,
            ABS(DATEDIFF(warranty_expiry, CURDATE())) AS days_ago
     FROM assets WHERE warranty_expiry < CURDATE()
     ORDER BY warranty_expiry DESC LIMIT 5'
);

/* ── Low stock items ── */
$lowStockItems = safeQuery(
    'SELECT item_name, category, quantity, unit, min_stock_level, status
     FROM stock_items
     WHERE status IN ("Low Stock", "Out of Stock")
     ORDER BY status DESC, quantity ASC LIMIT 8'
);

/* ── Recent activity (last 8 of each) ── */
$recentAssets = safeQuery(
    'SELECT asset_id, device_name, device_type, brand, status, created_at
     FROM assets ORDER BY created_at DESC LIMIT 8'
);
$recentAssign = safeQuery(
    'SELECT asn.assigned_to, asn.department, asn.date_assigned, asn.status,
            a.asset_id, a.device_name
     FROM assignments asn
     LEFT JOIN assets a ON asn.asset_id = a.id
     ORDER BY asn.created_at DESC LIMIT 8'
);

/* ── JSON for charts ── */
$chartStatus = json_encode($byStatus);
$chartTypes  = json_encode($byType);
$chartDept   = json_encode($byDept);

$page_title  = 'Dashboard';
$active_page = 'dashboard';
// Load Chart.js before the page body renders
$extra_css = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
include 'includes/layout.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     ROW 1 — PRIMARY KPI CARDS
     4 big numbers the hospital IT head looks at first
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">

  <!-- Total Assets -->
  <div class="stat-card" style="position:relative;overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <div class="stat-label">Total Assets</div>
        <div class="stat-value" style="font-size:36px;margin:6px 0"><?= $assetTotal ?></div>
        <div style="display:flex;gap:12px;font-size:11px;font-family:var(--font-mono)">
          <span style="color:var(--green)">● <?= $assetActive ?> active</span>
          <span style="color:var(--yellow)">● <?= $assetRepair ?> repair</span>
          <span style="color:var(--text-muted)">● <?= $assetRetired ?> retired</span>
        </div>
      </div>
      <div style="width:44px;height:44px;background:var(--accent-light);border-radius:var(--radius-md);
                  display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="var(--accent)" stroke-width="1.8">
          <rect x="2" y="3" width="20" height="14" rx="2"/>
          <line x1="8" y1="21" x2="16" y2="21"/>
          <line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Assigned Devices -->
  <div class="stat-card" style="position:relative;overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <div class="stat-label">Assigned Devices</div>
        <div class="stat-value" style="font-size:36px;margin:6px 0"><?= $assignActive ?></div>
        <div style="font-size:11px;font-family:var(--font-mono);color:var(--text-muted)">
          out of <?= $assignTotal ?> total assignments
        </div>
      </div>
      <div style="width:44px;height:44px;background:#ede9fe;border-radius:var(--radius-md);
                  display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="var(--purple)" stroke-width="1.8">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Stock Alerts -->
  <?php $stockAlert = $stockLow + $stockOut; ?>
  <div class="stat-card <?= $stockAlert > 0 ? 'yellow' : 'green' ?>" style="position:relative;overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <div class="stat-label">Stock Alerts</div>
        <div class="stat-value" style="font-size:36px;margin:6px 0"><?= $stockAlert ?></div>
        <div style="display:flex;gap:12px;font-size:11px;font-family:var(--font-mono)">
          <span style="color:var(--yellow)">● <?= $stockLow ?> low</span>
          <span style="color:var(--red)">● <?= $stockOut ?> out</span>
        </div>
      </div>
      <div style="width:44px;height:44px;background:<?= $stockAlert > 0 ? 'var(--yellow-bg)' : 'var(--green-bg)' ?>;
                  border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="<?= $stockAlert > 0 ? 'var(--yellow)' : 'var(--green)' ?>" stroke-width="1.8">
          <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/>
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
          <line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Warranty Alerts -->
  <?php $warrantyAlert = count($expiringSoon) + count($expired); ?>
  <div class="stat-card <?= count($expired) > 0 ? 'red' : ($warrantyAlert > 0 ? 'yellow' : 'green') ?>"
       style="position:relative;overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <div class="stat-label">Warranty Alerts</div>
        <div class="stat-value" style="font-size:36px;margin:6px 0"><?= $warrantyAlert ?></div>
        <div style="display:flex;gap:12px;font-size:11px;font-family:var(--font-mono)">
          <span style="color:var(--yellow)">● <?= count($expiringSoon) ?> expiring</span>
          <span style="color:var(--red)">● <?= count($expired) ?> expired</span>
        </div>
      </div>
      <div style="width:44px;height:44px;background:<?= count($expired) > 0 ? 'var(--red-bg)' : 'var(--yellow-bg)' ?>;
                  border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="<?= count($expired) > 0 ? 'var(--red)' : 'var(--yellow)' ?>" stroke-width="1.8">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ROW 2 — SECONDARY COUNTERS + CHARTS
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">

  <!-- Left: Asset breakdown donut -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Asset Status</div>
        <div class="card-subtitle">Current breakdown</div>
      </div>
    </div>
    <div class="card-body" style="display:flex;align-items:center;justify-content:center;min-height:220px">
      <canvas id="chart-status" style="max-height:220px"></canvas>
    </div>
  </div>

  <!-- Middle: Devices by type bar -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Devices by Type</div>
        <div class="card-subtitle"><?= $assetTotal ?> total registered</div>
      </div>
    </div>
    <div class="card-body" style="min-height:220px;display:flex;align-items:center">
      <canvas id="chart-types" style="max-height:220px;width:100%"></canvas>
    </div>
  </div>

  <!-- Right: Quick system numbers -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">System Summary</div>
        <div class="card-subtitle">All modules at a glance</div>
      </div>
    </div>
    <div class="card-body" style="padding:12px 16px">
      <?php
      $summary = [
          ['label' => 'Total Assets',    'value' => $assetTotal,   'color' => 'var(--accent)',  'href' => 'assets.php'],
          ['label' => 'Locations',        'value' => $locTotal,     'color' => 'var(--accent)',  'href' => 'location.php'],
          ['label' => 'Assignments',      'value' => $assignTotal,  'color' => 'var(--purple)',  'href' => 'assignment.php'],
          ['label' => 'Stock Items',      'value' => $stockTotal,   'color' => 'var(--green)',   'href' => 'stock.php'],
          ['label' => 'Low / Out Stock',  'value' => $stockAlert,   'color' => $stockAlert > 0 ? 'var(--yellow)' : 'var(--text-muted)', 'href' => 'stock.php'],
          ['label' => 'Under Repair',     'value' => $assetRepair,  'color' => $assetRepair > 0 ? 'var(--yellow)' : 'var(--text-muted)', 'href' => 'assets.php'],
          ['label' => 'Expired Warranty', 'value' => count($expired), 'color' => count($expired) > 0 ? 'var(--red)' : 'var(--text-muted)', 'href' => 'assets.php'],
          ['label' => 'Maintenance (Month)', 'value' => $maintThisMonth, 'color' => 'var(--accent)', 'href' => 'maintenance.php'],
          ['label' => 'Pending Maintenance', 'value' => $maintPending,   'color' => $maintPending > 0 ? 'var(--yellow)' : 'var(--text-muted)', 'href' => 'maintenance.php'],
      ];
      foreach ($summary as $s):
      ?>
      <a href="<?= $s['href'] ?>"
         style="display:flex;align-items:center;justify-content:space-between;
                padding:10px 12px;border-radius:var(--radius-sm);text-decoration:none;
                transition:background .15s;margin-bottom:4px"
         onmouseover="this.style.background='var(--bg-elevated)'"
         onmouseout="this.style.background='transparent'">
        <span style="font-size:13px;color:var(--text-secondary)"><?= $s['label'] ?></span>
        <span style="font-size:16px;font-weight:700;font-family:var(--font-mono);color:<?= $s['color'] ?>">
          <?= $s['value'] ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ROW 3 — ASSETS BY DEPARTMENT + ALERT PANELS
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

  <!-- Assets by department horizontal bar -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Assets by Department</div>
        <div class="card-subtitle">Via location mapping</div>
      </div>
    </div>
    <div class="card-body" style="min-height:220px;display:flex;align-items:center">
      <canvas id="chart-dept" style="max-height:260px;width:100%"></canvas>
    </div>
  </div>

  <!-- Warranty alerts panel -->
  <div class="card">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="var(--yellow)" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <div>
        <div class="card-title">Warranty Alerts</div>
        <div class="card-subtitle">Expiring within 90 days + expired</div>
      </div>
      <?php if ($warrantyAlert > 0): ?>
      <span class="badge badge-yellow ml-auto"><?= $warrantyAlert ?></span>
      <?php endif; ?>
    </div>
    <?php if (empty($expiringSoon) && empty($expired)): ?>
    <div class="empty-state" style="padding:40px 0">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.2" style="width:36px;height:36px;opacity:.25">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      <h3>All warranties current</h3>
      <p>No expiries within 90 days.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Asset ID</th><th>Device</th><th>Expiry Date</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($expiringSoon as $w):
              $d   = (int)$w['days_left'];
              $cls = $d <= 30 ? 'badge-red' : 'badge-yellow';
          ?>
          <tr>
            <td><span class="asset-id"><?= htmlspecialchars($w['asset_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($w['device_name']) ?></span>
              <div class="text-muted"><?= htmlspecialchars($w['brand']) ?></div>
            </td>
            <td class="cell-mono"><?= date('d M Y', strtotime($w['warranty_expiry'])) ?></td>
            <td><span class="badge <?= $cls ?>"><?= $d ?> days left</span></td>
          </tr>
          <?php endforeach; ?>
          <?php foreach ($expired as $w): ?>
          <tr>
            <td><span class="asset-id"><?= htmlspecialchars($w['asset_id']) ?></span></td>
            <td>
              <span class="cell-primary"><?= htmlspecialchars($w['device_name']) ?></span>
              <div class="text-muted"><?= htmlspecialchars($w['brand']) ?></div>
            </td>
            <td class="cell-mono" style="color:var(--red)">
              <?= date('d M Y', strtotime($w['warranty_expiry'])) ?>
            </td>
            <td><span class="badge badge-red">Expired <?= $w['days_ago'] ?>d ago</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ROW 4 — RECENT ACTIVITY + STOCK ALERTS
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">

  <!-- Recently added assets -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Recent Assets</div>
        <div class="card-subtitle">Latest registrations</div>
      </div>
      <a href="assets.php" class="btn btn-ghost btn-sm ml-auto">View all</a>
    </div>
    <?php if (empty($recentAssets)): ?>
    <div class="empty-state" style="padding:32px 0"><p>No assets yet.</p></div>
    <?php else: ?>
    <div style="overflow:hidden">
      <?php foreach ($recentAssets as $a):
          $statusColors = ['Active' => 'var(--green)', 'Under Repair' => 'var(--yellow)', 'Retired' => 'var(--text-muted)'];
          $statusColor  = $statusColors[$a['status']] ?? 'var(--text-muted)';
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;
                  border-bottom:1px solid var(--border);transition:background .12s"
           onmouseover="this.style.background='var(--bg-elevated)'"
           onmouseout="this.style.background='transparent'">
        <span style="width:7px;height:7px;border-radius:50%;background:<?= $statusColor ?>;flex-shrink:0"></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary);
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($a['device_name']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            <?= htmlspecialchars($a['brand']) ?> · <?= htmlspecialchars($a['device_type']) ?>
          </div>
        </div>
        <span style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono);white-space:nowrap">
          <?= date('d M', strtotime($a['created_at'])) ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent assignments -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Recent Assignments</div>
        <div class="card-subtitle">Latest allocations</div>
      </div>
      <a href="assignment.php" class="btn btn-ghost btn-sm ml-auto">View all</a>
    </div>
    <?php if (empty($recentAssign)): ?>
    <div class="empty-state" style="padding:32px 0"><p>No assignments yet.</p></div>
    <?php else: ?>
    <div style="overflow:hidden">
      <?php foreach ($recentAssign as $r):
          $statusColors = ['Assigned' => 'var(--accent)', 'Returned' => 'var(--green)', 'Available' => 'var(--text-muted)'];
          $dotColor     = $statusColors[$r['status']] ?? 'var(--text-muted)';
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;
                  border-bottom:1px solid var(--border);transition:background .12s"
           onmouseover="this.style.background='var(--bg-elevated)'"
           onmouseout="this.style.background='transparent'">
        <span style="width:7px;height:7px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0"></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary);
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($r['assigned_to']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            <?= htmlspecialchars($r['asset_id'] ?? 'N/A') ?> · <?= htmlspecialchars($r['department']) ?>
          </div>
        </div>
        <span style="font-size:10px;color:var(--text-muted);font-family:var(--font-mono);white-space:nowrap">
          <?= date('d M', strtotime($r['date_assigned'])) ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Stock alerts -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Stock Alerts</div>
        <div class="card-subtitle">Low and out-of-stock items</div>
      </div>
      <a href="stock.php" class="btn btn-ghost btn-sm ml-auto">View all</a>
    </div>
    <?php if (empty($lowStockItems)): ?>
    <div class="empty-state" style="padding:32px 0">
      <h3 style="font-size:13px">All items well-stocked</h3>
    </div>
    <?php else: ?>
    <div style="overflow:hidden">
      <?php foreach ($lowStockItems as $s):
          $isOut   = $s['status'] === 'Out of Stock';
          $dotColor = $isOut ? 'var(--red)' : 'var(--yellow)';
          $textColor= $isOut ? 'var(--red)'  : 'var(--yellow)';
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;
                  border-bottom:1px solid var(--border);transition:background .12s"
           onmouseover="this.style.background='var(--bg-elevated)'"
           onmouseout="this.style.background='transparent'">
        <span style="width:7px;height:7px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0"></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary);
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($s['item_name']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            <?= htmlspecialchars($s['category']) ?>
          </div>
        </div>
        <span style="font-size:13px;font-weight:700;font-family:var(--font-mono);color:<?= $textColor ?>">
          <?= $s['quantity'] ?>
          <span style="font-size:10px;font-weight:400;color:var(--text-muted)"><?= htmlspecialchars($s['unit']) ?></span>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MAINTENANCE ANALYTICS
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">

  <!-- Maintenance KPIs -->
  <div class="card">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
      </svg>
      <div>
        <div class="card-title">Maintenance Overview</div>
        <div class="card-subtitle">Current month summary</div>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <?php
      $maintKpis = [
        ['This Month',      $maintThisMonth, 'var(--accent)'],
        ['Pending',         $maintPending,   $maintPending > 0 ? 'var(--yellow)' : 'var(--text-muted)'],
        ['All Time Total',  $maintTotal,     'var(--text-primary)'],
        ['Cost This Month', '₱' . number_format($maintCostMonth, 0), $maintCostMonth > 0 ? 'var(--purple)' : 'var(--text-muted)'],
      ];
      foreach ($maintKpis as [$lbl, $val, $clr]):
      ?>
      <a href="maintenance.php"
         style="display:flex;align-items:center;justify-content:space-between;
                padding:11px 20px;border-bottom:1px solid var(--border);
                text-decoration:none;transition:background .12s"
         onmouseover="this.style.background='var(--bg-elevated)'"
         onmouseout="this.style.background='transparent'">
        <span style="font-size:13px;color:var(--text-secondary)"><?= $lbl ?></span>
        <span style="font-size:16px;font-weight:700;font-family:var(--font-mono);color:<?= $clr ?>">
          <?= $val ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Maintenance by type chart -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">By Type — This Month</div>
        <div class="card-subtitle">Breakdown of maintenance categories</div>
      </div>
    </div>
    <?php if (empty($maintByType)): ?>
    <div class="empty-state" style="padding:40px 0">
      <p style="font-size:13px">No maintenance this month yet.</p>
    </div>
    <?php else: ?>
    <div class="card-body" style="display:flex;align-items:center;justify-content:center;min-height:200px">
      <canvas id="chart-maint-type" style="max-height:200px"></canvas>
    </div>
    <?php endif; ?>
  </div>

  <!-- Upcoming scheduled maintenance -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Upcoming Schedule</div>
        <div class="card-subtitle">Next planned maintenance</div>
      </div>
      <?php if (!empty($upcomingMaint)): ?>
      <span class="badge badge-blue ml-auto"><?= count($upcomingMaint) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($dueSoonCount > 0): ?>
    <div style="margin:12px 20px 0;padding:12px 14px;border-radius:var(--radius-sm);
                background:var(--yellow-bg);color:var(--text-primary);display:flex;align-items:center;gap:10px">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="var(--yellow)" stroke-width="2">
        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/>
        <path d="M12 7v5"/>
        <circle cx="12" cy="17" r="1"/>
      </svg>
      <div style="font-size:13px;line-height:1.4">
        <strong><?= $dueSoonCount ?> maintenance schedule<?= $dueSoonCount !== 1 ? 's' : '' ?> due within 7 days</strong><br>
        <?= htmlspecialchars($nextDueText) ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (empty($upcomingMaint)): ?>
    <div class="empty-state" style="padding:40px 0">
      <p style="font-size:13px">No upcoming schedules.</p>
    </div>
    <?php else: ?>
    <div style="overflow:hidden">
      <?php foreach ($upcomingMaint as $u):
          $daysUntil = (strtotime($u['next_schedule']) - time()) / 86400;
          $urgency   = $daysUntil <= 3 ? 'var(--red)' : ($daysUntil <= 7 ? 'var(--yellow)' : 'var(--green)');
      ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 20px;
                  border-bottom:1px solid var(--border)">
        <span style="width:7px;height:7px;border-radius:50%;background:<?= $urgency ?>;
                     flex-shrink:0;margin-top:5px"></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary)">
            <?= htmlspecialchars($u['asset_code'] ?? 'General') ?>
            <?php if ($u['device_name']): ?>
            <span style="font-weight:400;color:var(--text-muted)">· <?= htmlspecialchars($u['device_name']) ?></span>
            <?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            <?= htmlspecialchars($u['description'] ?? 'Scheduled maintenance') ?>
          </div>
        </div>
        <span style="font-size:11px;font-family:var(--font-mono);color:<?= $urgency ?>;
                     white-space:nowrap;font-weight:600">
          <?= date('d M', strtotime($u['next_schedule'])) ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <div class="card-title">Maintenance Trend</div>
      <div class="card-subtitle">Last 12 months of jobs and cost</div>
    </div>
  </div>
  <?php if (empty($maintenanceTrend)): ?>
  <div class="empty-state" style="padding:40px 0">
    <p style="font-size:13px">No maintenance history is available yet.</p>
  </div>
  <?php else: ?>
  <div class="card-body" style="min-height:240px;position:relative">
    <canvas id="chart-maint-trend" style="width:100%;height:240px"></canvas>
  </div>
  <?php endif; ?>
</div>

<!-- Recent maintenance activity -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <div class="card-title">Recent Maintenance Activity</div>
      <div class="card-subtitle">Latest records across all months</div>
    </div>
    <a href="maintenance.php" class="btn btn-ghost btn-sm ml-auto">View all</a>
  </div>
  <?php if (empty($recentMaint)): ?>
  <div class="empty-state" style="padding:32px 0"><p>No maintenance records yet.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Asset</th><th>Type</th><th>Performed By</th><th>Cost</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentMaint as $m): ?>
        <tr>
          <td class="cell-mono text-muted"><?= date('d M Y', strtotime($m['maintenance_date'])) ?></td>
          <td>
            <?php if ($m['asset_code']): ?>
            <span class="asset-id"><?= htmlspecialchars($m['asset_code']) ?></span>
            <div class="text-muted"><?= htmlspecialchars($m['device_name'] ?? '') ?></div>
            <?php else: ?>
            <span class="text-muted">General</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($m['maintenance_type']) ?></span></td>
          <td class="cell-primary"><?= htmlspecialchars($m['performed_by']) ?></td>
          <td class="cell-mono"><?= $m['cost'] ? '₱'.number_format((float)$m['cost'],2) : '—' ?></td>
          <td>
            <span class="badge <?= $m['status']==='Completed' ? 'badge-green' : 'badge-yellow' ?>">
              <?= htmlspecialchars($m['status']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php
$extra_js = <<<HTML
<script>
/* ============================================================
   DASHBOARD — CHARTS
   Chart.js is loaded in extra_css at the top of the page.
   Colors match the CSS design system variables.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {

    // Color palette matching the CSS design system
    const colors = {
        accent:  '#3b6ef0',
        green:   '#16a34a',
        yellow:  '#b45309',
        red:     '#dc2626',
        purple:  '#7c3aed',
        muted:   '#8b91a8',
        grid:    'rgba(0,0,0,.06)',
        text:    '#8b91a8',
    };

    Chart.defaults.color       = colors.text;
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.font.size   = 12;

    /* ── Doughnut: Maintenance by type ── */
    const maintCanvas = document.getElementById('chart-maint-type');
    if (maintCanvas) {
        const maintData = $maintTypeChart;
        const maintColors = [colors.accent, colors.green, colors.yellow, colors.purple, colors.red, colors.muted, '#0891b2', '#db2777'];
        new Chart(maintCanvas, {
            type: 'doughnut',
            data: {
                labels:   maintData.map(d => d.maintenance_type),
                datasets: [{
                    data:            maintData.map(d => parseInt(d.total)),
                    backgroundColor: maintColors.slice(0, maintData.length),
                    borderColor:     '#ffffff',
                    borderWidth:     3,
                    hoverOffset:     6,
                }]
            },
            options: {
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 12, boxWidth: 10, usePointStyle: true }
                    }
                }
            }
        });
    }

    /* ── Donut: Asset Status ── */
    const statusData = $chartStatus;
    new Chart(document.getElementById('chart-status'), {
        type: 'doughnut',
        data: {
            labels:   statusData.map(d => d.label),
            datasets: [{
                data:            statusData.map(d => d.value),
                backgroundColor: [colors.green, colors.yellow, colors.muted],
                borderColor:     '#ffffff',
                borderWidth:     3,
                hoverOffset:     8,
            }]
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, boxWidth: 10, borderRadius: 4, usePointStyle: true }
                }
            }
        }
    });

    /* ── Bar: Devices by Type ── */
    const typeData = $chartTypes;
    new Chart(document.getElementById('chart-types'), {
        type: 'bar',
        data: {
            labels:   typeData.map(d => d.device_type),
            datasets: [{
                label:           'Devices',
                data:            typeData.map(d => parseInt(d.total)),
                backgroundColor: colors.accent + 'cc',
                borderColor:     colors.accent,
                borderWidth:     1,
                borderRadius:    6,
                borderSkipped:   false,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: colors.grid }, beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    /* ── Maintenance Trend Chart ── */
    const trendCanvas = document.getElementById('chart-maint-trend');
    if (trendCanvas) {
        const trendData = {
            labels: $maintenanceTrendLabels,
            jobs:   $maintenanceTrendJobs,
            costs:  $maintenanceTrendCost,
            year:   $maintenanceTrendYear,
        };
        new Chart(trendCanvas, {
            type: 'bar',
            data: {
                labels: trendData.labels,
                datasets: [
                    {
                        label: 'Maintenance jobs',
                        data: trendData.jobs,
                        backgroundColor: colors.accent + 'cc',
                        borderColor: colors.accent,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 24,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Cost (₱)',
                        type: 'line',
                        data: trendData.costs,
                        borderColor: colors.yellow,
                        backgroundColor: 'rgba(180,83,9,0.16)',
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: colors.yellow,
                        yAxisID: 'y1',
                        fill: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    title: {
                        display: true,
                        text: trendData.year,
                        align: 'end',
                        color: colors.muted,
                        font: { size: 11, weight: '500' },
                        padding: { bottom: 6 }
                    },
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                if (context.dataset.type === 'line') {
                                    return context.dataset.label + ': ₱' + Number(context.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, minRotation: 0 } },
                    y: { beginAtZero: true, title: { display: true, text: 'Jobs' } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true, title: { display: true, text: 'Cost (₱)' } }
                }
            }
        });
    }

    /* ── Horizontal Bar: By Department ── */
    const deptData = $chartDept;
    new Chart(document.getElementById('chart-dept'), {
        type: 'bar',
        data: {
            labels:   deptData.map(d => d.dept),
            datasets: [{
                label:           'Assets',
                data:            deptData.map(d => parseInt(d.total)),
                backgroundColor: colors.purple + 'cc',
                borderColor:     colors.purple,
                borderWidth:     1,
                borderRadius:    4,
                borderSkipped:   false,
            }]
        },
        options: {
            indexAxis: 'y',
            plugins:   { legend: { display: false } },
            scales: {
                x: { grid: { color: colors.grid }, beginAtZero: true, ticks: { stepSize: 1 } },
                y: { grid: { display: false } }
            }
        }
    });

    /* ── Auto-refresh dashboard data every 30 seconds ──
       Reloads just the stat numbers by fetching this same page
       and extracting the updated values via a lightweight endpoint.
       Charts do NOT re-render (too expensive) — only the KPI
       numbers update. A full page refresh gets fresh charts.
    ─────────────────────────────────────────────────────── */
    setInterval(function() {
        fetch('dashboard_data.php')
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(d) {
                if (!d) return;
                // Update only elements that exist and have changed
                var updates = {
                    'kpi-assets':   d.assetTotal,
                    'kpi-assigned': d.assignActive,
                    'kpi-stock':    d.stockAlert,
                    'kpi-warranty': d.warrantyAlert,
                };
                Object.entries(updates).forEach(function([id, val]) {
                    var el = document.getElementById(id);
                    if (el && el.textContent != val) el.textContent = val;
                });
            })
            .catch(function() { /* Silent — dashboard is read-only, a blip is fine */ });
    }, 30000);
});
</script>
HTML;
include 'includes/layout_end.php';
?>