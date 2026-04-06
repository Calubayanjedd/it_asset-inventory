<?php
/* ============================================================
   IT INVENTORY — AJAX API HANDLER
   File: api.php
   All AJAX form submissions go through here.
   Returns JSON: { success, error, data }
   ============================================================ */

require_once 'includes/auth.php';

header('Content-Type: application/json');

/* Only accept POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$module = $_POST['module'] ?? '';
$action = $_POST['action'] ?? '';

function ok(string $msg, array $data = []): void {
    echo json_encode(['success' => $msg, 'data' => $data]); exit;
}

function fail(string $msg): void {
    echo json_encode(['error' => $msg]); exit;
}

/* ================================================================
   MODULE: ASSETS
================================================================ */
if ($module === 'assets') {

    if ($action === 'add' || $action === 'edit') {
        $fields = [
            'asset_id'       => strtoupper(trim($_POST['asset_id'] ?? '')),
            'device_name'    => trim($_POST['device_name'] ?? ''),
            'device_type'    => $_POST['device_type'] ?? 'PC',
            'brand'          => trim($_POST['brand'] ?? ''),
            'model'          => trim($_POST['model'] ?? ''),
            'serial_number'  => trim($_POST['serial_number'] ?? ''),
            'ip_address'     => trim($_POST['ip_address'] ?? '') ?: null,
            'purchase_date'  => $_POST['purchase_date'] ?: null,
            'warranty_expiry'=> $_POST['warranty_expiry'] ?: null,
            'status'         => $_POST['status'] ?? 'Active',
        ];

        if (!$fields['asset_id'])      fail('Asset ID is required.');
        if (!$fields['device_name'])   fail('Device Name is required.');
        if (!$fields['brand'])         fail('Brand is required.');
        if (!$fields['model'])         fail('Model is required.');
        if (!$fields['serial_number']) fail('Serial Number is required.');

        /* Resolve location */
        $loc = trim($_POST['location_id'] ?? '');
        $fields['location_id'] = null;
        if ($loc) {
            $locRow = dbRow('SELECT id FROM locations WHERE id = ?', [(int)$loc]);
            if ($locRow) $fields['location_id'] = $locRow['id'];
        }

        try {
            if ($action === 'add') {
                dbExecute(
                    'INSERT INTO assets (asset_id,device_name,device_type,brand,model,serial_number,
                     ip_address,purchase_date,warranty_expiry,status,location_id)
                     VALUES (:asset_id,:device_name,:device_type,:brand,:model,:serial_number,
                     :ip_address,:purchase_date,:warranty_expiry,:status,:location_id)',
                    $fields
                );
                $newId = (int)getDB()->lastInsertId();
                $row = dbRow(
                    'SELECT a.*, l.building_name, l.room_number, l.department AS loc_dept
                     FROM assets a LEFT JOIN locations l ON a.location_id = l.id WHERE a.id = ?',
                    [$newId]
                );
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ADD ASSET', "Asset ID: {$fields['asset_id']}"]);
                ok('Asset added successfully.', ['row' => $row, 'op' => 'add']);
            } else {
                $id = (int)($_POST['record_id'] ?? 0);
                if (!$id) fail('Missing record ID.');
                dbExecute(
                    'UPDATE assets SET asset_id=:asset_id,device_name=:device_name,device_type=:device_type,
                     brand=:brand,model=:model,serial_number=:serial_number,ip_address=:ip_address,
                     purchase_date=:purchase_date,warranty_expiry=:warranty_expiry,
                     status=:status,location_id=:location_id WHERE id=:id',
                    array_merge($fields, ['id' => $id])
                );
                $row = dbRow(
                    'SELECT a.*, l.building_name, l.room_number, l.department AS loc_dept
                     FROM assets a LEFT JOIN locations l ON a.location_id = l.id WHERE a.id = ?',
                    [$id]
                );
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'UPDATE ASSET', "Asset ID: {$fields['asset_id']}"]);
                ok('Asset updated successfully.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? "Asset ID '{$fields['asset_id']}' already exists."
                : 'Database error: ' . $e->getMessage();
            fail($msg);
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute('DELETE FROM assets WHERE id = ?', [$id]);
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'DELETE ASSET', "Asset ID: {$id}"]);
            ok('Asset deleted.', ['op' => 'delete', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }
}

