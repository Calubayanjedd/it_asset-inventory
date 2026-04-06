<?php
/* ============================================================
   MAINTENANCE PRINT VIEW
   Clean printable/PDF page for a selected month.
   No sidebar, no nav — just the report.
   Open in new tab → browser Print → Save as PDF.
   ============================================================ */
require_once 'includes/auth.php';

$filterMonth = $_GET['month'] ?? date('Y-m');
$filterYear  = substr($filterMonth, 0, 4);
$filterMon   = substr($filterMonth, 5, 2);

$records = dbQuery(
    'SELECT m.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     WHERE YEAR(m.maintenance_date) = ? AND MONTH(m.maintenance_date) = ?
     ORDER BY m.maintenance_date ASC',
    [(int)$filterYear, (int)$filterMon]
);

$total     = count($records);
$completed = count(array_filter($records, fn($r) => $r['status'] === 'Completed'));
$pending   = count(array_filter($records, fn($r) => $r['status'] === 'Pending'));
$totalCost = array_sum(array_column($records, 'cost'));
$monthLabel= date('F Y', mktime(0,0,0,(int)$filterMon,1,(int)$filterYear));

// Load system settings for name/logo
$sysName = $sysSettings['system_name']     ?? 'IT INVENTORY SYSTEM';
$sysSub  = $sysSettings['system_subtitle'] ?? 'MIS Department';
$accent  = $sysSettings['accent_color']   ?? '#3b6ef0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Maintenance Report — <?= htmlspecialchars($monthLabel) ?></title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&display=swap');

    * { box-sizing:border-box; margin:0; padding:0; }

    body {
      font-family: 'DM Sans', sans-serif;
      font-size: 13px;
      color: #1a1f2e;
      background: #fff;
      padding: 32px 40px;
    }

    /* ── HEADER ── */
    .report-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 28px;
      padding-bottom: 20px;
      border-bottom: 2px solid <?= htmlspecialchars($accent) ?>;
    }
    .report-header .org-name {
      font-size: 18px;
      font-weight: 700;
      color: #1a1f2e;
      letter-spacing: -.02em;
    }
    .report-header .org-sub {
      font-size: 11px;
      color: #6b7280;
      margin-top: 2px;
    }
    .report-header .report-title {
      text-align: right;
    }
    .report-header .report-title h2 {
      font-size: 20px;
      font-weight: 700;
      color: <?= htmlspecialchars($accent) ?>;
    }
    .report-header .report-title .month {
      font-size: 13px;
      color: #6b7280;
      margin-top: 3px;
    }

    /* ── SUMMARY STRIP ── */
    .summary-strip {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .summary-card {
      border: 1px solid #e2e5eb;
      border-radius: 8px;
      padding: 14px 16px;
      text-align: center;
    }
    .summary-card .label {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #8b91a8;
      margin-bottom: 4px;
    }
    .summary-card .value {
      font-size: 22px;
      font-weight: 700;
      font-family: 'IBM Plex Mono', monospace;
      color: #1a1f2e;
    }
    .summary-card.accent .value { color: <?= htmlspecialchars($accent) ?>; }
    .summary-card.green  .value { color: #16a34a; }
    .summary-card.yellow .value { color: #b45309; }

    /* ── TABLE ── */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }
    thead th {
      background: <?= htmlspecialchars($accent) ?>;
      color: #fff;
      padding: 9px 10px;
      text-align: left;
      font-weight: 600;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    tbody tr:nth-child(even) { background: #f8f9fb; }
    tbody tr:last-child td   { border-bottom: none; }
    td {
      padding: 8px 10px;
      border-bottom: 1px solid #e2e5eb;
      vertical-align: top;
      color: #374151;
    }
    .mono { font-family: 'IBM Plex Mono', monospace; font-size: 11px; }
    .asset-id {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 11px;
      background: <?= htmlspecialchars($accent) ?>18;
      color: <?= htmlspecialchars($accent) ?>;
      border: 1px solid <?= htmlspecialchars($accent) ?>30;
      padding: 1px 6px;
      border-radius: 4px;
    }
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 99px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .badge-green  { background: #dcfce7; color: #16a34a; }
    .badge-yellow { background: #fef3c7; color: #b45309; }
    .badge-gray   { background: #f1f2f6; color: #6b7280; }
    .text-muted   { color: #9ca3af; font-size: 11px; }

    /* ── FOOTER ── */
    .report-footer {
      margin-top: 28px;
      padding-top: 16px;
      border-top: 1px solid #e2e5eb;
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      color: #9ca3af;
    }
    .sig-block {
      display: grid;
      grid-template-columns: repeat(3, 180px);
      gap: 24px;
      margin-top: 32px;
    }
    .sig-line {
      border-top: 1px solid #374151;
      padding-top: 6px;
      font-size: 11px;
      color: #6b7280;
    }

    /* ── EMPTY STATE ── */
    .empty-print {
      text-align: center;
      padding: 60px 0;
      color: #9ca3af;
    }
    .empty-print h3 { font-size: 16px; margin-bottom: 6px; }

    /* ── PRINT BUTTON (hidden when printing) ── */
    .print-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-bottom: 24px;
    }
    .btn-print {
      padding: 8px 20px;
      background: <?= htmlspecialchars($accent) ?>;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
    }
    .btn-close {
      padding: 8px 20px;
      background: #f1f2f6;
      color: #374151;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
    }

    @media print {
      .print-actions { display: none !important; }
      body { padding: 20px; }
      thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .summary-card.green  .value,
      .summary-card.yellow .value,
      .summary-card.accent .value { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

<!-- Print / Close buttons -->
<div class="print-actions">
  <button class="btn-close" onclick="window.close()">✕ Close</button>
  <button class="btn-print" onclick="window.print()">
    🖨 Print / Save as PDF
  </button>
</div>

<!-- Report Header -->
<div class="report-header">
  <div>
    <div class="org-name"><?= htmlspecialchars($sysName) ?></div>
    <div class="org-sub"><?= htmlspecialchars($sysSub) ?></div>
  </div>
  <div class="report-title">
    <h2>Maintenance Report</h2>
    <div class="month"><?= htmlspecialchars($monthLabel) ?></div>
    <div class="month" style="margin-top:2px">
      Generated: <?= date('d M Y, h:i A') ?>
    </div>
  </div>
</div>

<!-- Summary Strip -->
<div class="summary-strip">
  <div class="summary-card accent">
    <div class="label">Total Records</div>
    <div class="value"><?= $total ?></div>
  </div>
  <div class="summary-card green">
    <div class="label">Completed</div>
    <div class="value"><?= $completed ?></div>
  </div>
  <div class="summary-card yellow">
    <div class="label">Pending</div>
    <div class="value"><?= $pending ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Total Cost</div>
    <div class="value" style="font-size:16px">₱<?= number_format($totalCost, 2) ?></div>
  </div>
</div>

<!-- Records Table -->
<?php if (empty($records)): ?>
<div class="empty-print">
  <h3>No maintenance records for <?= htmlspecialchars($monthLabel) ?></h3>
  <p>No records were logged during this period.</p>
</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th style="width:28px">#</th>
      <th style="width:80px">Date</th>
      <th style="width:100px">Asset</th>
      <th style="width:110px">Type</th>
      <th>Description</th>
      <th style="width:110px">Performed By</th>
      <th style="width:70px">Cost</th>
      <th style="width:80px">Next Schedule</th>
      <th style="width:75px">Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($records as $i => $r): ?>
    <tr>
      <td class="mono"><?= $i + 1 ?></td>
      <td class="mono"><?= date('d M Y', strtotime($r['maintenance_date'])) ?></td>
      <td>
        <?php if ($r['asset_code']): ?>
        <span class="asset-id"><?= htmlspecialchars($r['asset_code']) ?></span>
        <div class="text-muted"><?= htmlspecialchars($r['device_name'] ?? '') ?></div>
        <?php else: ?>
        <span class="text-muted">General</span>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-gray"><?= htmlspecialchars($r['maintenance_type']) ?></span></td>
      <td style="font-size:12px;color:#374151">
        <?= htmlspecialchars($r['description'] ?? '—') ?>
      </td>
      <td style="font-weight:500"><?= htmlspecialchars($r['performed_by']) ?></td>
      <td class="mono">
        <?= $r['cost'] ? '₱' . number_format((float)$r['cost'], 2) : '—' ?>
      </td>
      <td class="mono">
        <?= $r['next_schedule'] ? date('d M Y', strtotime($r['next_schedule'])) : '—' ?>
      </td>
      <td>
        <span class="badge <?= $r['status'] === 'Completed' ? 'badge-green' : 'badge-yellow' ?>">
          <?= htmlspecialchars($r['status']) ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
    <!-- Totals row -->
    <tr style="background:#f0f2f5;font-weight:600">
      <td colspan="6" style="text-align:right;padding-right:12px;font-size:12px">
        Total (<?= $total ?> records)
      </td>
      <td class="mono" style="font-weight:700;color:<?= htmlspecialchars($accent) ?>">
        ₱<?= number_format($totalCost, 2) ?>
      </td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>
<?php endif; ?>

<!-- Signature Block -->
<div style="margin-top:40px">
  <div style="font-size:11px;color:#9ca3af;margin-bottom:16px">
    Certified correct and prepared by:
  </div>
  <div class="sig-block">
    <div>
      <div style="height:40px"></div>
      <div class="sig-line">Prepared by</div>
    </div>
    <div>
      <div style="height:40px"></div>
      <div class="sig-line">Reviewed by</div>
    </div>
    <div>
      <div style="height:40px"></div>
      <div class="sig-line">Approved by</div>
    </div>
  </div>
</div>

<!-- Report Footer -->
<div class="report-footer">
  <span><?= htmlspecialchars($sysName) ?> — Maintenance Report</span>
  <span><?= htmlspecialchars($monthLabel) ?> · Generated <?= date('d M Y h:i A') ?></span>
</div>

</body>
</html>