<?php
require_once 'config/koneksi.php';
require_once 'config/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php?mode=register');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: login.php?mode=register&pesan=daftar_csrf');
    exit;
}

$nama = isset($_POST['nama']) && is_string($_POST['nama']) ? trim($_POST['nama']) : '';
$username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$password_confirmation = isset($_POST['password_confirmation']) && is_string($_POST['password_confirmation'])
    ? $_POST['password_confirmation']
    : '';

$nama_valid = preg_match('/\A.{1,100}\z/us', $nama) === 1;
$username_valid = preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) === 1;
$password_valid = strlen($password) >= 8 && strlen($password) <= 72;

if (!$nama_valid || !$username_valid || !$password_valid || $password !== $password_confirmation) {
    header('Location: login.php?mode=register&pesan=daftar_invalid');
    exit;
}

try {
    if (simbar_username_in_use($pdo, $username)) {
        header('Location: login.php?mode=register&pesan=username_terpakai');
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        error_log('SIMBAR: Password hashing failed during registration.');
        header('Location: login.php?mode=register&pesan=daftar_gagal');
        exit;
    }

    $pdo->beginTransaction();
    $user_id = simbar_next_user_id($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO users (id, nama, username, password, role)
         VALUES (:id, :nama, :username, :password, :role)'
    );
    $stmt->execute([
        ':id' => $user_id,
        ':nama' => $nama,
        ':username' => $username,
        ':password' => $password_hash,
        ':role' => 'peminjam',
    ]);
    $pdo->commit();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: login.php?pesan=daftar_sukses');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof PDOException && simbar_is_duplicate_username($e)) {
        header('Location: login.php?mode=register&pesan=username_terpakai');
        exit;
    }

    error_log('SIMBAR registration error: ' . $e->getMessage());
    header('Location: login.php?mode=register&pesan=daftar_gagal');
    exit;
}
