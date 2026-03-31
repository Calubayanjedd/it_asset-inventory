<?php
/* ============================================================
   IT INVENTORY SYSTEM — SETUP / INSTALLER
   File: setup.php
   Run this ONCE to create all tables and seed the first admin.
   DELETE or RENAME this file after setup is complete.
   ============================================================ */

/* ── Prevent running if already set up ── */
$lockFile = __DIR__ . '/setup.lock';
$alreadyRun = file_exists($lockFile);

$steps   = [];
$hasError = false;
$done     = false;

/* ── PROCESS SETUP ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyRun) {

    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? 'it_inventory');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = trim($_POST['admin_username'] ?? 'admin');
    $adminPass = $_POST['admin_password'] ?? '';
    $adminName = trim($_POST['admin_fullname'] ?? 'System Administrator');
    $adminEmail= trim($_POST['admin_email'] ?? '');

    /* Validate */
    if (!$dbHost || !$dbName || !$dbUser) {
        $steps[] = ['error', 'Database host, name, and user are required.'];
        $hasError = true;
    }
    if (strlen($adminUser) < 3) {
        $steps[] = ['error', 'Admin username must be at least 3 characters.'];
        $hasError = true;
    }
    if (strlen($adminPass) < 6) {
        $steps[] = ['error', 'Admin password must be at least 6 characters.'];
        $hasError = true;
    }

    if (!$hasError) {

        /* ── Step 1: Connect ── */
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $steps[] = ['ok', "Connected to MySQL on <strong>{$dbHost}</strong>"];
        } catch (PDOException $e) {
            $steps[] = ['error', 'Cannot connect to MySQL: ' . $e->getMessage()];
            $hasError = true;
        }

        /* ── Step 2: Create DB ── */
        if (!$hasError) {
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbName}`");
                $steps[] = ['ok', "Database <strong>{$dbName}</strong> ready"];
            } catch (PDOException $e) {
                $steps[] = ['error', 'Cannot create database: ' . $e->getMessage()];
                $hasError = true;
            }
        }

        /* ── Step 3: Create tables ── */
        if (!$hasError) {
            $tables = [

                'locations' => "CREATE TABLE IF NOT EXISTS locations (
                  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  location_id   VARCHAR(20)  NOT NULL UNIQUE,
                  building_name VARCHAR(100) NOT NULL,
                  floor         VARCHAR(40)  NOT NULL,
                  room_number   VARCHAR(40)  NOT NULL,
                  department    VARCHAR(100) NOT NULL,
                  notes         TEXT         DEFAULT NULL,
                  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  INDEX idx_department (department)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'assets' => "CREATE TABLE IF NOT EXISTS assets (
                  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  asset_id        VARCHAR(20)  NOT NULL UNIQUE,
                  device_name     VARCHAR(100) NOT NULL,
                  device_type     ENUM('PC','Laptop','Printer','Router','Switch','Monitor','Other') NOT NULL DEFAULT 'PC',
                  brand           VARCHAR(80)  NOT NULL,
                  model           VARCHAR(100) NOT NULL,
                  serial_number   VARCHAR(100) NOT NULL,
                  ip_address      VARCHAR(45)  DEFAULT NULL,
                  purchase_date   DATE         DEFAULT NULL,
                  warranty_expiry DATE         DEFAULT NULL,
                  status          ENUM('Active','Under Repair','Retired') NOT NULL DEFAULT 'Active',
                  notes           TEXT         DEFAULT NULL,
                  location_id     INT UNSIGNED DEFAULT NULL,
                  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  CONSTRAINT fk_asset_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL ON UPDATE CASCADE,
                  INDEX idx_status (status),
                  INDEX idx_device_type (device_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'assignments' => "CREATE TABLE IF NOT EXISTS assignments (
                  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  asset_id      INT UNSIGNED NOT NULL,
                  assigned_to   VARCHAR(120) NOT NULL,
                  department    VARCHAR(100) NOT NULL,
                  date_assigned DATE         NOT NULL,
                  date_returned DATE         DEFAULT NULL,
                  status        ENUM('Assigned','Available','Returned') NOT NULL DEFAULT 'Assigned',
                  notes         TEXT         DEFAULT NULL,
                  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  CONSTRAINT fk_assign_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE ON UPDATE CASCADE,
                  INDEX idx_assign_status (status),
                  INDEX idx_assign_dept (department)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'stock_items' => "CREATE TABLE IF NOT EXISTS stock_items (
                  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  item_id         VARCHAR(20)   NOT NULL UNIQUE,
                  item_name       VARCHAR(150)  NOT NULL,
                  category        VARCHAR(60)   NOT NULL DEFAULT 'Other',
                  unit            VARCHAR(20)   NOT NULL DEFAULT 'pcs',
                  quantity        INT           NOT NULL DEFAULT 0,
                  min_stock_level INT           NOT NULL DEFAULT 5,
                  supplier        VARCHAR(150)  DEFAULT NULL,
                  unit_cost       DECIMAL(10,2) DEFAULT NULL,
                  location_ref    VARCHAR(100)  DEFAULT NULL,
                  status          ENUM('OK','Low Stock','Out of Stock') NOT NULL DEFAULT 'OK',
                  notes           TEXT          DEFAULT NULL,
                  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  INDEX idx_stock_status (status),
                  INDEX idx_stock_category (category)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'stock_movements' => "CREATE TABLE IF NOT EXISTS stock_movements (
                  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  stock_item_id INT UNSIGNED  NOT NULL,
                  movement_type ENUM('IN','OUT') NOT NULL,
                  quantity      INT           NOT NULL,
                  notes         VARCHAR(255)  DEFAULT NULL,
                  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  CONSTRAINT fk_movement_item FOREIGN KEY (stock_item_id) REFERENCES stock_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
                  INDEX idx_move_item (stock_item_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'sys_users' => "CREATE TABLE IF NOT EXISTS sys_users (
                  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  username      VARCHAR(60)  NOT NULL UNIQUE,
                  password_hash VARCHAR(255) NOT NULL,
                  full_name     VARCHAR(120) DEFAULT NULL,
                  email         VARCHAR(150) DEFAULT NULL,
                  role          ENUM('Admin','IT Staff','Viewer') NOT NULL DEFAULT 'Viewer',
                  status        ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
                  last_login    DATETIME     DEFAULT NULL,
                  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  INDEX idx_user_role (role),
                  INDEX idx_user_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'activity_log' => "CREATE TABLE IF NOT EXISTS activity_log (
                  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  user_id    INT UNSIGNED DEFAULT NULL,
                  action     VARCHAR(80) NOT NULL,
                  details    TEXT        DEFAULT NULL,
                  ip_address VARCHAR(45) DEFAULT NULL,
                  created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES sys_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
                  INDEX idx_log_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            ];

            foreach ($tables as $name => $sql) {
                try {
                    $pdo->exec($sql);
                    $steps[] = ['ok', "Table <strong>{$name}</strong> created"];
                } catch (PDOException $e) {
                    $steps[] = ['error', "Table {$name} failed: " . $e->getMessage()];
                    $hasError = true;
                    break;
                }
            }
        }

        /* ── Step 4: Create admin user ── */
        if (!$hasError) {
            try {
                $hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    'INSERT INTO sys_users (username, password_hash, full_name, email, role, status)
                     VALUES (?, ?, ?, ?, "Admin", "Active")
                     ON DUPLICATE KEY UPDATE
                       password_hash = VALUES(password_hash),
                       full_name     = VALUES(full_name),
                       email         = VALUES(email),
                       role          = "Admin",
                       status        = "Active"'
                );
                $stmt->execute([$adminUser, $hash, $adminName ?: null, $adminEmail ?: null]);
                $steps[] = ['ok', "Admin account <strong>{$adminUser}</strong> created"];
            } catch (PDOException $e) {
                $steps[] = ['error', 'Admin user creation failed: ' . $e->getMessage()];
                $hasError = true;
            }
        }

        /* ── Step 5: Write db.php config ── */
        if (!$hasError) {
            $dbPhpContent = '<?php' . "\n" .
                "/* Auto-generated by setup.php */\n" .
                "define('DB_HOST',    " . var_export($dbHost, true) . ");\n" .
                "define('DB_NAME',    " . var_export($dbName, true) . ");\n" .
                "define('DB_USER',    " . var_export($dbUser, true) . ");\n" .
                "define('DB_PASS',    " . var_export($dbPass, true) . ");\n" .
                "define('DB_CHARSET', 'utf8mb4');\n\n" .
                'function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(["error" => "Database connection failed: " . $e->getMessage()]));
    }
    return $pdo;
}

function dbQuery(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbExecute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int) getDB()->lastInsertId() ?: $stmt->rowCount();
}

function dbRow(string $sql, array $params = []): ?array {
    $rows = dbQuery($sql, $params);
    return $rows[0] ?? null;
}
';
            if (file_put_contents(__DIR__ . '/includes/db.php', $dbPhpContent)) {
                $steps[] = ['ok', 'Database config written to <strong>includes/db.php</strong>'];
            } else {
                $steps[] = ['warning', 'Could not write includes/db.php — update it manually with your credentials'];
            }
        }

        /* ── Step 6: Write lock file ── */
        if (!$hasError) {
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $steps[] = ['ok', 'Setup complete — lock file created'];
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup — IT Inventory System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 40px 20px;
    }

    .wrap { width: 100%; max-width: 560px; }

    /* Header */
    .header {
      text-align: center;
      margin-bottom: 28px;
    }

    .logo {
      width: 52px; height: 52px;
      background: #3b6ef0;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
      box-shadow: 0 4px 16px rgba(59,110,240,.35);
    }

    .header h1 { font-size: 22px; font-weight: 700; color: #1a1f2e; letter-spacing: -.02em; }
    .header p  { font-size: 13px; color: #8b91a8; margin-top: 4px; font-family: 'IBM Plex Mono', monospace; }

    /* Card */
    .card {
      background: #fff;
      border: 1px solid #e2e5eb;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }

    .card-header {
      padding: 20px 28px 16px;
      border-bottom: 1px solid #e2e5eb;
      background: #f8f9fb;
    }

    .card-header h2 { font-size: 15px; font-weight: 700; color: #1a1f2e; }
    .card-header p  { font-size: 12.5px; color: #8b91a8; margin-top: 3px; }

    .card-body { padding: 28px; }

    /* Step indicator */
    .steps-bar {
      display: flex;
      gap: 0;
      margin-bottom: 28px;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #e2e5eb;
    }

    .step-item {
      flex: 1;
      padding: 10px 8px;
      text-align: center;
      font-size: 11px;
      font-weight: 600;
      color: #8b91a8;
      background: #f8f9fb;
      border-right: 1px solid #e2e5eb;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .step-item:last-child { border-right: none; }
    .step-item.active { background: #3b6ef0; color: #fff; }
    .step-item.done   { background: #dcfce7; color: #15803d; }

    /* Sections */
    .section-title {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #8b91a8;
      margin-bottom: 14px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e2e5eb;
    }

    /* Form */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-row.one { grid-template-columns: 1fr; }

    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    .form-group:last-child { margin-bottom: 0; }

    label { font-size: 12.5px; font-weight: 600; color: #4a5068; }
    label .req { color: #dc2626; margin-left: 2px; }
    label .hint { font-weight: 400; color: #8b91a8; font-size: 11px; margin-left: 4px; font-family: 'IBM Plex Mono', monospace; }

    input {
      padding: 9px 12px;
      background: #f8f9fb;
      border: 1.5px solid #d0d4dc;
      border-radius: 7px;
      font-family: 'DM Sans', sans-serif;
      font-size: 13.5px;
      color: #1a1f2e;
      outline: none;
      transition: border-color .15s, box-shadow .15s;
      width: 100%;
    }

    input:focus {
      border-color: #3b6ef0;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(59,110,240,.1);
    }

    input::placeholder { color: #c0c5d4; }

    .divider { border: none; border-top: 1px solid #e2e5eb; margin: 24px 0; }

    /* Submit */
    .btn-setup {
      width: 100%;
      padding: 12px;
      background: #3b6ef0;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 24px;
      transition: background .15s, box-shadow .15s, transform .12s;
      box-shadow: 0 2px 8px rgba(59,110,240,.3);
    }

    .btn-setup:hover {
      background: #2952cc;
      box-shadow: 0 4px 14px rgba(59,110,240,.38);
      transform: translateY(-1px);
    }

    /* Results */
    .results { margin-top: 0; }

    .result-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 7px;
      font-size: 13px;
      margin-bottom: 8px;
    }

    .result-item:last-child { margin-bottom: 0; }

    .result-item.ok      { background: #dcfce7; color: #15803d; }
    .result-item.error   { background: #fee2e2; color: #b91c1c; }
    .result-item.warning { background: #fef3c7; color: #92400e; }

    .result-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

    /* Success box */
    .success-box {
      background: #dcfce7;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: 28px;
      text-align: center;
    }

    .success-box .check {
      width: 52px; height: 52px;
      background: #16a34a;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
    }

    .success-box h3 { font-size: 18px; font-weight: 700; color: #15803d; margin-bottom: 8px; }
    .success-box p  { font-size: 13px; color: #166534; line-height: 1.6; }

    .btn-goto {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 20px;
      padding: 11px 24px;
      background: #16a34a;
      color: #fff;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: background .15s;
    }

    .btn-goto:hover { background: #15803d; }

    /* Already ran */
    .locked-box {
      background: #fef3c7;
      border: 1px solid #fde68a;
      border-radius: 12px;
      padding: 28px;
      text-align: center;
    }

    .locked-box h3 { font-size: 17px; font-weight: 700; color: #92400e; margin-bottom: 8px; }
    .locked-box p  { font-size: 13px; color: #78350f; line-height: 1.6; }

    .footnote {
      text-align: center;
      margin-top: 16px;
      font-size: 12px;
      color: #8b91a8;
      font-family: 'IBM Plex Mono', monospace;
    }
  </style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="logo">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
           fill="none" stroke="#fff" stroke-width="2.2">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
    </div>
    <h1>IT Inventory System</h1>
    <p>Database Setup &amp; Configuration</p>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>First-Time Setup</h2>
      <p>This will create all required database tables and your admin account.</p>
    </div>
    <div class="card-body">

      <?php if ($alreadyRun): ?>
      <!-- Already set up -->
      <div class="locked-box">
        <h3>⚠️ Setup Already Complete</h3>
        <p>The system has already been configured.<br>
           Delete <code>setup.lock</code> from the project folder if you need to re-run setup.</p>
        <a href="login.php" class="btn-goto" style="background:#d97706;margin-top:16px;display:inline-flex">
          Go to Login →
        </a>
      </div>

      <?php elseif ($done): ?>
      <!-- Success -->
      <div class="results" style="margin-bottom:20px">
        <?php foreach ($steps as [$type, $msg]): ?>
        <div class="result-item <?= $type ?>">
          <span class="result-icon"><?= $type === 'ok' ? '✓' : ($type === 'error' ? '✗' : '⚠') ?></span>
          <span><?= $msg ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="success-box">
        <div class="check">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
               fill="none" stroke="#fff" stroke-width="3">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <h3>Setup Complete!</h3>
        <p>All tables created and admin account ready.<br>
           <strong>Delete or rename <code>setup.php</code></strong> before going live.</p>
        <a href="login.php" class="btn-goto">Go to Login →</a>
      </div>

      <?php elseif (!empty($steps) && $hasError): ?>
      <!-- Errors during setup -->
      <div class="results" style="margin-bottom:20px">
        <?php foreach ($steps as [$type, $msg]): ?>
        <div class="result-item <?= $type ?>">
          <span class="result-icon"><?= $type === 'ok' ? '✓' : ($type === 'error' ? '✗' : '⚠') ?></span>
          <span><?= $msg ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Show form again with values pre-filled -->
      <?php
        $dbHost    = htmlspecialchars($_POST['db_host']        ?? 'localhost');
        $dbName    = htmlspecialchars($_POST['db_name']        ?? 'it_inventory');
        $dbUser    = htmlspecialchars($_POST['db_user']        ?? 'root');
        $adminUser = htmlspecialchars($_POST['admin_username'] ?? 'admin');
        $adminName = htmlspecialchars($_POST['admin_fullname'] ?? '');
        $adminEmail= htmlspecialchars($_POST['admin_email']    ?? '');
      ?>
      <p style="font-size:13px;color:#6b7280;margin-top:16px">
        Please <a href="setup.php" style="color:#3b6ef0">go back</a> and try again.
      </p>

      <?php else: ?>
      <!-- Initial form -->
      <form method="POST" id="setup-form">

        <p class="section-title">Database Connection</p>

        <div class="form-row">
          <div class="form-group">
            <label>Host <span class="req">*</span></label>
            <input type="text" name="db_host" value="localhost" placeholder="localhost" required>
          </div>
          <div class="form-group">
            <label>Database Name <span class="req">*</span></label>
            <input type="text" name="db_name" value="it_inventory" placeholder="it_inventory" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>MySQL Username <span class="req">*</span></label>
            <input type="text" name="db_user" value="root" placeholder="root" required>
          </div>
          <div class="form-group">
            <label>MySQL Password <span class="hint">(leave blank if none)</span></label>
            <input type="password" name="db_pass" placeholder="••••••••">
          </div>
        </div>

        <div class="divider"></div>
        <p class="section-title">Admin Account</p>

        <div class="form-row">
          <div class="form-group">
            <label>Admin Username <span class="req">*</span></label>
            <input type="text" name="admin_username" value="admin" placeholder="admin" required minlength="3">
          </div>
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <input type="password" name="admin_password" placeholder="Min. 6 characters" required minlength="6">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="admin_fullname" placeholder="System Administrator">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="admin_email" placeholder="admin@company.com">
          </div>
        </div>

        <button type="submit" class="btn-setup">Run Setup →</button>

      </form>
      <?php endif; ?>

    </div><!-- /card-body -->
  </div><!-- /card -->

  <p class="footnote">Delete setup.php after installation is complete.</p>

</div>
</body>
</html>
