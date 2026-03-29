<?php
/* ============================================================
   IT INVENTORY — LOGIN PAGE
   File: login.php
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['it_user'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';

$error    = '';
$redirect = trim($_GET['redirect'] ?? 'dashboard.php');
$loggedOut = isset($_GET['logged_out']);

/* Sanitise redirect */
if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+\.php$/', $redirect)) {
    $redirect = 'dashboard.php';
}

/* ── HANDLE LOGIN ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $user = dbRow(
                'SELECT * FROM sys_users WHERE username = ? AND status = "Active" LIMIT 1',
                [$username]
            );
        } catch (Throwable $e) {
            $error = 'Database error: ' . $e->getMessage();
            $user  = null;
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['it_user'] = [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'] ?? '',
                'role'      => $user['role'],
                'email'     => $user['email'] ?? '',
            ];

            try {
                dbExecute('UPDATE sys_users SET last_login = NOW() WHERE id = ?', [$user['id']]);
                dbExecute(
                    'INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)',
                    [$user['id'], 'LOGIN', 'Logged in', $_SERVER['REMOTE_ADDR'] ?? '']
                );
            } catch (Throwable $e) { /* non-fatal */ }

            header('Location: ' . $redirect);
            exit;
        } else {
            sleep(1);
            $error = 'Invalid username or password, or account is disabled.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — IT Inventory System</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --accent:       #3b6ef0;
      --accent-dim:   #2952cc;
      --accent-glow:  rgba(59,110,240,.12);
      --bg:           #f0f2f5;
      --surface:      #ffffff;
      --border:       #e2e5eb;
      --border-md:    #d0d4dc;
      --text-1:       #1a1f2e;
      --text-2:       #4a5068;
      --text-3:       #8b91a8;
      --red:          #dc2626;
      --red-bg:       #fee2e2;
      --green:        #16a34a;
      --green-bg:     #dcfce7;
    }

    html, body {
      min-height: 100vh;
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text-1);
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 50% at 10% 10%, rgba(59,110,240,.07) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 90% 90%, rgba(124,58,237,.05) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    .login-wrap {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 400px;
    }

    /* Brand */
    .brand {
      text-align: center;
      margin-bottom: 28px;
    }

    .brand-icon {
      width: 52px; height: 52px;
      background: var(--accent);
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
      box-shadow: 0 4px 16px rgba(59,110,240,.35);
    }

    .brand h1 {
      font-size: 21px;
      font-weight: 700;
      color: var(--text-1);
      letter-spacing: -.02em;
    }

    .brand p {
      font-size: 12.5px;
      color: var(--text-3);
      margin-top: 4px;
      font-family: 'IBM Plex Mono', monospace;
    }

    /* Card */
    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px 32px 28px;
      box-shadow: 0 4px 24px rgba(0,0,0,.09), 0 1px 4px rgba(0,0,0,.05);
    }

    .login-card h2 {
      font-size: 16px;
      font-weight: 700;
      color: var(--text-1);
      margin-bottom: 4px;
    }

    .login-card .subtitle {
      font-size: 13px;
      color: var(--text-3);
      margin-bottom: 24px;
    }

    /* Alerts */
    .alert {
      border-radius: 8px;
      font-size: 13px;
      padding: 11px 14px;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 9px;
      line-height: 1.5;
    }

    .alert svg { flex-shrink: 0; margin-top: 1px; }

    .alert-error   { background: var(--red-bg);   color: #b91c1c; border: 1px solid #fecaca; }
    .alert-success { background: var(--green-bg); color: #15803d; border: 1px solid #bbf7d0; }

    /* Form */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }

    label {
      font-size: 12.5px;
      font-weight: 600;
      color: var(--text-2);
    }

    /* Input wrapper — icon LEFT, eye toggle RIGHT */
    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 11px;
      color: var(--text-3);
      width: 16px; height: 16px;
      pointer-events: none;
      flex-shrink: 0;
      z-index: 1;
    }

    .input-wrap input {
      width: 100%;
      padding: 10px 12px 10px 36px;
      background: #f8f9fb;
      border: 1.5px solid var(--border-md);
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 13.5px;
      color: var(--text-1);
      outline: none;
      transition: border-color .16s, box-shadow .16s, background .16s;
    }

    /* Password field needs right padding for eye button */
    .input-wrap input.has-toggle {
      padding-right: 40px;
    }

    .input-wrap input::placeholder { color: #c0c5d4; }
    .input-wrap input:hover { border-color: #b0b8cc; }

    .input-wrap input:focus {
      border-color: var(--accent);
      background: #fff;
      box-shadow: 0 0 0 3px var(--accent-glow);
    }

    /* Eye toggle button */
    .eye-toggle {
      position: absolute;
      right: 10px;
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-3);
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
      transition: color .15s, background .15s;
      z-index: 2;
    }

    .eye-toggle:hover {
      color: var(--text-2);
      background: var(--bg);
    }

    /* Submit button */
    .btn-login {
      width: 100%;
      padding: 11px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
      transition: background .16s, box-shadow .16s, transform .12s;
      box-shadow: 0 2px 8px rgba(59,110,240,.3);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-login:hover {
      background: var(--accent-dim);
      box-shadow: 0 4px 14px rgba(59,110,240,.38);
      transform: translateY(-1px);
    }

    .btn-login:active { transform: none; }
    .btn-login:disabled { opacity: .7; cursor: not-allowed; transform: none; }

    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: none;
      flex-shrink: 0;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 22px;
      font-size: 12px;
      color: var(--text-3);
      font-family: 'IBM Plex Mono', monospace;
    }
  </style>
</head>
<body>

<div class="login-wrap">

  <div class="brand">
    <div class="brand-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
           fill="none" stroke="#fff" stroke-width="2.2">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
    </div>
    <h1>IT Inventory System</h1>
    <p>MIS Department · v1.0</p>
  </div>

  <div class="login-card">
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your account to continue</p>

    <?php if ($loggedOut && !$error): ?>
    <div class="alert alert-success">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      You have been signed out successfully.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="login-form">

      <div class="form-group">
        <label for="username">Username</label>
        <div class="input-wrap">
          <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <input
            type="text"
            id="username"
            name="username"
            placeholder="Enter your username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            required
          >
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            class="has-toggle"
            autocomplete="current-password"
            required
          >
          <button type="button" class="eye-toggle" id="eye-toggle"
                  onclick="togglePassword()" title="Show / hide password" tabindex="-1">
            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="17" height="17"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="submit-btn">
        <span class="spinner" id="spinner"></span>
        <span id="btn-label">Sign In</span>
      </button>

    </form>
  </div>

  <div class="login-footer">IT Inventory System &middot; MIS Department</div>

</div>

<script>
function togglePassword() {
  var input   = document.getElementById('password');
  var eyeIcon = document.getElementById('eye-icon');
  var showing = input.type === 'text';

  input.type = showing ? 'password' : 'text';

  /* Update SVG icon */
  if (showing) {
    /* Eye open */
    eyeIcon.innerHTML =
      '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
      '<circle cx="12" cy="12" r="3"/>';
  } else {
    /* Eye with slash */
    eyeIcon.innerHTML =
      '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8' +
      'a18.45 18.45 0 0 1 5.06-5.94"/>' +
      '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8' +
      'a18.5 18.5 0 0 1-2.16 3.19"/>' +
      '<line x1="1" y1="1" x2="23" y2="23"/>';
  }

  input.focus();
}

document.getElementById('login-form').addEventListener('submit', function() {
  var btn     = document.getElementById('submit-btn');
  var spinner = document.getElementById('spinner');
  var label   = document.getElementById('btn-label');

  btn.disabled         = true;
  spinner.style.display = 'block';
  label.textContent    = 'Signing in…';
});
</script>

</body>
</html>
