<?php
/* ============================================================
   SETTINGS PAGE  (Admin only)
   Allows admin to change:
     - System name + subtitle shown in the sidebar
     - Accent color (buttons, links, badges, active nav)
     - Sidebar theme (light or dark)
     - Logo (upload image or use default icon)
   All changes apply instantly to all users on next page load.
   ============================================================ */
require_once 'includes/auth.php';
requireRole('Admin');

$page_title  = 'Settings';
$active_page = 'settings';
include 'includes/layout.php';

// Preset accent color options
$presetColors = [
    ['name' => 'Ocean Blue',    'value' => '#3b6ef0'],
    ['name' => 'Emerald',       'value' => '#059669'],
    ['name' => 'Rose',          'value' => '#e11d48'],
    ['name' => 'Violet',        'value' => '#7c3aed'],
    ['name' => 'Amber',         'value' => '#d97706'],
    ['name' => 'Cyan',          'value' => '#0891b2'],
    ['name' => 'Slate',         'value' => '#475569'],
    ['name' => 'Pink',          'value' => '#db2777'],
];

$currentAccent  = $sysSettings['accent_color']    ?? '#3b6ef0';

$currentName    = $sysSettings['system_name']      ?? 'IT INVENTORY SYSTEM';
$currentSub     = $sysSettings['system_subtitle']  ?? 'v1.0 · MIS Department';
$currentLogoType= $sysSettings['logo_type']        ?? 'icon';
$currentLogoImg = $sysSettings['logo_image']       ?? '';
?>

