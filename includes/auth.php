<?php
/* ============================================================
   IT INVENTORY — AUTH GUARD
   File: includes/auth.php
   Include this as the VERY FIRST thing in every protected page.
   Starts session, loads DB, redirects to login if not authed.
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Load DB helpers — auth.php is always included before db.php */
require_once __DIR__ . '/db.php';

if (empty($_SESSION['it_user'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: login.php' . ($redirect ? '?redirect=' . $redirect : ''));
    exit;
}

/* Shortcuts available everywhere */
$authUser     = $_SESSION['it_user'];
$authRole     = $authUser['role']      ?? 'Viewer';
$authUsername = $authUser['username']  ?? '';
$authName     = !empty($authUser['full_name']) ? $authUser['full_name'] : $authUser['username'];

function requireRole(string ...$roles): void {
    global $authRole;
    if (!in_array($authRole, $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
              <style>body{font-family:sans-serif;display:flex;align-items:center;
              justify-content:center;min-height:100vh;background:#f0f2f5;margin:0}
              .box{text-align:center;padding:48px;background:#fff;border-radius:14px;
              border:1px solid #e2e5eb;box-shadow:0 4px 24px rgba(0,0,0,.08)}
              h2{color:#dc2626;margin-bottom:8px}p{color:#6b7280;font-size:14px}
              a{color:#3b6ef0;margin-top:20px;display:inline-block;text-decoration:none;
              font-size:14px}</style></head><body>
              <div class="box"><h2>Access Denied</h2>
              <p>You do not have permission to view this page.</p>
              <a href="dashboard.php">← Back to Dashboard</a></div></body></html>';
        exit;
    }
}
