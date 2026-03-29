<?php
/* ============================================================
   PROFILE — Edit My Account
   File: profile.php
   ============================================================ */

require_once 'includes/auth.php';

$success = $error = '';

/* ── FETCH fresh user data ── */
$user = dbRow('SELECT * FROM sys_users WHERE id = ?', [$authUser['id']]);
if (!$user) {
    header('Location: logout.php');
    exit;
}

/* ── HANDLE FORM ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Update profile info */
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!$fullName) {
            $error = 'Full name cannot be empty.';
        } else {
            try {
                dbExecute(
                    'UPDATE sys_users SET full_name=?, email=? WHERE id=?',
                    [$fullName, $email ?: null, $user['id']]
                );
                /* Refresh session name */
                $_SESSION['it_user']['full_name'] = $fullName;
                $authName = $fullName;

                /* Re-fetch */
                $user = dbRow('SELECT * FROM sys_users WHERE id = ?', [$user['id']]);
                $success = 'Profile updated successfully.';

                dbExecute(
                    'INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)',
                    [$user['id'], 'UPDATE', 'Updated profile info', $_SERVER['REMOTE_ADDR'] ?? '']
                );
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }

    /* Change password */
    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$newPass || !$confirm) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                dbExecute(
                    'UPDATE sys_users SET password_hash=? WHERE id=?',
                    [$hash, $user['id']]
                );
                $success = 'Password changed successfully.';
                dbExecute(
                    'INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)',
                    [$user['id'], 'UPDATE', 'Changed password', $_SERVER['REMOTE_ADDR'] ?? '']
                );
            } catch (PDOException $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$roleColor   = match($user['role']) { 'Admin' => '#dc2626', 'IT Staff' => '#3b6ef0', default => '#8b91a8' };
$authInitial = strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1));

$page_title  = 'My Profile';
$active_page = '';
include 'includes/layout.php';
?>

