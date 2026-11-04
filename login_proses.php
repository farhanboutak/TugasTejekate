<?php
require_once 'config/koneksi.php';
require_once 'config/audit.php';
require_once 'config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: login.php?pesan=login_csrf');
    exit;
}

$username = isset($_POST['username']) && is_string($_POST['username'])
    ? trim($_POST['username'])
    : '';
$password = isset($_POST['password']) && is_string($_POST['password'])
    ? $_POST['password']
    : '';
$remember = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

try {
    $stmt = $pdo->prepare(
        'SELECT id, nama, username, password, role FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        if ($user && simbar_role_has_any_permission($pdo, (string)$user['role'], ['transactions.manage', 'users.manage', 'audit.view'])) {
            try {
                catat_aktivitas($pdo, 'login.gagal', 'auth', 'Percobaan login gagal untuk akun staf.', null, [], [
                    'id' => null,
                    'nama' => $user['username'],
                    'role' => $user['role'],
                ]);
            } catch (PDOException $auditError) {
                error_log('SIMBAR failed login audit write failed: ' . $auditError->getMessage());
            }
        }
        header('Location: login.php?pesan=gagal');
        exit;
    }

    if ($remember) {
        remember_issue_token($pdo, (int)$user['id']);
    } else {
        remember_clear_cookie($pdo);
    }

    if (simbar_role_has_any_permission($pdo, (string)$user['role'], ['transactions.manage', 'users.manage', 'audit.view'])) {
        try {
            catat_aktivitas($pdo, 'login.berhasil', 'auth', 'Pengguna berhasil masuk.', (int)$user['id'], [], [
                'id' => (int)$user['id'],
                'nama' => $user['nama'],
                'role' => $user['role'],
            ]);
        } catch (PDOException $auditError) {
            error_log('SIMBAR login audit write failed: ' . $auditError->getMessage());
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $destination = simbar_dashboard_url($pdo, (string)$user['role']);
    if ($destination === 'login.php') {
        session_unset();
        session_destroy();
        error_log('SIMBAR login failed: unsupported role for user ID ' . (int)$user['id']);
        header('Location: login.php?pesan=gagal');
        exit;
    }

    header('Location: ' . $destination);
    exit;
} catch (PDOException $e) {
    error_log('SIMBAR login failed: ' . $e->getMessage());
    header('Location: login.php?pesan=sistem');
    exit;
}
