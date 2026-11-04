<?php
function simbar_permission_catalog(): array
{
    return [
        'transactions.view' => 'Melihat transaksi',
        'transactions.manage' => 'Mengubah status transaksi',
        'transactions.export' => 'Mengekspor transaksi',
        'transactions.delete' => 'Menghapus transaksi',
        'inventory.manage' => 'Mengelola inventaris',
        'users.manage' => 'Mengelola pengguna dan role',
        'audit.view' => 'Melihat log aktivitas',
        'borrow.use' => 'Mengajukan dan memantau peminjaman',
    ];
}

function simbar_default_role_permissions(): array
{
    return [
        'admin' => array_keys(simbar_permission_catalog()),
        'petugas' => ['transactions.view', 'transactions.manage', 'transactions.export', 'inventory.manage'],
        'peminjam' => ['borrow.use'],
    ];
}

function simbar_role_permissions(PDO $pdo, string $role): array
{
    static $cache = [];
    if (isset($cache[$role])) {
        return $cache[$role];
    }

    try {
        $stmt = $pdo->prepare('SELECT permissions FROM roles WHERE role_key = :role LIMIT 1');
        $stmt->execute([':role' => $role]);
        $stored_permissions = $stmt->fetchColumn();
        $permissions = $stored_permissions === false
            ? []
            : json_decode((string)$stored_permissions, true);
        if (!is_array($permissions)) {
            $permissions = [];
        }
    } catch (PDOException $e) {
        $permissions = simbar_default_role_permissions()[$role] ?? [];
    }

    $valid_permissions = simbar_permission_catalog();
    $cache[$role] = array_values(array_intersect(array_keys($valid_permissions), $permissions));

    return $cache[$role];
}

function simbar_user_has_permission(string $permission): bool
{
    global $pdo;
    $role = $_SESSION['role'] ?? '';
    if (!isset($pdo) || !is_string($role)) {
        return false;
    }

    return simbar_role_has_permission($pdo, $role, $permission);
}

function simbar_role_has_permission(PDO $pdo, string $role, string $permission): bool
{
    $permissions = simbar_role_permissions($pdo, $role);
    if ($permission === 'transactions.view' && array_intersect(
        ['transactions.manage', 'transactions.export', 'transactions.delete'],
        $permissions
    )) {
        return true;
    }

    return in_array($permission, $permissions, true);
}

function simbar_role_has_any_permission(PDO $pdo, string $role, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (simbar_role_has_permission($pdo, $role, $permission)) {
            return true;
        }
    }

    return false;
}

function simbar_user_has_any_permission(array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (simbar_user_has_permission($permission)) {
            return true;
        }
    }

    return false;
}

function simbar_role_label(PDO $pdo, string $role): string
{
    try {
        $stmt = $pdo->prepare('SELECT label FROM roles WHERE role_key = :role LIMIT 1');
        $stmt->execute([':role' => $role]);
        $label = $stmt->fetchColumn();
        if (is_string($label) && $label !== '') {
            return $label;
        }
    } catch (PDOException $e) {
        // Existing built-in role labels remain available until the migration is applied.
    }

    return ucfirst($role);
}

function simbar_dashboard_url(PDO $pdo, string $role): string
{
    $permissions = simbar_role_permissions($pdo, $role);
    if ($role === 'admin' && in_array('transactions.view', $permissions, true)) {
        return 'admin/index.php';
    }
    if ($role === 'petugas' && in_array('transactions.view', $permissions, true)) {
        return 'petugas/index.php';
    }

    foreach ([
        'transactions.view' => 'admin/index.php',
        'transactions.manage' => 'admin/index.php',
        'transactions.export' => 'admin/index.php',
        'transactions.delete' => 'admin/index.php',
        'borrow.use' => 'peminjaman/index.php',
        'inventory.manage' => 'admin/barang.php',
        'users.manage' => 'admin/pengguna.php',
        'audit.view' => 'admin/log.php',
    ] as $permission => $url) {
        if (in_array($permission, $permissions, true)) {
            return $url;
        }
    }

    return 'login.php';
}

function cek_akses($required_permission): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    if (is_array($required_permission)) {
        if (in_array($_SESSION['role'] ?? '', $required_permission, true)) {
            return;
        }
    } elseif (is_string($required_permission) && simbar_user_has_permission($required_permission)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(403);
    }
    echo 'Akses ditolak. Role akun ini tidak memiliki izin untuk halaman tersebut.';
    exit();
}

function simbar_require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }
}
?>