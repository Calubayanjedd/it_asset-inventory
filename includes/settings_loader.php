<?php
/* ============================================================
   SETTINGS LOADER
   File: includes/settings_loader.php
   Loaded once by auth.php on every page.
   Fetches sys_settings from DB and makes them available
   globally as $sysSettings array and individual variables.
   Falls back to defaults if the table doesn't exist yet.
   ============================================================ */

function loadSettings(): array {
    $defaults = [
        'system_name'     => 'IT INVENTORY SYSTEM',
        'system_subtitle' => 'v1.0 · MIS Department',
        'accent_color'    => '#3b6ef0',
        'logo_type'       => 'icon',   // 'icon' or 'image'
        'logo_image'      => null,
    ];

    try {
        $rows = dbQuery('SELECT setting_key, setting_value FROM sys_settings');
        $loaded = [];
        foreach ($rows as $row) {
            $loaded[$row['setting_key']] = $row['setting_value'];
        }
        return array_merge($defaults, $loaded);
    } catch (Throwable $e) {
        // Table doesn't exist yet — return defaults silently
        return $defaults;
    }
}

$sysSettings = loadSettings();