<div style="max-width:720px">

  <!-- ── SECTION: IDENTITY ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <div>
        <div class="card-title">System Identity</div>
        <div class="card-subtitle">Name and subtitle shown in the sidebar</div>
      </div>
    </div>
    <div class="card-body" style="padding:24px">
      <form id="form-identity">
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
          <div class="form-group full-span">
            <label>System Name</label>
            <input type="text" name="system_name" id="inp-name"
                   value="<?= htmlspecialchars($currentName) ?>"
                   placeholder="IT INVENTORY SYSTEM"
                   maxlength="50">
            <span class="form-hint">Shown at the top of the sidebar</span>
          </div>
          <div class="form-group full-span">
            <label>Subtitle</label>
            <input type="text" name="system_subtitle" id="inp-sub"
                   value="<?= htmlspecialchars($currentSub) ?>"
                   placeholder="v1.0 · MIS Department"
                   maxlength="60">
            <span class="form-hint">Shown below the system name</span>
          </div>
        </div>
        <div style="margin-top:16px">
          <button type="submit" class="btn btn-primary">Save Identity</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── SECTION: LOGO ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <div>
        <div class="card-title">Logo</div>
        <div class="card-subtitle">Upload a custom logo or use the default icon</div>
      </div>
    </div>
    <div class="card-body" style="padding:24px">

      <!-- Current logo preview -->
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;
                  padding:16px;background:var(--bg-elevated);border-radius:var(--radius-md);
                  border:1px solid var(--border)">
        <div id="logo-preview">
          <?php if ($currentLogoType === 'image' && $currentLogoImg): ?>
          <img src="<?= htmlspecialchars($currentLogoImg) ?>"
               style="width:48px;height:48px;object-fit:contain;border-radius:8px"
               alt="Current logo">
          <?php else: ?>
          <div style="width:48px;height:48px;background:var(--accent);border-radius:10px;
                      display:flex;align-items:center;justify-content:center">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                 fill="none" stroke="#fff" stroke-width="2.5">
              <rect x="2" y="3" width="20" height="14" rx="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/>
              <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
          </div>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text-primary)">
            <?= $currentLogoType === 'image' && $currentLogoImg ? 'Custom logo' : 'Default icon' ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
            <?= $currentLogoType === 'image' && $currentLogoImg
                ? htmlspecialchars(basename($currentLogoImg))
                : 'Using built-in monitor icon' ?>
          </div>
        </div>
        <?php if ($currentLogoType === 'image' && $currentLogoImg): ?>
        <button type="button" class="btn btn-ghost btn-sm ml-auto" id="btn-reset-logo">
          Reset to default
        </button>
        <?php endif; ?>
      </div>

      <!-- Upload form -->
      <form id="form-logo" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_logo">
        <div class="form-group">
          <label>Upload New Logo</label>
          <input type="file" name="logo_file" id="logo-file-input"
                 accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                 style="padding:8px;border:1px dashed var(--border);border-radius:var(--radius-sm);
                        width:100%;font-size:13px;cursor:pointer">
          <span class="form-hint">PNG, JPG, SVG or WEBP · Max 2MB · Recommended: 48×48px or square</span>
        </div>
        <button type="submit" class="btn btn-primary">Upload Logo</button>
      </form>
    </div>
  </div>

  <!-- ── SECTION: APPEARANCE ── -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <div>
        <div class="card-title">Appearance</div>
        <div class="card-subtitle">Accent color and sidebar theme</div>
      </div>
    </div>
    <div class="card-body" style="padding:24px">
      <form id="form-appearance">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="accent_color" id="inp-accent" value="<?= htmlspecialchars($currentAccent) ?>">

        <!-- Accent color presets -->
        <div class="form-group" style="margin-bottom:24px">
          <label style="display:block;margin-bottom:10px">Accent Color</label>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px">
            <?php foreach ($presetColors as $preset): ?>
            <button type="button"
                    class="color-preset <?= $currentAccent === $preset['value'] ? 'selected' : '' ?>"
                    data-color="<?= htmlspecialchars($preset['value']) ?>"
                    title="<?= htmlspecialchars($preset['name']) ?>"
                    style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;
                           background:<?= htmlspecialchars($preset['value']) ?>;cursor:pointer;
                           transition:all .15s;<?= $currentAccent === $preset['value'] ? 'border-color:#000;transform:scale(1.15)' : '' ?>">
            </button>
            <?php endforeach; ?>
            <!-- Custom color picker -->
            <div style="position:relative;width:32px;height:32px">
              <input type="color" id="color-picker" value="<?= htmlspecialchars($currentAccent) ?>"
                     title="Pick a custom color"
                     style="width:32px;height:32px;border-radius:50%;border:2px solid var(--border);
                            cursor:pointer;padding:0;overflow:hidden;background:none">
            </div>
          </div>
          <!-- Live preview strip -->
          <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;
                      background:var(--bg-elevated);border-radius:var(--radius-sm);
                      border:1px solid var(--border)">
            <span style="font-size:12px;color:var(--text-muted)">Preview:</span>
            <button id="preview-btn"
                    style="padding:5px 14px;border-radius:6px;border:none;cursor:default;
                           font-size:12px;font-weight:600;color:#fff;
                           background:<?= htmlspecialchars($currentAccent) ?>">
              Button
            </button>
            <span id="preview-link" style="font-size:12px;font-weight:500;
                  color:<?= htmlspecialchars($currentAccent) ?>">Link text</span>
            <span id="preview-badge"
                  style="font-size:11px;padding:2px 10px;border-radius:99px;font-weight:600;color:#fff;
                         background:<?= htmlspecialchars($currentAccent) ?>">Badge</span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Appearance</button>
      </form>
    </div>
  </div>

  <!-- ── SECTION: DANGER ZONE ── -->
  <div class="card" style="border-color:var(--red);margin-bottom:20px">
    <div class="card-header">
      <div>
        <div class="card-title" style="color:var(--red)">Reset Settings</div>
        <div class="card-subtitle">Restore all settings to their original defaults</div>
      </div>
    </div>
    <div class="card-body" style="padding:24px">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        This will reset the system name, subtitle, accent color, sidebar theme, and logo back to the original defaults. Your data (assets, locations, etc.) will not be affected.
      </p>
      <button type="button" class="btn btn-danger" id="btn-reset-all">
        Reset All Settings to Default
      </button>
    </div>
  </div>

</div><!-- end max-width wrapper -->

<?php include 'includes/layout_end.php'; ?>
<script>
/* ============================================================
   SETTINGS PAGE — CLIENT SCRIPT
   All saves go through api.php module=settings.
   Color changes apply a live CSS variable override so the
   admin can preview the color before saving.
   ============================================================ */

let selectedColor = '<?= htmlspecialchars($currentAccent) ?>';

