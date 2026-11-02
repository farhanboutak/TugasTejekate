<?php
// Pastikan file koneksi sudah dipanggil
require_once '../config/koneksi.php';

try {
    // Query dengan SQL JOIN untuk menghubungkan 3 tabel (peminjaman, users, barang)
    $sql = "SELECT peminjaman.id, users.nama AS nama_peminjam, barang.nama_barang, 
                   peminjaman.tanggal_pinjam, peminjaman.tanggal_kembali, 
                   peminjaman.jumlah_pinjam, peminjaman.status 
            FROM peminjaman
            JOIN users ON peminjaman.user_id = users.id
            JOIN barang ON peminjaman.barang_id = barang.id
            ORDER BY peminjaman.id DESC";
            
    $stmt = $pdo->query($sql);
    $data_peminjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error query: " . $e->getMessage();
}
?>

<!-- Tampilan Tabel Data di Frontend (Bootstrap) -->
<div class="container mt-4">
    <h3>Data Transaksi Peminjaman Barang</h3>
    <table class="table table-bordered table-striped mt-3">
        <thead>
            <tr>
                <th>No</th>
                <th>Peminjam</th>
                <th>Nama Barang</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>Jumlah</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_peminjaman as $row): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['nama_peminjam']); ?></td>
                <td><?= htmlspecialchars($row['nama_barang']); ?></td>
                <td><?= $row['tanggal_pinjam']; ?></td>
                <td><?= $row['tanggal_kembali']; ?></td>
                <td><?= $row['jumlah_pinjam']; ?></td>
                <td>
                    <span class="badge bg-<?= 
                        $row['status'] == 'Disetujui' ? 'success' : 
                        ($row['status'] == 'Pending' ? 'warning' : 'secondary'); 
                    ?>">
                        <?= $row['status']; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>