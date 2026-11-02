<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['admin']);

function kembali_ke_dashboard($pesan)
{
    header('Location: index.php?pesan=' . urlencode($pesan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali_ke_dashboard('invalid');
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    kembali_ke_dashboard('csrf');
}

$id_input = $_POST['id'] ?? '';
$id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
if ($id <= 0) {
    kembali_ke_dashboard('invalid');
}

try {
    $stmt = $pdo->prepare('DELETE FROM peminjaman WHERE id = :id');
    $stmt->execute([':id' => $id]);
    kembali_ke_dashboard($stmt->rowCount() > 0 ? 'dihapus' : 'tidak_ada');
} catch (PDOException $e) {
    error_log('SIMBAR transaction delete failed: ' . $e->getMessage());
    kembali_ke_dashboard('gagal');
}