<div style="max-width:700px;margin:0 auto">

  <?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom:20px">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.5">
      <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Profile header card -->
  <div class="card" style="margin-bottom:20px">
    <div style="padding:28px;display:flex;align-items:center;gap:20px">
      <!-- Avatar -->
      <div style="width:64px;height:64px;border-radius:50%;background:<?= $roleColor ?>;
           display:flex;align-items:center;justify-content:center;
           font-size:26px;font-weight:700;color:#fff;flex-shrink:0;
           box-shadow:0 4px 14px <?= $roleColor ?>44">
        <?= $authInitial ?>
      </div>
      <div>
        <div style="font-size:20px;font-weight:700;color:var(--text-primary)">
          <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap">
          <span style="font-family:var(--font-mono);font-size:12.5px;color:var(--text-muted)">
            @<?= htmlspecialchars($user['username']) ?>
          </span>
          <?php
            $roleCls = match($user['role']) { 'Admin' => 'badge-red', 'IT Staff' => 'badge-blue', default => 'badge-gray' };
          ?>
          <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
          <span class="badge <?= $user['status'] === 'Active' ? 'badge-green' : 'badge-gray' ?>">
            <?= htmlspecialchars($user['status']) ?>
          </span>
        </div>
        <?php if ($user['email']): ?>
        <div style="font-size:12.5px;color:var(--text-muted);margin-top:5px">
          <?= htmlspecialchars($user['email']) ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="margin-left:auto;text-align:right">
        <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono)">Member since</div>
        <div style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-top:2px">
          <?= date('d M Y', strtotime($user['created_at'])) ?>
        </div>
        <?php if ($user['last_login']): ?>
        <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono);margin-top:8px">Last login</div>
        <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">
          <?= date('d M Y, H:i', strtotime($user['last_login'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Edit profile info -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="var(--accent)" stroke-width="2">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
      <div>
        <div class="card-title">Personal Information</div>
        <div class="card-subtitle">Update your name and email</div>
      </div>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name <span class="req">*</span></label>
            <input type="text" name="full_name"
                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                   placeholder="Your full name" required>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                   placeholder="your@email.com">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled
                   style="opacity:.6;cursor:not-allowed">
            <span class="form-hint">Username cannot be changed here</span>
          </div>
          <div class="form-group">
            <label>Role</label>
            <input type="text" value="<?= htmlspecialchars($user['role']) ?>" disabled
                   style="opacity:.6;cursor:not-allowed">
            <span class="form-hint">Role is managed by an Admin</span>
          </div>
        </div>
        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px">
          <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Change password -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="var(--yellow)" stroke-width="2">
        <rect x="3" y="11" width="18" height="11" rx="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      <div>
        <div class="card-title">Change Password</div>
        <div class="card-subtitle">Use a strong password of at least 6 characters</div>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" id="pwd-form">
        <input type="hidden" name="action" value="change_password">
        <div class="form-grid">
          <div class="form-group full-span">
            <label>Current Password <span class="req">*</span></label>
            <div style="position:relative">
              <input type="password" name="current_password" id="cur-pwd"
                     placeholder="Enter your current password" required
                     style="padding-right:40px">
              <button type="button" onclick="togglePwd('cur-pwd','eye-cur')"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--text-muted);
                             display:flex;align-items:center;padding:2px">
                <svg id="eye-cur" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label>New Password <span class="req">*</span></label>
            <div style="position:relative">
              <input type="password" name="new_password" id="new-pwd"
                     placeholder="Min. 6 characters" required minlength="6"
                     style="padding-right:40px" oninput="checkStrength(this.value)">
              <button type="button" onclick="togglePwd('new-pwd','eye-new')"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--text-muted);
                             display:flex;align-items:center;padding:2px">
                <svg id="eye-new" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <!-- Strength bar -->
            <div id="strength-bar" style="height:3px;border-radius:99px;background:var(--border);margin-top:4px;overflow:hidden">
              <div id="strength-fill" style="height:100%;width:0;border-radius:99px;transition:width .3s,background .3s"></div>
            </div>
            <span id="strength-label" class="form-hint"></span>
          </div>
          <div class="form-group">
            <label>Confirm New Password <span class="req">*</span></label>
            <div style="position:relative">
              <input type="password" name="confirm_password" id="con-pwd"
                     placeholder="Repeat new password" required
                     style="padding-right:40px">
              <button type="button" onclick="togglePwd('con-pwd','eye-con')"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--text-muted);
                             display:flex;align-items:center;padding:2px">
                <svg id="eye-con" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('pwd-form').reset()">Clear</button>
          <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /max-width -->

<?php include 'includes/layout_end.php'; ?>
<script>
function togglePwd(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon  = document.getElementById(iconId);
  var show  = input.type === 'text';
  input.type = show ? 'password' : 'text';
  icon.innerHTML = show
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>' +
      '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>' +
      '<line x1="1" y1="1" x2="23" y2="23"/>';
}

function checkStrength(val) {
  var fill  = document.getElementById('strength-fill');
  var label = document.getElementById('strength-label');
  var score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  var configs = [
    { w:'0%',   bg:'var(--border)',  txt:'' },
    { w:'25%',  bg:'var(--red)',     txt:'Weak' },
    { w:'50%',  bg:'var(--yellow)',  txt:'Fair' },
    { w:'75%',  bg:'#f59e0b',        txt:'Good' },
    { w:'90%',  bg:'var(--green)',   txt:'Strong' },
    { w:'100%', bg:'var(--green)',   txt:'Very Strong' },
  ];
  var c = configs[Math.min(score, 5)];
  fill.style.width      = val.length ? c.w : '0%';
  fill.style.background = c.bg;
  label.textContent     = c.txt;
  label.style.color     = c.bg;
}

<?php if ($success): ?>
Toast.show(<?= json_encode($success) ?>, 'success');
<?php endif; ?>
<?php if ($error): ?>
Toast.show(<?= json_encode($error) ?>, 'error');
<?php endif; ?>
</script>
<?php // end ?>