/* ── Color preset buttons ── */
document.querySelectorAll('.color-preset').forEach(function(btn) {
    btn.addEventListener('click', function() {
        applyColor(this.dataset.color);
    });
});

/* ── Custom color picker ── */
document.getElementById('color-picker').addEventListener('input', function() {
    applyColor(this.value);
});

/* ── Apply color: update preview + hidden input + CSS variable live ── */
function applyColor(color) {
    selectedColor = color;
    document.getElementById('inp-accent').value = color;
    document.getElementById('color-picker').value = color;

    // Live preview elements
    document.getElementById('preview-btn').style.background   = color;
    document.getElementById('preview-link').style.color       = color;
    document.getElementById('preview-badge').style.background = color;

    // Live CSS variable override so sidebar/buttons update in real time
    document.documentElement.style.setProperty('--accent', color);
    document.documentElement.style.setProperty('--accent-dim', color + 'cc');
    document.documentElement.style.setProperty('--accent-light', color + '18');
    document.documentElement.style.setProperty('--accent-glow',  color + '12');

    // Highlight selected preset
    document.querySelectorAll('.color-preset').forEach(function(b) {
        const isSelected = b.dataset.color === color;
        b.style.borderColor = isSelected ? '#000' : 'transparent';
        b.style.transform   = isSelected ? 'scale(1.15)' : 'scale(1)';
    });
}


/* ── Form: Save Identity ── */
document.getElementById('form-identity').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'settings', function() {
        // Reflect name change in sidebar immediately
        const nameEl = document.querySelector('.sidebar-logo h1');
        const subEl  = document.querySelector('.sidebar-logo span');
        if (nameEl) nameEl.textContent = document.getElementById('inp-name').value;
        if (subEl)  subEl.textContent  = document.getElementById('inp-sub').value;
    });
});

/* ── Form: Save Appearance ── */
document.getElementById('form-appearance').addEventListener('submit', function(e) {
    e.preventDefault();
    AjaxForm.submit(this, 'settings', function() {
        // Full reload after appearance save so all pages use the new colors
        setTimeout(function() { window.location.reload(); }, 800);
    });
});

/* ── Form: Upload Logo ── */
document.getElementById('form-logo').addEventListener('submit', function(e) {
    e.preventDefault();
    const file = document.getElementById('logo-file-input').files[0];
    if (!file) { Toast.show('Please choose a file first.', 'error'); return; }

    const formData = new FormData(this);
    formData.set('module', 'settings');

    const btn = this.querySelector('[type=submit]');
    btn.disabled    = true;
    btn.textContent = 'Uploading…';

    fetch('api.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success) {
                Toast.show(json.success, 'success');
                // Show the uploaded logo in the preview
                const preview = document.getElementById('logo-preview');
                preview.innerHTML = '<img src="' + json.data.url + '?t=' + Date.now() + '" '
                    + 'style="width:48px;height:48px;object-fit:contain;border-radius:8px" alt="Logo">';
            } else {
                Toast.show(json.error || 'Upload failed.', 'error');
            }
        })
        .catch(function() { Toast.show('Upload error. Please try again.', 'error'); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Upload Logo'; });
});

/* ── Reset Logo ── */
var resetLogoBtn = document.getElementById('btn-reset-logo');
if (resetLogoBtn) {
    resetLogoBtn.addEventListener('click', function() {
        if (!confirm('Reset to the default icon?')) return;
        AjaxForm.action('settings', { action: 'reset_logo' }, function() {
            setTimeout(function() { window.location.reload(); }, 600);
        });
    });
}

/* ── Reset All Settings ── */
document.getElementById('btn-reset-all').addEventListener('click', function() {
    if (!confirm('Reset ALL settings to defaults? This cannot be undone.')) return;

    const defaults = {
        action:           'save',
        system_name:      'IT INVENTORY SYSTEM',
        system_subtitle:  'v1.0 · MIS Department',
        accent_color:     '#3b6ef0',
    };

    const formData = new FormData();
    formData.set('module', 'settings');
    Object.entries(defaults).forEach(function([k, v]) { formData.set(k, v); });

    fetch('api.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success) {
                Toast.show('Settings reset to defaults.', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                Toast.show(json.error || 'Reset failed.', 'error');
            }
        });
});
</script>
<?php // end ?>