<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once '../config/audit.php';

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
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT u.nama AS peminjam, b.nama_barang
         FROM peminjaman p
         JOIN users u ON u.id = p.user_id
         JOIN barang b ON b.id = p.barang_id
         WHERE p.id = :id
         FOR UPDATE'
    );
    $stmt->execute([':id' => $id]);
    $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transaksi) {
        $pdo->rollBack();
        kembali_ke_dashboard('tidak_ada');
    }

    $stmt = $pdo->prepare('DELETE FROM peminjaman WHERE id = :id');
    $stmt->execute([':id' => $id]);
    catat_aktivitas(
        $pdo,
        'transaksi.dihapus',
        'transaksi',
        'Transaksi #' . $id . ' dihapus (' . $transaksi['peminjam'] . ' — ' . $transaksi['nama_barang'] . ').',
        $id
    );
    $pdo->commit();
    kembali_ke_dashboard($stmt->rowCount() > 0 ? 'dihapus' : 'tidak_ada');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SIMBAR transaction delete failed: ' . $e->getMessage());
    kembali_ke_dashboard('gagal');
}
