<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once '../config/audit.php';

cek_akses(['admin']);

function kembali_ke_pengguna($pesan)
{
    header('Location: pengguna.php?pesan=' . urlencode($pesan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali_ke_pengguna('invalid');
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    kembali_ke_pengguna('csrf');
}

$aksi = isset($_POST['aksi']) && is_string($_POST['aksi']) ? $_POST['aksi'] : '';
$roles = ['admin', 'petugas', 'peminjam'];

try {
    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $nama = isset($_POST['nama']) && is_string($_POST['nama']) ? trim($_POST['nama']) : '';
        $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $role = isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

        if (
            $nama === '' ||
            mb_strlen($nama, 'UTF-8') > 100 ||
            preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1 ||
            !in_array($role, $roles, true)
        ) {
            kembali_ke_pengguna('invalid');
        }

        if ($aksi === 'tambah' && (strlen($password) < 8 || strlen($password) > 72)) {
            kembali_ke_pengguna('password_invalid');
        }

        if ($aksi === 'ubah' && $password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
            kembali_ke_pengguna('password_invalid');
        }

        if ($aksi === 'tambah') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($password_hash === false) {
                error_log('SIMBAR: Password hashing failed while creating a user.');
                kembali_ke_pengguna('gagal');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO users (nama, username, password, role)
                 VALUES (:nama, :username, :password, :role)'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':username' => $username,
                ':password' => $password_hash,
                ':role' => $role,
            ]);
            $id_baru = (int)$pdo->lastInsertId();
            catat_aktivitas(
                $pdo,
                'pengguna.ditambahkan',
                'pengguna',
                'Akun "' . $nama . '" (' . $username . ') ditambahkan dengan role ' . $role . '.',
                $id_baru,
                ['username' => $username, 'role' => $role]
            );
            $pdo->commit();
            kembali_ke_pengguna('ditambahkan');
        }

        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_pengguna('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->rollBack();
            kembali_ke_pengguna('tidak_ada');
        }

        if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
            $pdo->rollBack();
            kembali_ke_pengguna('role_ditolak');
        }

        if ($user['role'] === 'admin' && $role !== 'admin') {
            $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($admin_count <= 1) {
                $pdo->rollBack();
                kembali_ke_pengguna('admin_terakhir');
            }
        }

        if ($password !== '') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($password_hash === false) {
                $pdo->rollBack();
                error_log('SIMBAR: Password hashing failed while editing a user.');
                kembali_ke_pengguna('gagal');
            }

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET nama = :nama, username = :username, role = :role, password = :password
                 WHERE id = :id'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':username' => $username,
                ':role' => $role,
                ':password' => $password_hash,
                ':id' => $id,
            ]);
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET nama = :nama, username = :username, role = :role
                 WHERE id = :id'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':username' => $username,
                ':role' => $role,
                ':id' => $id,
            ]);
        }

        catat_aktivitas(
            $pdo,
            'pengguna.diperbarui',
            'pengguna',
            'Akun "' . $username . '" diperbarui.',
            $id,
            [
                'sebelum' => [
                    'nama' => $user['nama'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                ],
                'sesudah' => [
                    'nama' => $nama,
                    'username' => $username,
                    'role' => $role,
                    'password_diubah' => $password !== '',
                ],
            ]
        );
        $pdo->commit();
        if ($id === (int)$_SESSION['user_id']) {
            $_SESSION['nama'] = $nama;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
        }
        kembali_ke_pengguna('diperbarui');
    }

    if ($aksi === 'hapus') {
        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0 || $id === (int)$_SESSION['user_id']) {
            kembali_ke_pengguna('hapus_ditolak');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->rollBack();
            kembali_ke_pengguna('tidak_ada');
        }

        if ($user['role'] === 'admin') {
            $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($admin_count <= 1) {
                $pdo->rollBack();
                kembali_ke_pengguna('admin_terakhir');
            }
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM peminjaman WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            kembali_ke_pengguna('memiliki_transaksi');
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        catat_aktivitas(
            $pdo,
            'pengguna.dihapus',
            'pengguna',
            'Akun "' . $user['nama'] . '" (' . $user['username'] . ') dihapus.',
            $id,
            ['username' => $user['username'], 'role' => $user['role']]
        );
        $pdo->commit();
        kembali_ke_pengguna('dihapus');
    }

    kembali_ke_pengguna('invalid');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        kembali_ke_pengguna('username_terpakai');
    }

    error_log('SIMBAR user management error: ' . $e->getMessage());
    kembali_ke_pengguna('gagal');
}
