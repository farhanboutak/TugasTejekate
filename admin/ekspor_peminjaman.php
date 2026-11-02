<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['admin', 'petugas']);

function filter_tanggal($value)
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$tanggal || $tanggal->format('Y-m-d') !== $value) {
        http_response_code(400);
        exit('Format tanggal filter tidak valid.');
    }

    return $value;
}

$status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$cari = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$dari = filter_tanggal($_GET['dari'] ?? '');
$sampai = filter_tanggal($_GET['sampai'] ?? '');
$allowed_status = ['Pending', 'Disetujui', 'Ditolak', 'Dikembalikan'];

if (($status !== '' && !in_array($status, $allowed_status, true)) ||
    ($dari !== null && $sampai !== null && $dari > $sampai)) {
    http_response_code(400);
    exit('Filter ekspor tidak valid.');
}

$sql = 'SELECT p.id, u.nama AS nama_peminjam, u.username, b.nama_barang,
               p.tanggal_pinjam, p.tanggal_kembali, p.jumlah_pinjam, p.status
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        JOIN barang b ON p.barang_id = b.id
        WHERE 1 = 1';
$params = [];

if ($status !== '') {
    $sql .= ' AND p.status = :status';
    $params[':status'] = $status;
}
if ($dari !== null) {
    $sql .= ' AND p.tanggal_pinjam >= :dari';
    $params[':dari'] = $dari;
}
if ($sampai !== null) {
    $sql .= ' AND p.tanggal_pinjam <= :sampai';
    $params[':sampai'] = $sampai;
}
if ($cari !== '') {
    $sql .= ' AND (u.nama LIKE :cari_nama OR u.username LIKE :cari_username OR b.nama_barang LIKE :cari_barang)';
    $needle = '%' . $cari . '%';
    $params[':cari_nama'] = $needle;
    $params[':cari_username'] = $needle;
    $params[':cari_barang'] = $needle;
}
$sql .= ' ORDER BY p.id DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR transaction CSV export failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Ekspor transaksi gagal. Silakan coba kembali.');
}

function csv_cell($value): string
{
    $value = (string)$value;
    if ($value !== '' && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

$filename = 'transaksi-simbar-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('File ekspor tidak dapat dibuat.');
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['ID', 'Nama Peminjam', 'Username', 'Nama Barang', 'Tanggal Pinjam', 'Tanggal Kembali', 'Jumlah', 'Status']);
foreach ($rows as $row) {
    fputcsv($output, [
        (int)$row['id'],
        csv_cell($row['nama_peminjam']),
        csv_cell($row['username']),
        csv_cell($row['nama_barang']),
        $row['tanggal_pinjam'],
        $row['tanggal_kembali'],
        (int)$row['jumlah_pinjam'],
        csv_cell($row['status']),
    ]);
}
fclose($output);
