<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once '../config/audit.php';

cek_akses('transactions.manage');

function kembali_ke_dashboard($pesan = null)
{
    global $pdo;
    $dashboard = '../' . simbar_dashboard_url($pdo, (string)$_SESSION['role']);
    if ($pesan !== null) {
        $dashboard .= '?pesan=' . urlencode($pesan);
    }

    header('Location: ' . $dashboard);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali_ke_dashboard();
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    kembali_ke_dashboard('csrf');
}

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
$allowed = ['Disetujui', 'Ditolak', 'Dikembalikan'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    kembali_ke_dashboard('invalid');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT status FROM peminjaman WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        kembali_ke_dashboard('tidak_ada');
    }

    $status_lama = $row['status'];
    $transisi_valid = (
        ($status_lama === 'Pending'   && in_array($status, ['Disetujui', 'Ditolak'], true)) ||
        ($status_lama === 'Disetujui' && $status === 'Dikembalikan')
    );

    if (!$transisi_valid) {
        $pdo->rollBack();
        kembali_ke_dashboard('invalid');
    }

    $stmt = $pdo->prepare("UPDATE peminjaman SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);
    if ($stmt->rowCount() > 0) {
        catat_aktivitas(
            $pdo,
            'transaksi.status_diubah',
            'transaksi',
            'Status transaksi #' . $id . ' diubah dari ' . $status_lama . ' menjadi ' . $status . '.',
            $id,
            ['status_lama' => $status_lama, 'status_baru' => $status]
        );
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }

    kembali_ke_dashboard($stmt->rowCount() > 0 ? 'sukses' : 'tidak_ada');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SIMBAR transaction status update failed: ' . $e->getMessage());
    kembali_ke_dashboard('gagal');
}