/* ================================================================
   MODULE: ASSIGNMENTS
================================================================ */
if ($module === 'assignments') {

    if ($action === 'add' || $action === 'edit') {
        $fields = [
            'asset_id'      => (int)($_POST['asset_id'] ?? 0),
            'assigned_to'   => trim($_POST['assigned_to'] ?? ''),
            'department'    => trim($_POST['department'] ?? ''),
            'date_assigned' => $_POST['date_assigned'] ?? date('Y-m-d'),
            'date_returned' => $_POST['date_returned'] ?: null,
            'status'        => $_POST['status'] ?? 'Assigned',
            'notes'         => trim($_POST['notes'] ?? '') ?: null,
        ];
        if (!$fields['asset_id'])    fail('Please select an asset.');
        if (!$fields['assigned_to']) fail('Assigned To is required.');
        if (!$fields['department'])  fail('Department is required.');

        // If status is 'Assigned', clear date_returned
        if ($fields['status'] === 'Assigned') {
            $fields['date_returned'] = null;
        }

        try {
            if ($action === 'add') {
                dbExecute(
                    'INSERT INTO assignments (asset_id,assigned_to,department,date_assigned,date_returned,status,notes)
                     VALUES (:asset_id,:assigned_to,:department,:date_assigned,:date_returned,:status,:notes)',
                    $fields
                );
                $newId = (int)getDB()->lastInsertId();
                $row = dbRow(
                    'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
                     FROM assignments asn LEFT JOIN assets a ON asn.asset_id = a.id WHERE asn.id = ?',
                    [$newId]
                );
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ADD ASSIGNMENT', "Asset ID: {$fields['asset_id']}, Assigned to: {$fields['assigned_to']}"]);
                ok('Assignment recorded successfully.', ['row' => $row, 'op' => 'add']);
            } else {
                $id = (int)($_POST['record_id'] ?? 0);
                if (!$id) fail('Missing record ID.');
                dbExecute(
                    'UPDATE assignments SET asset_id=:asset_id,assigned_to=:assigned_to,department=:department,
                     date_assigned=:date_assigned,date_returned=:date_returned,status=:status,notes=:notes
                     WHERE id=:id',
                    array_merge($fields, ['id' => $id])
                );
                $row = dbRow(
                    'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
                     FROM assignments asn LEFT JOIN assets a ON asn.asset_id = a.id WHERE asn.id = ?',
                    [$id]
                );
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'UPDATE ASSIGNMENT', "Assignment ID: {$id}, Asset ID: {$fields['asset_id']}"]);
                ok('Assignment updated.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
            }
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }

    if ($action === 'return') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute("UPDATE assignments SET status='Returned', date_returned=CURDATE() WHERE id=? AND status='Assigned'", [$id]);
            $row = dbRow(
                'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
                 FROM assignments asn LEFT JOIN assets a ON asn.asset_id = a.id WHERE asn.id = ?',
                [$id]
            );
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'RETURN ASSET', "Assignment ID: {$id}"]);
            ok('Asset marked as returned.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }

    if ($action === 'assign') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute("UPDATE assignments SET status='Assigned', date_assigned=CURDATE() WHERE id=?", [$id]);
            $row = dbRow(
                'SELECT asn.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
                 FROM assignments asn LEFT JOIN assets a ON asn.asset_id = a.id WHERE asn.id = ?',
                [$id]
            );
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ASSIGN ASSET', "Assignment ID: {$id}"]);
            ok('Asset assigned successfully.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute('DELETE FROM assignments WHERE id=?', [$id]);
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'DELETE ASSIGNMENT', "Assignment ID: {$id}"]);
            ok('Assignment deleted.', ['op' => 'delete', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }
}

