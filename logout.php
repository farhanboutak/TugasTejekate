<?php
require_once 'config/koneksi.php';
require_once 'config/audit.php';
require_once 'config/auth.php';

if (simbar_role_has_any_permission($pdo, (string)($_SESSION['role'] ?? ''), ['transactions.manage', 'users.manage', 'audit.view'])) {
    try {
        catat_aktivitas($pdo, 'logout', 'auth', 'Pengguna keluar dari SIMBAR.');
    } catch (PDOException $e) {
        error_log('SIMBAR logout audit write failed: ' . $e->getMessage());
    }
}
remember_clear_cookie($pdo);
session_unset();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header("Location: login.php");
exit();
?>