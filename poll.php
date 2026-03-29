<?php
/* ============================================================
   IT INVENTORY — POLLING ENDPOINT
   File: poll.php
   Called every 5 seconds by the browser.
   Returns: { module, rows[], hash }
   The hash is a fingerprint of the data — if it hasn't
   changed since last poll, JS skips the DOM update entirely.
   ============================================================ */

require_once 'includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$module = $_GET['module'] ?? '';

function sendRows(array $rows): void {
    $hash = md5(json_encode($rows));
    echo json_encode([
        'ok'   => true,
        'rows' => $rows,
        'hash' => $hash,
    ]);
    exit;
}

switch ($module) {

    case 'assets':
        $rows = dbQuery(
            'SELECT a.*, l.building_name, l.room_number, l.department AS loc_dept
             FROM assets a
             LEFT JOIN locations l ON a.location_id = l.id
             ORDER BY a.created_at DESC'
        );
        sendRows($rows);

    case 'assignments':
        $rows = dbQuery(
            'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
             FROM assignments asn
             LEFT JOIN assets a ON asn.asset_id = a.id
             ORDER BY asn.created_at DESC'
        );
        sendRows($rows);

    case 'locations':
        $rows = dbQuery(
            'SELECT l.*, COUNT(a.id) AS asset_count
             FROM locations l
             LEFT JOIN assets a ON a.location_id = l.id
             GROUP BY l.id
             ORDER BY l.building_name, l.floor, l.room_number'
        );
        sendRows($rows);

    case 'stock':
        $rows = dbQuery('SELECT * FROM stock_items ORDER BY status ASC, item_name ASC');
        sendRows($rows);

    case 'users':
        /* Never send password hashes to the browser */
        $rows = dbQuery(
            'SELECT id, username, full_name, email, role, status, last_login, created_at
             FROM sys_users ORDER BY role ASC, username ASC'
        );
        sendRows($rows);

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown module']);
        exit;
}