/* ================================================================
   MODULE: LOCATIONS
================================================================ */
if ($module === 'locations') {

    if ($action === 'add' || $action === 'edit') {
        $fields = [
            'location_id'   => strtoupper(trim($_POST['location_id'] ?? '')),
            'building_name' => trim($_POST['building_name'] ?? ''),
            'floor'         => trim($_POST['floor'] ?? ''),
            'room_number'   => trim($_POST['room_number'] ?? ''),
            'department'    => trim($_POST['department'] ?? ''),
            'notes'         => trim($_POST['notes'] ?? '') ?: null,
        ];
        if (!$fields['location_id'])   fail('Location ID is required.');
        if (!$fields['building_name']) fail('Building Name is required.');
        if (!$fields['floor'])         fail('Floor is required.');
        if (!$fields['room_number'])   fail('Room Number is required.');
        if (!$fields['department'])    fail('Department is required.');

        try {
            if ($action === 'add') {
                dbExecute(
                    'INSERT INTO locations (location_id,building_name,floor,room_number,department,notes)
                     VALUES (:location_id,:building_name,:floor,:room_number,:department,:notes)',
                    $fields
                );
                $newId = (int)getDB()->lastInsertId();
                $row = dbRow('SELECT l.*, COUNT(a.id) AS asset_count FROM locations l LEFT JOIN assets a ON a.location_id = l.id WHERE l.id = ? GROUP BY l.id', [$newId]);
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ADD LOCATION', "Location ID: {$fields['location_id']}"]);
                ok('Location added successfully.', ['row' => $row, 'op' => 'add']);
            } else {
                $id = (int)($_POST['record_id'] ?? 0);
                if (!$id) fail('Missing record ID.');
                dbExecute(
                    'UPDATE locations SET location_id=:location_id,building_name=:building_name,
                     floor=:floor,room_number=:room_number,department=:department,notes=:notes WHERE id=:id',
                    array_merge($fields, ['id' => $id])
                );
                $row = dbRow('SELECT l.*, COUNT(a.id) AS asset_count FROM locations l LEFT JOIN assets a ON a.location_id = l.id WHERE l.id = ? GROUP BY l.id', [$id]);
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'UPDATE LOCATION', "Location ID: {$fields['location_id']}"]);
                ok('Location updated.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? "Location ID '{$fields['location_id']}' already exists."
                : 'Error: ' . $e->getMessage();
            fail($msg);
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute('DELETE FROM locations WHERE id=?', [$id]);
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'DELETE LOCATION', "Location ID: {$id}"]);
            ok('Location deleted.', ['op' => 'delete', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Cannot delete: assets may still reference this location. ' . $e->getMessage());
        }
    }
}

