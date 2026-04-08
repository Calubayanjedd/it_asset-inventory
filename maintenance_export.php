<?php
/* ============================================================
   MAINTENANCE EXPORT — CSV EXPORT
   Outputs CSV for Excel with columns: Date, Asset, Description, Cost, Status
   ============================================================ */
require_once 'includes/auth.php';

$filterMonth = $_GET['month'] ?? date('Y-m');
$filterYear  = substr($filterMonth, 0, 4);
$filterMon   = substr($filterMonth, 5, 2);

$records = dbQuery(
    'SELECT m.*, a.asset_id AS asset_code, a.model, a.device_type, a.brand
     FROM maintenance_logs m
     LEFT JOIN assets a ON m.asset_id = a.id
     WHERE YEAR(m.maintenance_date) = ? AND MONTH(m.maintenance_date) = ?
     ORDER BY m.maintenance_date ASC',
    [(int)$filterYear, (int)$filterMon]
);

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="maintenance_' . $filterMonth . '.csv"');
echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8

// Open output stream
$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['Date', 'Asset', 'Description', 'Cost', 'Status']);

// Write data
foreach ($records as $r) {
    $date = date('m/d/Y', strtotime($r['maintenance_date']));
    $asset = $r['asset_code'] ? 'PC (' . $r['brand'] . ', ' . $r['model'] . ')' : 'General';
    $description = $r['description'] ?? '';
    $cost = $r['cost'] ? number_format((float)$r['cost'], 2, '.', '') : '';
    $status = $r['status'];

    fputcsv($output, [$date, $asset, $description, $cost, $status]);
}

fclose($output);
exit;
?>