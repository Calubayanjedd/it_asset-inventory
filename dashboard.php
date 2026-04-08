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

$page_title  = 'Dashboard';
$active_page = 'dashboard';
// Load Chart.js before the page body renders
$extra_css = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
include 'includes/layout.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     ROW 1 — PRIMARY KPI CARDS
     5 key KPI cards the IT head needs up front
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:20px">

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

  <!-- Upcoming Schedule -->
  <?php $upcomingCount = count($upcomingMaint); ?>
  <div class="stat-card <?= $upcomingCount > 0 ? 'yellow' : 'green' ?>" style="position:relative;overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between">
      <div>
        <div class="stat-label">Upcoming Schedule</div>
        <div class="stat-value" style="font-size:36px;margin:6px 0"><?= $upcomingCount ?></div>
        <div style="display:flex;gap:12px;font-size:11px;font-family:var(--font-mono)">
          <span style="color:var(--yellow)">● <?= $dueSoonCount ?> due soon</span>
          <span style="color:var(--text-muted)">● <?= max(0, $upcomingCount - $dueSoonCount) ?> later</span>
        </div>
      </div>
      <div style="width:44px;height:44px;background:var(--yellow-bg);border-radius:var(--radius-md);
                  display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="var(--yellow)" stroke-width="1.8">
          <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/>
          <path d="M8 4h8"/>
          <path d="M12 9v5"/>
          <path d="M10 12h4"/>
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
     ROW 2 — MAINTENANCE TREND
════════════════════════════════════════════════════════════ -->
<div style="margin-bottom:20px">

<div class="card">
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

</div>

<!-- ═══════════════════════════════════════════════════════════
     ROW 3 — SUMMARY CARDS
════════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px">

  <!-- Asset breakdown donut -->
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

  <!-- Assets by department numbers -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Assets by Department</div>
        <div class="card-subtitle">Via location mapping</div>
      </div>
    </div>
    <div class="card-body" style="padding:20px">
      <?php
      $deptTotal = array_sum(array_column($byDept, 'total'));
      ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($byDept as $dept): ?>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:500"><?= htmlspecialchars($dept['dept']) ?></span>
          <span style="font-family:var(--font-mono);font-size:14px;color:var(--accent)"><?= $dept['total'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <hr style="margin:16px 0;border:none;border-top:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;align-items:center;font-weight:600">
        <span>Total</span>
        <span style="font-family:var(--font-mono);font-size:16px;color:var(--accent)"><?= $deptTotal ?></span>
      </div>
    </div>
  </div>


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