/* ================================================================
   MODULE: STOCK
================================================================ */
if ($module === 'stock') {

    if ($action === 'add' || $action === 'edit') {
        $qty = max(0, (int)($_POST['quantity'] ?? 0));
        $min = max(0, (int)($_POST['min_stock_level'] ?? 0));
        $autoStatus = $qty <= 0 ? 'Out of Stock' : ($qty <= $min ? 'Low Stock' : 'OK');

        $fields = [
            'item_id'         => strtoupper(trim($_POST['item_id'] ?? '')),
            'item_name'       => trim($_POST['item_name'] ?? ''),
            'category'        => trim($_POST['category'] ?? 'Other'),
            'unit'            => trim($_POST['unit'] ?? 'pcs'),
            'quantity'        => $qty,
            'min_stock_level' => $min,
            'supplier'        => trim($_POST['supplier'] ?? '') ?: null,
            'unit_cost'       => is_numeric($_POST['unit_cost'] ?? '') ? (float)$_POST['unit_cost'] : null,
            'location_ref'    => trim($_POST['location_ref'] ?? '') ?: null,
            'status'          => $autoStatus,
            'notes'           => trim($_POST['notes'] ?? '') ?: null,
        ];
        if (!$fields['item_id'])   fail('Item ID is required.');
        if (!$fields['item_name']) fail('Item Name is required.');

        try {
            if ($action === 'add') {
                dbExecute(
                    'INSERT INTO stock_items (item_id,item_name,category,unit,quantity,min_stock_level,supplier,unit_cost,location_ref,status,notes)
                     VALUES (:item_id,:item_name,:category,:unit,:quantity,:min_stock_level,:supplier,:unit_cost,:location_ref,:status,:notes)',
                    $fields
                );
                $newId = (int)getDB()->lastInsertId();
                $row = dbRow('SELECT * FROM stock_items WHERE id = ?', [$newId]);
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ADD STOCK ITEM', "Item ID: {$fields['item_id']}"]);
                ok("Item '{$fields['item_name']}' added.", ['row' => $row, 'op' => 'add']);
            } else {
                $id = (int)($_POST['record_id'] ?? 0);
                if (!$id) fail('Missing record ID.');
                dbExecute(
                    'UPDATE stock_items SET item_id=:item_id,item_name=:item_name,category=:category,unit=:unit,
                     quantity=:quantity,min_stock_level=:min_stock_level,supplier=:supplier,unit_cost=:unit_cost,
                     location_ref=:location_ref,status=:status,notes=:notes WHERE id=:id',
                    array_merge($fields, ['id' => $id])
                );
                $row = dbRow('SELECT * FROM stock_items WHERE id = ?', [$id]);
                dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'UPDATE STOCK ITEM', "Item ID: {$fields['item_id']}"]);
                ok('Item updated.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
            }
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? "Item ID '{$fields['item_id']}' already exists."
                : 'Error: ' . $e->getMessage();
            fail($msg);
        }
    }

    if ($action === 'restock') {
        $id  = (int)($_POST['record_id'] ?? 0);
        $add = max(1, (int)($_POST['add_qty'] ?? 0));
        if (!$id) fail('Missing record ID.');
        $item = dbRow('SELECT * FROM stock_items WHERE id=?', [$id]);
        if (!$item) fail('Item not found.');
        $newQty = $item['quantity'] + $add;
        $stat   = $newQty <= 0 ? 'Out of Stock' : ($newQty <= $item['min_stock_level'] ? 'Low Stock' : 'OK');
        dbExecute('UPDATE stock_items SET quantity=?, status=? WHERE id=?', [$newQty, $stat, $id]);
        dbExecute('INSERT INTO stock_movements (stock_item_id,movement_type,quantity,notes) VALUES (?,?,?,?)',
            [$id, 'IN', $add, 'Manual restock']);
        $row = dbRow('SELECT * FROM stock_items WHERE id=?', [$id]);
        dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'RESTOCK ITEM', "Item ID: {$id}, Added: {$add}"]);
        ok("Restocked +{$add} units.", ['row' => $row, 'op' => 'edit', 'id' => $id]);
    }

    if ($action === 'issue') {
        $id  = (int)($_POST['record_id'] ?? 0);
        $sub = max(1, (int)($_POST['sub_qty'] ?? 0));
        if (!$id) fail('Missing record ID.');
        $item = dbRow('SELECT * FROM stock_items WHERE id=?', [$id]);
        if (!$item) fail('Item not found.');
        if ($sub > $item['quantity']) fail('Cannot issue more than available stock (' . $item['quantity'] . ').');
        $newQty = max(0, $item['quantity'] - $sub);
        $stat   = $newQty <= 0 ? 'Out of Stock' : ($newQty <= $item['min_stock_level'] ? 'Low Stock' : 'OK');
        dbExecute('UPDATE stock_items SET quantity=?, status=? WHERE id=?', [$newQty, $stat, $id]);
        dbExecute('INSERT INTO stock_movements (stock_item_id,movement_type,quantity,notes) VALUES (?,?,?,?)',
            [$id, 'OUT', $sub, trim($_POST['issue_notes'] ?? 'Manual issue')]);
        $row = dbRow('SELECT * FROM stock_items WHERE id=?', [$id]);
        dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ISSUE STOCK', "Item ID: {$id}, Issued: {$sub}"]);
        ok("Issued {$sub} unit(s).", ['row' => $row, 'op' => 'edit', 'id' => $id]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        dbExecute('DELETE FROM stock_items WHERE id=?', [$id]);
        dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'DELETE STOCK ITEM', "Item ID: {$id}"]);
        ok('Item deleted.', ['op' => 'delete', 'id' => $id]);
    }
}

