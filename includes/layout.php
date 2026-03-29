<?php
/* ============================================================
   IT INVENTORY — SHARED HTML HEADER + SIDEBAR
   File: includes/layout.php
   ============================================================ */

require_once __DIR__ . '/auth.php';

$page_title  = $page_title  ?? 'IT Inventory';
$active_page = $active_page ?? '';

$nav = [
    [
        'label' => 'Overview',
        'items' => [
            ['id'=>'dashboard', 'label'=>'Dashboard',           'href'=>'dashboard.php',  'icon'=>'layout-dashboard'],
        ]
    ],
    [
        'label' => 'Core Modules',
        'items' => [
            ['id'=>'assets',     'label'=>'Asset Registry',    'href'=>'assets.php',     'icon'=>'monitor'],
            ['id'=>'assignment', 'label'=>'Assignment',         'href'=>'assignment.php', 'icon'=>'users'],
            ['id'=>'location',   'label'=>'Locations',          'href'=>'location.php',   'icon'=>'map-pin'],
        ]
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['id'=>'stock', 'label'=>'Stock / Consumables', 'href'=>'stock.php', 'icon'=>'package'],
        ]
    ],
    [
        'label' => 'Administration',
        'items' => [
            ['id'=>'users', 'label'=>'User Roles', 'href'=>'users.php', 'icon'=>'shield'],
        ]
    ]
];

/* Hide Administration section for non-admins */
if ($authRole !== 'Admin') {
    $nav[3]['items'] = [];
}

function navIcon(string $name): string {
    $icons = [
        'monitor'          => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'users'            => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'map-pin'          => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'package'          => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'layout-dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'shield'           => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

$roleColor   = match($authRole) { 'Admin' => '#dc2626', 'IT Staff' => '#3b6ef0', default => '#8b91a8' };
$authInitial = strtoupper(substr($authName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — IT Inventory</title>
  <link rel="stylesheet" href="css/style.css">
  <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>

<aside class="sidebar" id="sidebar">

  <div class="sidebar-logo">
    <div class="logo-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
           fill="none" stroke="#fff" stroke-width="2.5">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
    </div>
    <h1>IT Inventory<br>System</h1>
    <span>IT Department · v1.0</span>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($nav as $section): ?>
      <?php if (empty($section['items'])) continue; ?>
      <div class="nav-section-label"><?= $section['label'] ?></div>
      <?php foreach ($section['items'] as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= $active_page === $item['id'] ? 'active' : '' ?>">
          <?= navIcon($item['icon']) ?>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <!-- User profile block — click to open profile page -->
  <div style="padding:10px;border-top:1px solid var(--border)">
    <a href="profile.php"
       style="display:flex;align-items:center;gap:10px;padding:9px 10px;
              border-radius:var(--radius-sm);background:var(--bg-elevated);
              text-decoration:none;transition:background .15s;cursor:pointer;border:1px solid transparent"
       onmouseover="this.style.background='var(--accent-light)';this.style.borderColor='rgba(59,110,240,.2)'"
       onmouseout="this.style.background='var(--bg-elevated)';this.style.borderColor='transparent'"
       title="Edit your profile">
      <!-- Avatar -->
      <div style="width:32px;height:32px;border-radius:50%;background:<?= $roleColor ?>;
           display:flex;align-items:center;justify-content:center;
           font-size:13px;font-weight:700;color:#fff;flex-shrink:0;
           box-shadow:0 2px 6px <?= $roleColor ?>44">
        <?= $authInitial ?>
      </div>
      <!-- Name + role -->
      <div style="flex:1;min-width:0">
        <div style="font-size:12.5px;font-weight:600;color:var(--text-primary);
             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3">
          <?= htmlspecialchars($authName) ?>
        </div>
        <div style="font-size:10.5px;color:var(--text-muted);font-family:var(--font-mono);line-height:1.3">
          <?= htmlspecialchars($authRole) ?>
        </div>
      </div>
      <!-- Edit icon -->
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
           fill="none" stroke="var(--text-muted)" stroke-width="2" style="flex-shrink:0">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
      </svg>
    </a>

    <!-- Logout button -->
    <a href="logout.php"
       style="display:flex;align-items:center;justify-content:center;gap:7px;
              margin-top:6px;padding:7px 10px;border-radius:var(--radius-sm);
              text-decoration:none;font-size:12.5px;font-weight:500;
              color:var(--text-muted);border:1px solid var(--border);
              transition:all .15s;background:transparent"
       onmouseover="this.style.color='var(--red)';this.style.borderColor='rgba(220,38,38,.3)';this.style.background='var(--red-bg)'"
       onmouseout="this.style.color='var(--text-muted)';this.style.borderColor='var(--border)';this.style.background='transparent'">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Sign Out
    </a>
  </div>

</aside>

<div class="main-content">

  <header class="topbar">
    <button class="btn btn-ghost btn-sm" id="sidebar-toggle" style="display:none"
            onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <div>
      <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    </div>
    <div class="topbar-actions">
      <span class="text-mono text-muted"><?= date('D, d M Y') ?></span>
    </div>
  </header>

  <main class="page-body">
