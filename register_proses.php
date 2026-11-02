<?php
require_once 'config/koneksi.php';

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
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: login.php?mode=register&pesan=username_terpakai');
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        error_log('SIMBAR: Password hashing failed during registration.');
        header('Location: login.php?mode=register&pesan=daftar_gagal');
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (nama, username, password, role)
         VALUES (:nama, :username, :password, :role)'
    );
    $stmt->execute([
        ':nama' => $nama,
        ':username' => $username,
        ':password' => $password_hash,
        ':role' => 'peminjam',
    ]);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: login.php?pesan=daftar_sukses');
    exit;
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        header('Location: login.php?mode=register&pesan=username_terpakai');
        exit;
    }

    error_log('SIMBAR registration error: ' . $e->getMessage());
    header('Location: login.php?mode=register&pesan=daftar_gagal');
    exit;
}