/* ================================================================
   MODULE: USERS
================================================================ */
if ($module === 'users') {

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'Viewer';
        $fullname = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!$username || strlen($username) < 3) fail('Username must be at least 3 characters.');
        if (!$password || strlen($password) < 6)  fail('Password must be at least 6 characters.');

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            dbExecute(
                'INSERT INTO sys_users (username,password_hash,role,full_name,email) VALUES (?,?,?,?,?)',
                [$username, $hash, $role, $fullname ?: null, $email ?: null]
            );
            $newId = (int)getDB()->lastInsertId();
            $row = dbRow('SELECT * FROM sys_users WHERE id=?', [$newId]);
            unset($row['password_hash']);
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'ADD USER', "Username: {$username}, Role: {$role}"]);
            ok("User '{$username}' created.", ['row' => $row, 'op' => 'add']);
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? "Username '{$username}' already exists."
                : 'Error: ' . $e->getMessage();
            fail($msg);
        }
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['record_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $role     = $_POST['role'] ?? 'Viewer';
        $fullname = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $status   = $_POST['status'] ?? 'Active';
        $newPass  = trim($_POST['new_password'] ?? '');

        if (!$id) fail('Missing record ID.');
        if (!$username) fail('Username is required.');

        try {
            if ($newPass) {
                if (strlen($newPass) < 6) fail('Password must be at least 6 characters.');
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                dbExecute(
                    'UPDATE sys_users SET username=?,role=?,full_name=?,email=?,status=?,password_hash=? WHERE id=?',
                    [$username, $role, $fullname ?: null, $email ?: null, $status, $hash, $id]
                );
            } else {
                dbExecute(
                    'UPDATE sys_users SET username=?,role=?,full_name=?,email=?,status=? WHERE id=?',
                    [$username, $role, $fullname ?: null, $email ?: null, $status, $id]
                );
            }
            $row = dbRow('SELECT * FROM sys_users WHERE id=?', [$id]);
            unset($row['password_hash']);
            dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'UPDATE USER', "User ID: {$id}, Username: {$username}"]);
            ok('User updated.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['record_id'] ?? 0);
        /* Prevent disabling yourself */
        if ($id === (int)$authUser['id']) fail('You cannot disable your own account.');
        $user = dbRow('SELECT * FROM sys_users WHERE id=?', [$id]);
        if (!$user) fail('User not found.');
        $newStatus = $user['status'] === 'Active' ? 'Inactive' : 'Active';
        dbExecute('UPDATE sys_users SET status=? WHERE id=?', [$newStatus, $id]);
        $row = dbRow('SELECT * FROM sys_users WHERE id=?', [$id]);
        unset($row['password_hash']);
        $verb = $newStatus === 'Active' ? 'activated' : 'deactivated';
        ok("User {$verb}.", ['row' => $row, 'op' => 'edit', 'id' => $id]);
        dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'TOGGLE USER STATUS', "User ID: {$id}, Status: {$newStatus}"]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if ($id === (int)$authUser['id']) fail('You cannot delete your own account.');
        dbExecute('DELETE FROM sys_users WHERE id=?', [$id]);
        dbExecute('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', [$authUser['id'], 'DELETE USER', "User ID: {$id}"]);
        ok('User deleted.', ['op' => 'delete', 'id' => $id]);
    }
}

