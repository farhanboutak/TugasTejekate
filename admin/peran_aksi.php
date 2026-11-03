<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once '../config/audit.php';

cek_akses('users.manage');

function kembali_ke_peran(string $pesan): void
{
    header('Location: peran.php?pesan=' . urlencode($pesan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali_ke_peran('invalid');
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    kembali_ke_peran('csrf');
}

$aksi = isset($_POST['aksi']) && is_string($_POST['aksi']) ? $_POST['aksi'] : '';
$label = isset($_POST['label']) && is_string($_POST['label']) ? trim($_POST['label']) : '';
$raw_permissions = $_POST['permissions'] ?? [];
$permission_catalog = simbar_permission_catalog();
$valid_permissions = [];
if (is_array($raw_permissions)) {
    foreach ($raw_permissions as $permission) {
        if (!is_string($permission) || !isset($permission_catalog[$permission])) {
            kembali_ke_peran('invalid');
        }
        $valid_permissions[] = $permission;
    }
}
$valid_permissions = array_values(array_unique($valid_permissions));
if (array_intersect(['transactions.manage', 'transactions.export', 'transactions.delete'], $valid_permissions)) {
    $valid_permissions[] = 'transactions.view';
}
$valid_permissions = array_values(array_unique($valid_permissions));

if (
    $label === '' ||
    mb_strlen($label, 'UTF-8') > 100 ||
    !$valid_permissions ||
    preg_match('/\A[\p{L}\p{N} _.-]+\z/u', $label) !== 1
) {
    kembali_ke_peran('invalid');
}

if ($aksi === 'ubah') {
    $role_key = isset($_POST['role_key']) && is_string($_POST['role_key']) ? $_POST['role_key'] : '';
    if ($role_key === '' || $role_key === 'admin' && !in_array('users.manage', $valid_permissions, true)) {
        kembali_ke_peran($role_key === 'admin' ? 'izin_admin_wajib' : 'invalid');
    }
    if ($role_key === 'peminjam' && $valid_permissions !== ['borrow.use']) {
        kembali_ke_peran('invalid');
    }
} elseif ($aksi === 'tambah') {
    $role_key = 'custom_' . bin2hex(random_bytes(6));
} else {
    kembali_ke_peran('invalid');
}

try {
    $pdo->beginTransaction();
    $before = null;
    if ($aksi === 'ubah') {
        $stmt = $pdo->prepare('SELECT label, permissions FROM roles WHERE role_key = :role_key FOR UPDATE');
        $stmt->execute([':role_key' => $role_key]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            $pdo->rollBack();
            kembali_ke_peran('invalid');
        }

        if ($role_key === (string)$_SESSION['role'] && !in_array('users.manage', $valid_permissions, true)) {
            $pdo->rollBack();
            kembali_ke_peran('izin_admin_wajib');
        }

        $stmt = $pdo->prepare('UPDATE roles SET label = :label, permissions = :permissions WHERE role_key = :role_key');
        $stmt->execute([
            ':label' => $label,
            ':permissions' => json_encode($valid_permissions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':role_key' => $role_key,
        ]);
        $event = 'role.diperbarui';
        $summary = 'Role "' . $label . '" diperbarui.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO roles (role_key, label, permissions, is_system)
             VALUES (:role_key, :label, :permissions, 0)'
        );
        $stmt->execute([
            ':role_key' => $role_key,
            ':label' => $label,
            ':permissions' => json_encode($valid_permissions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $event = 'role.ditambahkan';
        $summary = 'Role "' . $label . '" ditambahkan.';
    }

    catat_aktivitas($pdo, $event, 'role', $summary, null, [
        'role_key' => $role_key,
        'nama' => $label,
        'izin' => $valid_permissions,
        'sebelum' => $before,
    ]);
    $pdo->commit();
    kembali_ke_peran($aksi === 'ubah' ? 'diperbarui' : 'ditambahkan');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        kembali_ke_peran('nama_terpakai');
    }
    error_log('SIMBAR role management error: ' . $e->getMessage());
    kembali_ke_peran('gagal');
}