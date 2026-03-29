<?php
/* ============================================================
   IT INVENTORY — LOGOUT
   File: logout.php
   Destroys the session and redirects to login.
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Log the activity before destroying session */
if (!empty($_SESSION['it_user'])) {
    try {
        require_once 'includes/db.php';
        $uid = $_SESSION['it_user']['id'] ?? null;
        if ($uid) {
            dbExecute(
                'INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)',
                [$uid, 'LOGOUT', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '']
            );
        }
    } catch (Throwable $e) {
        /* Non-fatal */
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'],
        $p['secure'], $p['httponly']
    );
}

session_destroy();
header('Location: login.php?logged_out=1');
exit;