/* ================================================================
   MODULE: SETTINGS  (Admin only)
================================================================ */
if ($module === 'settings') {
    if ($authRole !== 'Admin') fail('Access denied.');
 
    if ($action === 'save') {
        $allowed = ['system_name', 'system_subtitle', 'accent_color', 'sidebar_dark', 'logo_type'];
 
        foreach ($allowed as $key) {
            if (!isset($_POST[$key])) continue;
            $value = trim($_POST[$key]);
 
            // Validate accent color — must be a valid hex color
            if ($key === 'accent_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                fail('Invalid color format. Use a hex color like #3b6ef0.');
            }
 
            dbExecute(
                'INSERT INTO sys_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value ?: null]
            );
        }
 
        ok('Settings saved successfully.');
    }
 
    if ($action === 'upload_logo') {
        if (empty($_FILES['logo_file']['tmp_name'])) {
            fail('No file uploaded.');
        }
 
        $file     = $_FILES['logo_file'];
        $allowed  = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $maxSize  = 2 * 1024 * 1024; // 2MB
 
        if (!in_array($file['type'], $allowed)) {
            fail('Only PNG, JPG, GIF, WEBP or SVG files are allowed.');
        }
        if ($file['size'] > $maxSize) {
            fail('File is too large. Maximum size is 2MB.');
        }
 
        // Save to uploads folder
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
 
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;
 
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            fail('Failed to save file. Check folder permissions.');
        }
 
        $url = 'uploads/' . $filename;
        dbExecute(
            'INSERT INTO sys_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['logo_image', $url]
        );
        dbExecute(
            'INSERT INTO sys_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['logo_type', 'image']
        );
 
        ok('Logo uploaded.', ['url' => $url]);
    }
 
    if ($action === 'reset_logo') {
        dbExecute(
            'INSERT INTO sys_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['logo_type', 'icon']
        );
        dbExecute(
            'INSERT INTO sys_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['logo_image', null]
        );
        ok('Logo reset to default icon.');
    }
}

/* ================================================================
   MODULE: MAINTENANCE
================================================================ */
if ($module === 'maintenance') {
 
    // Shared row query — joins asset info for display
    $rowQuery = 'SELECT m.*, a.asset_id AS asset_code, a.device_name, a.device_type, a.brand
                 FROM maintenance_logs m
                 LEFT JOIN assets a ON m.asset_id = a.id
                 WHERE m.id = ?';
 
    if ($action === 'add' || $action === 'edit') {
        $fields = [
            'asset_id'         => (int)($_POST['asset_id'] ?? 0) ?: null,
            'maintenance_type' => trim($_POST['maintenance_type'] ?? ''),
            'description'      => trim($_POST['description']      ?? '') ?: null,
            'performed_by'     => trim($_POST['performed_by']     ?? ''),
            'maintenance_date' => $_POST['maintenance_date']      ?? date('Y-m-d'),
            'status'           => $_POST['status']                ?? 'Completed',
            'cost'             => is_numeric($_POST['cost'] ?? '') ? (float)$_POST['cost'] : null,
            'next_schedule'    => $_POST['next_schedule'] ?: null,
        ];
 
        if (!$fields['maintenance_type']) fail('Maintenance type is required.');
        if (!$fields['performed_by'])     fail('Performed By is required.');
        if (!$fields['maintenance_date']) fail('Maintenance date is required.');
 
        try {
            if ($action === 'add') {
                dbExecute(
                    'INSERT INTO maintenance_logs
                     (asset_id, maintenance_type, description, performed_by,
                      maintenance_date, status, cost, next_schedule)
                     VALUES
                     (:asset_id, :maintenance_type, :description, :performed_by,
                      :maintenance_date, :status, :cost, :next_schedule)',
                    $fields
                );
                $newId = (int)getDB()->lastInsertId();
                $row   = dbRow($rowQuery, [$newId]);
                ok('Maintenance record saved.', ['row' => $row, 'op' => 'add']);
            } else {
                $id = (int)($_POST['record_id'] ?? 0);
                if (!$id) fail('Missing record ID.');
                dbExecute(
                    'UPDATE maintenance_logs
                     SET asset_id=:asset_id, maintenance_type=:maintenance_type,
                         description=:description, performed_by=:performed_by,
                         maintenance_date=:maintenance_date, status=:status,
                         cost=:cost, next_schedule=:next_schedule
                     WHERE id=:id',
                    array_merge($fields, ['id' => $id])
                );
                $row = dbRow($rowQuery, [$id]);
                ok('Maintenance record updated.', ['row' => $row, 'op' => 'edit', 'id' => $id]);
            }
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }
 
    if ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) fail('Missing record ID.');
        try {
            dbExecute('DELETE FROM maintenance_logs WHERE id = ?', [$id]);
            ok('Record deleted.', ['op' => 'delete', 'id' => $id]);
        } catch (PDOException $e) {
            fail('Error: ' . $e->getMessage());
        }
    }
}

/* Fallback */
fail('Unknown module or action.');
